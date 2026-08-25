import os
import json
import time
import ipaddress
import re
import mysql.connector
from virtvs import virtapi
import jdatetime
from dotenv import load_dotenv

load_dotenv()

now_shamsi_day = jdatetime.datetime.now().strftime("%Y/%m/%d %H:%M")

# Database Config
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
    'database': 'IRvpsInfo'
}

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

def unix_to_shamsi(unix_timestamp):
    try:
        if not unix_timestamp or int(unix_timestamp) == 0:
            return "N/A"
        dt_object = jdatetime.datetime.fromtimestamp(int(unix_timestamp))
        return dt_object.strftime("%Y/%m/%d %H:%M:%S")
    except:
        return "Invalid Date"


def archive_old_data(cursor):
    """
    Retention policy:
      - TODAY's snapshots: keep all (every 30 min run stays)
      - YESTERDAY and before: keep exactly ONE record per (id, day)
        → the LAST record of that day (most recent snapshot of the day)

    Strategy:
      1. For each (vpsid/ippid, date) before today, find the MAX(id) = last record
      2. Delete all other records for that (id, date) that are NOT the last one
      3. No separate history table needed — snapshots table becomes the archive itself
    """
    print("Starting data archival and rotation...")

    tables = {
        'vps_snapshots':   'vpsid',
        'ippool_snapshots': 'ippid',
    }

    for table, id_col in tables.items():

        # ── Step 1: Delete duplicates older than today ────────────────────────
        # Keep only MAX(id) per (entity_id, date) for records before today.
        # Uses a self-join to find rows that are NOT the last of their day.
        dedup_query = f"""
            DELETE s
            FROM `{table}` s
            INNER JOIN (
                SELECT {id_col}, DATE(recorded_at) AS snap_date, MAX(id) AS keep_id
                FROM `{table}`
                WHERE DATE(recorded_at) < CURDATE()
                GROUP BY {id_col}, DATE(recorded_at)
            ) AS keeper
              ON  s.{id_col}    = keeper.{id_col}
              AND DATE(s.recorded_at) = keeper.snap_date
              AND s.id          != keeper.keep_id
            WHERE DATE(s.recorded_at) < CURDATE()
        """
        cursor.execute(dedup_query)
        removed = cursor.rowcount
        if removed:
            print(f"  {table}: removed {removed} duplicate historical records (kept last per day)")

    print("Archival process finished.")


