from virtvs.py import virtapi  # Assuming your class is in virtapi.py

# 1. Initialize the API object
# You can pass arguments directly or use environment variables
api = virtapi(ip="YOUR_SERVER_IP", key="YOUR_API_KEY", pass_word="YOUR_API_PASS")

def get_my_servers():
    # 2. Example: Get servers with a search filter
    search_params = {
        'servername': 'my-server-name',
        'serverip': '192.168.1.1',
        'ptype': 'kvm'
    }
    
    print("Fetching server list...")
    server_list = api.servers(search=search_params)
    print(server_list)

def delete_a_server(server_id):
    # 3. Example: Delete a server by ID
    print(f"Deleting server {server_id}...")
    result = api.servers(del_serid=server_id)
    print(result)

if __name__ == "__main__":
    # Uncomment the function you want to run
    # get_my_servers()
    # delete_a_server(123)
    pass