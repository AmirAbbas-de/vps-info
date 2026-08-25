"""
Debug: show raw API field names and values.
Usage:  python3 debug_server.py
"""
import json
from dotenv import load_dotenv
from virtvs import virtapi

load_dotenv()
api = virtapi()

print("=== listservers() ===")
raw = api.listservers()
for serid, s in list(raw.items())[:2]:
    print(f"\n--- serid={serid} server_name={s.get('server_name')} ---")
    print(f"  ALL keys : {list(s.keys())}")
    for k in ("locked", "vs_limit", "vps_limit", "licnumvs", "numvps", "status"):
        print(f"  {k:15} = {repr(s.get(k))}")

print("\n=== listvs() — first 5 VPS (status field check) ===")
batch = api.listvs(page=1, reslen=5)
if batch:
    for vid, v in list(batch.items())[:5]:
        print(f"\n  vpsid={vid}  hostname={v.get('hostname')}")
        print(f"  ALL keys : {list(v.keys())}")
        for k in ("machine_status", "suspended", "locked", "nw_suspended", "band_suspend", "rescue"):
            if k in v:
                print(f"  {k:15} = {repr(v.get(k))}")
        cr = v.get("current_resource")
        print(f"  current_resource = {repr(cr)}")
else:
    print("  (no data)")

print("\n=== listips() sample ===")
ips = api.listips(page=1, reslen=5)
for ipid, ip in list(ips.items())[:3]:
    print(f"  ip={ip.get('ip')}  locked={repr(ip.get('locked'))}  keys={list(ip.keys())}")
