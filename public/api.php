<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/MastoCache.php';

$widgetId = $_GET['widget'] ?? '';
$configPath = __DIR__ . '/../config/accounts.json';

if (!file_exists($configPath)) {
    echo json_encode(['error' => 'Konfiguration nicht gefunden.']);
    exit;
}

$config = json_decode(file_get_contents($configPath), true);

if (!isset($config['widgets'][$widgetId])) {
    echo json_encode(['error' => 'Widget existiert nicht.']);
    exit;
}

$mastoCache = new MastoCache();
// Erzwinge Refresh der abgelaufenen Caches im Hintergrund-Polling
$data = $mastoCache->getWidgetData($config['widgets'][$widgetId], $config['accounts'], true);

echo json_encode(['success' => true, 'data' => $data]);
exit;