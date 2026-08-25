from ipaddress import IPv4Address
from ipaddress import IPv4Network
import hpilo

ranges = [
   "172.30.1.0/26", "172.30.2.0/26", "172.30.3.0/26",
   "172.30.4.0/26", "172.30.4.0/26", "172.30.6.0/26",
   "172.30.7.0/26", "172.30.8.0/26"
]

def iLOallInfo():
    try:
        iLOip="172.30.1.23"
        ilo = hpilo.Ilo(iLOip, 'gdata', 'MobinHost123!@#')
        health = ilo.get_embedded_health()
        if "Timeout connecting" in health :
            pass
        else:
            print(health)
    except:
        pass
