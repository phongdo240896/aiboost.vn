<?php
echo "<h1>🧹 Clear All Cache</h1>";

try {
    // Clear PHP OPcache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "<p>✅ Cleared PHP OPcache</p>";
    } else {
        echo "<p>⚠️ PHP OPcache not available</p>";
    }
    
    // Clear file-based cache if exists
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach($files as $file) {
            if(is_file($file)) {
                unlink($file);
            }
        }
        echo "<p>✅ Cleared file cache</p>";
    }
    
    // Force session refresh
    session_start();
    session_regenerate_id(true);
    echo "<p>✅ Refreshed session</p>";
    
    echo "<h2>🔄 Test Pages:</h2>";
    echo "<p><a href='/pricing?debug=1' target='_blank'>🎯 Pricing Page (Debug Mode)</a></p>";
    echo "<p><a href='/test_sync.php' target='_blank'>🧪 Test Sync</a></p>";
    echo "<p><a href='/admin/package' target='_blank'>⚙️ Package Admin</a></p>";
    
    echo "<h2>🚀 Actions:</h2>";
    echo "<button onclick='window.location.reload()'>🔄 Reload This Page</button><br><br>";
    echo "<button onclick=\"window.open('/pricing?t=' + Date.now(), '_blank')\">🎯 Open Fresh Pricing</button>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<script>
// Auto refresh every 5 seconds to show real-time updates
let autoRefresh = false;
function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    if (autoRefresh) {
        setInterval(() => {
            if (autoRefresh) {
                fetch('/test_sync.php')
                    .then(response => response.text())
                    .then(data => {
                        console.log('Data synced at:', new Date().toLocaleTimeString());
                    });
            }
        }, 5000);
        document.getElementById('autoBtn').textContent = '⏹️ Stop Auto Refresh';
    } else {
        document.getElementById('autoBtn').textContent = '▶️ Start Auto Refresh';
    }
}
</script>

<button id="autoBtn" onclick="toggleAutoRefresh()">▶️ Start Auto Refresh</button>