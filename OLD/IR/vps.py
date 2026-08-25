import os
import json
import re
import subprocess
import time
import ipaddress
from virtvs import virtapi
import jdatetime
from dotenv import load_dotenv

load_dotenv()

BASE_DIR = '/var/www/html/views/pages/IR'
now_shamsi_day = jdatetime.datetime.now().strftime("%Y-%m-%d")
output_dir = f'{BASE_DIR}/data_{now_shamsi_day}'
os.makedirs(output_dir, exist_ok=True)


def unix_to_shamsi(unix_timestamp):
    try:
        if not unix_timestamp or int(unix_timestamp) == 0:
            return "N/A"
        return jdatetime.datetime.fromtimestamp(int(unix_timestamp)).strftime("%Y/%m/%d %H:%M:%S")
    except:
        return "Invalid Date"


# ─── Data compaction ─────────────────────────────────────────────────────────

def _first_per_hour(files):
    """Return the first filename for each clock hour from a sorted list."""
    seen = {}
    for f in sorted(files):
        m = re.search(r'_(\d{2})-\d{2}\.json$', f)
        if m:
            h = m.group(1)
            if h not in seen:
                seen[h] = f
    return set(seen.values())


def compact_history(base_dir):
    """
    Thin out historical data files:
      - today          → keep all  (15-min granularity)
      - ≤ 30 days ago  → keep one per hour
      - > 30 days ago  → keep one per day
    """
    if not os.path.exists(base_dir):
        return
    today_g = jdatetime.date.today().togregorian()
    deleted = 0

    for entry in os.listdir(base_dir):
        if not entry.startswith('data_'):
            continue
        parts = entry[5:].split('-')
        if len(parts) != 3:
            continue
        try:
            j_date = jdatetime.date(int(parts[0]), int(parts[1]), int(parts[2]))
        except Exception:
            continue

        days_ago = (today_g - j_date.togregorian()).days
        if days_ago == 0:
            continue

        dir_path = os.path.join(base_dir, entry)
        if not os.path.isdir(dir_path):
            continue

        all_files = sorted(os.listdir(dir_path))
        for prefix in ('vps_', 'ippools_', 'servers_', 'fr_vps_', 'fr_ippools_'):
            typed = [f for f in all_files if f.startswith(prefix) and f.endswith('.json')]
            if not typed:
                continue
            keep = _first_per_hour(typed) if days_ago <= 30 else {typed[0]}
            for f in typed:
                if f not in keep:
                    try:
                        os.remove(os.path.join(dir_path, f))
                        deleted += 1
                    except Exception:
                        pass

    print(f"Compaction: {deleted} old files removed")


# ─── Slave server stats ───────────────────────────────────────────────────────

