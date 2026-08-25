import os
import pymysql
import pymysql.cursors
from datetime import datetime
from dotenv import load_dotenv

load_dotenv()


def get_conn():
    return pymysql.connect(
        host=os.getenv('DB_HOST', '127.0.0.1'),
        port=int(os.getenv('DB_PORT', 3306)),
        user=os.getenv('DB_USER', 'root'),
        password=os.getenv('DB_PASS', ''),
        database=os.getenv('DB_NAME', 'vps_monitor'),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
        connect_timeout=10,
    )


def init_db(conn):
    """Create all tables if they don't exist."""
    statements = [
        # ── vps_info: current (static) state of each VPS ─────────────────
        """
        CREATE TABLE IF NOT EXISTS vps_info (
            vpsid         INT          NOT NULL,
            region        VARCHAR(5)   NOT NULL,
            hostname      VARCHAR(255) DEFAULT '',
            email         VARCHAR(255) DEFAULT '',
            os_name       VARCHAR(100) DEFAULT '',
            ram           INT          DEFAULT 0,
            space         INT          DEFAULT 0,
            cpu_cores     INT          DEFAULT 0,
            nic_type      VARCHAR(50)  DEFAULT '',
            mac           VARCHAR(20)  DEFAULT '',
            timezone      VARCHAR(50)  DEFAULT '',
            server_name   VARCHAR(100) DEFAULT '',
            ips           VARCHAR(255) DEFAULT '',
            all_ips       TEXT,
            ip_location   VARCHAR(100) DEFAULT 'Unknown',
            creation_date DATETIME     NULL,
            edit_date     DATETIME     NULL,
            updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (vpsid, region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """,
        # ── vps_snapshots: time-series VPS stats ──────────────────────────
        """
        CREATE TABLE IF NOT EXISTS vps_snapshots (
            id           BIGINT       NOT NULL AUTO_INCREMENT,
            region       VARCHAR(5)   NOT NULL,
            vpsid        INT          NOT NULL,
            collected_at DATETIME     NOT NULL,
            status       VARCHAR(10)  DEFAULT 'Unknown',
            used_cpu     DECIMAL(6,2) DEFAULT 0,
            used_ram     INT          DEFAULT 0,
            used_inode   VARCHAR(30)  DEFAULT '0',
            io_read      INT          DEFAULT 0,
            io_write     INT          DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_rvt (region, vpsid, collected_at),
            INDEX idx_time (collected_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """,
        # ── ip_pools: current IP pool state (upserted each run) ───────────
        """
        CREATE TABLE IF NOT EXISTS ip_pools (
            region           VARCHAR(5)   NOT NULL,
            ippid            VARCHAR(20)  NOT NULL,
            name             VARCHAR(255) DEFAULT '',
            subnet           VARCHAR(50)  DEFAULT '',
            total_ip         INT          DEFAULT 0,
            free_ip          INT          DEFAULT 0,
            ip_group         VARCHAR(100) DEFAULT 'Unknown',
            locked           TINYINT      DEFAULT 0,
            lock_time        DATETIME     NULL,
            lock_description VARCHAR(255) DEFAULT '',
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (region, ippid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """,
        # ── locked_ips: individual IPs currently locked ───────────────────
        """
        CREATE TABLE IF NOT EXISTS locked_ips (
            region           VARCHAR(5)   NOT NULL,
            ip               VARCHAR(50)  NOT NULL,
            ippid            VARCHAR(20)  DEFAULT '',
            pool_name        VARCHAR(255) DEFAULT '',
            lock_description VARCHAR(255) DEFAULT '',
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (region, ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """,
        # ── server_snapshots: time-series slave-server stats ──────────────
        """
        CREATE TABLE IF NOT EXISTS server_snapshots (
            id                   BIGINT       NOT NULL AUTO_INCREMENT,
            region               VARCHAR(5)   NOT NULL,
            serid                VARCHAR(20)  NOT NULL,
            collected_at         DATETIME     NOT NULL,
            server_name          VARCHAR(100) DEFAULT '',
            server_group         VARCHAR(100) DEFAULT '',
            hostname             VARCHAR(255) DEFAULT '',
            ip                   VARCHAR(50)  DEFAULT '',
            server_type          VARCHAR(20)  DEFAULT '',
            status               TINYINT      DEFAULT 0,
            locked               TINYINT      DEFAULT 0,
            lock_description     VARCHAR(255) DEFAULT '',
            os                   VARCHAR(100) DEFAULT '',
            cpu_cores            INT          DEFAULT 0,
            sys_load             DECIMAL(6,2) DEFAULT 0,
            total_ram_mb         INT          DEFAULT 0,
            used_ram_mb          INT          DEFAULT 0,
            available_ram_mb     INT          DEFAULT 0,
            total_disk_gb        INT          DEFAULT 0,
            used_disk_gb         INT          DEFAULT 0,
            free_space_gb        INT          DEFAULT 0,
            vps_running          INT          DEFAULT 0,
            vps_total            INT          DEFAULT 0,
            vps_limit            INT          DEFAULT 0,
            vps_allocated_ram_mb INT          DEFAULT 0,
            ram_overcommit       INT          DEFAULT 0,
            version              VARCHAR(20)  DEFAULT '',
            patch                VARCHAR(10)  DEFAULT '',
            lic_expires          VARCHAR(30)  DEFAULT '',
            vnc_ip               VARCHAR(50)  DEFAULT '',
            alloc_space_gb       INT          DEFAULT 0,
            alloc_cpu            INT          DEFAULT 0,
            alloc_cpu_percent    DECIMAL(6,2) DEFAULT 0,
            alloc_bandwidth      BIGINT       DEFAULT 0,
            last_reverse_sync    DATETIME     NULL,
            cpu_percent          TINYINT      DEFAULT 0,
            cpu_percent_free     TINYINT      DEFAULT 0,
            ram_percent          TINYINT      DEFAULT 0,
            ram_percent_free     TINYINT      DEFAULT 0,
            num_vps_api          INT          DEFAULT 0,
            ip_count             INT          DEFAULT 0,
            bandwidth            BIGINT       DEFAULT 0,
            virts                VARCHAR(100) DEFAULT '',
            PRIMARY KEY (id),
            INDEX idx_rst  (region, serid, collected_at),
            INDEX idx_group (region, server_group),
            INDEX idx_time  (collected_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """,
    ]
    with conn.cursor() as cur:
        for sql in statements:
            cur.execute(sql)
        migrations = [
            # add new columns if missing
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS vps_total INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS vps_limit INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS ram_overcommit INT DEFAULT 0",
            "ALTER TABLE server_snapshots MODIFY COLUMN ram_overcommit INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS lock_description VARCHAR(255) DEFAULT ''",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS version VARCHAR(20) DEFAULT ''",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS lic_expires VARCHAR(30) DEFAULT ''",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS cpu_percent TINYINT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS cpu_percent_free TINYINT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS ram_percent TINYINT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS ram_percent_free TINYINT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS patch VARCHAR(10) DEFAULT ''",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS vnc_ip VARCHAR(50) DEFAULT ''",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS alloc_space_gb INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS alloc_cpu INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS alloc_cpu_percent DECIMAL(6,2) DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS alloc_bandwidth BIGINT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS last_reverse_sync DATETIME NULL",
            "ALTER TABLE server_snapshots DROP COLUMN IF EXISTS net_in_bytes",
            "ALTER TABLE server_snapshots DROP COLUMN IF EXISTS net_out_bytes",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS num_vps_api INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS ip_count INT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS bandwidth BIGINT DEFAULT 0",
            "ALTER TABLE server_snapshots ADD COLUMN IF NOT EXISTS virts VARCHAR(100) DEFAULT ''",
            # drop percentage columns (data is derived from raw MB/GB values)
            "ALTER TABLE server_snapshots DROP COLUMN IF EXISTS cpu_used_pct",
            "ALTER TABLE server_snapshots DROP COLUMN IF EXISTS ram_used_pct",
            "ALTER TABLE server_snapshots DROP COLUMN IF EXISTS disk_used_pct",
            # ip_pools lock columns
            "ALTER TABLE ip_pools ADD COLUMN IF NOT EXISTS locked TINYINT DEFAULT 0",
            "ALTER TABLE ip_pools ADD COLUMN IF NOT EXISTS lock_time DATETIME NULL",
            "ALTER TABLE ip_pools ADD COLUMN IF NOT EXISTS lock_description VARCHAR(255) DEFAULT ''",
        ]
        for sql in migrations:
            try:
                cur.execute(sql)
            except Exception:
                pass
    conn.commit()


