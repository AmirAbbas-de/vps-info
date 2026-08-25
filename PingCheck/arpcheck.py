import requests
import csv
import re
import getpass
from datetime import datetime
from requests.auth import HTTPBasicAuth

# ====================== CONFIGURATION ======================
ROUTER_IP = "192.168.88.1"           # Change to your router's IP
PORT = 10881                         # Your custom web port
USE_SSL = False                      # Set True if using https (recommended!)

# Base URL (http or https depending on your choice)
PROTOCOL = "https" if USE_SSL else "http"
BASE_URL = f"{PROTOCOL}://{ROUTER_IP}:{PORT}/rest"

# MAC address regex (accepts : or - separator)
MAC_REGEX = re.compile(r'^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$')

# Username (usually admin or your read-only user)
USERNAME = "admin"

# Script will ask for password securely – never hard-code it!
# ============================================================

print(f"Connecting to MikroTik REST API at {BASE_URL}...")

PASSWORD = getpass.getpass("Enter router password: ")

try:
    auth = HTTPBasicAuth(USERNAME, PASSWORD)

    # Optional: disable SSL verification if self-signed cert (common on MikroTik)
    verify = True if USE_SSL else False  # Set False only for testing!

    print("Fetching /ip/arp ...")
    response = requests.get(
        f"{BASE_URL}/ip/arp",
        auth=auth,
        verify=verify,
        timeout=10
    )

    response.raise_for_status()  # Raise error if not 2xx

    arp_entries = response.json()

    # Filter entries with valid MAC address
    valid_entries = []
    for entry in arp_entries:
        mac = entry.get('mac-address', '').strip()
        ip = entry.get('address', '').strip()

        if ip and mac and MAC_REGEX.match(mac):
            valid_entries.append({
                'IP': ip,
                'MAC': mac.upper(),  # normalize to uppercase
                'Interface': entry.get('interface', 'N/A'),
                'Flags': entry.get('flags', 'N/A'),           # D=Dynamic, etc.
                'Status': entry.get('status', 'N/A')          # complete / reachable / etc.
            })

    print(f"Found {len(valid_entries)} entries with valid MAC addresses")

    # Save to CSV with timestamp
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"mikrotik_arp_valid_rest_{timestamp}.csv"

    with open(filename, 'w', newline='', encoding='utf-8') as csvfile:
        fieldnames = ['IP', 'MAC', 'Interface', 'Flags', 'Status']
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(valid_entries)

    print(f"Saved to: {filename}")
    print(f"Total rows: {len(valid_entries)}")

except requests.exceptions.HTTPError as http_err:
    if response.status_code == 401:
        print("❌ Authentication failed – wrong username/password?")
    elif response.status_code == 404:
        print("❌ REST API not found – is RouterOS v7+ and www/www-ssl service enabled?")
    else:
        print(f"❌ HTTP error: {http_err} (status {response.status_code})")
    # Optional: print(response.text) to see error message from router

except requests.exceptions.ConnectionError:
    print("❌ Connection failed – check IP, port, firewall, www/www-ssl service enabled?")
except Exception as e:
    print(f"❌ Error: {e}")

print("Done.")