<?php

$DATACENTERS = [
    'dc1' => ['label' => 'Datacenter - BlueBox',   'url' => 'https://my.mobinhost.com/dl/vpn/iLO-Afranet-vm3ov0k.ovpn'],
    'dc2' => ['label' => 'Datacenter - Sabalan',          'url' => 'https:my.mobinhost.com/dl/vpn/iLO-Sabalan-xwef2132f.ovpn'],
    'dc3' => ['label' => 'Datacenter - Arvand',          'url' => 'https://my.mobinhost.com/dl/vpn/iLO-Arvand-vl3mv3w.ovpn'],
    'dc4' => ['label' => 'Datacenter - Hiweb ',          'url' => 'https://my.mobinhost.com/dl/vpn/iLO-Hiweb-xwef32f.ovpn'],
];


/* ---------- helpers ---------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function v($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }

/* last octet of an IPv4 address, or '' if not parseable */
function last_octet($ip){
    $ip = trim($ip);
    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return '';
    $parts = explode('.', $ip);
    return end($parts);
}

/* random 12-char password: letters + numbers + symbols, guaranteed one of each */
function gen_password($len = 12){
    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $num   = '23456789';
    $sym   = '!@#$%^&*-_=+';
    $all   = $lower.$upper.$num.$sym;
    $pick  = fn($set) => $set[random_int(0, strlen($set)-1)];
    $out   = [$pick($lower), $pick($upper), $pick($num), $pick($sym)];
    for($i = count($out); $i < $len; $i++) $out[] = $pick($all);
    for($i = count($out)-1; $i > 0; $i--){ $j = random_int(0,$i); [$out[$i],$out[$j]] = [$out[$j],$out[$i]]; }
    return implode('', $out);
}

/* parse "a.b.c.d/xx" -> details, or null if invalid */
function subnet_info($cidr){
    $cidr = trim($cidr);
    if($cidr === '' || strpos($cidr, '/') === false) return null;
    [$ip, $mask] = explode('/', $cidr, 2);
    $ip = trim($ip); $mask = (int)trim($mask);
    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return null;
    if($mask < 0 || $mask > 32) return null;

    $ipLong   = ip2long($ip);
    $maskLong = $mask === 0 ? 0 : (~((1 << (32 - $mask)) - 1)) & 0xFFFFFFFF;
    $network  = $ipLong & $maskLong;
    $broadcast= $network | (~$maskLong & 0xFFFFFFFF);

    if($mask <= 30){
        $firstUsable = $network + 1;
        $lastUsable  = $broadcast - 1;
        $usableCount = $lastUsable - $firstUsable + 1;
    } elseif($mask == 31){          // point-to-point, both usable
        $firstUsable = $network;
        $lastUsable  = $broadcast;
        $usableCount = 2;
    } else {                        // /32 single host
        $firstUsable = $network;
        $lastUsable  = $network;
        $usableCount = 1;
    }

    // full list of usable IPs (capped to avoid rendering huge subnets, e.g. /8)
    $usableList = [];
    $cap = 4096;
    if($usableCount <= $cap){
        for($cur = $firstUsable; $cur <= $lastUsable; $cur++){
            $usableList[] = long2ip($cur);
        }
    }

    return [
        'cidr'      => long2ip($network).'/'.$mask,
        'netmask'   => long2ip($maskLong),
        'network'   => long2ip($network),
        'broadcast' => long2ip($broadcast),
        'gateway'   => long2ip($firstUsable),                 // first usable
        'first'     => long2ip($firstUsable),
        'last'      => long2ip($lastUsable),
        'range'     => long2ip($firstUsable).' - '.long2ip($lastUsable),
        'usable_list'=> $usableList,                          // full list, or [] if capped
        'count'     => $usableCount,
        'mask'      => $mask,
    ];
}

/* ---------- read input ---------- */
$submitted = ($_SERVER['REQUEST_METHOD'] === 'POST');

$userId   = v('user_id');       // e.g. 555
$engName  = v('eng_name');      // e.g. English-name
$location = v('location');      // e.g. 404 -> VLAN ID
$dcKey    = v('datacenter');
$iloIps   = isset($_POST['ilo_ip']) && is_array($_POST['ilo_ip']) ? $_POST['ilo_ip'] : [''];
$iloSwName= isset($_POST['ilo_sw_name']) && is_array($_POST['ilo_sw_name']) ? $_POST['ilo_sw_name'] : [];
$iloSwPort= isset($_POST['ilo_sw_port']) && is_array($_POST['ilo_sw_port']) ? $_POST['ilo_sw_port'] : [];
$subnets  = isset($_POST['subnet']) && is_array($_POST['subnet']) ? $_POST['subnet'] : [''];