def collect_servers(api):
    """Fetch slave server resource data and return a normalized list."""
    raw = api.listservers()
    if not raw or not isinstance(raw, dict):
        print("No server data returned from API")
        return []

    result = []
    for serid, s in raw.items():
        if not isinstance(s, dict):
            continue

        manage = api.server_manageinfo(serid)
        usage = manage.get('usage', {}) if manage else {}

        # ── RAM ──
        ram_total = int(s.get('ram', 0))
        ram_info = usage.get('ram', {})
        if isinstance(ram_info, dict):
            ram_used = int(ram_info.get('used', s.get('ram_used', 0)))
            ram_free = int(ram_info.get('free', max(0, ram_total - ram_used)))
        else:
            ram_used = int(s.get('ram_used', 0))
            ram_free = max(0, ram_total - ram_used)
        ram_pct = round(ram_used / ram_total * 100, 1) if ram_total > 0 else 0.0

        # ── Disk ──
        disk_total = int(s.get('disk', s.get('hdd', 0)))
        disk_info = usage.get('disk', {})
        if isinstance(disk_info, dict):
            disk_used = int(disk_info.get('used', s.get('hdd_used', s.get('disk_used', 0))))
            disk_free = int(disk_info.get('free', max(0, disk_total - disk_used)))
        else:
            disk_used = int(s.get('hdd_used', s.get('disk_used', 0)))
            disk_free = max(0, disk_total - disk_used)
        disk_pct = round(disk_used / disk_total * 100, 1) if disk_total > 0 else 0.0

        # ── CPU / Load ──
        cpu_info = usage.get('cpu', {})
        if isinstance(cpu_info, dict):
            cpu_pct = float(cpu_info.get('used', cpu_info.get('used_percent', 0)))
        else:
            try:
                cpu_pct = float(cpu_info) if cpu_info else 0.0
            except Exception:
                cpu_pct = 0.0

        load_raw = usage.get('load', s.get('load', s.get('sys_load', '0')))
        if isinstance(load_raw, (list, tuple)):
            sys_load = str(load_raw[0]) if load_raw else '0'
        else:
            sys_load = str(load_raw).split()[0] if load_raw else '0'

        # ── Network ──
        net_info = usage.get('network', usage.get('net', {}))
        if isinstance(net_info, dict):
            net_in  = net_info.get('in', net_info.get('rx', 0))
            net_out = net_info.get('out', net_info.get('tx', 0))
        else:
            net_in = net_out = 0

        # ── VPS counts ──
        vs_count     = int(s.get('vs_count', s.get('vps_count', s.get('totalvs', 0))))
        alloc_ram    = int(s.get('ram_used_by_vs', s.get('allocated_ram', s.get('vs_ram', 0))))

        # ── Locked ──
        locked_raw = s.get('locked', 0)
        if isinstance(locked_raw, dict):
            locked_status = bool(locked_raw.get('status', False))
        else:
            locked_status = bool(int(locked_raw)) if locked_raw else False

        result.append({
            "serid":         str(serid),
            "server_name":   s.get('server_name', ''),
            "hostname":      s.get('hostname', ''),
            "ip":            s.get('ip', ''),
            "server_type":   str(s.get('server_type', '')),
            "status":        int(s.get('status', 0)),
            "locked":        {"status": locked_status},
            "os":            s.get('os', s.get('os_type', '')),
            "cpu_cores":     int(s.get('cores', 0)),
            "sys_load":      sys_load,
            "total_ram_mb":  ram_total,
            "used_ram_mb":   ram_used,
            "available_ram_mb": ram_free,
            "total_disk_gb": disk_total,
            "used_disk_gb":  disk_used,
            "free_space_gb": disk_free,
            "resource_usage": {
                "cpu":  {"used_percent": str(round(cpu_pct, 1))},
                "ram":  {"used_percent": str(ram_pct)},
                "disk": {"used_percent": str(disk_pct)},
            },
            "network": {
                "in_bytes":  net_in,
                "out_bytes": net_out,
            },
            "vps": {
                "running_count":    vs_count,
                "allocated_ram_mb": alloc_ram,
            },
        })

    return result


# ─── FR data via SSH ──────────────────────────────────────────────────────────

def fetch_fr_data():
    """
    SSH into the FR server and read its pre-generated JSON files.
    Requires FR_SSH_HOST in .env; returns (vps_dict, ippools_dict).
    """
    host      = os.getenv('FR_SSH_HOST', '')
    user      = os.getenv('FR_SSH_USER', 'root')
    key       = os.getenv('FR_SSH_KEY', '/root/.ssh/id_rsa')
    vps_path  = os.getenv('FR_VPS_JSON', '/var/www/html/FR/vps.json')
    pool_path = os.getenv('FR_IPPOOLS_JSON', '/var/www/html/FR/ippools.json')

    if not host:
        return None, None

    def ssh_read(remote_path):
        try:
            r = subprocess.run(
                ['ssh', '-i', key, '-o', 'StrictHostKeyChecking=no',
                 '-o', 'ConnectTimeout=15', f'{user}@{host}', f'cat {remote_path}'],
                capture_output=True, text=True, timeout=30
            )
            if r.returncode == 0 and r.stdout.strip():
                return json.loads(r.stdout)
            if r.stderr:
                print(f"SSH stderr ({remote_path}): {r.stderr.strip()}")
        except Exception as e:
            print(f"SSH error reading {remote_path}: {e}")
        return None

    return ssh_read(vps_path), ssh_read(pool_path)


