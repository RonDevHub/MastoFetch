<?php
$configPath = __DIR__ . '/../config/accounts.json';
$widgets = [];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    $widgets = $config['widgets'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>MastoFetch Dashboard</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1a1a1a; color: #f0f0f0; max-width: 1000px; margin: 40px auto; padding: 20px; }
        h1 { border-bottom: 2px solid #333; padding-bottom: 10px; color: #8c8dff; }
        .widget-panel { background: #252525; border: 1px solid #333; border-radius: 8px; padding: 20px; margin-bottom: 30px; }
        .code-box { background: #111; padding: 12px; border-radius: 4px; overflow-x: auto; color: #a2ffb2; font-family: monospace; font-size: 0.9rem; margin: 10px 0; }
        iframe { border: 1px solid #333; border-radius: 8px; width: 100%; height: 500px; background: #121212; }
    </style>
</head>
<body>
    <h1>⚓ MastoFetch - Widget Steuerung</h1>
    <p>Definierte Widgets aus deiner <code>accounts.json</code>:</p>

    <?php if (empty($widgets)): ?>
        <p>Keine Widgets konfiguriert.</p>
    <?php else: ?>
        <?php foreach ($widgets as $id => $w): 
            $embedUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['SCRIPT_NAME']) . "/widget.php?id={$id}";
            ?>
            <div class="widget-panel">
                <h2>📊 Widget: <?php echo htmlspecialchars($w['title']); ?> (ID: <?php echo htmlspecialchars($id); ?>)</h2>
                <p>Zugeordnete Accounts: <code><?php echo implode(', ', $w['accounts']); ?></code></p>
                
                <h3>🛠️ Einbettungscode:</h3>
                <div class="code-box">
                    &lt;iframe src="<?php echo $embedUrl; ?>" width="100%" height="600" style="border:none;"&gt;&lt;/iframe&gt;
                </div>

                <h3>👁️ Live-Vorschau:</h3>
                <iframe src="widget.php?id=<?php echo urlencode($id); ?>"></iframe>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>