"""
dashboard.py — Virtualizor Server Dashboard
Flask app. Run: python dashboard.py
"""
from flask import Flask, render_template_string, request, jsonify
import mysql.connector
import os
import json
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)

DB_CONFIG = {
    "host":     os.getenv("DB_HOST", "localhost"),
    "user":     os.getenv("DB_USER"),
    "password": os.getenv("DB_PASSWORD"),
    "database": "IRservers",
    "charset":  "utf8",
}

def get_db():
    return mysql.connector.connect(**DB_CONFIG)

def query(sql, params=None):
    conn = get_db()
    cur  = conn.cursor(dictionary=True)
    cur.execute(sql, params or ())
    rows = cur.fetchall()
    cur.close(); conn.close()
    return rows

def query_one(sql, params=None):
    rows = query(sql, params)
    return rows[0] if rows else None

# ── available timestamps (for the time selector) ──────────────────────────────
def get_timestamps():
    """Return distinct recorded_at_shamsi values from snapshots + daily, newest first."""
    rows = query("""
        SELECT recorded_at_shamsi, recorded_at, 'snapshot' AS src
        FROM server_snapshots
        GROUP BY recorded_at_shamsi, recorded_at
        UNION ALL
        SELECT recorded_at_shamsi, recorded_at, 'daily' AS src
        FROM server_daily
        GROUP BY recorded_at_shamsi, recorded_at
        ORDER BY recorded_at DESC
        LIMIT 200
    """)
    seen = set()
    out  = []
    for r in rows:
        key = r['recorded_at_shamsi']
        if key not in seen:
            seen.add(key)
            out.append(r)
    return out

def get_servers_at(shamsi_ts):
    """
    Return the server list for a given shamsi timestamp.
    Tries snapshots first, falls back to daily.
    """
    rows = query("""
        SELECT server_id, server_name, ip, num_vps, vps_limit,
               vcores_sold, cpu_physical_cores, status, locked, locked_reason,
               virt_version, virt_type,
               ram_total_gb, ram_sold_gb, ram_overcommit_gb,
               disk_total_gb, disk_sold_gb,
               live_cpu_pct, live_ram_pct, sys_load,
               recorded_at_shamsi, recorded_at
        FROM server_snapshots
        WHERE recorded_at_shamsi = %s
        ORDER BY server_id
    """, (shamsi_ts,))
    if not rows:
        rows = query("""
            SELECT server_id, server_name, ip, num_vps, vps_limit,
                   vcores_sold, cpu_physical_cores, status, locked, locked_reason,
                   virt_version, virt_type,
                   ram_total_gb, ram_sold_gb, ram_overcommit_gb,
                   disk_total_gb, disk_sold_gb,
                   live_cpu_pct, live_ram_pct, sys_load,
                   recorded_at_shamsi, recorded_at
            FROM server_daily
            WHERE recorded_at_shamsi = %s
            ORDER BY server_id
        """, (shamsi_ts,))
    return rows

def get_server_detail(server_id, shamsi_ts):
    """Full detail for one server at a given time."""
    row = query_one("""
        SELECT * FROM server_snapshots
        WHERE server_id = %s AND recorded_at_shamsi = %s
        ORDER BY recorded_at DESC LIMIT 1
    """, (server_id, shamsi_ts))
    if not row:
        row = query_one("""
            SELECT * FROM server_daily
            WHERE server_id = %s AND recorded_at_shamsi = %s
            ORDER BY recorded_at DESC LIMIT 1
        """, (server_id, shamsi_ts))
    return row

def get_server_history(server_id):
    """Last 48 snapshots for sparklines."""
    return query("""
        SELECT recorded_at_shamsi, live_cpu_pct, live_ram_pct,
               storage_used_pct, num_vps, sys_load
        FROM server_snapshots
        WHERE server_id = %s
        ORDER BY recorded_at DESC
        LIMIT 48
    """, (server_id,))

