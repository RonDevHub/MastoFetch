<?php
// Strenger Datenschutz-Proxy für lokale Medien-Dateien
$file = $_GET['file'] ?? '';

if (empty($file) || !preg_with('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $file)) {
    header("HTTP/1.1 400 Bad Request");
    exit('Ungültige Anfrage.');
}

$filePath = __DIR__ . '/../storage/media/' . $file;

if (!file_exists($filePath)) {
    header("HTTP/1.1 404 Not Found");
    exit('Datei existiert nicht.');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = match($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    default => 'application/octet-stream'
};

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=86400'); // 24 Stunden Browser-Caching erlauben

readfile($filePath);
exit;