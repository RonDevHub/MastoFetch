<?php
require_once __DIR__ . '/MastoAPI.php';

class MastoCache {
    private string $dataDir = __DIR__ . '/../storage/data/';
    private string $mediaDir = __DIR__ . '/../storage/media/';
    private MastoAPI $api;

    public function __construct() {
        $this->api = new MastoAPI();
        if (!is_dir($this->dataDir)) mkdir($this->dataDir, 0755, true);
        if (!is_dir($this->mediaDir)) mkdir($this->mediaDir, 0755, true);
    }

    public function getWidgetData(array $widgetConfig, array $allAccounts, bool $forceRefresh = false): array {
        $combinedFeed = [];

        foreach ($widgetConfig['accounts'] as $accountKey) {
            if (!isset($allAccounts[$accountKey])) continue;
            
            $acc = $allAccounts[$accountKey];
            $cacheFile = $this->dataDir . "cache_{$accountKey}.json";
            $isExpired = !file_exists($cacheFile) || (time() - filemtime($cacheFile) > $acc['cache_ttl']);

            if ($isExpired || $forceRefresh) {
                $this->refreshAccountCache($accountKey, $acc, $cacheFile);
            }

            if (file_exists($cacheFile)) {
                $cachedData = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cachedData)) {
                    $combinedFeed = array_merge($combinedFeed, $cachedData);
                }
            }
        }

        // Strikt chronologische Sortierung (absteigend nach Erstelldatum)
        usort($combinedFeed, function($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return $combinedFeed;
    }

    private function refreshAccountCache(string $accountKey, array $acc, string $cacheFile): void {
        // ID-Auflösung (wird gecached, um API-Anfragen zu sparen)
        $idFile = $this->dataDir . "id_{$accountKey}.txt";
        if (file_exists($idFile)) {
            $accountId = trim(file_get_contents($idFile));
        } else {
            $accountId = $this->api->getAccountId($acc['instance'], $acc['username']);
            if ($accountId) {
                file_put_contents($idFile, $accountId);
            }
        }

        if (!$accountId) return;

        $statuses = $this->api->fetchStatuses($acc['instance'], $accountId, $acc['limit']);
        $processedStatuses = [];

        foreach ($statuses as $status) {
            // Bilder datenschutzkonform proxien und lokal speichern
            $avatarUrl = $status['account']['avatar'] ?? '';
            $localAvatar = $this->downloadMedia($avatarUrl, 'avatar_' . md5($avatarUrl));

            $mediaAttachments = [];
            if (!empty($status['media_attachments'])) {
                foreach ($status['media_attachments'] as $media) {
                    if ($media['type'] === 'image') {
                        $localImg = $this->downloadMedia($media['url'], 'media_' . md5($media['url']));
                        $mediaAttachments[] = [
                            'url' => $localImg,
                            'preview_url' => $localImg
                        ];
                    }
                }
            }

            $processedStatuses[] = [
                'id' => $status['id'],
                'created_at' => $status['created_at'],
                'url' => $status['url'],
                'content' => $status['content'],
                'favourites_count' => $status['favourites_count'] ?? 0,
                'replies_count' => $status['replies_count'] ?? 0,
                'reblogs_count' => $status['reblogs_count'] ?? 0,
                'account' => [
                    'username' => $status['account']['username'],
                    'display_name' => $status['account']['display_name'] ?: $status['account']['username'],
                    'url' => $status['account']['url'],
                    'avatar' => $localAvatar
                ],
                'media_attachments' => $mediaAttachments
            ];
        }

        if (!empty($processedStatuses)) {
            file_put_contents($cacheFile, json_encode($processedStatuses));
        }
    }

    private function downloadMedia(string $url, string $filename): string {
        if (empty($url)) return '';
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        $fullFilename = $filename . '.' . $ext;
        $localPath = $this->mediaDir . $fullFilename;

        if (!file_exists($localPath)) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetch/1.0');
            $data = curl_exec($ch);
            curl_close($ch);
            if ($data) {
                file_put_contents($localPath, $data);
            }
        }
        return $fullFilename;
    }

    public function isCacheExpiredForWidget(array $widgetConfig, array $allAccounts): bool {
        foreach ($widgetConfig['accounts'] as $accountKey) {
            if (!isset($allAccounts[$accountKey])) continue;
            $cacheFile = $this->dataDir . "cache_{$accountKey}.json";
            if (!file_exists($cacheFile) || (time() - filemtime($cacheFile) > $allAccounts[$accountKey]['cache_ttl'])) {
                return true;
            }
        }
        return false;
    }
}