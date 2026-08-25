import hashlib
import random
import string
import requests
import urllib.parse
import phpserialize
import base64
import urllib3
import os

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

class virtapi:
    def __init__(self, ip=None, key=None, pass_word=None, port=None, protocol=None):
        # Fallback to .env if arguments are not provided directly
        self.ip = ip or os.getenv("IP_ADDRESS")
        self.key = key or os.getenv("API_KEY")
        self.pass_word = pass_word or os.getenv("API_PASS")
        self.port = int(port or os.getenv("PORT", 4084))
        self.protocol = protocol or os.getenv("PROTOCOL", "http")
            

    def generate_rand_str(self, length=8):
        chars = string.ascii_lowercase + string.digits
        return ''.join(random.choice(chars) for _ in range(length))

    def make_apikey(self, key, password):
        hash_val = hashlib.md5((password + key).encode()).hexdigest()
        return f"{key}{hash_val}"

    def call(self, path, data=None, post=None, cookies=None):
        key = self.generate_rand_str(8)
        apikey = self.make_apikey(key, self.pass_word)

        url = f"{self.protocol}://{self.ip}:{self.port}/{path}"
        sep = '&' if '?' in url else '?'
        
        url += f"{sep}adminapikey={urllib.parse.quote(self.key)}"
        url += f"&adminapipass={urllib.parse.quote(self.pass_word)}"
        url += f"&api=serialize&apikey={urllib.parse.quote(apikey)}"

        if data:
            serialized_data = phpserialize.dumps(data)
            b64_str = base64.b64encode(serialized_data).decode('utf-8')
            url += f"&apidata={urllib.parse.quote(b64_str)}"

        headers = {'User-Agent': 'Softaculous'}
        
        try:
            if post:
                response = requests.post(url, data=post, cookies=cookies, headers=headers, verify=False, timeout=1000)
            else:
                response = requests.get(url, cookies=cookies, headers=headers, verify=False, timeout=1000)

            if not response.text: return False

            return phpserialize.loads(response.content, decode_strings=True)

        except Exception as e:
            print(f"Connection Error: {e}")
            return False

    def listvs(self, page=1, reslen=90000, search=None):
        if search is None:
            path = f"index.php?act=vs&page={page}&reslen={reslen}"
        else:
            path = f"index.php?act=vs&search=1&page={page}&reslen={reslen}"
            for k, v in search.items():
                path += f"&{k}={v}"

        result = self.call(path)

        return result['vs']

    def status(self, vids):

        if not isinstance(vids, (list, tuple)):
            vids = [vids]
            
        vids_str = ",".join(map(str, vids))
        
        path = f"index.php?act=vs&vs_status={vids_str}"
        result = self.call(path)
        
        if isinstance(result, dict) and 'status' in result:
            return result['status']
        return result

    def listippools(self, page=1, reslen=90000, search=None):

        if search is None:
            search = {}
        
        path = f"index.php?act=ippool&page={page}&reslen={reslen}"
        
        for k, v in search.items():
            if v:
                path += f"&{k}={v}"
        
        result = self.call(path)
        
        return result.get('ippools', {})
        
    def listips(self, page=1, reslen=90000, search=None):
        if search is None:
            search = {}

        path = f"index.php?act=ips&page={page}&reslen={reslen}"

        for k, v in search.items():
            if v not in [None, '']:
                path += f"&{k}={v}"

        result = self.call(path)
        return result.get('ips', {})

    def listservers(self):
        result = self.call("index.php?act=servers")
        return result.get('servers', {}) if isinstance(result, dict) else {}

    def server_manageinfo(self, serid):
        result = self.call(f"index.php?act=manageserver&changeserid={serid}")
        return result if isinstance(result, dict) else {}

    def server_performance(self, serid, network=False):
        path = f"index.php?act=performance&changeserid={serid}"
        if network:
            path += "&network_stats=1"
        result = self.call(path)
        return result if isinstance(result, dict) else {}

    def servergroups(self):
        result = self.call("index.php?act=servergroups")
        if not isinstance(result, dict):
            return {}
        return result.get('servergroups', result.get('sgs', {}))