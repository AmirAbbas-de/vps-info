import json
import os
import re
import time
import ipaddress
from datetime import datetime
from virtvs import virtapi
from db import (
    get_conn,
    init_db,
    save_vps_info,
    save_vps_snapshots,
    save_ip_pools,
    save_locked_ips,
    save_servers,
    compact_db,
)
from dotenv import load_dotenv

load_dotenv()

REGION = "IR"

GROUP_MAPPING = {
    "AF": "Afranet",
    "HI": "Hiweb",
    "RS": "Respina",
    "EM": "Respina",
    "Hiweb": "HiWeb",
    "ARVAND": "Arvand",
    "2a0a:": "IPv6",
}


# ─── IP pool collection ───────────────────────────────────────────────────────


def collect_ip_pools(api):
    print("Fetching IP pools...")
    pools_data = api.listippools(page=1, reslen=90000)
    raw_ips = api.listips(page=1, reslen=90000)

    ip_to_pool_name = {}
    locked_ips = []
    if raw_ips and isinstance(raw_ips, dict):
        for _, ip_info in raw_ips.items():
            ip_to_pool_name[ip_info["ip"]] = ip_info.get("ippool_name", "")
            # collect locked IPs
            lk = ip_info.get("locked", ip_info.get("suspended", 0))
            if isinstance(lk, dict):
                is_locked = bool(lk.get("status", False))
                lk_desc = str(
                    lk.get("description", lk.get("reason", lk.get("msg", "")))
                )
            else:
                is_locked = bool(int(lk) if lk else 0)
                lk_desc = str(
                    ip_info.get("lock_description", ip_info.get("lock_reason", ""))
                )
            if is_locked:
                locked_ips.append(
                    {
                        "ip": ip_info["ip"],
                        "ippid": str(ip_info.get("ippid", "")),
                        "pool_name": ip_info.get("ippool_name", ""),
                        "lock_description": lk_desc,
                    }
                )

    sorted_pfx = sorted(GROUP_MAPPING.keys(), key=len, reverse=True)
    grouped = {}
    net_lookup = []
    ipv4_re = re.compile(r"\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b")

    for _, pool in pools_data.items():
        name = pool["ippool_name"].strip()
        total_ip = int(pool.get("totalip", 0))
        group = "Unknown"
        for pfx in sorted_pfx:
            if pfx in name:
                group = GROUP_MAPPING[pfx]
                break

        try:
            if total_ip == 1:
                m = ipv4_re.search(name)
                subnet_str = f"{m.group(1)}/32" if m else "unknown/32"
            else:
                net_type = (
                    ipaddress.IPv6Network
                    if pool.get("ipv6") == "1"
                    else ipaddress.IPv4Network
                )
                subnet_str = str(
                    net_type(f"{pool['gateway']}/{pool['netmask']}", strict=False)
                )
        except Exception:
            m = re.search(r"(\d+\.\d+\.\d+\.\d+/\d+)", name)
            subnet_str = m.group(1) if m else "Unknown"

        # pool locked status
        pool_locked_raw = pool.get("locked", pool.get("suspended", 0))
        pool_lock_time = None
        pool_lock_desc = ""
        if isinstance(pool_locked_raw, dict):
            if "status" in pool_locked_raw:
                pool_locked = 1 if pool_locked_raw.get("status", False) else 0
                pool_lock_desc = str(
                    pool_locked_raw.get("description", pool_locked_raw.get("reason", ""))
                )
            else:
                pool_locked = 1 if pool_locked_raw else 0
                reason = pool_locked_raw.get(
                    "reason", pool_locked_raw.get("description", pool_locked_raw.get("msg"))
                )
                if isinstance(reason, dict):
                    pool_lock_desc = str(list(reason.values())[0]) if reason else ""
                elif reason is not None:
                    pool_lock_desc = str(reason)
                else:
                    str_vals = [str(v) for v in pool_locked_raw.values() if isinstance(v, str)]
                    pool_lock_desc = str_vals[0] if str_vals else ""
            raw_ts = pool_locked_raw.get("time", 0)
            if raw_ts:
                try:
                    pool_lock_time = datetime.fromtimestamp(int(raw_ts))
                except Exception:
                    pass
        else:
            pool_locked = 1 if pool_locked_raw else 0

        grouped.setdefault(group, []).append(
            {
                "ippid": str(pool["ippid"]),
                "name": name,
                "subnet": subnet_str,
                "total_ip": pool.get("totalip", "0"),
                "free_ip": pool.get("freeip", pool.get("unassignedip", "0")),
                "locked": pool_locked,
                "lock_time": pool_lock_time,
                "lock_description": pool_lock_desc,
            }
        )
        if subnet_str != "Unknown":
            try:
                net_lookup.append(
                    {
                        "network": ipaddress.ip_network(subnet_str, strict=False),
                        "group": group,
                    }
                )
            except Exception:
                pass

    return grouped, ip_to_pool_name, sorted_pfx, net_lookup, locked_ips