$routerType   = v('router_type') !== '' ? v('router_type') : 'mikrotik';   // mikrotik | cisco
$trunkIface   = v('trunk_iface') !== '' ? v('trunk_iface') : 'Bonding - N5K - Access';
$vpnRemoteAdr = v('vpn_remote_address');

$vpnUrl   = isset($DATACENTERS[$dcKey]) ? $DATACENTERS[$dcKey]['url'] : '';

/* derived */
$vlanId    = $location;
$slug      = $engName;                                   // used verbatim in name/descr
$ifaceName = "Vlan{$vlanId}-ID{$userId}-{$slug}";
$ifaceDesc = "MH{$vlanId}-ID{$userId}-{$slug}";
$username  = "userID{$userId}";
$idName    = "ID{$userId}-{$engName}";                    // used in VPN/ILO command comments & lists
$password  = $submitted ? (v('gen_password') !== '' ? v('gen_password') : gen_password(12)) : '';

/* parse subnets */
$parsed = [];
if($submitted){
    foreach($subnets as $s){
        $s = trim($s);
        if($s === '') continue;
        $info = subnet_info($s);
        $parsed[] = ['raw'=>$s, 'info'=>$info];
    }
}

/* build iLO rows: ip + switch name + switch port (port defaults to last octet), sorted by last octet asc */
$iloRows = [];
if($submitted){
    foreach($iloIps as $i => $ip){
        $ip = trim($ip);
        if($ip === '') continue;
        $oct  = last_octet($ip);
        $port = isset($iloSwPort[$i]) && trim($iloSwPort[$i]) !== '' ? trim($iloSwPort[$i]) : $oct;
        $name = isset($iloSwName[$i]) ? trim($iloSwName[$i]) : '';
        $iloRows[] = [
            'ip'        => $ip,
            'sw_name'   => $name,
            'sw_port'   => $port,
            'sort'      => ($oct === '' ? PHP_INT_MAX : (int)$oct),
        ];
    }
    usort($iloRows, fn($a,$b) => $a['sort'] <=> $b['sort']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Server Info Sheet Generator</title>
<style>
  :root{
    --bg:#0d1117; --panel:#161b22; --panel2:#0b0f14; --line:#232c38;
    --ink:#e6edf3; --muted:#8b98a9; --accent:#3fb6a8; --accent2:#f0a63c; --bad:#e5534b;
    --mono:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.5}
  .wrap{margin:0 10%;padding:28px 20px 60px}
  h1{font-size:20px;letter-spacing:.02em;margin:0 0 4px}
  .sub{color:var(--muted);font-size:13px;margin-bottom:22px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
  @media(max-width:840px){.grid{grid-template-columns:1fr}}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:20px}
  .card h2{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--accent);margin:0 0 16px}
  fieldset{border:1px solid var(--line);border-radius:8px;margin:0 0 16px;padding:14px}
  legend{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:0 6px}
  label{display:block;font-size:12px;color:var(--muted);margin:10px 0 4px}
  input,select{width:100%;background:var(--panel2);border:1px solid var(--line);color:var(--ink);
    border-radius:6px;padding:8px 10px;font-size:13px;font-family:var(--mono)}
  input:focus,select:focus{outline:none;border-color:var(--accent)}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
  button{background:var(--accent);color:#04120f;border:0;border-radius:6px;padding:11px;
    font-size:14px;font-weight:600;cursor:pointer}
  button.full{width:100%;margin-top:8px}
  button.ghost{background:var(--line);color:var(--ink);font-weight:500;padding:7px 12px;font-size:12px}
  .subnet-row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
  .subnet-row input{flex:1}
  .subnet-row .rm{background:transparent;border:1px solid var(--line);color:var(--muted);
    border-radius:6px;padding:6px 10px;cursor:pointer;font-size:14px}
  .subnet-row .rm:hover{border-color:var(--bad);color:var(--bad)}
  .ilo-block{border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:10px;background:var(--panel2)}
  .ilo-block .ilo-head{display:flex;gap:8px;align-items:center;margin-bottom:8px}
  .ilo-block .ilo-head input{flex:1}
  .ilo-block .rm{background:transparent;border:1px solid var(--line);color:var(--muted);
    border-radius:6px;padding:6px 10px;cursor:pointer;font-size:14px}
  .ilo-block .rm:hover{border-color:var(--bad);color:var(--bad)}
  .sheet{background:var(--panel2);border:1px solid var(--line);border-radius:8px;padding:18px;
    font-family:var(--mono);font-size:13px;white-space:pre-wrap;color:var(--ink);overflow:auto}
  .empty{color:var(--muted);text-align:center;padding:40px 10px;font-size:13px}
  .copybar{display:flex;gap:10px;margin-top:12px}
  .copybar button{margin:0;background:var(--line);color:var(--ink);font-weight:500}
  .warn{color:var(--bad);font-size:12px;margin-top:6px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Server Info Sheet Generator</h1>
  <div class="sub">Enter user ID, location and subnets — VLAN, interface name/description, username, password and per-subnet gateway/usable IPs are generated automatically.</div>

  <form method="post" class="grid">
    <div class="card">
      <h2>Input</h2>

      <fieldset>
        <legend>Identity</legend>
        <div class="row3">
          <div><label>User ID</label><input name="user_id" value="<?=e($userId)?>" placeholder="555" required></div>
          <div><label>Location (= VLAN ID)</label><input name="location" value="<?=e($location)?>" placeholder="404" required></div>
          <div><label>English name</label><input name="eng_name" value="<?=e($engName)?>" placeholder="English-name" required></div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Subnets</legend>
        <div id="subnets">
          <?php foreach(($submitted ? $subnets : ['']) as $s): ?>
          <div class="subnet-row">
            <input name="subnet[]" value="<?=e($s)?>" placeholder="185.10.20.0/29">
            <button type="button" class="rm" onclick="rmSubnet(this)">&times;</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="ghost" onclick="addSubnet()">+ Add subnet</button>
      </fieldset>

      <fieldset>
        <legend>Router &amp; Switch</legend>
        <label>Router Type</label>
        <select name="router_type">
          <option value="mikrotik" <?=$routerType==='mikrotik'?'selected':''?>>MikroTik</option>
          <option value="cisco" <?=$routerType==='cisco'?'selected':''?>>Cisco</option>
        </select>
        <label>Trunk Interface (used for MikroTik VLAN parent)</label>
        <input name="trunk_iface" value="<?=e($trunkIface)?>" placeholder="Bonding - N5K - Access">
      </fieldset>

      <fieldset>
        <legend>OpenVPN Datacenter</legend>
        <select name="datacenter">
          <?php foreach($DATACENTERS as $k=>$dc): ?>
            <option value="<?=e($k)?>" <?=$dcKey===$k?'selected':''?>><?=e($dc['label'])?></option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <fieldset>
        <legend>iLO Access</legend>
        <div id="ilos">
          <?php
            // On submit, re-render rows in original POST order (so indexes line up); otherwise one empty row.
            $renderIlos = $submitted ? $iloIps : [''];
            foreach($renderIlos as $i => $ip):
              $ip   = trim($ip);
              $nm   = isset($iloSwName[$i]) ? $iloSwName[$i] : '';
              $pt   = isset($iloSwPort[$i]) ? $iloSwPort[$i] : '';
          ?>
          <div class="ilo-block">
            <div class="ilo-head">
              <input class="ilo-ip" name="ilo_ip[]" value="<?=e($ip)?>" placeholder="172.30.4.3" oninput="syncPort(this)">
              <button type="button" class="rm" onclick="rmIlo(this)">&times;</button>
            </div>
            <div class="row">
              <div><label>Switch Name</label><input name="ilo_sw_name[]" value="<?=e($nm)?>" placeholder="N9K - R04"></div>
              <div><label>Switch Port (last octet)</label><input class="ilo-port" name="ilo_sw_port[]" value="<?=e($pt)?>" placeholder="auto from IP"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="ghost" onclick="addIlo()">+ Add iLO address</button>
      </fieldset>

      <button type="submit" class="full">Generate Sheet</button>
    </div>

    <div class="card">
      <h2>Generated Sheet</h2>
      <?php if(!$submitted): ?>
        <div class="empty">Fill the form and press <b>Generate Sheet</b>.</div>
      <?php else:
        $lines = [];
        $lines[] = "Server info ::";
        $lines[] = "VLAN ID        : {$vlanId}";
        $lines[] = "Interface name : {$ifaceName}";
        $lines[] = "Description    : {$ifaceDesc}";
        $lines[] = "User ID        : {$userId}";
        $lines[] = "";
        $lines[] = "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=";
        $lines[] = "";
        $hasBad = false;
        $n = 0;
        foreach($parsed as $p){
            $n++;
            $info = $p['info'];
            if($n == 1){
                $lines[] = "Subnet ::";
            }else{
                $lines[] = "Subnet #{$n} ::";
            }
            if($info === null){
                $hasBad = true;
                $lines[] = "  ! invalid CIDR: {$p['raw']}";
            } else {
                $lines[] = "  Network   = {$info['cidr']}  ({$info['netmask']})";
                $lines[] = "  Gateway   = {$info['gateway']}";
                $lines[] = "  Broadcast = {$info['broadcast']}";
                if(!empty($info['usable_list'])){
                    $lines[] = "  Usable IPs ({$info['count']}): " . implode(', ', $info['usable_list']);
                } else {
                    $lines[] = "  Usable IPs ({$info['count']}): {$info['range']}  (range too large to list)";
                }
            }
            $lines[] = "";
            $lines[] = "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=";
        }
        if($n === 0){ $lines[] = "(no subnets entered)"; $lines[] = "-------------------------------------------"; }

        $lines[] = "";
        $lines[] = "VPN Access ::";
        $lines[] = '';
        $lines[] = "Protocol : OpenVPN";
        $lines[] = "File URL : {$vpnUrl}";
        $lines[] = "username : {$username}";
        $lines[] = "password : {$password}";
        $lines[] = "PrivateKey Password : MobinHost";
        $lines[] = "";
        $lines[] = "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=";
        $lines[] = "";
        $lines[] = "ILO Access ::";
        $lines[] = "";
        $iloN = 0;
        foreach($iloRows as $r){
            $iloN++;
            if($iloN == 1){
                $lines[] = "Server :";
            }else{
                $lines[] = "Server #{$iloN} :";
            }
            $lines[] = "URL         : https://{$r['ip']}";
            $lines[] = "Switch Name : {$r['sw_name']}";
            $lines[] = "Switch Port : {$r['sw_port']}";
            $lines[] = "Username    : hpdl360";
            $lines[] = "Password    : {$password}";
            $lines[] = "";
        }
        if($iloN === 0){ $lines[] = "(no iLO addresses entered)"; }

        $lines[] = "*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*";
        $sheet = implode("\n", $lines);

        /* ---------- Router / Switch / VPN-ILO commands ---------- */
        $cmd = [];

        $cmd[] = "# Router config ({$routerType})";
        if($routerType === 'cisco'){
            $cmd[] = "interface Vlan{$vlanId}";
            $cmd[] = " description {$ifaceDesc}";
            foreach($parsed as $index => $p){
                if($p['info'] === null) continue;
                if($index > 0){
                    $cmd[] = " ip address {$p['info']['gateway']}/{$p['info']['mask']} secondary";
                }else{
                    $cmd[] = " ip address {$p['info']['gateway']}/{$p['info']['mask']} ";
                }
            }
            $cmd[] = "no shutdown";
            $cmd[] = "exit";
            $cmd[] = "vlan {$vlanId}";
            $cmd[] = "name ID{$userId}";
            $cmd[] = "exit";
        } else {
            $cmd[] = "interface/vlan/add name={$ifaceName} vlan-id={$vlanId} interface=\"{$trunkIface}\"";
            foreach($parsed as $index => $p){
                if($p['info'] === null) continue;
                if($index > 0){
                    $cmd[] = " ip address {$p['info']['gateway']}/{$p['info']['mask']} secondary";
                }else{
                    $cmd[] = " ip address {$p['info']['gateway']}/{$p['info']['mask']} ";
                }
            }
        }

        $cmd[] = "";
        // switch name for the header comment: use the first iLO row's switch name if present
        $swHeader = '';
        foreach($iloRows as $r){ if($r['sw_name'] !== ''){ $swHeader = $r['sw_name']; break; } }
        $cmd[] = "# Switch config" . ($swHeader !== '' ? " ({$swHeader})" : "");
        // one switchport block per iLO port, ordered by last octet (iloRows already sorted)
        foreach($iloRows as $r){
            if($r['sw_port'] === '') continue;
            $cmd[] = "interface eth 1/{$r['sw_port']}";
            $cmd[] = "description {$ifaceDesc}";
            $cmd[] = "switchport access vlan {$vlanId}";
            $cmd[] = "exit";
            $cmd[] = "";
        }
        $cmd[] = "vlan {$vlanId}";
        $cmd[] = "name ID{$userId}";
        $cmd[] = "exit";
        $cmd[] = "";
        $cmd[] = "# VPN / ILO (MikroTik)";
        $cmd[] = "ppp/profile/add name={$idName}-Profile local-address=10.0.16.1 only-one=yes address-list={$idName}-Remote remote-address=iLO-Pool";
        $cmd[] = "ppp/secret/add name={$username} password={$password} comment={$idName} profile={$idName}-Profile";
        foreach($iloRows as $r){
            $cmd[] = "ip firewall/address-list/add list={$idName} address={$r['ip']}";
        }
        $cmd[] = "ip firewall/filter/add action=drop chain=input src-address-list={$idName}-Remote";
        $cmd[] = "ip firewall/filter/add chain=forward src-address-list={$idName}-Remote dst-address-list=!{$idName} action=drop comment={$idName}";
        $cmd[] = "";

        $commands = implode("\n", $cmd);
      ?>
        <div class="sheet" id="sheet"><?=e($sheet)?></div>
        <?php if($hasBad): ?><div class="warn">One or more subnets were not valid CIDR (e.g. 185.10.20.0/29) and were skipped.</div><?php endif; ?>
        <div class="copybar">
          <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('sheet').innerText)">Copy to clipboard</button>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <?php if($submitted): ?>
  <div class="card" style="margin-top:22px">
    <h2>Router / Switch / VPN-ILO Commands</h2>
    <div class="sheet" id="commands"><?=e($commands)?></div>
    <div class="copybar">
      <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('commands').innerText)">Copy to clipboard</button>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function addSubnet(){
  const wrap = document.getElementById('subnets');
  const div = document.createElement('div');
  div.className = 'subnet-row';
  div.innerHTML = '<input name="subnet[]" placeholder="185.10.20.0/29">' +
                  '<button type="button" class="rm" onclick="rmSubnet(this)">&times;</button>';
  wrap.appendChild(div);
}
function rmSubnet(btn){
  const rows = document.querySelectorAll('#subnets .subnet-row');
  if(rows.length > 1) btn.parentElement.remove();
  else btn.previousElementSibling.value = '';
}

