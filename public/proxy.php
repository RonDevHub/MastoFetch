<?php
// proxy.php - Sicherer Medien-Proxy gegen Path Traversal und unbefugten Dateizugriff

$file = $_GET['file'] ?? '';

// 1. Basis-Validierung des Formats (verhindert schon im Ansatz groben Unfug)
if (empty($file) || !preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $file)) {
    http_response_code(400);
    echo "Bad Request: Invalid file format.";
    exit;
}

$storageMediaDir = __DIR__ . '/../storage/media/';
$filePath = $storageMediaDir . $file;

// 2. Absolute Pfade auflösen zur Absicherung gegen Directory Traversal
$realStorageDir = realpath($storageMediaDir);
$realFilePath = realpath($filePath);

// 3. Sicherheitsprüfung: Existiert die Datei und liegt sie wirklich im Zielordner?
if (!$realFilePath || !$realStorageDir || strpos($realFilePath, $realStorageDir) !== 0 || !file_exists($realFilePath)) {
    http_response_code(404);
    echo "Not Found.";
    exit;
}

// 4. Content-Type Header basierend auf der Dateiendung ermitteln
$ext = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml'
];

$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

// 5. Header setzen und Datei ressourcensparend ausgeben
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($realFilePath));
header('Cache-Control: public, max-age=86400'); // 24 Stunden Browser-Caching aktivieren

readfile($realFilePath);
exit;