# ─── helpers ──────────────────────────────────────────────────────────────────

def _ts_to_dt(ts):
    try:
        t = int(ts) if ts else 0
        return datetime.fromtimestamp(t) if t > 0 else None
    except Exception:
        return None


def _flt(v, default=0.0):
    try:
        return float(v)
    except Exception:
        return default


def _int(v, default=0):
    try:
        return int(v)
    except Exception:
        return default


# ─── save functions ───────────────────────────────────────────────────────────

def save_vps_info(conn, region, vps_dict):
    if not vps_dict:
        return
    sql = """
        INSERT INTO vps_info
            (vpsid, region, hostname, email, os_name, ram, space, cpu_cores,
             nic_type, mac, timezone, server_name, ips, all_ips,
             ip_location, creation_date, edit_date, updated_at)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,NOW())
        ON DUPLICATE KEY UPDATE
            hostname      = VALUES(hostname),
            email         = VALUES(email),
            os_name       = VALUES(os_name),
            ram           = VALUES(ram),
            space         = VALUES(space),
            cpu_cores     = VALUES(cpu_cores),
            nic_type      = VALUES(nic_type),
            mac           = VALUES(mac),
            timezone      = VALUES(timezone),
            server_name   = VALUES(server_name),
            ips           = VALUES(ips),
            all_ips       = VALUES(all_ips),
            ip_location   = VALUES(ip_location),
            creation_date = VALUES(creation_date),
            edit_date     = VALUES(edit_date),
            updated_at    = NOW()
    """
    rows = [
        (
            _int(vid), region,
            v.get('hostname', ''),
            v.get('email', 'no-email'),
            v.get('os_name', ''),
            _int(v.get('ram', 0)),
            _int(v.get('space', 0)),
            _int(v.get('cpu_cores', 0)),
            v.get('nic_type', ''),
            v.get('mac', ''),
            v.get('timezone', ''),
            v.get('server_name', ''),
            v.get('ips', ''),
            v.get('all_ips', ''),
            v.get('IP Location', 'Unknown'),
            _ts_to_dt(v.get('_created_ts', 0)),
            _ts_to_dt(v.get('_edited_ts', 0)),
        )
        for vid, v in vps_dict.items()
    ]
    with conn.cursor() as cur:
        cur.executemany(sql, rows)
    conn.commit()
    print(f"  vps_info      : {len(rows)} upserted")


