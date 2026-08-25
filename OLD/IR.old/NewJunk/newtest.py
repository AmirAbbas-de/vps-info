import requests
import json

API_KEY = 'frwq1PL7HhfWBKIJGq29RCjC4cJgY1lP';
API_PASS = 'ldPYRRTzI0pJ08aYnYatmYexj0d4XVxP';
IP = '45.129.39.116';
PORT = '4084';
URL = "http://45.129.39.116:4084/index.php"

params = {
    "adminapikey": API_KEY,
    "adminapipass": API_PASS,
    "act": "vs",
    "api": "json",
    "vpsid": "",
    "vpsname": "",
    "vpsip": "",
    "vpshostname": "",
    "vsstatus": "",
    "vstype": "",
    "speedcap": "",
    "user": "",
    "vsgid": "",
    "vserid": "",
    "plid": "",
    "bpid": "",
    "search": "Search"
}

try:
    response = requests.get(URL, params=params, verify=False)
    
    data = response.json()
    
    vps_list = data.get("vs", {})
    
    print(json.dumps(vps_list, indent=4))

except Exception as e:
    print(f"Request failed: {e}")