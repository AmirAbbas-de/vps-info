"""
listservers.py
==============
Collects all Virtualizor slave server data and saves to MySQL.

Tables (database: Mobin-DB):
  server_snapshots  → every run (every 30 min via cron)
  server_daily      → one record per server per day (upsert on conflict)

After inserting snapshots, records older than 25 hours are pruned
(giving a small buffer beyond the 24h window to ensure daily archival).

Cron (every 30 min):
  */30 * * * * /path/to/.venv/bin/python3 /path/to/listservers.py >> /var/log/listservers.log 2>&1
"""

import csv
import json
import os
import sys
import traceback
from datetime import datetime
import jdatetime

import mysql.connector
from dotenv import load_dotenv
from virtvs import virtapi

load_dotenv()

# ─── Shamsi helper ───────────────────────────────────────────────────────────
def now_shamsi():
    """Returns current Shamsi datetime string: 1403/09/15 14:30"""
    return jdatetime.datetime.now().strftime("%Y/%m/%d %H:%M")

# ─── DB config ────────────────────────────────────────────────────────────────
DB_CONFIG = {
    "host":     os.getenv("DB_HOST", "localhost"),
    "user":     os.getenv("DB_USER"),
    "password": os.getenv("DB_PASSWORD"),
    "database": "IRservers",
    "charset":  "utf8",
}

# ─── helpers ──────────────────────────────────────────────────────────────────
def sf(v, d=2):
    try: return round(float(v), d)
    except: return 0.0

def si(v):
    try: return int(v)
    except: return 0

def mb_gb(v, d=2):
    try:
        f = float(v)
        return round(f / 1024, d) if f > 0 else 0.0
    except: return 0.0

def parse_locked(locked_val):
    if not locked_val or locked_val == "0" or locked_val == 0:
        return False, ""
    if isinstance(locked_val, dict):
        reasons = locked_val.get("reason", {})
        reason_str = ", ".join(str(v) for v in reasons.values()) if reasons else ""
        return True, reason_str
    return bool(locked_val), str(locked_val)

def parse_location(loc_val):
    if not loc_val:
        return "", "", ""
    try:
        d = json.loads(loc_val) if isinstance(loc_val, str) else loc_val
        return d.get("country_code",""), d.get("state",""), d.get("city","")
    except:
        return "", "", str(loc_val)

def primary_storage(storages_dict):
    if not storages_dict or not isinstance(storages_dict, dict):
        return {}, ""
    for st in storages_dict.values():
        if si(st.get("primary_storage", 0)) == 1:
            return st, st.get("path","")
    first = next(iter(storages_dict.values()))
    return first, first.get("path","")


# ─── API calls ────────────────────────────────────────────────────────────────
def api_manageserver(api, serid):
    return api.call(f"index.php?act=manageserver&changeserid={serid}") or {}

def api_performance(api, serid):
    return api.call(f"index.php?act=performance&changeserid={serid}") or {}

def api_performance_live(api, serid):
    return api.call(f"index.php?act=performance&changeserid={serid}&ajax=true") or {}

def api_serverinfo(api, serid):
    return api.call(f"index.php?act=serverinfo&changeserid={serid}") or {}