# ─── VPS collection ───────────────────────────────────────────────────────────


def collect_vps(api, ip_to_pool_name, sorted_pfx, net_lookup):
    print("Fetching VPS list...")
    all_ids, all_info = [], {}
    page, reslen = 1, 1000
    while True:
        batch = api.listvs(page=page, reslen=reslen)
        if not batch or not isinstance(batch, dict):
            break
        all_ids.extend(list(batch.keys()))
        all_info.update(batch)
        if len(batch) < reslen:
            break
        page += 1

    print(f"Fetching live status for {len(all_ids)} VPS...")
    all_status = {}
    for i in range(0, len(all_ids), 1000):
        chunk = all_ids[i:i + 1000]
        for _ in range(3):
            sb = api.status(chunk)
            if sb and isinstance(sb, dict):
                all_status.update(sb)
                break
            time.sleep(2)
        time.sleep(1)

    status_map = {0: "Offline", 1: "Online", 2: "Suspend", 3: "Unknown"}
    result = {}

    for vid in all_ids:
        vinfo = all_info.get(vid, {})
        sinfo = all_status.get(vid, {})
        if not isinstance(sinfo, dict):
            sinfo = {}

        ip_list = list(vinfo.get("ips", {}).values())
        first_ip = ip_list[0] if ip_list else ""
        all_ips = ", ".join(ip_list) if ip_list else ""
        srv_name = vinfo.get("server_name", "")

        group = "Unknown"
        if first_ip:
            pool_name = ip_to_pool_name.get(first_ip, "")
            if pool_name:
                for pfx in sorted_pfx:
                    if pfx in pool_name:
                        group = GROUP_MAPPING[pfx]
                        break
            if group == "Unknown":
                try:
                    v_ip = ipaddress.ip_address(first_ip)
                    for item in net_lookup:
                        if v_ip in item["network"]:
                            group = item["group"]
                            break
                except Exception:
                    pass
            if group == "Unknown":
                for pfx in sorted_pfx:
                    if pfx in srv_name:
                        group = GROUP_MAPPING[pfx]
                        break

        try:
            suspended = int(vinfo.get("suspended", 0) or 0)
            if suspended:
                raw_st = 2  # administrative suspension takes priority
            elif sinfo:
                raw_st = int(sinfo.get("status", 3))
            else:
                raw_st = 3  # no live data
        except (ValueError, TypeError):
            raw_st = 3

        result[str(vid)] = {
            # ── time-series (vps_snapshots) ──
            "status": status_map.get(raw_st, "Unknown"),
            "used_cpu": sinfo.get("used_cpu", "0.00"),
            "used_ram": int(sinfo.get("used_ram", 0)),
            "used_inode": sinfo.get("used_inode", "0"),
            "io_read": int(sinfo.get("io_read", 0)),
            "io_write": int(sinfo.get("io_write", 0)),
            # ── static (vps_info) ──
            "ram": vinfo.get("ram", "0"),
            "hostname": vinfo.get("hostname", ""),
            "os_name": vinfo.get("os_name", ""),
            "space": vinfo.get("space", "0"),
            "cpu_cores": vinfo.get("cores", "0"),
            "nic_type": vinfo.get("nic_type", ""),
            "mac": vinfo.get("mac", ""),
            "timezone": vinfo.get("timezone", ""),
            "server_name": srv_name,
            "email": vinfo.get("email", "no-email"),
            "ips": first_ip,
            "all_ips": all_ips,
            "IP Location": group,
            "_created_ts": vinfo.get("date_created", 0),
            "_edited_ts": vinfo.get("date_edit", 0),
        }

    return result


# ─── slave server collection ──────────────────────────────────────────────────


def _parse_load(raw):
    if isinstance(raw, (list, tuple)):
        return str(raw[0]) if raw else "0"
    s = str(raw).strip()
    return s.split()[0] if s else "0"



