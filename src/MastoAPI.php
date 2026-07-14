<?php

class MastoAPI {
    private array $env = [];

    public function __construct() {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                $this->env[trim($name)] = trim($value, '"\' ');
            }
        }
    }

    private function getToken(string $instance): ?string {
        $key = 'MASTO_TOKEN_' . strtoupper(str_replace('.', '_', $instance));
        return $this->env[$key] ?? getenv($key) ?: null;
    }

    public function getAccountId(string $instance, string $username): ?string {
        $token = $this->getToken($instance);
        $url = "https://{$instance}/api/v1/accounts/lookup?acct=" . urlencode($username);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetch/1.0');
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['id'] ?? null;
        }
        return null;
    }

    public function fetchStatuses(string $instance, string $accountId, int $limit): array {
        $token = $this->getToken($instance);
        $url = "https://{$instance}/api/v1/accounts/{$accountId}/statuses?limit={$limit}&exclude_reblogs=false";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetch/1.0');
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}