# ─── Row builder ──────────────────────────────────────────────────────────────
def build_row(sid, sinfo, mgr, perf, perf_live, srvinfo):
    row = {}

    # ── Identity ──────────────────────────────────────────────────────────────
    locked_bool, locked_reason = parse_locked(sinfo.get("locked", 0))
    country, state, city = parse_location(sinfo.get("location",""))

    row.update({
        "server_id":       si(sid),
        "server_name":     sinfo.get("server_name",""),
        "hostname":        "",
        "ip":              sinfo.get("ip",""),
        "virt_type":       sinfo.get("virt",""),
        "sgid":            si(sinfo.get("sgid",0)),
        "status":          si(sinfo.get("status",0)),
        "locked":          int(locked_bool),
        "locked_reason":   locked_reason,
        "country":         country,
        "state":           state,
        "city":            city,
        "os_distro":       sinfo.get("os",""),
        "virt_version":    sinfo.get("version",""),
        "virt_patch":      sinfo.get("patch",""),
        "lic_expires":     sinfo.get("lic_expires",""),
        "sys_load":        sf(sinfo.get("sys_load", 0)),
    })

    # ── VPS & allocation ──────────────────────────────────────────────────────
    total_ram_mb  = si(sinfo.get("total_ram", 0))
    alloc_ram_mb  = si(sinfo.get("alloc_ram", 0))
    free_ram_mb   = si(sinfo.get("ram", 0))
    overcommit_mb = si(sinfo.get("overcommit", 0))
    total_disk_gb = sf(sinfo.get("total_space", 0))
    free_disk_gb  = sf(sinfo.get("space", 0))
    alloc_disk_gb = sf(sinfo.get("alloc_space", 0))
    vcores_sold   = si(sinfo.get("vcores", 0))
    alloc_cpu     = si(sinfo.get("alloc_cpu", 0))
    alloc_bw_mb   = si(sinfo.get("alloc_bandwidth",0))
    num_vps       = si(sinfo.get("numvps", 0))

    row.update({
        "num_vps":           num_vps,
        "vps_limit":         si(sinfo.get("licnumvs", 0)),  # 0 = unlimited
        "ram_total_gb":      mb_gb(total_ram_mb),
        "ram_sold_gb":       mb_gb(alloc_ram_mb),
        "ram_free_panel_gb": mb_gb(free_ram_mb),
        "ram_overcommit_gb": mb_gb(overcommit_mb),
        "ram_sold_pct":      round(alloc_ram_mb / total_ram_mb * 100, 1) if total_ram_mb else 0,
        "disk_total_gb":     total_disk_gb,
        "disk_sold_gb":      alloc_disk_gb,
        "disk_free_gb":      free_disk_gb,
        "disk_used_gb":      round(total_disk_gb - free_disk_gb, 2),
        "disk_used_pct":     round((total_disk_gb - free_disk_gb) / total_disk_gb * 100, 1) if total_disk_gb else 0,
        "vcores_sold":       vcores_sold,
        "cpu_units_alloc":   alloc_cpu,
        "bw_alloc_mb":       alloc_bw_mb,
    })

    # ── Live OS usage (manageserver) ──────────────────────────────────────────
    usage    = mgr.get("usage", {})
    mgr_info = mgr.get("info", {})
    mgr_res  = mgr_info.get("resources", {}) if mgr_info else {}

    row["hostname"] = mgr_info.get("hostname","") if mgr_info else ""
    row["uptime"]   = mgr_info.get("uptime","")   if mgr_info else ""

    cpu_u = usage.get("cpu", {})
    row.update({
        "live_cpu_model":     cpu_u.get("cpumodel","").strip() if isinstance(cpu_u, dict) else "",
        "live_cpu_mfg":       cpu_u.get("manu","")            if isinstance(cpu_u, dict) else "",
        "live_cpu_limit_mhz": sf(cpu_u.get("limit", 0))       if isinstance(cpu_u, dict) else 0,
        "live_cpu_used_mhz":  sf(cpu_u.get("used",  0))       if isinstance(cpu_u, dict) else 0,
        "live_cpu_pct":       sf(cpu_u.get("percent",0))       if isinstance(cpu_u, dict) else 0,
    })

    ram_u = usage.get("ram", {})
    row.update({
        "live_ram_total_gb": mb_gb(ram_u.get("limit", 0))     if isinstance(ram_u, dict) else 0,
        "live_ram_used_gb":  mb_gb(ram_u.get("used",  0))     if isinstance(ram_u, dict) else 0,
        "live_ram_free_gb":  mb_gb(ram_u.get("free",  0))     if isinstance(ram_u, dict) else 0,
        "live_ram_pct":      sf(ram_u.get("percent",  0))     if isinstance(ram_u, dict) else 0,
        "live_swap_total_gb":mb_gb(ram_u.get("swap",     0))  if isinstance(ram_u, dict) else 0,
        "live_swap_used_gb": mb_gb(ram_u.get("swap_used",0))  if isinstance(ram_u, dict) else 0,
        "live_swap_free_gb": mb_gb(ram_u.get("swap_free",0))  if isinstance(ram_u, dict) else 0,
    })

    io_cpu = usage.get("io", {}).get("avg_cpu", {})
    row.update({
        "io_user_pct":   sf(io_cpu.get("user",   0)),
        "io_system_pct": sf(io_cpu.get("system", 0)),
        "io_iowait_pct": sf(io_cpu.get("iowait", 0)),
        "io_idle_pct":   sf(io_cpu.get("idle",   0)),
        "io_steal_pct":  sf(io_cpu.get("steal",  0)),
    })

    bw_u   = usage.get("bandwidth", {})
    bw_in  = bw_u.get("in",  {}) if isinstance(bw_u, dict) else {}
    bw_out = bw_u.get("out", {}) if isinstance(bw_u, dict) else {}
    row.update({
        "live_bw_used_gb":     sf(bw_u.get("used_gb",  0))   if isinstance(bw_u, dict) else 0,
        "live_bw_in_used_gb":  sf(bw_in.get("used_gb",  0))  if isinstance(bw_in, dict) else 0,
        "live_bw_out_used_gb": sf(bw_out.get("used_gb", 0))  if isinstance(bw_out, dict) else 0,
        "live_bw_limit_gb":    sf(bw_u.get("limit_gb", 0))   if isinstance(bw_u, dict) else 0,
    })

    disk_u = usage.get("disk", {})
    storages = mgr_res.get("storages", {})
    pst, ppath = primary_storage(storages)
    primary_mount = ""
    primary_disk  = {}
    if ppath and isinstance(disk_u, dict) and ppath in disk_u:
        primary_mount = ppath
        primary_disk  = disk_u[ppath]
    elif isinstance(disk_u, dict) and disk_u:
        for mount, dinfo in disk_u.items():
            if isinstance(dinfo, dict) and sf(dinfo.get("limit_gb",0)) > sf(primary_disk.get("limit_gb",0)):
                primary_mount = mount
                primary_disk  = dinfo

    row.update({
        "storage_mount":    primary_mount,
        "storage_type":     pst.get("type","")   if pst else "",
        "storage_format":   pst.get("format","") if pst else "",
        "storage_total_gb": sf(primary_disk.get("limit_gb", 0)),
        "storage_used_gb":  sf(primary_disk.get("used_gb",  0)),
        "storage_free_gb":  sf(primary_disk.get("free_gb",  0)),
        "storage_used_pct": sf(primary_disk.get("percent",  0)),
    })

    row["cpu_physical_cores"] = si(mgr_res.get("cpucores", 0))

    # ── Hardware specs (performance) ──────────────────────────────────────────
    cpu_specs = perf.get("cpu_specs", {}) or {}
    row.update({
        "hw_cpu_model":         cpu_specs.get("Model name",""),
        "hw_cpu_vendor":        cpu_specs.get("Vendor ID",""),
        "hw_cpu_logical":       si(cpu_specs.get("CPU(s)", 0)),
        "hw_cpu_cores_socket":  si(cpu_specs.get("Core(s) per socket", 0)),
        "hw_cpu_threads_core":  si(cpu_specs.get("Thread(s) per core", 0)),
        "hw_cpu_sockets":       si(cpu_specs.get("Socket(s)", 0)),
        "hw_cpu_mhz":           sf(cpu_specs.get("CPU MHz", 0)),
        "hw_cpu_max_mhz":       sf(cpu_specs.get("CPU max MHz", 0)),
        "hw_virt_support":      cpu_specs.get("Virtualization",""),
        "hw_l1d_cache":         str(cpu_specs.get("L1d cache",""))[:64],
        "hw_l2_cache":          str(cpu_specs.get("L2 cache",""))[:64],
        "hw_l3_cache":          str(cpu_specs.get("L3 cache",""))[:64],
        "hw_numa_nodes":        si(cpu_specs.get("NUMA node(s)", 0)),
    })

    disks = perf.get("disks", {}) or {}
    phys_disks = []
    for d in disks.values():
        if isinstance(d, dict):
            dev  = str(d.get(1,"")).strip().rstrip(":")
            size = f"{d.get(2,'')} {d.get(3,'')}".strip().rstrip(",")
            dtype= str(d.get(8,"")).strip()
            if dev:
                phys_disks.append(f"{dev} {size} {dtype}".strip())
    row["hw_physical_disks"] = " | ".join(phys_disks)

    disks_df = perf.get("disks_df", {}) or {}
    if primary_mount and primary_mount in disks_df:
        pfd = disks_df[primary_mount]
        row.update({
            "perf_storage_total_gb": sf(pfd.get("limit_gb", 0)),
            "perf_storage_used_gb":  sf(pfd.get("used_gb",  0)),
            "perf_storage_free_gb":  sf(pfd.get("free_gb",  0)),
            "perf_storage_pct":      sf(pfd.get("percent",  0)),
        })
    else:
        row.update({"perf_storage_total_gb":0,"perf_storage_used_gb":0,
                    "perf_storage_free_gb":0,"perf_storage_pct":0})

    # ── Ajax live stats ───────────────────────────────────────────────────────
    pl_perf = perf_live.get("performance")
    ajax_cpu_pct  = 0.0
    ajax_ram_used = 0.0
    ajax_ram_free = 0.0
    ajax_ram_pct  = 0.0
    if isinstance(pl_perf, dict):
        pl_cpu = pl_perf.get("cpu")
        pl_ram = pl_perf.get("ram")
        if isinstance(pl_cpu, dict):
            ajax_cpu_pct = sf(pl_cpu.get("percent", pl_cpu.get("usage_pct", 0)))
        elif pl_cpu is not None:
            ajax_cpu_pct = sf(pl_cpu)
        if isinstance(pl_ram, dict):
            ajax_ram_used = mb_gb(pl_ram.get("used", 0))
            ajax_ram_free = mb_gb(pl_ram.get("free", 0))
            ajax_ram_pct  = sf(pl_ram.get("percent", 0))
        elif pl_ram is not None:
            ajax_ram_used = mb_gb(pl_ram)
    row.update({
        "ajax_cpu_pct":     ajax_cpu_pct,
        "ajax_ram_used_gb": ajax_ram_used,
        "ajax_ram_free_gb": ajax_ram_free,
        "ajax_ram_pct":     ajax_ram_pct,
    })

    # ── Panel config (serverinfo) ─────────────────────────────────────────────
    si_info = srvinfo.get("info", {}) or {}
    row.update({
        "panel_timezone":   si_info.get("timezone",""),
        "panel_interface":  si_info.get("interface",""),
        "panel_license":    si_info.get("lictype_txt",""),
        "panel_num_vs":     si(si_info.get("num_vs", 0)),
        "panel_overcommit": si(si_info.get("overcommit", 0)),
        "panel_vcores_cfg": si(si_info.get("vcores", 0)),
        "panel_vpslimit":   str(si_info.get("vpslimit","0")),
    })

    return row


