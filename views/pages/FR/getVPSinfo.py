import os
import json
import mysql.connector
import paramiko
import re  # Ensure this is imported for the filename date extraction
from scp import SCPClient
from dotenv import load_dotenv

load_dotenv()

# --- CONFIGURATION ---
SSH_CONFIG = {
    'host': os.getenv('SSH_HOST'),
    'user': os.getenv('SSH_USER'),
    'pass': os.getenv('SSH_PASS'),
    'remote_path': '/root/Virtualizor/'
}

DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'user': os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_NAME', 'FRvpsInfo')
}

TEMP_LOCAL_DIR = './temp_json/'
if not os.path.exists(TEMP_LOCAL_DIR):
    os.makedirs(TEMP_LOCAL_DIR)


def archive_old_data(cursor):
    """
    Retention policy:
      - Last 24 hours   : keep everything in *_snapshots (raw 15-minute data)
      - 1 day - 3 weeks : keep only the FIRST record of each hour
                          (moved into *_hourly_history)
      - Older than 3 wk : keep only the FIRST record of each day
                          (thinned inside *_hourly_history)
    "First record" = lowest auto-increment id within the group,
    which is the earliest snapshot of that hour/day.
    """
    print("Starting data archival and rotation...")

    # --- 1. ARCHIVE SNAPSHOTS OLDER THAN 1 DAY TO HOURLY HISTORY ---
    tables_map = {
        'vps_snapshots': 'vps_hourly_history',
        'ippool_snapshots': 'ippool_hourly_history'
    }

    for snap_table, hist_table in tables_map.items():
        id_col = 'vpsid' if 'vps' in snap_table else 'ippid'

        # Move the first record of each hour (older than 1 day) into history
        archive_query = f"""
            INSERT INTO {hist_table}
            SELECT * FROM {snap_table}
            WHERE recorded_at < NOW() - INTERVAL 1 DAY
            AND id IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id
                    FROM {snap_table}
                    WHERE recorded_at < NOW() - INTERVAL 1 DAY
                    GROUP BY {id_col}, DATE_FORMAT(recorded_at, '%Y-%m-%d %H')
                ) AS tmp
            )
        """
        cursor.execute(archive_query)
        print(f"  {snap_table}: archived {cursor.rowcount} hourly rows to {hist_table}")

        # Delete everything older than 1 day from snapshots
        # (the kept-per-hour copies now live in history)
        cursor.execute(f"DELETE FROM {snap_table} WHERE recorded_at < NOW() - INTERVAL 1 DAY")
        print(f"  {snap_table}: purged {cursor.rowcount} raw rows")

    # --- 2. THIN HOURLY HISTORY TO DAILY (After 3 Weeks) ---
    history_tables = ['vps_hourly_history', 'ippool_hourly_history']

    for hist_table in history_tables:
        id_col = 'vpsid' if 'vps' in hist_table else 'ippid'

        # Delete records older than 3 weeks UNLESS they are the first record of that day
        thinning_query = f"""
            DELETE FROM {hist_table}
            WHERE recorded_at < NOW() - INTERVAL 21 DAY
            AND id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id
                    FROM {hist_table}
                    WHERE recorded_at < NOW() - INTERVAL 21 DAY
                    GROUP BY {id_col}, DATE(recorded_at)
                ) AS temp
            )
        """
        cursor.execute(thinning_query)
        print(f"  {hist_table}: daily thinning removed {cursor.rowcount} rows")

    print("Archival process finished.")
