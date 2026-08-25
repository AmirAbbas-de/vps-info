import os
import time
import ipaddress
import re
import mysql.connector
from virtvs import virtapi
import jdatetime
from dotenv import load_dotenv

load_dotenv()

DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'user': os.getenv('DB_USER', 'mobin'),
    'password': os.getenv('DB_PASSWORD'),
    'database': 'FRvpsInfo'
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
      - Last 24 hours   : keep everything (raw 15-minute snapshots)
      - 1 day - 3 weeks : keep only the FIRST record of each hour
      - Older than 3 wk : keep only the FIRST record of each day
    "First record" = lowest auto-increment id within the group,
    which is the earliest snapshot of that hour/day.
    """
    print("Starting multi-stage archival...")
    target_tables = {'vps_snapshots': 'vpsid', 'ippool_snapshots': 'ippid'}

    for table, id_field in target_tables.items():
        # Stage 1: older than 1 day -> 1 record per hour (first of the hour)
        cursor.execute(f"""
            DELETE FROM {table}
            WHERE recorded_at < NOW() - INTERVAL 1 DAY
            AND id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id FROM {table}
                    WHERE recorded_at < NOW() - INTERVAL 1 DAY
                    GROUP BY {id_field}, DATE_FORMAT(recorded_at, '%Y-%m-%d %H')
                ) AS tmp
            );
        """)
        print(f"  {table}: hourly downsample removed {cursor.rowcount} rows")

        # Stage 2: older than 3 weeks -> 1 record per day (first of the day)
        cursor.execute(f"""
            DELETE FROM {table}
            WHERE recorded_at < NOW() - INTERVAL 21 DAY
            AND id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id FROM {table}
                    WHERE recorded_at < NOW() - INTERVAL 21 DAY
                    GROUP BY {id_field}, DATE_FORMAT(recorded_at, '%Y-%m-%d')
                ) AS tmp
            );
        """)
        print(f"  {table}: daily downsample removed {cursor.rowcount} rows")

    print("Archival finished.")

def main():
    api = virtapi()
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        print("Processing IP pools...")
        pools_data = api.listippools(page=1, reslen=90000)
        raw_ips = api.listips(page=1, reslen=90000)
        
        ip_to_pool_name = {info['ip']: info.get('ippool_name', '') for id, info in raw_ips.items()}
        
        group_mapping = {
            'EQ': 'EQ-L4 - Turkey', '45.95.65.': 'EQ-L4 - Turkey', '181.214.115.': 'M247 - UAE',
            '181.41.216.': 'M247 - UAE', '185.135.156': 'M247 - UAE', '194.110.242.': 'M247 - UAE',
            '181.214.140': 'Redstation - Netherland', '81.161.229': 'Redstation - Netherland',
            '185.179.216': 'Redstation - England', '213.137.72': 'Redstation - England','PM-': 'Payam - OLD',
            '185.214.101': 'Redstation - Spin', 'TR': 'MUV - Turkey', 'FR3': 'OVH - France',
            'FR-152.': 'OVH - France', 'FR-146.': 'OVH - France', '151.80.171': 'OVH - France',
            'FR-149.202': 'OVH - France', '176.31.219': 'OVH - France', 'CA571823SA': 'OVH - Canada',
            '66.70.234': 'OVH - Canada', '191.101.113': 'HostKEY - Netherland', 'PM-158.': 'Payam - OLD',
            'PM-185': 'Payam - OLD', 'Hostkey-NL': 'HostKEY - Netherland', '46.183.31': 'HostKEY - Netherland',
            '141.11.0.': 'HostKEY - Netherland', '213.139.72': 'Redstation - England',
            '185.45.252': 'Redstation - France', 'RDST-2001:1b40:5000': 'Redstation - V6',
            '163.5.94': 'Redstation - Germany', '188.209.138': 'HostKEY - USA',
            '217.138.162': 'M247', '81.168.119': 'Redstation - England',
            'CA5': 'OVH - Canada', 'Hostkey-DE': 'HostKEY - Germany'
            }

        sorted_prefixes = sorted(group_mapping.keys(), key=len, reverse=True)

        for pid, pool in pools_data.items():
            name = pool['ippool_name'].strip()
            location = "Unknown"
            for prefix in sorted_prefixes:
                if prefix in name:
                    location = group_mapping[prefix]
                    break
            
            cursor.execute("""
                INSERT INTO ippool_snapshots (ippid, name, location, total_ip, free_ip)
                VALUES (%s, %s, %s, %s, %s)
            """, (pool['ippid'], name, location, int(pool.get('totalip', 0)), 
                  int(pool.get('freeip', pool.get('unassignedip', '0')))))

        # 2. Processing VPS
        print("Processing VPS data...")
        all_vps_ids, all_vps_info = [], {}
        page = 1
        while True:
            raw_vps = api.listvs(page=page, reslen=1000)
            if not raw_vps: break
            all_vps_ids.extend(list(raw_vps.keys()))
            all_vps_info.update(raw_vps)
            if len(raw_vps) < 1000: break
            page += 1

        all_status = api.status(all_vps_ids)
        status_map = {0: "Offline", 1: "Online", 2: "Suspend", 3: "Unknown"}

        for vid in all_vps_ids:
            v = all_vps_info.get(vid, {})
            s = all_status.get(vid, {}) if isinstance(all_status.get(vid), dict) else {}
            ips = list(v.get('ips', {}).values())
            first_ip = ips[0] if ips else ""

            vps_group = "Unknown"
            if first_ip:
                p_name = ip_to_pool_name.get(first_ip, "")
                for prefix in sorted_prefixes:
                    if prefix in p_name:
                        vps_group = group_mapping[prefix]
                        break

            cursor.execute("""
                INSERT INTO vps_snapshots (
                    vpsid, hostname, status, used_cpu, used_ram, used_inode,
                    io_read, io_write, ram, os_name, space, cpu_cores,
                    server_name, email, ips, all_ips, location,
                    creation_date_shamsi, edit_date_shamsi
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                int(vid), v.get('hostname', ""), status_map.get(s.get('status', 3), "Unknown"),
                s.get('used_cpu', "0.00"), int(s.get('used_ram', 0)), s.get('used_inode', "0"),
                int(s.get('io_read', 0)), int(s.get('io_write', 0)),
                v.get('ram', "0"), v.get('os_name', ""), v.get('space', "0"), v.get('cores', "0"),
                v.get('server_name', ""), v.get('email', ""), first_ip, ", ".join(ips),
                vps_group, unix_to_shamsi(v.get('time')), unix_to_shamsi(v.get('edittime'))
            ))

        # 3. Final Archival and Commit
        archive_old_data(cursor)
        conn.commit()
        print("Process finished successfully.")

    except Exception as e:
        print(f"Error: {e}")
        conn.rollback()
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    main()