def _to_int(v, default=0):
    try:
        return int(v)
    except (ValueError, TypeError):
        return default


def _to_float(v, default=0.0):
    try:
        return float(v)
    except (ValueError, TypeError):
        return default


def collect_servers(api, server_vps_stats=None):
    """Collect slave server stats.

    server_vps_stats: dict keyed by server_name with keys
        running, total, allocated_ram_mb — computed from VPS list in main().
    """
    print("Fetching slave server stats...")
    raw = api.listservers()
    if not raw or not isinstance(raw, dict):
        print("  No server data from API")
        return []

    raw_groups = api.servergroups()
    sgid_to_name = {}
    if isinstance(raw_groups, dict):
        for gid, g in raw_groups.items():
            if isinstance(g, dict):
                sgid_to_name[str(gid)] = g.get("sg_name", g.get("name", ""))

    if server_vps_stats is None:
        server_vps_stats = {}

    result = []
    for serid, s in raw.items():
        if not isinstance(s, dict):
            continue

        actual_serid = s.get("serid", s.get("server_id", serid))

        # Parse live resource percentages from the 'data' JSON field (already in listservers)
        cpu_pct = cpu_pct_free = ram_pct = ram_pct_free = 0
        sys_load = 0.0
        try:
            res = json.loads(s.get("data") or "{}").get("resource", {})
            cpu_pct      = int(res.get("cpu", {}).get("percent", 0) or 0)
            cpu_pct_free = int(res.get("cpu", {}).get("percent_free", 0) or 0)
            ram_pct      = int(res.get("ram", {}).get("percent", 0) or 0)
            ram_pct_free = int(res.get("ram", {}).get("percent_free", 0) or 0)
            # parse load average from uptime string
            uptime_str = res.get("uptime", "")
            if "load average:" in uptime_str:
                sys_load = float(uptime_str.split("load average:")[-1].split(",")[0].strip())
        except Exception:
            pass

        if sys_load == 0.0:
            try:
                sys_load = float(s.get("sys_load", 0) or 0)
            except Exception:
                sys_load = 0.0

        # RAM — total from listservers, used derived from ram_pct
        ram_total = int(s.get("total_ram", s.get("ram", 0)) or 0)
        ram_used  = int(ram_total * ram_pct / 100) if ram_pct else 0
        ram_free  = max(0, ram_total - ram_used)

        # Disk — total_space and space (free) are already in GB in listservers
        disk_total = int(s.get("total_space", 0) or 0)
        disk_free  = int(s.get("space", 0) or 0)
        disk_used  = max(0, disk_total - disk_free)

        # VPS running/total from pre-computed VPS list; allocated RAM from listservers
        srv_name    = s.get("server_name", "")
        vps_stat    = server_vps_stats.get(srv_name, {})
        vps_running = vps_stat.get("running", 0)
        vps_total   = vps_stat.get("total", 0)
        alloc_ram   = int(s.get("alloc_ram", vps_stat.get("allocated_ram_mb", 0)) or 0)

        # All config fields come directly from listservers — no extra API calls
        cpu_cores      = int(s.get("vcores", 0) or 0)
        ram_overcommit = int(s.get("overcommit", 0) or 0)
        vps_limit      = int(s.get("licnumvs", s.get("vs_limit", s.get("vpslimit", s.get("nummvps", 0)))) or 0)

        patch         = str(s.get("patch", "") or "")
        vnc_ip        = str(s.get("vnc_ip", "") or "")
        alloc_space   = int(s.get("alloc_space", 0) or 0)
        alloc_cpu     = int(s.get("alloc_cpu", 0) or 0)
        alloc_cpu_pct = float(s.get("alloc_cpu_percent", 0) or 0)
        alloc_bw      = int(s.get("alloc_bandwidth", 0) or 0)
        last_sync_ts  = int(s.get("last_reverse_sync", 0) or 0)

        num_vps_api = int(s.get("numvps", 0) or 0)
        ip_count    = int(s.get("ips", 0) or 0)
        bandwidth   = int(s.get("bandwidth", 0) or 0)
        virts_raw   = s.get("virts", {})
        virts       = ",".join(str(v) for v in virts_raw.values()) if isinstance(virts_raw, dict) else str(virts_raw or "")

        locked_raw = s.get("locked", 0)

        sgid = str(s.get("sgid", s.get("server_group_id", "")))
        server_group = sgid_to_name.get(sgid, s.get("sg_name", ""))

        result.append(
            {
                "serid": str(actual_serid),
                "server_name": srv_name,
                "server_group": server_group,
                "hostname": s.get("hostname", ""),
                "ip": s.get("ip", ""),
                "server_type": str(
                    s.get(
                        "server_type",
                        s.get("virt", s.get("virtualization", s.get("type", ""))),
                    )
                ),
                "status": int(s.get("status", 0)),
                "locked": locked_raw,
                "os": s.get("os", s.get("os_type", "")),
                "cpu_cores": cpu_cores,
                "sys_load": sys_load,
                "total_ram_mb": ram_total,
                "used_ram_mb": ram_used,
                "available_ram_mb": ram_free,
                "total_disk_gb": disk_total,
                "used_disk_gb": disk_used,
                "free_space_gb": disk_free,
                "version":            s.get("version", ""),
                "lic_expires":        s.get("lic_expires", ""),
                "patch":              patch,
                "vnc_ip":             vnc_ip,
                "alloc_space_gb":     alloc_space,
                "alloc_cpu":          alloc_cpu,
                "alloc_cpu_percent":  alloc_cpu_pct,
                "alloc_bandwidth":    alloc_bw,
                "last_reverse_sync_ts": last_sync_ts,
                "cpu_percent":        cpu_pct,
                "cpu_percent_free":   cpu_pct_free,
                "ram_percent":        ram_pct,
                "ram_percent_free":   ram_pct_free,
                "vps": {
                    "running_count": vps_running,
                    "total_count":   vps_total,
                    "num_vps_api":   num_vps_api,
                    "limit":         vps_limit,
                    "allocated_ram_mb": alloc_ram,
                    "ram_overcommit":   ram_overcommit,
                },
                "ip_count":  ip_count,
                "bandwidth": bandwidth,
                "virts":     virts,
            }
        )

    return result