def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        ssh.connect(SSH_CONFIG['host'], username=SSH_CONFIG['user'], password=SSH_CONFIG['pass'])
        
        # 1. Find the latest data folder on the remote server
        stdin, stdout, stderr = ssh.exec_command(f"ls -td {SSH_CONFIG['remote_path']}data_*/ | head -1")
        latest_folder = stdout.read().decode().strip()
        
        if not latest_folder:
            print("No data folders found on remote server.")
            return

        # 2. Find the single newest VPS file and IPPools file in that folder
        cmd_vps = f"ls -t {latest_folder}vps_*.json | head -n 1"
        cmd_ipp = f"ls -t {latest_folder}ippools_*.json | head -n 1"

        stdin, stdout, stderr = ssh.exec_command(cmd_vps)
        latest_vps_path = stdout.read().decode().strip()

        stdin, stdout, stderr = ssh.exec_command(cmd_ipp)
        latest_ipp_path = stdout.read().decode().strip()

        # 3. Download only these specific latest files
        with SCPClient(ssh.get_transport()) as scp:
            if latest_vps_path:
                print(f"Downloading newest VPS: {os.path.basename(latest_vps_path)}")
                scp.get(latest_vps_path, TEMP_LOCAL_DIR)
            
            if latest_ipp_path:
                print(f"Downloading newest IPPools: {os.path.basename(latest_ipp_path)}")
                scp.get(latest_ipp_path, TEMP_LOCAL_DIR)
                
    except Exception as e:
        print(f"SSH/SCP Error: {e}")
        return
    finally:
        ssh.close()

    # Database connection logic
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()

    try:
        # Identify the local files just downloaded
        vps_file = next((f for f in os.listdir(TEMP_LOCAL_DIR) if 'vps' in f), None)
        ipp_file = next((f for f in os.listdir(TEMP_LOCAL_DIR) if 'ippools' in f), None)

        # --- PROCESS VPS ---
        if vps_file:
            # Capture Date_HH-MM part
            match = re.search(r'(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})', vps_file)
            if match:
                # Groups: 1=Date, 2=Hour, 3=Minute -> Result: "1405-02-01 15:42"
                now_shamsi_day = f"{match.group(1)} {match.group(2)}:{match.group(3)}"
            else:
                now_shamsi_day = "Unknown"
            
            print(f"Processing VPS records for: {now_shamsi_day}")
            
            with open(os.path.join(TEMP_LOCAL_DIR, vps_file), 'r') as f:
                vps_data = json.load(f)
                for vid, vinfo in vps_data.items():
                    # Extract IP info
                    raw_all_ips = vinfo.get('all_ips', "")
                    ip_list = raw_all_ips.split(',') if isinstance(raw_all_ips, str) else []
                    if not ip_list and vinfo.get('ips'):
                        ip_list = [vinfo.get('ips')]
                    
                    first_ip = vinfo.get('ips', "")
                    vps_group = vinfo.get('IP Location', "Unknown")

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
                        vinfo.get('status', "Unknown"),
                        vinfo.get('used_cpu', "0.00"), 
                        int(vinfo.get('used_ram', 0)), 
                        vinfo.get('used_inode', "0"),
                        int(vinfo.get('io_read', 0)), 
                        int(vinfo.get('io_write', 0)),
                        int(vinfo.get('ram', 0)), 
                        vinfo.get('os_name', ""), 
                        int(vinfo.get('space', 0)),
                        int(vinfo.get('cpu_cores', 0)), 
                        vinfo.get('nic_type', ""), 
                        vinfo.get('mac', ""),
                        vinfo.get('timezone', ""), 
                        vinfo.get('server_name', ""), 
                        vinfo.get('email', "no-email"),
                        first_ip, 
                        ", ".join(ip_list), 
                        vps_group, 
                        vinfo.get('creation_date_shamsi'), 
                        vinfo.get('edit_date_shamsi'), 
                        now_shamsi_day
                    ))

        # --- PROCESS IP POOLS ---
        if ipp_file:
            match_ipp = re.search(r'(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})', ipp_file)
            ipp_shamsi_day = f"{match_ipp.group(1)} {match_ipp.group(2)}:{match_ipp.group(3)}" if match_ipp else "Unknown"
            
            print(f"Processing IP Pool records for: {ipp_shamsi_day}")
            
            with open(os.path.join(TEMP_LOCAL_DIR, ipp_file), 'r') as f:
                ipp_data = json.load(f)
                for location_name, pools in ipp_data.items():
                    for pool in pools:
                        cursor.execute("""
                            INSERT INTO ippool_snapshots (ippid, name, location, subnet, total_ip, free_ip, saved_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """, (
                            int(pool.get('ippid')), 
                            pool.get('name'), 
                            location_name,
                            pool.get('subnet'), 
                            int(pool.get('total_ip', 0)), 
                            int(pool.get('free_ip', 0)),
                            ipp_shamsi_day
                        ))

        # Perform retention archival and commit changes
        archive_old_data(cursor)
        conn.commit()
        print("Success: Database updated with latest snapshots.")

    except Exception as e:
        print(f"Database Error: {e}")
        conn.rollback()
    finally:
        # Cleanup: Remove files from local machine to prevent re-processing next time
        for f in os.listdir(TEMP_LOCAL_DIR):
            try:
                os.remove(os.path.join(TEMP_LOCAL_DIR, f))
            except:
                pass
        cursor.close()
        conn.close()


if __name__ == "__main__":
    main()