<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Server Log Parser - Wide View</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef2f7; margin: 0; padding: 20px; color: #333; }
        
        /* 30/70 Split Layout */
        .split-container { display: flex; gap: 20px; max-width: 100%; margin: auto; align-items: stretch; }
        .input-panel { flex: 0 0 30%; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: flex; flex-direction: column; }
        .result-panel { flex: 0 0 67%; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: flex; flex-direction: column; }
        
        h3 { margin-top: 0; color: #007bff; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; font-size: 1.1em; }
        
        textarea { width: 100%; height: 500px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-family: monospace; box-sizing: border-box; resize: none; background: #fdfdfd; }
        
        button { width: 100%; background: #007bff; color: white; border: none; padding: 15px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 15px; }
        button:hover { background: #0056b3; }

        /* Filter & Summary */
        .tools-row { display: flex; gap: 15px; margin-bottom: 15px; align-items: center; }
        #reasonFilter { flex-grow: 1; padding: 12px; border: 1px solid #007bff; border-radius: 6px; box-sizing: border-box; font-size: 14px; outline: none; }
        .stats-badge { background: #007bff; color: white; padding: 10px 15px; border-radius: 6px; font-weight: bold; font-size: 14px; white-space: nowrap; }

        .result-box { flex-grow: 1; overflow-y: auto; max-height: 700px; border: 1px solid #eee; padding: 5px; border-radius: 4px; }
        
        .header-error { background: #fff5f5; color: #c92a2a; padding: 12px; border-left: 5px solid #ff6b6b; margin-bottom: 15px; font-size: 13px; font-family: monospace; word-break: break-all; }

        .log-line { font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; padding: 10px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: flex-start; transition: background 0.2s; }
        .log-line:hover { background: #f0f7ff; }
        .log-line:nth-child(even) { background: #fafafa; }
        .log-line.hidden { display: none; }
        
        .server-id { color: #007bff; font-weight: bold; min-width: 120px; }
        .reason-text { color: #d9480f; background: #fff4e6; padding: 2px 6px; border-radius: 4px; margin-left: 10px; }
        .placeholder { color: #999; text-align: center; margin-top: 100px; font-style: italic; }
    </style>
</head>
<body>

<div class="split-container">
    <div class="input-panel">
        <h3>Input</h3>
        <form method="POST">
            <textarea name="log_data" placeholder="Paste error log here..."><?php echo isset($_POST['log_data']) ? htmlspecialchars($_POST['log_data']) : ''; ?></textarea>
            <button type="submit">Format & Parse Data</button>
        </form>
    </div>

    <div class="result-panel">
        <h3>Resualt</h3>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['log_data'])): ?>
            <?php
                $raw_input = $_POST['log_data'];
                $clean_text = str_replace(['<br>', '<br />', '<br/>'], "\n", $raw_input);
                $lines = explode("\n", $clean_text);
                $server_count = 0;
                
                $output_buffer = "";
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    if (strpos($line, 'Module Create Failed') !== false) {
                        $output_buffer .= "<div class='header-error'>" . htmlspecialchars($line) . "</div>";
                    } elseif (strpos($line, 'Server ID:') !== false) {
                        $server_count++;
                        $display_line = ltrim($line, '* ');
                        $parts = explode('|', $display_line);
                        $server_info = $parts[0];
                        $reason = isset($parts[1]) ? $parts[1] : "Unknown Error";
                        
                        $output_buffer .= "<div class='log-line'>";
                        $output_buffer .= "<span class='server-id'>• " . htmlspecialchars($server_info) . "</span>";
                        $output_buffer .= "<span class='reason-text'>" . htmlspecialchars($reason) . "</span>";
                        $output_buffer .= "</div>";
                    }
                }
            ?>

            <div class="tools-row">
                <input type="text" id="reasonFilter" onkeyup="filterResults()" placeholder="Search reasons ...">
                <div class="stats-badge" id="visibleCounter">Servers: <?php echo $server_count; ?></div>
            </div>

            <div class="result-box" id="resultContainer">
                <?php echo $output_buffer; ?>
            </div>

        <?php else: ?>
            <div class="placeholder">
                <p>Waiting for data...</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterResults() {
    let input = document.getElementById('reasonFilter');
    let filter = input.value.toLowerCase();
    let container = document.getElementById('resultContainer');
    let lines = container.getElementsByClassName('log-line');
    let count = 0;

    for (let i = 0; i < lines.length; i++) {
        let textContent = lines[i].textContent || lines[i].innerText;
        if (textContent.toLowerCase().indexOf(filter) > -1) {
            lines[i].classList.remove('hidden');
            count++;
        } else {
            lines[i].classList.add('hidden');
        }
    }
    document.getElementById('visibleCounter').innerText = "Found: " + count;
}
</script>

</body>
</html>