# ─── Main ─────────────────────────────────────────────────────────────────────

def main():
    api = virtapi()

    now_shamsi     = jdatetime.datetime.now().strftime("%Y-%m-%d_%H-%M")
    now_shamsi_day = jdatetime.datetime.now().strftime("%Y-%m-%d")
    this_dir       = f'{BASE_DIR}/data_{now_shamsi_day}'
    os.makedirs(this_dir, exist_ok=True)

    # ── IP Pools ──────────────────────────────────────────────────────────────
    print("Fetching IP pools...")
    pools_data = api.listippools(page=1, reslen=90000)
    raw_ips    = api.listips(page=1, reslen=90000)

    ip_to_pool_name = {}
    if raw_ips and isinstance(raw_ips, dict):
        for _, ip_info in raw_ips.items():
            ip_to_pool_name[ip_info['ip']] = ip_info.get('ippool_name', '')

    group_mapping = {
        'AF': 'Afranet', 'HI': 'Hiweb', 'RS': 'Respina', 'EM': 'Respina',
        'Hiweb': 'HiWeb', 'ARVAND': 'Arvand', '2a0a:': 'IPv6',
    }
    sorted_prefixes  = sorted(group_mapping.keys(), key=len, reverse=True)
    grouped_pools    = {}
    network_lookup   = []
    ipv4_re          = re.compile(r'\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b')

    for _, pool in pools_data.items():
        name     = pool['ippool_name'].strip()
        total_ip = int(pool.get('totalip', 0))
        location = "Unknown"
        for prefix in sorted_prefixes:
            if prefix in name:
                location = group_mapping[prefix]
                break

        try:
            if total_ip == 1:
                m          = ipv4_re.search(name)
                subnet_str = f"{m.group(1)}/32" if m else "unknown/32"
            else:
                net_type   = ipaddress.IPv6Network if pool.get('ipv6') == '1' else ipaddress.IPv4Network
                subnet_str = str(net_type(f"{pool['gateway']}/{pool['netmask']}", strict=False))
        except Exception:
            m          = re.search(r'(\d+\.\d+\.\d+\.\d+/\d+)', name)
            subnet_str = m.group(1) if m else "Unknown"

        grouped_pools.setdefault(location, []).append({
            "ippid":    str(pool['ippid']),
            "name":     name,
            "subnet":   subnet_str,
            "total_ip": pool.get('totalip', '0'),
            "free_ip":  pool.get('freeip', pool.get('unassignedip', '0')),
        })
        if subnet_str != "Unknown":
            try:
                network_lookup.append({
                    'network': ipaddress.ip_network(subnet_str, strict=False),
                    'group':   location,
                })
            except Exception:
                continue

    with open(f'{this_dir}/ippools_{now_shamsi}.json', 'w', encoding='utf-8') as f:
        json.dump(grouped_pools, f, indent=4, ensure_ascii=False)

    # ── VPS List ──────────────────────────────────────────────────────────────
    print("Fetching VPS list...")
    all_vps_ids, all_vps_info = [], {}
    page, reslen = 1, 1000
    while True:
        batch = api.listvs(page=page, reslen=reslen)
        if not batch or not isinstance(batch, dict):
            break
        all_vps_ids.extend(list(batch.keys()))
        all_vps_info.update(batch)
        if len(batch) < reslen:
            break
        page += 1

    print(f"Fetching status for {len(all_vps_ids)} VPS...")
    all_status = {}
    for i in range(0, len(all_vps_ids), 1000):
        chunk = all_vps_ids[i:i + 1000]
        for _ in range(3):
            sb = api.status(chunk)
            if sb and isinstance(sb, dict):
                all_status.update(sb)
                break
            time.sleep(2)
        time.sleep(1)

    status_map   = {0: "Offline", 1: "Online", 2: "Suspend", 3: "Unknown"}
    final_vps    = {}

    for vid in all_vps_ids:
        vinfo = all_vps_info.get(vid, {})
        sinfo = all_status.get(vid, {})
        if not isinstance(sinfo, dict):
            sinfo = {}

        ip_list   = list(vinfo.get('ips', {}).values())
        first_ip  = ip_list[0] if ip_list else ""
        all_ips   = ", ".join(ip_list) if ip_list else ""
        srv_name  = vinfo.get('server_name', "")

        vps_group = "Unknown"
        if first_ip:
            pool_name = ip_to_pool_name.get(first_ip, "")
            if pool_name:
                for prefix in sorted_prefixes:
                    if prefix in pool_name:
                        vps_group = group_mapping[prefix]
                        break
            if vps_group == "Unknown":
                try:
                    v_ip = ipaddress.ip_address(first_ip)
                    for item in network_lookup:
                        if v_ip in item['network']:
                            vps_group = item['group']
                            break
                except Exception:
                    pass
            if vps_group == "Unknown":
                for prefix in sorted_prefixes:
                    if prefix in srv_name:
                        vps_group = group_mapping[prefix]
                        break

        final_vps[str(vid)] = {
            "status":                status_map.get(sinfo.get('status', 3), "Unknown"),
            "used_cpu":              sinfo.get('used_cpu', "0.00"),
            "used_ram":              int(sinfo.get('used_ram', 0)),
            "used_inode":            sinfo.get('used_inode', "0"),
            "io_read":               int(sinfo.get('io_read', 0)),
            "io_write":              int(sinfo.get('io_write', 0)),
            "ram":                   vinfo.get('ram', "0"),
            "vpsid":                 int(vid),
            "hostname":              vinfo.get('hostname', ""),
            "os_name":               vinfo.get('os_name', ""),
            "space":                 vinfo.get('space', "0"),
            "cpu_cores":             vinfo.get('cores', "0"),
            "nic_type":              vinfo.get('nic_type', ""),
            "mac":                   vinfo.get('mac', ""),
            "timezone":              vinfo.get('timezone', ""),
            "server_name":           srv_name,
            "email":                 vinfo.get('email', "no-email"),
            "ips":                   first_ip,
            "all_ips":               all_ips,
            "IP Location":           vps_group,
            "creation_date_shamsi":  unix_to_shamsi(vinfo.get('date_created', 0)),
            "edit_date_shamsi":      unix_to_shamsi(vinfo.get('date_edit', 0)),
        }

    with open(f'{this_dir}/vps_{now_shamsi}.json', 'w', encoding='utf-8') as f:
        json.dump(final_vps, f, indent=4, ensure_ascii=False)
    print(f"VPS saved: {len(all_vps_ids)} records")

    # ── Server (slave) stats ──────────────────────────────────────────────────
    print("Fetching slave server stats...")
    servers = collect_servers(api)
    if servers:
        with open(f'{this_dir}/servers_{now_shamsi}.json', 'w', encoding='utf-8') as f:
            json.dump(servers, f, indent=4, ensure_ascii=False)
        print(f"Servers saved: {len(servers)} servers")

    # ── FR data via SSH ───────────────────────────────────────────────────────
    print("Fetching FR data via SSH...")
    fr_vps, fr_pools = fetch_fr_data()
    if fr_vps:
        with open(f'{this_dir}/fr_vps_{now_shamsi}.json', 'w', encoding='utf-8') as f:
            json.dump(fr_vps, f, indent=4, ensure_ascii=False)
        print(f"FR VPS saved: {len(fr_vps)} records")
    if fr_pools:
        with open(f'{this_dir}/fr_ippools_{now_shamsi}.json', 'w', encoding='utf-8') as f:
            json.dump(fr_pools, f, indent=4, ensure_ascii=False)
        print("FR IP pools saved")
    if not fr_vps and not fr_pools:
        print("FR SSH skipped (FR_SSH_HOST not set or connection failed)")

    # ── Compact historical data ───────────────────────────────────────────────
    print("Compacting historical data...")
    compact_history(BASE_DIR)

    print(f"\nDone! Saved to: {this_dir}")


if __name__ == "__main__":
    main()