/* auto-fill switch port from the last octet of the iLO IP, unless the user already typed a port */
function syncPort(ipInput){
  const block = ipInput.closest('.ilo-block');
  const portInput = block.querySelector('.ilo-port');
  if(!portInput) return;
  if(portInput.dataset.touched === '1') return;   // don't overwrite manual edits
  const m = ipInput.value.trim().match(/(\d{1,3})\s*$/);
  portInput.value = m ? m[1] : '';
}

function addIlo(){
  const wrap = document.getElementById('ilos');
  const div = document.createElement('div');
  div.className = 'ilo-block';
  div.innerHTML =
    '<div class="ilo-head">' +
      '<input class="ilo-ip" name="ilo_ip[]" placeholder="172.30.4.3" oninput="syncPort(this)">' +
      '<button type="button" class="rm" onclick="rmIlo(this)">&times;</button>' +
    '</div>' +
    '<div class="row">' +
      '<div><label>Switch Name</label><input name="ilo_sw_name[]" placeholder="N9K - R04"></div>' +
      '<div><label>Switch Port (last octet)</label><input class="ilo-port" name="ilo_sw_port[]" placeholder="auto from IP"></div>' +
    '</div>';
  wrap.appendChild(div);
}
function rmIlo(btn){
  const rows = document.querySelectorAll('#ilos .ilo-block');
  if(rows.length > 1) btn.closest('.ilo-block').remove();
  else {
    const block = btn.closest('.ilo-block');
    block.querySelectorAll('input').forEach(i => i.value = '');
  }
}

/* mark a port field as manually touched so auto-sync stops overwriting it */
document.addEventListener('input', function(ev){
  if(ev.target.classList && ev.target.classList.contains('ilo-port')){
    ev.target.dataset.touched = ev.target.value.trim() === '' ? '' : '1';
  }
});
</script>
</body>
</html>