def main():
    api = virtapi()
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        # ── 1. IP POOL PROCESSING ─────────────────────────────────────────────
        print("Fetching IP pools and processing...")
        pools_data = api.listippools(page=1, reslen=90000)
        raw_ips = api.listips(page=1, reslen=90000)

        ip_to_pool_name = {}
        if raw_ips and isinstance(raw_ips, dict):
            for ip_id, ip_info in raw_ips.items():
                ip_to_pool_name[ip_info['ip']] = ip_info.get('ippool_name', '')

        group_mapping = {
            'AF': 'Afranet', 'HI': 'Hiweb', 'RS': 'Respina', 'EM': 'Respina',
            'Hiweb': 'HiWeb', 'ARVAND': 'Arvand', '2a0a:': 'IPv6'
        }
        sorted_prefixes = sorted(group_mapping.keys(), key=len, reverse=True)
        ipv4_pattern = re.compile(r'\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b')
        network_lookup_list = []

        for pool_id, pool in pools_data.items():
            name     = pool['ippool_name'].strip()
            total_ip = int(pool.get('totalip', 0))
            free_ip  = int(pool.get('freeip', pool.get('unassignedip', '0')))

            location = "Unknown"
            for prefix in sorted_prefixes:
                if prefix in name:
                    location = group_mapping[prefix]
                    break

            try:
                if total_ip == 1:
                    ip_match   = ipv4_pattern.search(name)
                    subnet_str = f"{ip_match.group(1)}/32" if ip_match else "unknown/32"
                else:
                    net_type   = ipaddress.IPv6Network if pool.get('ipv6') == '1' else ipaddress.IPv4Network
                    subnet_str = str(net_type(f"{pool['gateway']}/{pool['netmask']}", strict=False))
            except:
                cidr_match = re.search(r'(\d+\.\d+\.\d+\.\d+/\d+)', name)
                subnet_str = cidr_match.group(1) if cidr_match else "Unknown"

            cursor.execute("""
                INSERT INTO ippool_snapshots (ippid, name, location, subnet, total_ip, free_ip, saved_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """, (pool['ippid'], name, location, subnet_str, total_ip, free_ip, now_shamsi_day))

            if subnet_str != "Unknown":
                try:
                    network_lookup_list.append({
                        'network': ipaddress.ip_network(subnet_str, strict=False),
                        'group': location
                    })
                except:
                    continue

        # ── 2. VPS PROCESSING ─────────────────────────────────────────────────
        print("Fetching VPS list...")
        all_vps_ids, all_vps_info = [], {}
        page, reslen = 1, 1000
        while True:
            raw_vps_list = api.listvs(page=page, reslen=reslen)
            if not raw_vps_list or not isinstance(raw_vps_list, dict):
                break
            all_vps_ids.extend(list(raw_vps_list.keys()))
            all_vps_info.update(raw_vps_list)
            if len(raw_vps_list) < reslen:
                break
            page += 1

        print(f"Fetching status for {len(all_vps_ids)} VPS...")
        all_status_data = {}
        for i in range(0, len(all_vps_ids), 1000):
            batch = all_vps_ids[i:i + 1000]
            status_batch = api.status(batch)
            if status_batch and isinstance(status_batch, dict):
                all_status_data.update(status_batch)
            time.sleep(1)

        status_map = {0: "Offline", 1: "Online", 2: "Suspend", 3: "Unknown"}

        for vid in all_vps_ids:
            vinfo = all_vps_info.get(vid, {})
            sinfo = all_status_data.get(vid, {}) if isinstance(all_status_data.get(vid), dict) else {}

            ips_dict  = vinfo.get('ips', {})
            ip_list   = list(ips_dict.values())
            first_ip  = ip_list[0] if ip_list else ""

            vps_group = "Unknown"
            if first_ip:
                pool_name_from_ip = ip_to_pool_name.get(first_ip, "")
                for prefix in sorted_prefixes:
                    if prefix in pool_name_from_ip:
                        vps_group = group_mapping[prefix]
                        break

            cursor.execute("""
                INSERT INTO vps_snapshots (
                    vpsid, hostname, status, used_cpu, used_ram, used_inode,
                    io_read, io_write, ram, os_name, space, cpu_cores,
                    nic_type, mac, timezone, server_name, email, ips,
                    all_ips, location, creation_date_shamsi, edit_date_shamsi, saved_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                int(vid),
                vinfo.get('hostname', ""),
                status_map.get(sinfo.get('status', 3), "Unknown"),
                sinfo.get('used_cpu', "0.00"),
                int(sinfo.get('used_ram', 0)),
                sinfo.get('used_inode', "0"),
                int(sinfo.get('io_read', 0)),
                int(sinfo.get('io_write', 0)),
                int(vinfo.get('ram', 0)),
                vinfo.get('os_name', ""),
                int(vinfo.get('space', 0)),
                int(vinfo.get('cores', 0)),
                vinfo.get('nic_type', ""),
                vinfo.get('mac', ""),
                vinfo.get('timezone', ""),
                vinfo.get('server_name', ""),
                vinfo.get('email', "no-email"),
                first_ip,
                ", ".join(ip_list),
                vps_group,
                unix_to_shamsi(vinfo.get('time')),
                unix_to_shamsi(vinfo.get('edittime')),
                now_shamsi_day,
            ))

        # ── 3. ARCHIVING ──────────────────────────────────────────────────────
        archive_old_data(cursor)

        conn.commit()
        print(f"Successfully saved {len(all_vps_ids)} VPS records to IRvpsInfo.")

    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()
        conn.rollback()
    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    main()