class _DtEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, datetime):
            return obj.isoformat()
        return super().default(obj)


# ─── main ─────────────────────────────────────────────────────────────────────


def main():
    api = virtapi()
    collected_at = datetime.now()

    print(f"=== IR collector started at {collected_at:%Y-%m-%d %H:%M:%S} ===")

    conn = get_conn()
    init_db(conn)

    try:
        # 1. IP pools
        grouped_pools, ip_to_pool_name, sorted_pfx, net_lookup, locked_ips = (
            collect_ip_pools(api)
        )
        save_ip_pools(conn, REGION, grouped_pools)
        save_locked_ips(conn, REGION, locked_ips)

        # 2. VPS
        vps_data = collect_vps(api, ip_to_pool_name, sorted_pfx, net_lookup)
        save_vps_info(conn, REGION, vps_data)
        save_vps_snapshots(conn, REGION, vps_data, collected_at)

        # 3. Compute per-server VPS stats from collected VPS data
        server_vps_stats = {}
        for vid, v in vps_data.items():
            sname = v.get("server_name", "")
            if not sname:
                continue
            if sname not in server_vps_stats:
                server_vps_stats[sname] = {
                    "running": 0,
                    "total": 0,
                    "allocated_ram_mb": 0,
                }
            server_vps_stats[sname]["total"] += 1
            # vs_status API unavailable → count non-suspended as running approximation
            if v.get("status") != "Suspend":
                server_vps_stats[sname]["running"] += 1
            server_vps_stats[sname]["allocated_ram_mb"] += int(v.get("ram", 0) or 0)

        # 4. Slave servers
        servers = collect_servers(api, server_vps_stats)
        save_servers(conn, REGION, servers, collected_at)

        # 5. Compact historical data
        print("Running compaction...")
        compact_db(conn)

        # 6. Write JSON files for PHP frontend
        base = os.path.dirname(os.path.abspath(__file__))
        with open(os.path.join(base, "vps.json"), "w", encoding="utf-8") as f:
            json.dump(list(vps_data.values()), f, cls=_DtEncoder, ensure_ascii=False)
        with open(os.path.join(base, "ippools.json"), "w", encoding="utf-8") as f:
            json.dump(grouped_pools, f, cls=_DtEncoder, ensure_ascii=False)
        print("  json files    : vps.json + ippools.json written")

    except Exception as e:
        print(f"ERROR: {e}")
        conn.rollback()
        raise
    finally:
        conn.close()

    print(f"=== Done in {(datetime.now() - collected_at).seconds}s ===")


if __name__ == "__main__":
    main()