# ─── DB helpers ───────────────────────────────────────────────────────────────

# All columns that go into server_snapshots (excludes id, recorded_at)
SNAPSHOT_COLS = [
    "server_id","server_name","hostname","ip","virt_type","sgid","status",
    "locked","locked_reason","country","state","city","os_distro",
    "virt_version","virt_patch","lic_expires","sys_load",
    "num_vps","vps_limit","ram_total_gb","ram_sold_gb","ram_free_panel_gb",
    "ram_overcommit_gb","ram_sold_pct","disk_total_gb","disk_sold_gb",
    "disk_free_gb","disk_used_gb","disk_used_pct","vcores_sold",
    "cpu_units_alloc","bw_alloc_mb",
    "live_cpu_model","live_cpu_mfg","live_cpu_limit_mhz","live_cpu_used_mhz",
    "live_cpu_pct","cpu_physical_cores",
    "live_ram_total_gb","live_ram_used_gb","live_ram_free_gb","live_ram_pct",
    "live_swap_total_gb","live_swap_used_gb","live_swap_free_gb",
    "storage_mount","storage_type","storage_format",
    "storage_total_gb","storage_used_gb","storage_free_gb","storage_used_pct",
    "io_user_pct","io_system_pct","io_iowait_pct","io_idle_pct","io_steal_pct",
    "live_bw_used_gb","live_bw_in_used_gb","live_bw_out_used_gb","live_bw_limit_gb",
    "uptime",
    "hw_cpu_model","hw_cpu_vendor","hw_cpu_logical","hw_cpu_sockets",
    "hw_cpu_cores_socket","hw_cpu_threads_core","hw_cpu_mhz","hw_cpu_max_mhz",
    "hw_virt_support","hw_l1d_cache","hw_l2_cache","hw_l3_cache","hw_numa_nodes",
    "hw_physical_disks",
    "perf_storage_total_gb","perf_storage_used_gb","perf_storage_free_gb","perf_storage_pct",
    "ajax_cpu_pct","ajax_ram_used_gb","ajax_ram_free_gb","ajax_ram_pct",
    "panel_timezone","panel_interface","panel_license","panel_num_vs",
    "panel_overcommit","panel_vcores_cfg","panel_vpslimit",
    "recorded_at_shamsi",
]

