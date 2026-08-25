import requests
import json

API_KEY = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
API_PASS = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
IP = '46.105.45.161';
PORT = '4084';
URL = "http://46.105.45.161:4084/index.php"

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