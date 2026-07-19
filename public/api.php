<?php
// public/api.php - Sichere API-Schnittstelle für MastoFetch Widgets

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../src/MastoCache.php';

    $configPath = __DIR__ . '/../config/accounts.json';

    if (!file_exists($configPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Systemkonfiguration fehlt.']);
        exit;
    }

    $config = json_decode(file_get_contents($configPath), true);
    if (!is_array($config)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ungültiges Konfigurationsformat.']);
        exit;
    }

    // 1. Strikte Eingabe-Validierung der Widget-ID (Behebt Schwachstelle #3)
    $widgetId = $_GET['widget'] ?? '';
    if (empty($widgetId) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $widgetId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültige Widget-ID übergeben.']);
        exit;
    }

    // Prüfen, ob das angeforderte Widget in der Konfiguration existiert
    if (!isset($config['widgets'][$widgetId])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Widget nicht gefunden.']);
        exit;
    }

    $widget = $config['widgets'][$widgetId];
    $mastoCache = new MastoCache();

    // 2. Behebt Schwachstelle #1 (DoS / Erzwungener Refresh):
    // Wir übergeben 'false' für $forceRefresh. Die API liest primär aus dem Cache.
    // Der Refresh wird innerhalb von getWidgetData() vollautomatisch und zeitgesteuert
    // nur dann getriggert, wenn die cache_ttl abgelaufen ist.
    $feedData = $mastoCache->getWidgetData($widget, $config['accounts'], false);

    echo json_encode([
        'success' => true,
        'data' => $feedData
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    // 3. Behebt Schwachstelle #2 (Information Disclosure):
    // Internes Protokollieren des echten Fehlers für das Server-Log
    error_log("MastoFetch API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    // Dem Client wird nur eine generische, sichere Meldung ohne Internal präsentiert
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ein interner Serverfehler ist aufgetreten.'
    ]);
}
exit;