def save_vps_snapshots(conn, region, vps_dict, collected_at):
    if not vps_dict:
        return
    sql = """
        INSERT INTO vps_snapshots
            (region, vpsid, collected_at, status, used_cpu,
             used_ram, used_inode, io_read, io_write)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    rows = [
        (
            region,
            _int(vid),
            collected_at,
            v.get('status', 'Unknown'),
            _flt(v.get('used_cpu', 0)),
            _int(v.get('used_ram', 0)),
            str(v.get('used_inode', '0')),
            _int(v.get('io_read', 0)),
            _int(v.get('io_write', 0)),
        )
        for vid, v in vps_dict.items()
    ]
    with conn.cursor() as cur:
        cur.executemany(sql, rows)
    conn.commit()
    print(f"  vps_snapshots : {len(rows)} inserted")


def save_ip_pools(conn, region, grouped_pools):
    if not grouped_pools:
        return
    sql = """
        INSERT INTO ip_pools
            (region, ippid, name, subnet, total_ip, free_ip, ip_group,
             locked, lock_time, lock_description, updated_at)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,NOW())
        ON DUPLICATE KEY UPDATE
            name             = VALUES(name),
            subnet           = VALUES(subnet),
            total_ip         = VALUES(total_ip),
            free_ip          = VALUES(free_ip),
            ip_group         = VALUES(ip_group),
            locked           = VALUES(locked),
            lock_time        = VALUES(lock_time),
            lock_description = VALUES(lock_description),
            updated_at       = NOW()
    """
    rows = [
        (
            region,
            p.get('ippid', ''),
            p.get('name', ''),
            p.get('subnet', ''),
            _int(p.get('total_ip', 0)),
            _int(p.get('free_ip', 0)),
            group_name,
            _int(p.get('locked', 0)),
            p.get('lock_time'),
            p.get('lock_description', ''),
        )
        for group_name, pools in grouped_pools.items()
        for p in pools
    ]
    with conn.cursor() as cur:
        cur.executemany(sql, rows)
    conn.commit()
    print(f"  ip_pools      : {len(rows)} upserted")


def save_locked_ips(conn, region, locked_ips_list):
    """Replace locked IPs for this region — reflects current lock state."""
    with conn.cursor() as cur:
        cur.execute("DELETE FROM locked_ips WHERE region = %s", (region,))
        if locked_ips_list:
            sql = """
                INSERT INTO locked_ips
                    (region, ip, ippid, pool_name, lock_description, updated_at)
                VALUES (%s,%s,%s,%s,%s,NOW())
            """
            rows = [
                (
                    region,
                    item['ip'],
                    item.get('ippid', ''),
                    item.get('pool_name', ''),
                    item.get('lock_description', ''),
                )
                for item in locked_ips_list
            ]
            cur.executemany(sql, rows)
    conn.commit()
    print(f"  locked_ips    : {len(locked_ips_list)} saved")


def save_servers(conn, region, servers_list, collected_at):
    if not servers_list:
        return
    sql = """
        INSERT INTO server_snapshots
            (region, serid, collected_at, server_name, server_group, hostname, ip,
             server_type, status, locked, lock_description, os, cpu_cores, sys_load,
             total_ram_mb, used_ram_mb, available_ram_mb,
             total_disk_gb, used_disk_gb, free_space_gb,
             vps_running, vps_total, vps_limit, vps_allocated_ram_mb, ram_overcommit,
             version, lic_expires,
             patch, vnc_ip, alloc_space_gb, alloc_cpu, alloc_cpu_percent,
             alloc_bandwidth, last_reverse_sync,
             cpu_percent, cpu_percent_free, ram_percent, ram_percent_free,
             num_vps_api, ip_count, bandwidth, virts)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    rows = []
    for s in servers_list:
        vps    = s.get('vps', {})
        locked = s.get('locked', 0)
        if isinstance(locked, dict):
            if 'status' in locked:
                # {'status': True/False, 'description': '...'}
                locked_val  = bool(locked.get('status', False))
                locked_desc = str(locked.get('description', locked.get('reason', '')))
            else:
                locked_val = len(locked) > 0
                # {'time': 1783027719, 'reason': {0: 'Locked By Admin'}} or {0: 'text'}
                reason = locked.get('reason', locked.get('description', locked.get('msg')))
                if isinstance(reason, dict):
                    locked_desc = str(list(reason.values())[0]) if reason else ''
                elif reason is not None:
                    locked_desc = str(reason)
                else:
                    # raw int-keyed dict: {0: 'Locked By Admin'}
                    str_vals = [str(v) for v in locked.values() if isinstance(v, str)]
                    locked_desc = str_vals[0] if str_vals else ''
        else:
            locked_val  = bool(int(locked) if locked else 0)
            locked_desc = ''

        total_ram  = _int(s.get('total_ram_mb', 0))
        alloc_ram  = _int(vps.get('allocated_ram_mb', 0))
        overcommit = _int(vps.get('ram_overcommit', 0))

        rows.append((
            region,
            s.get('serid', ''),
            collected_at,
            s.get('server_name', ''),
            s.get('server_group', ''),
            s.get('hostname', ''),
            s.get('ip', ''),
            s.get('server_type', ''),
            _int(s.get('status', 0)),
            1 if locked_val else 0,
            locked_desc,
            s.get('os', ''),
            _int(s.get('cpu_cores', 0)),
            _flt(s.get('sys_load', 0)),
            total_ram,
            _int(s.get('used_ram_mb', 0)),
            _int(s.get('available_ram_mb', 0)),
            _int(s.get('total_disk_gb', 0)),
            _int(s.get('used_disk_gb', 0)),
            _int(s.get('free_space_gb', 0)),
            _int(vps.get('running_count', 0)),
            _int(vps.get('total_count', 0)),
            _int(vps.get('limit', 0)),
            alloc_ram,
            overcommit,
            s.get('version', ''),
            s.get('lic_expires', ''),
            s.get('patch', ''),
            s.get('vnc_ip', ''),
            _int(s.get('alloc_space_gb', 0)),
            _int(s.get('alloc_cpu', 0)),
            _flt(s.get('alloc_cpu_percent', 0)),
            _int(s.get('alloc_bandwidth', 0)),
            _ts_to_dt(s.get('last_reverse_sync_ts', 0)),
            _int(s.get('cpu_percent', 0)),
            _int(s.get('cpu_percent_free', 0)),
            _int(s.get('ram_percent', 0)),
            _int(s.get('ram_percent_free', 0)),
            _int(vps.get('num_vps_api', 0)),
            _int(s.get('ip_count', 0)),
            _int(s.get('bandwidth', 0)),
            s.get('virts', ''),
        ))
    with conn.cursor() as cur:
        cur.executemany(sql, rows)
    conn.commit()
    print(f"  server_snapshots: {len(rows)} inserted")