# Columns for server_daily (subset — no per-run noise fields)
DAILY_COLS = [
    "server_id","server_name","hostname","ip","virt_type","sgid","status",
    "locked","locked_reason","country","state","city","os_distro",
    "virt_version","virt_patch","lic_expires","sys_load",
    "num_vps","vps_limit","ram_total_gb","ram_sold_gb","ram_free_panel_gb",
    "ram_overcommit_gb","ram_sold_pct","disk_total_gb","disk_sold_gb",
    "disk_free_gb","disk_used_gb","disk_used_pct","vcores_sold",
    "cpu_units_alloc","bw_alloc_mb",
    "live_cpu_pct","live_cpu_model","cpu_physical_cores",
    "live_ram_total_gb","live_ram_used_gb","live_ram_free_gb","live_ram_pct",
    "live_swap_used_gb","storage_total_gb","storage_used_gb","storage_free_gb",
    "storage_used_pct","io_iowait_pct",
    "live_bw_used_gb","live_bw_in_used_gb","live_bw_out_used_gb",
    "hw_cpu_model","hw_cpu_vendor","hw_cpu_logical","hw_cpu_sockets",
    "hw_cpu_cores_socket","hw_cpu_threads_core","hw_cpu_max_mhz",
    "hw_virt_support","hw_l3_cache","hw_physical_disks",
    "panel_timezone","panel_interface","panel_license","panel_num_vs",
    "panel_overcommit","uptime",
    "recorded_at_shamsi",
]


