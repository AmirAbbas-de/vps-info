<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Access Pro - Continuous Mode</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0a0a0a; color: #eee; display: flex; justify-content: center; padding: 40px; }
        .card { background: #161616; padding: 30px; border-radius: 12px; border: 1px solid #333; width: 450px; }
        h2 { color: #00d4ff; text-align: center; }
        .input-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; color: #777; margin-bottom: 5px; }
        input { width: 100%; padding: 12px; background: #222; border: 1px solid #444; color: #fff; border-radius: 6px; box-sizing: border-box; }
        #port-group { display: none; }
        .btn-main { width: 100%; padding: 14px; background: #00d4ff; border: none; font-weight: bold; border-radius: 6px; cursor: pointer; }
        .btn-cancel { width: 100%; padding: 14px; background: #ff4d4d; border: none; color: white; font-weight: bold; border-radius: 6px; cursor: pointer; margin-top: 10px; display: none; }
        #screenshot-area { margin-top: 30px; background: #000; border-radius: 8px; padding: 20px; border: 1px solid #444; display: none; width: 30%;}
        .term-body { font-family: 'Courier New', monospace; font-size: 13px; color: #00ff88; }
        .loading-dots:after { content: ' .'; animation: dots 1s steps(5, end) infinite; }
        @keyframes dots { 0%, 20% { content: ' .'; } 40% { content: ' ..'; } 60% { content: ' ...'; } }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="card">
        <h2>Remote Access</h2>
        <div class="input-group">
            <label>TARGET IP</label>
            <input type="text" id="ip" placeholder="192.168.1.1">
        </div>
        <div class="input-group">
            <label>USERNAME</label>
            <input type="text" id="user" placeholder="root / administrator" oninput="togglePort()">
        </div>
        <div id="port-group" class="input-group">
            <label>RDP PORT</label>
            <input type="number" id="port" placeholder="3389">
        </div>
        <div class="input-group">
            <label>PASSWORD</label>
            <input type="password" id="pass">
        </div>
        
        <button id="test-btn" class="btn-main" onclick="startProcess()">Connect & Verify</button>
        <button id="cancel-btn" class="btn-cancel" onclick="cancelProcess()">Cancel Operation</button>
    </div>

    <div id="screenshot-area">
        <div class="term-body" id="term-content"></div>
        <button id="snap-btn" style="display:none; width:100%; margin-top:15px;" onclick="downloadImage()">Download Screenshot</button>
    </div>
</div>

<script>
let isRunning = false;
let controller;

function togglePort() {
    const user = document.getElementById('user').value.toLowerCase().trim();
    document.getElementById('port-group').style.display = (user === 'administrator') ? 'block' : 'none';
}

function cancelProcess() {
    isRunning = false;
    if (controller) controller.abort();
    updateUI(false);
    document.getElementById('term-content').innerHTML += '<p style="color:#ff4d4d">> Operation Cancelled by User.</p>';
}

function updateUI(busy) {
    document.getElementById('test-btn').style.display = busy ? 'none' : 'block';
    document.getElementById('cancel-btn').style.display = busy ? 'block' : 'none';
}

async function startProcess() {
    const ip = document.getElementById('ip').value;
    const user = document.getElementById('user').value;
    const port = document.getElementById('port').value || 3389;
    const pass = document.getElementById('pass').value;

    if(!ip || !user || !pass) return alert("Fill all fields.");

    isRunning = true;
    updateUI(true);
    const area = document.getElementById('screenshot-area');
    const content = document.getElementById('term-content');
    area.style.display = 'block';
    content.innerHTML = `<p>> Waiting for Ping response from ${ip}<span class="loading-dots"></span></p>`;

    while (isRunning) {
        controller = new AbortController();
        try {
            const response = await fetch('backend.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ ip, user, port, pass, check_only: 'ping' }),
                signal: controller.signal
            });
            const data = await response.json();

            if (data.ping_ok) {
                content.innerHTML += `<p style="color:#00ff88">> Ping received! Proceeding to authentication...</p>`;
                
                // Final Auth Step
                const authResp = await fetch('backend.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ ip, user, port, pass, check_only: 'full' })
                });
                const authData = await authResp.json();
                
                renderFinal(authData);
                break; 
            }
        } catch (e) {
            if (e.name === 'AbortError') break;
            console.log("Retrying...");
        }
        
        // Wait 2 seconds before next ping
        await new Promise(r => setTimeout(r, 2000));
    }
}

function renderFinal(data) {
    const content = document.getElementById('term-content');
    isRunning = false;
    updateUI(false);
    
    if(data.status === 'success') {
        content.innerHTML += `<p style="color:#00ff88"> ${data.logs[1].msg}</p>`;
        content.innerHTML += `<hr style="border:0.5px solid #222"><div style="color:#888">${data.system_info}</div>`;
        document.getElementById('snap-btn').style.display = 'block';
        document.getElementById('screenshot-area').style.border = '1px solid #00ff88';
    } else {
        content.innerHTML += `<p style="color:#ff4d4d">> Auth Failed: ${data.logs[1].msg}</p>`;
    }
}

function downloadImage() {
    html2canvas(document.querySelector("#screenshot-area")).then(canvas => {
        const link = document.createElement('a');
        link.download = 'verify.png';
        link.href = canvas.toDataURL();
        link.click();
    });
}
</script>
</body>
</html>