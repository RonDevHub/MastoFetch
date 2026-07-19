<?php
// Explizite und sichere Einbindung der API-Klasse für die statische Code-Analyse (VS Code / Intelephense)
require_once __DIR__ . '/MastoAPI.php';

class MastoCache
{
    private string $dataDir = __DIR__ . '/../storage/data/';
    private string $mediaDir = __DIR__ . '/../storage/media/';
    private MastoAPI $api;

    public function __construct()
    {
        $this->api = new MastoAPI();

        // Verzeichnisse anlegen und Berechtigungskontext abfangen
        if (!is_dir($this->dataDir)) {
            if (!@mkdir($this->dataDir, 0755, true) && !is_dir($this->dataDir)) {
                error_log("MastoFetch Error: Verzeichnis kann nicht erstellt werden: " . $this->dataDir);
            }
        }

        if (!is_dir($this->mediaDir)) {
            if (!@mkdir($this->mediaDir, 0755, true) && !is_dir($this->mediaDir)) {
                error_log("MastoFetch Error: Verzeichnis kann nicht erstellt werden: " . $this->mediaDir);
            }
        }
    }

    public function getWidgetData(array $widgetConfig, array $allAccounts, bool $forceRefresh = false): array
    {
        $combinedFeed = [];
        $cacheTtl = $widgetConfig['cache_ttl'] ?? 900;
        $limit = $widgetConfig['limit'] ?? 10;

        foreach ($widgetConfig['accounts'] as $accountKey) {
            if (!isset($allAccounts[$accountKey])) continue;

            $acc = $allAccounts[$accountKey];
            $cacheFile = $this->dataDir . "cache_{$accountKey}.json";
            $isExpired = !file_exists($cacheFile) || (time() - filemtime($cacheFile) > $cacheTtl);

            if ($isExpired || $forceRefresh) {
                $this->refreshAccountCache($accountKey, $acc, $cacheFile, $limit);
            }

            if (file_exists($cacheFile)) {
                $cachedData = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cachedData)) {
                    $combinedFeed = array_merge($combinedFeed, $cachedData);
                }
            }
        }

        usort($combinedFeed, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return array_slice($combinedFeed, 0, $limit);
    }

    private function refreshAccountCache(string $accountKey, array $acc, string $cacheFile, int $limit): void
    {
        $idFile = $this->dataDir . "id_{$accountKey}.txt";

        // Sicherheitsprüfung gegen Errno 21 (Falls Pfad fälschlicherweise ein Ordner ist)
        $accountId = '';
        if (file_exists($idFile) && !is_dir($idFile)) {
            $accountId = trim(file_get_contents($idFile));
        } else {
            $accountId = $this->api->getAccountId($acc['instance'], $acc['username'], $accountKey);
            if ($accountId && !is_dir($idFile)) {
                @file_put_contents($idFile, $accountId);
            }
        }

        if (!$accountId) return;

        $statuses = $this->api->fetchStatuses($acc['instance'], $accountId, $limit, $accountKey);
        $processedStatuses = [];

        foreach ($statuses as $status) {
            $isReblog = !empty($status['reblog']);
            $targetStatus = $isReblog ? $status['reblog'] : $status;

            $avatarUrl = $targetStatus['account']['avatar'] ?? '';
            $localAvatar = $this->downloadMedia($avatarUrl, 'avatar_' . md5($avatarUrl));

            $rebloggedBy = null;
            if ($isReblog) {
                $boosterAvatarUrl = $status['account']['avatar'] ?? '';
                $localBoosterAvatar = $this->downloadMedia($boosterAvatarUrl, 'avatar_' . md5($boosterAvatarUrl));
                $rebloggedBy = [
                    'username' => $status['account']['username'],
                    'display_name' => $status['account']['display_name'] ?: $status['account']['username'],
                    'url' => $status['account']['url'],
                    'avatar' => $localBoosterAvatar
                ];
            }

            $mediaAttachments = [];
            if (!empty($targetStatus['media_attachments'])) {
                foreach ($targetStatus['media_attachments'] as $media) {
                    if ($media['type'] === 'image') {
                        $localImg = $this->downloadMedia($media['url'], 'media_' . md5($media['url']));
                        $mediaAttachments[] = [
                            'url' => $localImg,
                            'preview_url' => $localImg
                        ];
                    }
                }
            }

            $linkPreview = null;
            if (empty($mediaAttachments)) {
                $linkPreview = $this->extractLinkPreview($targetStatus['content']);
            }

            $processedStatuses[] = [
                'id' => $targetStatus['id'],
                'created_at' => $targetStatus['created_at'],
                'url' => $targetStatus['url'],
                'content' => $targetStatus['content'],
                'favourites_count' => $targetStatus['favourites_count'] ?? 0,
                'replies_count' => $targetStatus['replies_count'] ?? 0,
                'reblogs_count' => $targetStatus['reblogs_count'] ?? 0,
                'account' => [
                    'username' => $targetStatus['account']['username'],
                    'display_name' => $targetStatus['account']['display_name'] ?: $targetStatus['account']['username'],
                    'url' => $targetStatus['account']['url'],
                    'avatar' => $localAvatar
                ],
                'reblogged_by' => $rebloggedBy,
                'media_attachments' => $mediaAttachments,
                'link_preview' => $linkPreview
            ];
        }

        if (!empty($processedStatuses) && !is_dir($cacheFile)) {
            @file_put_contents($cacheFile, json_encode($processedStatuses));
        }
    }

    private function extractLinkPreview(string $htmlContent): ?array
    {
        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*>/i', $htmlContent, $matches);
        if (empty($matches[1])) return null;

        foreach ($matches[1] as $url) {
            if (strpos($url, '/tags/') !== false || strpos($url, '/users/') !== false || strpos($url, '@') !== false) {
                continue;
            }

            if (!$this->isValidPublicUrl($url)) {
                continue;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetchScraper/1.0');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) continue;

            $doc = new DOMDocument();
            @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($doc);

            $titleQuery = $xpath->query('//meta[@property="og:title"]/@content');
            $imageQuery = $xpath->query('//meta[@property="og:image"]/@content');
            $descQuery = $xpath->query('//meta[@property="og:description"]/@content');

            $title = $titleQuery->length > 0 ? $titleQuery->item(0)->nodeValue : '';
            $imageUrl = $imageQuery->length > 0 ? $imageQuery->item(0)->nodeValue : '';
            $desc = $descQuery->length > 0 ? $descQuery->item(0)->nodeValue : '';

            if (empty($title)) {
                $titleNodes = $doc->getElementsByTagName('title');
                if ($titleNodes->length > 0) {
                    $title = $titleNodes->item(0)->nodeValue;
                }
            }

            if (!empty($title)) {
                $localOgImg = '';
                if (!empty($imageUrl)) {
                    $localOgImg = $this->downloadMedia($imageUrl, 'og_' . md5($imageUrl));
                }
                return [
                    'url' => $url,
                    'title' => htmlspecialchars($title),
                    'description' => htmlspecialchars($desc),
                    'image' => $localOgImg
                ];
            }
        }
        return null;
    }

    private function downloadMedia(string $url, string $filename): string
    {
        if (empty($url)) return '';

        if (!$this->isValidPublicUrl($url)) {
            return '';
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        $ext = explode('?', $ext)[0];
        if (!in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) {
            $ext = 'png';
        }

        $fullFilename = $filename . '.' . $ext;
        $localPath = $this->mediaDir . $fullFilename;

        if (!file_exists($localPath)) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetch/1.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $data = curl_exec($ch);
            curl_close($ch);
            if ($data && !is_dir($localPath)) {
                @file_put_contents($localPath, $data);
            }
        }
        return $fullFilename;
    }

    public function isCacheExpiredForWidget(array $widgetConfig, array $allAccounts): bool
    {
        $cacheTtl = $widgetConfig['cache_ttl'] ?? 900;
        foreach ($widgetConfig['accounts'] as $accountKey) {
            if (!isset($allAccounts[$accountKey])) continue;
            $cacheFile = $this->dataDir . "cache_{$accountKey}.json";
            if (!file_exists($cacheFile) || (time() - filemtime($cacheFile) > $cacheTtl)) {
                return true;
            }
        }
        return false;
    }

    private function isValidPublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }

        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'])) {
            return false;
        }

        $host = $parts['host'];
        $ip = gethostbyname($host);
        if (!$ip || $ip === $host) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}