def insert_snapshots(cursor, rows, now):
    """Bulk insert all rows into server_snapshots."""
    if not rows:
        return
    cols_sql = ", ".join(f"`{c}`" for c in SNAPSHOT_COLS)
    placeholders = ", ".join(["%s"] * len(SNAPSHOT_COLS))
    sql = f"INSERT INTO `server_snapshots` (`recorded_at`, {cols_sql}) VALUES (NOW(), {placeholders})"
    data = [tuple(row.get(c, None) for c in SNAPSHOT_COLS) for row in rows]
    cursor.executemany(sql, data)
    print(f"  ✓ Inserted {len(rows)} rows into server_snapshots")


def upsert_daily(cursor, rows, today):
    """
    Upsert into server_daily: one row per (server_id, snapshot_date).
    ON DUPLICATE KEY UPDATE overwrites with the latest data of the day.
    """
    if not rows:
        return

    cols_sql   = ", ".join(f"`{c}`" for c in DAILY_COLS)
    placeholders = ", ".join(["%s"] * len(DAILY_COLS))
    # ON DUPLICATE KEY: update all non-key columns
    update_sql = ", ".join(
        f"`{c}`=VALUES(`{c}`)" for c in DAILY_COLS if c != "server_id"
    )
    sql = f"""
        INSERT INTO `server_daily`
            (`snapshot_date`, `recorded_at`, {cols_sql})
        VALUES
            (%s, NOW(), {placeholders})
        ON DUPLICATE KEY UPDATE
            `recorded_at` = NOW(),
            {update_sql}
    """
    data = [(today,) + tuple(row.get(c, None) for c in DAILY_COLS) for row in rows]
    cursor.executemany(sql, data)
    print(f"  ✓ Upserted {len(rows)} rows into server_daily for {today}")


