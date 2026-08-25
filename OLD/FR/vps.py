import os
import json
import time
import ipaddress
import re
from virtvs import virtapi
from dotenv import load_dotenv

load_dotenv()

def main():
    api = virtapi()
    
    print("Fetching IP pools and full IP list from Virtualizor...")
    pools_data = api.listippools(page=1, reslen=90000)
    raw_ips = api.listips(page=1, reslen=90000)
    
    ip_to_pool_name = {}
    if raw_ips and isinstance(raw_ips, dict):
        for ip_id, ip_info in raw_ips.items():
            ip_to_pool_name[ip_info['ip']] = ip_info.get('ippool_name', '')
    
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
    grouped_pools = {}
    network_lookup_list = []
    ipv4_pattern = re.compile(r'\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b')

    for pool_id, pool in pools_data.items():
        name = pool['ippool_name'].strip()
        total_ip = int(pool.get('totalip', 0))
        
        location = "Unknown"
        for prefix in sorted_prefixes:
            if prefix in name:
                location = group_mapping[prefix]
                break

        try:
            if total_ip == 1:
                ip_match = ipv4_pattern.search(name)
                subnet_str = f"{ip_match.group(1)}/32" if ip_match else "unknown_single_ip/32"
            else:
                net_type = ipaddress.IPv6Network if pool.get('ipv6') == '1' else ipaddress.IPv4Network
                subnet_str = str(net_type(f"{pool['gateway']}/{pool['netmask']}", strict=False))
        except:
            cidr_match = re.search(r'(\d+\.\d+\.\d+\.\d+/\d+)', name)
            subnet_str = cidr_match.group(1) if cidr_match else "Unknown"

        entry = {
            "ippid": str(pool['ippid']),
            "name": name,
            "subnet": subnet_str,
            "total_ip": pool.get('totalip', '0'),
            "free_ip": pool.get('freeip', pool.get('unassignedip', '0'))
        }
        grouped_pools.setdefault(location, []).append(entry)

        if subnet_str != "Unknown":
            try:
                network_lookup_list.append({
                    'network': ipaddress.ip_network(subnet_str, strict=False),
                    'group': location
                })
            except: continue

    with open('/var/www/html/FR/ippools.json', 'w', encoding='utf-8') as f:
        json.dump(grouped_pools, f, indent=4, ensure_ascii=False)

    print("Fetching VPS list...")
    all_vps_ids, all_vps_info = [], {}
    page, reslen = 1, 1000
    while True:
        raw_vps_list = api.listvs(page=page, reslen=reslen)
        if not raw_vps_list or not isinstance(raw_vps_list, dict): break
        all_vps_ids.extend(list(raw_vps_list.keys()))
        all_vps_info.update(raw_vps_list)
        if len(raw_vps_list) < reslen: break
        page += 1

    print(f"Fetching status for {len(all_vps_ids)} VPS in batches...")
    all_status_data = {}
    for i in range(0, len(all_vps_ids), 1000):
        batch = all_vps_ids[i:i + 1000]
        for attempt in range(1, 4):
            status_batch = api.status(batch)
            if status_batch and isinstance(status_batch, dict):
                all_status_data.update(status_batch)
                break
            time.sleep(2)
        time.sleep(1)

    final_vps_data = {}
    status_map = {0: "Offline", 1: "Online", 2: "Suspend", 3: "Unknown"}

    for vid in all_vps_ids:
        vinfo = all_vps_info.get(vid, {})
        sinfo = all_status_data.get(vid, {})
        
        ips_dict = vinfo.get('ips', {})
        ip_list = list(ips_dict.values())
        first_ip = ip_list[0] if ip_list else ""
        all_ips_str = ", ".join(ip_list) if ip_list else ""
        
        server_name = vinfo.get('server_name', "")

        vps_group = "Unknown"
        if first_ip:
            pool_name_from_ip = ip_to_pool_name.get(first_ip, "")
            if pool_name_from_ip:
                for prefix in sorted_prefixes:
                    if prefix in pool_name_from_ip:
                        vps_group = group_mapping[prefix]
                        break
            if vps_group == "Unknown":
                try:
                    v_ip = ipaddress.ip_address(first_ip)
                    for item in network_lookup_list:
                        if v_ip in item['network']:
                            vps_group = item['group']
                            break
                except: pass
            if vps_group == "Unknown":
                for prefix in sorted_prefixes:
                    if prefix in server_name:
                        vps_group = group_mapping[prefix]
                        break
        if not isinstance(sinfo, dict):
            sinfo = {}

        final_vps_data[str(vid)] = {
            "status": status_map.get(sinfo.get('status', 3), "Unknown"),
            "used_cpu": sinfo.get('used_cpu', "0.00"),
            "used_ram": int(sinfo.get('used_ram', 0)),
            "used_inode": sinfo.get('used_inode', "0"),
            "io_read": int(sinfo.get('io_read', 0)),
            "io_write": int(sinfo.get('io_write', 0)),
            "ram": vinfo.get('ram', "0"),
            "vpsid": int(vid),
            "hostname": vinfo.get('hostname', ""),
            "os_name": vinfo.get('os_name', ""),
            "space": vinfo.get('space', "0"),
            "cpu_cores": vinfo.get('cores', "0"),
            "nic_type": vinfo.get('nic_type', ""),
            "mac": vinfo.get('mac', ""),
            "timezone": vinfo.get('timezone', ""),
            "server_name": server_name,
            "email": vinfo.get('email', "no-email"),
            "ips": first_ip,
            "all_ips": all_ips_str,
            "IP Location": vps_group
        }

    with open('/var/www/html/FR/vps.json', 'w', encoding='utf-8') as f:
        json.dump(final_vps_data, f, indent=4, ensure_ascii=False)

    print(f"\nDone! Processed {len(all_vps_ids)} VPS with full IP tracking.")

if __name__ == "__main__":
    main()
