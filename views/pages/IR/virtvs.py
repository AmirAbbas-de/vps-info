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
    def servers(self, search=None, del_serid=0):
        """Fetches list of servers or deletes a server by ID."""
        if del_serid == 0:
            path = 'index.php?act=servers'
            if search and isinstance(search, dict):
                params = [f"{k}={urllib.parse.quote(str(v))}" for k, v in search.items() if v]
                if params:
                    path += '&' + '&'.join(params) + '&search=Search'
        else:
            path = f'index.php?act=servers&delete={del_serid}'
        return self.call(path)
    def get_server_performance(self, server_id):
        """Fetches resource usage, limits, and capacity for a physical server."""
        path = f"index.php?act=performance&changeserid={server_id}"
        return self.call(path)
    # ─────────────────────────────────────────────────────────────────────────────
# ADD THESE METHODS TO YOUR virtapi CLASS IN virtvs.py
# Translated directly from Virtualizor PHP Admin SDK (api.php)
# ─────────────────────────────────────────────────────────────────────────────

    def server_stats(self, serid=0, post=None):
        """
        PHP SDK: function server_stats($post = array())
            $path = 'index.php?act=server_stats'
                  . (!empty($post['serid']) ? '&changeserid='.(int)$post['serid'] : '');
            $ret = $this->call($path, array(), $post);

        Returns historical/aggregated stats for a server node.
        Keys typically include: cpu, ram, disk, network, io — each as time-series arrays.
        Pass serid=0 for master node.
        """
        path = f"index.php?act=server_stats"
        if serid:
            path += f"&changeserid={int(serid)}"
        post_data = post or {}
        return self.call(path, post=post_data)

    def serverinfo(self, serid=0):
        """
        PHP SDK: function serverinfo($serid = 0)
            $path = 'index.php?act=serverinfo';
            if(!empty($serid)) $path .= '&changeserid='.$serid;

        Returns node identity and version info.
        Keys: title, info -> {
            masterkey, path, key, pass,
            kernel, num_vs, version, patch
        }
        """
        path = "index.php?act=serverinfo"
        if serid:
            path += f"&changeserid={serid}"
        result = self.call(path)
        if not result:
            return {}
        # Mirror PHP SDK — return only the documented keys
        info = result.get("info", {})
        return {
            "title": result.get("title", ""),
            "info": {
                "masterkey": info.get("masterkey", ""),
                "path":      info.get("path", ""),
                "key":       info.get("key", ""),
                "pass":      info.get("pass", ""),
                "kernel":    info.get("kernel", ""),
                "num_vs":    info.get("num_vs", 0),
                "version":   info.get("version", ""),
                "patch":     info.get("patch", ""),
            }
        }

    def servergroups(self, post=None):
        """
        PHP SDK: function servergroups($post = 0)
            $path = 'index.php?act=servergroups';
            $ret = $this->call($path, array(), $post);

        Returns all server groups (clusters).
        Keys: servergroups -> { sgid: { sgid, sg_name, sg_reseller, ... } }
        """
        path = "index.php?act=servergroups"
        post_data = post or {}
        return self.call(path, post=post_data)

    def serverloads(self, post=None):
        """
        PHP SDK: function serverloads($post = array())
            $path = 'index.php?act=serverloads';
            $ret = $this->call($path, array(), $post);

        Returns live load metrics for ALL servers at once (no serid needed).
        Keys: loads -> { serid: { load1, load5, load15, cpu_usage, ram_usage,
                                  disk_usage, network_in, network_out, ... } }
        Very efficient — one call gives loads for every node.
        """
        path = "index.php?act=serverloads"
        post_data = post or {}
        return self.call(path, post=post_data)

    def manageserver(self, serid=0):
        """
        PHP SDK: used internally by cpu(), ram(), disk() functions:
            $path = 'index.php?act=manageserver&changeserid='.$serverid;
            return $ret['usage']['ram/cpu/disk'];

        Returns full live usage for one server node.
        Keys: usage -> {
            ram:  { total, used, free },          ← in MB
            cpu:  { load1, load5, load15, usage_pct },
            disk: { total, used, free },           ← in MB
        }
        Also contains: info, virt, etc.
        """
        path = f"index.php?act=manageserver&changeserid={serid}"
        return self.call(path)

    def performance(self, serid=0, option=""):
        """
        PHP SDK: function performance($serid, $option="")
            $path = 'index.php?act=performance&changeserid='.$serid;
            if($option == 'network_stats') $path .= '&network_stats=1';
            elif($option == 'live_stats')  $path .= '&ajax=true';

        Options:
            ""             → full performance page data (limits, capacity, graphs)
            "network_stats"→ network in/out stats
            "live_stats"   → live ajax stats snapshot

        Returns: limits, capacity, resources, graphs — structure varies by option.
        """
        path = f"index.php?act=performance&changeserid={serid}"
        if option == "network_stats":
            path += "&network_stats=1"
        elif option == "live_stats":
            path += "&ajax=true"
        return self.call(path)
    
    
    