def prune_old_snapshots(cursor):
    """Delete snapshot records older than 25 hours."""
    cursor.execute(
        "DELETE FROM `server_snapshots` WHERE `recorded_at` < NOW() - INTERVAL 25 HOUR"
    )
    deleted = cursor.rowcount
    if deleted:
        print(f"  ✓ Pruned {deleted} old snapshots (>25h)")


def save_to_db(rows):
    now   = datetime.now()
    today = now.date()
    shamsi_now = now_shamsi()
    # Inject shamsi timestamp into every row
    for row in rows:
        row["recorded_at_shamsi"] = shamsi_now

    conn   = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    try:
        insert_snapshots(cursor, rows, now)
        upsert_daily(cursor, rows, today)
        prune_old_snapshots(cursor)
        conn.commit()
        print(f"  ✓ DB commit OK  [{now.strftime('%Y-%m-%d %H:%M:%S')}]")
    except Exception as e:
        conn.rollback()
        print(f"  ✗ DB error: {e}")
        traceback.print_exc()
    finally:
        cursor.close()
        conn.close()


# ─── Main ─────────────────────────────────────────────────────────────────────
def list_servers(csv_path="servers.csv", print_table=True, save_db=True):
    api = virtapi()

    print(f"\n[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Fetching servers()...")
    raw = api.servers()
    if not raw:
        print("No data returned.")
        return []

    if "servers" in raw and isinstance(raw.get("servers"), dict):
        servers_data = raw["servers"]
    elif isinstance(raw, dict) and all(isinstance(v, dict) for v in list(raw.values())[:3]):
        servers_data = raw
    else:
        print("Unexpected structure:", list(raw.keys()))
        return []

    print(f"Found {len(servers_data)} servers.")

    rows = []
    for sid_str, sinfo in servers_data.items():
        sid  = si(sid_str)
        name = sinfo.get("server_name", sid)
        ip   = sinfo.get("ip","?")
        print(f"  [{sid:>3}] {name} ({ip})")

        mgr       = api_manageserver(api, sid)
        perf      = api_performance(api, sid)
        perf_live = api_performance_live(api, sid)
        srvinfo   = api_serverinfo(api, sid)

        row = build_row(sid_str, sinfo, mgr, perf, perf_live, srvinfo)
        rows.append(row)

    if not rows:
        return rows

    # ── Save to DB ────────────────────────────────────────────────────────────
    if save_db:
        print("\nSaving to database...")
        save_to_db(rows)

    # ── Save CSV (optional, for debugging) ────────────────────────────────────
    if csv_path:
        fieldnames = list(rows[0].keys())
        with open(csv_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(rows)
        print(f"  ✓ CSV saved → {csv_path}")

    # ── Print tables ──────────────────────────────────────────────────────────
    if print_table:
        t1 = [
            ("server_id",          "ID",        4),
            ("server_name",        "Name",      18),
            ("ip",                 "IP",        16),
            ("virt_type",          "Virt",       5),
            ("status",             "Up",         2),
            ("locked",             "Lck",        3),
            ("locked_reason",      "Lock Reason",18),
            ("virt_version",       "Ver",        6),
            ("lic_expires",        "Lic Exp",   14),
            ("hw_cpu_model",       "CPU Model", 36),
            ("hw_cpu_logical",     "LogCPU",     7),
            ("hw_cpu_sockets",     "Sock",       4),
            ("hw_cpu_cores_socket","C/Sock",     6),
            ("hw_cpu_threads_core","T/Core",     6),
            ("hw_cpu_max_mhz",     "MaxMHz",     8),
            ("hw_virt_support",    "VirtExt",    8),
            ("hw_l3_cache",        "L3",         7),
            ("hw_physical_disks",  "Physical Disk", 28),
            ("country",            "CC",         3),
            ("city",               "City",      12),
        ]
        _print_table("HARDWARE & IDENTITY", t1, rows)

        t2 = [
            ("server_id",          "ID",        4),
            ("server_name",        "Name",      16),
            ("num_vps",            "VPS",        4),
            ("cpu_physical_cores", "PhysCore",   8),
            ("vcores_sold",        "vCores",     6),
            ("live_cpu_pct",       "CPU%",       5),
            ("io_iowait_pct",      "IOWait%",    7),
            ("ram_total_gb",       "RAM Tot",    8),
            ("ram_sold_gb",        "RAM Sold",   8),
            ("live_ram_used_gb",   "RAM Live",   8),
            ("live_ram_pct",       "RAM%",       5),
            ("live_swap_used_gb",  "Swap",       6),
            ("ram_overcommit_gb",  "Overcom",    8),
            ("disk_total_gb",      "Dsk Tot",    8),
            ("disk_sold_gb",       "Dsk Sold",   8),
            ("storage_free_gb",    "Dsk Free",   8),
            ("storage_used_pct",   "Dsk%",       5),
            ("live_bw_used_gb",    "BW Used",    8),
            ("sys_load",           "SysLoad",    8),
        ]
        _print_table("RESOURCE USAGE", t2, rows)

        t3 = [
            ("server_id",          "ID",        4),
            ("server_name",        "Name",      16),
            ("io_user_pct",        "User%",      6),
            ("io_system_pct",      "Sys%",       5),
            ("io_iowait_pct",      "IOWait%",    7),
            ("io_idle_pct",        "Idle%",      6),
            ("io_steal_pct",       "Steal%",     7),
            ("live_bw_in_used_gb", "BW In GB",   9),
            ("live_bw_out_used_gb","BW Out GB",  9),
            ("live_bw_used_gb",    "BW Tot GB", 10),
            ("panel_timezone",     "Timezone",  14),
            ("panel_interface",    "Interface", 10),
            ("panel_license",      "License",   14),
            ("uptime",             "Uptime",    42),
        ]
        _print_table("IO / BANDWIDTH / CONFIG", t3, rows)

    return rows


def _print_table(title, cols, rows):
    hdr = " | ".join(f"{lbl:<{w}}" for _, lbl, w in cols)
    print(f"\n{'='*len(hdr)}")
    print(f" {title}")
    print('='*len(hdr))
    print(hdr)
    print('-'*len(hdr))
    for row in rows:
        line = " | ".join(f"{str(row.get(k,'')):<{w}}" for k, _, w in cols)
        print(line)


if __name__ == "__main__":
    # --no-db   : skip database, just print/CSV
    # --no-table: skip terminal tables
    # --no-csv  : skip CSV file
    save_db     = "--no-db"    not in sys.argv
    print_table = "--no-table" not in sys.argv
    csv_path    = None if "--no-csv" in sys.argv else "servers.csv"
    list_servers(csv_path=csv_path, print_table=print_table, save_db=save_db)