# ── HTML template ──────────────────────────────────────────────────────────────
TEMPLATE = r"""
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MobinHost — Node Monitor</title>
<style>
  :root {
    --bg:       #0d1117;
    --surface:  #161b22;
    --border:   #21262d;
    --accent:   #2ea043;
    --accent2:  #388bfd;
    --warn:     #d29922;
    --danger:   #da3633;
    --text:     #e6edf3;
    --muted:    #7d8590;
    --font:     'JetBrains Mono', 'Fira Mono', monospace;
    --sans:     'Inter', 'Segoe UI', sans-serif;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: var(--sans);
         font-size: 14px; min-height: 100vh; }

  /* ── header ── */
  header { border-bottom: 1px solid var(--border); padding: 14px 28px;
           display: flex; align-items: center; gap: 16px; }
  header h1 { font-size: 16px; font-weight: 600; letter-spacing: .04em;
              font-family: var(--font); color: var(--accent); }
  header .sub { color: var(--muted); font-size: 12px; margin-left: auto; }

  /* ── layout ── */
  .wrap { padding: 20px 28px; }

  /* ── time selector ── */
  .toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
             flex-wrap: wrap; }
  .toolbar label { color: var(--muted); font-size: 12px; }
  select, button {
    background: var(--surface); color: var(--text);
    border: 1px solid var(--border); border-radius: 6px;
    padding: 6px 12px; font-size: 13px; cursor: pointer;
    font-family: var(--font);
  }
  select:focus, button:focus { outline: 2px solid var(--accent2); }
  button.primary { background: var(--accent); color: #fff; border-color: var(--accent);
                   font-weight: 600; }
  button.primary:hover { opacity: .85; }
  .ts-badge { background: var(--border); border-radius: 4px; padding: 4px 10px;
              font-family: var(--font); font-size: 12px; color: var(--accent2); }

  /* ── stats row ── */
  .stats { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
  .stat-card { background: var(--surface); border: 1px solid var(--border);
               border-radius: 8px; padding: 14px 20px; min-width: 140px; flex: 1; }
  .stat-card .val { font-size: 26px; font-weight: 700; font-family: var(--font);
                    color: var(--accent2); }
  .stat-card .lbl { font-size: 11px; color: var(--muted); margin-top: 4px;
                    text-transform: uppercase; letter-spacing: .06em; }

  /* ── server table ── */
  .table-wrap { overflow-x: auto; border-radius: 8px;
                border: 1px solid var(--border); }
  table { width: 100%; border-collapse: collapse; }
  thead { background: var(--surface); }
  th { padding: 10px 14px; text-align: left; font-size: 11px;
       text-transform: uppercase; letter-spacing: .06em; color: var(--muted);
       border-bottom: 1px solid var(--border); white-space: nowrap; }
  td { padding: 10px 14px; border-bottom: 1px solid var(--border);
       font-family: var(--font); font-size: 12px; white-space: nowrap; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: rgba(56,139,253,.06); cursor: pointer; }

  /* ── badges ── */
  .badge { display: inline-block; border-radius: 4px; padding: 2px 8px;
           font-size: 11px; font-weight: 600; }
  .badge.online  { background: rgba(46,160,67,.2);  color: var(--accent); }
  .badge.offline { background: rgba(218,54,51,.2);  color: var(--danger); }
  .badge.locked  { background: rgba(210,153,34,.2); color: var(--warn); }
  .badge.ok      { background: rgba(56,139,253,.15);color: var(--accent2); }

  /* ── bar ── */
  .bar-wrap { width: 80px; height: 6px; background: var(--border);
              border-radius: 3px; display: inline-block; vertical-align: middle; }
  .bar-fill { height: 100%; border-radius: 3px; }
  .bar-green { background: var(--accent); }
  .bar-blue  { background: var(--accent2); }
  .bar-warn  { background: var(--warn); }
  .bar-red   { background: var(--danger); }

  /* ── modal overlay ── */
  .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7);
             z-index: 100; align-items: flex-start; justify-content: center;
             padding: 40px 16px; overflow-y: auto; }
  .overlay.show { display: flex; }
  .modal { background: var(--surface); border: 1px solid var(--border);
           border-radius: 12px; width: 100%; max-width: 860px;
           padding: 28px; position: relative; }
  .modal .close-btn { position: absolute; top: 16px; right: 16px;
                      background: none; border: none; color: var(--muted);
                      font-size: 20px; cursor: pointer; }
  .modal .close-btn:hover { color: var(--text); }
  .modal h2 { font-size: 16px; font-family: var(--font); color: var(--accent2);
              margin-bottom: 20px; }

  /* ── detail grid ── */
  .detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr));
                 gap: 12px; margin-bottom: 20px; }
  .detail-section { background: var(--bg); border: 1px solid var(--border);
                    border-radius: 8px; padding: 14px; }
  .detail-section h3 { font-size: 10px; text-transform: uppercase;
                       letter-spacing: .08em; color: var(--muted); margin-bottom: 10px; }
  .kv { display: flex; justify-content: space-between; gap: 8px;
        margin-bottom: 6px; align-items: baseline; }
  .kv .k { color: var(--muted); font-size: 11px; }
  .kv .v { color: var(--text); font-family: var(--font); font-size: 12px;
           text-align: right; max-width: 60%; overflow: hidden;
           text-overflow: ellipsis; white-space: nowrap; }

  /* ── sparkline ── */
  .spark-section { margin-top: 16px; }
  .spark-section h3 { font-size: 10px; text-transform: uppercase;
                      letter-spacing: .08em; color: var(--muted); margin-bottom: 10px; }
  .spark-row { display: flex; gap: 16px; flex-wrap: wrap; }
  .spark-box { background: var(--bg); border: 1px solid var(--border);
               border-radius: 8px; padding: 12px; flex: 1; min-width: 200px; }
  .spark-box .spark-label { font-size: 11px; color: var(--muted); margin-bottom: 6px; }
  svg.spark { width: 100%; height: 40px; overflow: visible; }

  /* ── empty ── */
  .empty { text-align: center; padding: 60px; color: var(--muted); }
</style>
</head>
<body>

<header>
  <h1>⬡ NODE MONITOR</h1>
  <span style="color:var(--muted);font-size:11px;font-family:var(--font)">MobinHost Infrastructure</span>
  <span class="sub" id="live-clock"></span>
</header>

<div class="wrap">

  <!-- Time selector -->
  <div class="toolbar">
    <label>Recorded at:</label>
    <select id="ts-select" onchange="loadServers()">
      {% for ts in timestamps %}
      <option value="{{ ts.recorded_at_shamsi }}"
        {% if ts.recorded_at_shamsi == selected_ts %}selected{% endif %}>
        {{ ts.recorded_at_shamsi }}  [{{ ts.src }}]
      </option>
      {% endfor %}
    </select>
    <span class="ts-badge" id="ts-badge">{{ selected_ts or '—' }}</span>
    <button class="primary" onclick="loadServers()">↻ Refresh</button>
  </div>

  <!-- Summary stats -->
  <div class="stats" id="stats-row">
    <div class="stat-card"><div class="val" id="st-total">—</div><div class="lbl">Total Nodes</div></div>
    <div class="stat-card"><div class="val" id="st-online">—</div><div class="lbl">Online</div></div>
    <div class="stat-card"><div class="val" id="st-vps">—</div><div class="lbl">Total VPS</div></div>
    <div class="stat-card"><div class="val" id="st-ram">—</div><div class="lbl">RAM Sold GB</div></div>
    <div class="stat-card"><div class="val" id="st-disk">—</div><div class="lbl">Disk Sold GB</div></div>
    <div class="stat-card"><div class="val" id="st-locked">—</div><div class="lbl">Locked</div></div>
  </div>

  <!-- Server table -->
  <div class="table-wrap">
    <table id="server-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Server</th>
          <th>IP</th>
          <th>Status</th>
          <th>Locked</th>
          <th>Virt</th>
          <th>Version</th>
          <th>VPS / Limit</th>
          <th>CPU Sold</th>
          <th>RAM Total</th>
          <th>RAM Sold</th>
          <th>RAM Overcom.</th>
          <th>Disk Total</th>
          <th>Disk Sold</th>
          <th>CPU Live%</th>
          <th>RAM Live%</th>
        </tr>
      </thead>
      <tbody id="server-tbody">
        <tr><td colspan="16" class="empty">Select a timestamp above</td></tr>
      </tbody>
    </table>
  </div>

</div>

<!-- Detail Modal -->
<div class="overlay" id="detail-overlay" onclick="closeModal(event)">
  <div class="modal" id="detail-modal">
    <button class="close-btn" onclick="closeOverlay()">✕</button>
    <h2 id="modal-title">Server Detail</h2>
    <div id="modal-body"></div>
  </div>
</div>

<script>
const SELECTED_TS = {{ selected_ts|tojson }};

// ── clock ──────────────────────────────────────────────────────────────────
function tick() {
  const now = new Date();
  document.getElementById('live-clock').textContent =
    now.toLocaleString('fa-IR', {timeZone:'Asia/Tehran'}) + ' — ' +
    now.toLocaleTimeString('en-GB');
}
tick(); setInterval(tick, 1000);

// ── load server list ────────────────────────────────────────────────────────
async function loadServers() {
  const ts = document.getElementById('ts-select').value;
  document.getElementById('ts-badge').textContent = ts;
  const res  = await fetch(`/api/servers?ts=${encodeURIComponent(ts)}`);
  const data = await res.json();
  renderTable(data.servers);
  renderStats(data.servers);
}

function pct_bar(pct, cls) {
  pct = Math.min(100, Math.max(0, pct||0));
  const color = pct > 90 ? 'bar-red' : pct > 75 ? 'bar-warn' : cls;
  return `<span class="bar-wrap"><span class="bar-fill ${color}" style="width:${pct}%"></span></span> ${pct.toFixed(0)}%`;
}

function gb(v) { return v != null ? (+v).toFixed(1) : '—'; }

function renderTable(servers) {
  const tbody = document.getElementById('server-tbody');
  if (!servers || servers.length === 0) {
    tbody.innerHTML = '<tr><td colspan="16" class="empty">No data for this timestamp</td></tr>';
    return;
  }
  tbody.innerHTML = servers.map(s => {
    const status  = s.status==1
      ? '<span class="badge online">Online</span>'
      : '<span class="badge offline">Offline</span>';
    const locked  = s.locked
      ? `<span class="badge locked" title="${s.locked_reason||''}">Locked</span>`
      : '<span class="badge ok">—</span>';
    const vpsLim  = s.vps_limit > 0 ? s.vps_limit : '∞';
    const cpuBar  = pct_bar(s.live_cpu_pct, 'bar-blue');
    const ramBar  = pct_bar(s.live_ram_pct, 'bar-green');
    const sold_ram_pct = s.ram_total_gb > 0
      ? (s.ram_sold_gb / s.ram_total_gb * 100) : 0;
    const sold_disk_pct = s.disk_total_gb > 0
      ? (s.disk_sold_gb / s.disk_total_gb * 100) : 0;

    return `<tr onclick="openDetail(${s.server_id})">
      <td style="color:var(--muted)">${s.server_id}</td>
      <td style="color:var(--accent2);font-weight:600">${s.server_name}</td>
      <td>${s.ip}</td>
      <td>${status}</td>
      <td>${locked}</td>
      <td style="color:var(--muted)">${s.virt_type}</td>
      <td>${s.virt_version||'—'}</td>
      <td><b>${s.num_vps}</b> / ${vpsLim}</td>
      <td>${s.vcores_sold||0} vCores</td>
      <td>${gb(s.ram_total_gb)} GB</td>
      <td>${pct_bar(sold_ram_pct,'bar-blue')} ${gb(s.ram_sold_gb)} GB</td>
      <td>${gb(s.ram_overcommit_gb)} GB</td>
      <td>${gb(s.disk_total_gb)} GB</td>
      <td>${pct_bar(sold_disk_pct,'bar-blue')} ${gb(s.disk_sold_gb)} GB</td>
      <td>${cpuBar}</td>
      <td>${ramBar}</td>
    </tr>`;
  }).join('');
}

function renderStats(servers) {
  if (!servers) return;
  document.getElementById('st-total').textContent  = servers.length;
  document.getElementById('st-online').textContent = servers.filter(s=>s.status==1).length;
  document.getElementById('st-vps').textContent    = servers.reduce((a,s)=>a+(+s.num_vps||0),0);
  document.getElementById('st-ram').textContent    = servers.reduce((a,s)=>a+(+s.ram_sold_gb||0),0).toFixed(0)+' GB';
  document.getElementById('st-disk').textContent   = servers.reduce((a,s)=>a+(+s.disk_sold_gb||0),0).toFixed(0)+' GB';
  document.getElementById('st-locked').textContent = servers.filter(s=>s.locked).length;
}

// ── detail modal ────────────────────────────────────────────────────────────
async function openDetail(server_id) {
  const ts  = document.getElementById('ts-select').value;
  const res = await fetch(`/api/server/${server_id}?ts=${encodeURIComponent(ts)}`);
  const d   = await res.json();
  if (!d.server) { return; }
  const s = d.server;
  const h = d.history || [];

  document.getElementById('modal-title').textContent =
    `${s.server_name}  ·  ${s.ip}  ·  ${s.os_distro||''}`;

  const sec = (title, kvs) => `
    <div class="detail-section">
      <h3>${title}</h3>
      ${kvs.map(([k,v])=>`<div class="kv"><span class="k">${k}</span><span class="v">${v??'—'}</span></div>`).join('')}
    </div>`;

  const vpsLim  = s.vps_limit > 0 ? s.vps_limit : 'Unlimited';
  const lockTxt = s.locked ? `🔒 ${s.locked_reason||'Locked'}` : 'No';

  document.getElementById('modal-body').innerHTML = `
    <div class="detail-grid">
      ${sec('Identity', [
        ['Server ID',    s.server_id],
        ['Hostname',     s.hostname],
        ['IP',           s.ip],
        ['Virt Type',    s.virt_type],
        ['Version',      `${s.virt_version} p${s.virt_patch}`],
        ['OS',           s.os_distro],
        ['Status',       s.status==1?'🟢 Online':'🔴 Offline'],
        ['Locked',       lockTxt],
        ['License',      s.panel_license],
        ['Lic Expires',  s.lic_expires],
        ['Location',     `${s.city}, ${s.state}, ${s.country}`],
        ['Timezone',     s.panel_timezone],
        ['Uptime',       s.uptime],
      ])}
      ${sec('CPU', [
        ['Model',        s.hw_cpu_model],
        ['Vendor',       s.hw_cpu_vendor],
        ['Physical Cores',s.cpu_physical_cores],
        ['Logical CPUs', s.hw_cpu_logical],
        ['Sockets',      s.hw_cpu_sockets],
        ['Cores/Socket', s.hw_cpu_cores_socket],
        ['Threads/Core', s.hw_cpu_threads_core],
        ['Max MHz',      s.hw_cpu_max_mhz],
        ['Virt Support', s.hw_virt_support],
        ['L3 Cache',     s.hw_l3_cache],
        ['vCores Sold',  s.vcores_sold],
        ['CPU Units',    s.cpu_units_alloc],
        ['Live CPU%',    `${s.live_cpu_pct}%`],
        ['IOWait%',      `${s.io_iowait_pct}%`],
        ['Sys Load',     s.sys_load],
      ])}
      ${sec('RAM', [
        ['Physical RAM', `${(+s.ram_total_gb).toFixed(2)} GB`],
        ['RAM Sold',     `${(+s.ram_sold_gb).toFixed(2)} GB`],
        ['RAM Free (panel)', `${(+s.ram_free_panel_gb).toFixed(2)} GB`],
        ['Overcommit Cap',`${(+s.ram_overcommit_gb).toFixed(2)} GB`],
        ['RAM Sold %',   `${s.ram_sold_pct}%`],
        ['Live Used',    `${(+s.live_ram_used_gb).toFixed(2)} GB`],
        ['Live Free',    `${(+s.live_ram_free_gb).toFixed(2)} GB`],
        ['Live RAM%',    `${s.live_ram_pct}%`],
        ['Swap Total',   `${(+s.live_swap_total_gb).toFixed(2)} GB`],
        ['Swap Used',    `${(+s.live_swap_used_gb).toFixed(2)} GB`],
      ])}
      ${sec('Storage', [
        ['Mount',        s.storage_mount],
        ['Type',         `${s.storage_type} / ${s.storage_format}`],
        ['Physical Disk',s.hw_physical_disks],
        ['Total',        `${(+s.storage_total_gb).toFixed(2)} GB`],
        ['Used',         `${(+s.storage_used_gb).toFixed(2)} GB`],
        ['Free',         `${(+s.storage_free_gb).toFixed(2)} GB`],
        ['Used %',       `${s.storage_used_pct}%`],
        ['Sold',         `${(+s.disk_sold_gb).toFixed(2)} GB`],
        ['Sold (total)', `${(+s.disk_total_gb).toFixed(2)} GB`],
      ])}
      ${sec('VPS & Bandwidth', [
        ['VPS Running',  s.num_vps],
        ['VPS Limit',    vpsLim],
        ['BW Used',      `${(+s.live_bw_used_gb).toFixed(2)} GB`],
        ['BW In',        `${(+s.live_bw_in_used_gb).toFixed(2)} GB`],
        ['BW Out',       `${(+s.live_bw_out_used_gb).toFixed(2)} GB`],
        ['BW Alloc MB',  s.bw_alloc_mb],
        ['Interface',    s.panel_interface],
      ])}
      ${sec('IO Breakdown', [
        ['User%',        `${s.io_user_pct}%`],
        ['System%',      `${s.io_system_pct}%`],
        ['IOWait%',      `${s.io_iowait_pct}%`],
        ['Idle%',        `${s.io_idle_pct}%`],
        ['Steal%',       `${s.io_steal_pct}%`],
      ])}
    </div>

    <div class="spark-section">
      <h3>Last ${h.length} snapshots</h3>
      <div class="spark-row">
        ${sparkBox('CPU %', h.map(r=>+r.live_cpu_pct), 'bar-blue')}
        ${sparkBox('RAM %', h.map(r=>+r.live_ram_pct), 'bar-green')}
        ${sparkBox('Disk %', h.map(r=>+r.storage_used_pct), 'bar-warn')}
        ${sparkBox('VPS Count', h.map(r=>+r.num_vps), 'bar-blue')}
      </div>
    </div>
  `;

  document.getElementById('detail-overlay').classList.add('show');
}

function sparkBox(label, values, cls) {
  if (!values || values.length === 0)
    return `<div class="spark-box"><div class="spark-label">${label}</div><div style="color:var(--muted);font-size:11px">No data</div></div>`;

  const rev = [...values].reverse(); // oldest→newest
  const min = Math.min(...rev);
  const max = Math.max(...rev) || 1;
  const W = 200, H = 40, pad = 2;
  const pts = rev.map((v,i) => {
    const x = pad + (i / (rev.length-1||1)) * (W - pad*2);
    const y = H - pad - ((v - min) / (max - min || 1)) * (H - pad*2);
    return `${x},${y}`;
  }).join(' ');
  const last = rev[rev.length-1];
  const color = cls === 'bar-blue' ? '#388bfd' : cls === 'bar-green' ? '#2ea043' : '#d29922';

  return `<div class="spark-box">
    <div class="spark-label">${label} — last: <b style="color:${color}">${last.toFixed(1)}</b></div>
    <svg class="spark" viewBox="0 0 ${W} ${H}">
      <polyline points="${pts}" fill="none" stroke="${color}" stroke-width="1.5" stroke-linejoin="round"/>
      <circle cx="${pts.split(' ').pop().split(',')[0]}" cy="${pts.split(' ').pop().split(',')[1]}"
              r="3" fill="${color}"/>
    </svg>
  </div>`;
}

function closeOverlay() {
  document.getElementById('detail-overlay').classList.remove('show');
}
function closeModal(e) {
  if (e.target === document.getElementById('detail-overlay')) closeOverlay();
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeOverlay(); });

// ── auto-load latest on page load ──────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('ts-select').value) loadServers();
});
</script>
</body>
</html>
"""

# ── Flask routes ──────────────────────────────────────────────────────────────
@app.route('/')
def index():
    timestamps  = get_timestamps()
    selected_ts = request.args.get('ts') or (timestamps[0]['recorded_at_shamsi'] if timestamps else '')
    return render_template_string(TEMPLATE,
        timestamps=timestamps,
        selected_ts=selected_ts,
    )

@app.route('/api/servers')
def api_servers():
    ts      = request.args.get('ts','')
    servers = get_servers_at(ts)
    # make serializable
    for s in servers:
        for k,v in s.items():
            if hasattr(v, 'isoformat'):
                s[k] = str(v)
    return jsonify(servers=servers)

@app.route('/api/server/<int:server_id>')
def api_server(server_id):
    ts      = request.args.get('ts','')
    server  = get_server_detail(server_id, ts)
    history = get_server_history(server_id)
    if server:
        for k,v in server.items():
            if hasattr(v, 'isoformat'):
                server[k] = str(v)
    for row in history:
        for k,v in row.items():
            if hasattr(v, 'isoformat'):
                row[k] = str(v)
    return jsonify(server=server, history=history)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5050, debug=False)