# ─── data retention / compaction ──────────────────────────────────────────────

def compact_db(conn):
    queries = [
        # vps_snapshots — hourly, last 30 days
        """
        DELETE s1 FROM vps_snapshots s1
        INNER JOIN vps_snapshots s2
            ON  s1.region = s2.region
            AND s1.vpsid  = s2.vpsid
            AND DATE(s1.collected_at) = DATE(s2.collected_at)
            AND HOUR(s1.collected_at) = HOUR(s2.collected_at)
            AND s2.id < s1.id
        WHERE s1.collected_at <  CURDATE()
          AND s1.collected_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        """,
        # vps_snapshots — daily, older than 30 days
        """
        DELETE s1 FROM vps_snapshots s1
        INNER JOIN vps_snapshots s2
            ON  s1.region = s2.region
            AND s1.vpsid  = s2.vpsid
            AND DATE(s1.collected_at) = DATE(s2.collected_at)
            AND s2.id < s1.id
        WHERE s1.collected_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        """,
        # server_snapshots — hourly, last 30 days
        """
        DELETE s1 FROM server_snapshots s1
        INNER JOIN server_snapshots s2
            ON  s1.region = s2.region
            AND s1.serid  = s2.serid
            AND DATE(s1.collected_at) = DATE(s2.collected_at)
            AND HOUR(s1.collected_at) = HOUR(s2.collected_at)
            AND s2.id < s1.id
        WHERE s1.collected_at <  CURDATE()
          AND s1.collected_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        """,
        # server_snapshots — daily, older than 30 days
        """
        DELETE s1 FROM server_snapshots s1
        INNER JOIN server_snapshots s2
            ON  s1.region = s2.region
            AND s1.serid  = s2.serid
            AND DATE(s1.collected_at) = DATE(s2.collected_at)
            AND s2.id < s1.id
        WHERE s1.collected_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        """,
    ]
    total = 0
    with conn.cursor() as cur:
        for q in queries:
            cur.execute(q)
            total += cur.rowcount
    conn.commit()
    print(f"  compact_db    : {total} rows removed")