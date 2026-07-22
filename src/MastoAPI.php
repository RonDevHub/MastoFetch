<?php

class MastoAPI {
    private array $env = [];

    public function __construct() {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $this->env[trim($parts[0])] = trim($parts[1], '"\' ');
                }
            }
        }
    }

    private function getToken(string $instance, string $accountKey): ?string {
        // Option 1: Account-spezifisches Token (z.B. MASTO_TOKEN_USER_MAIN)
        $accountSpecificKey = 'MASTO_TOKEN_' . strtoupper(str_replace('-', '_', $accountKey));
        if (isset($this->env[$accountSpecificKey])) {
            return $this->env[$accountSpecificKey];
        }
        
        // Option 2: Instanz-spezifisches Token (z.B. MASTO_TOKEN_MASTODON_SOCIAL)
        $instanceKey = 'MASTO_TOKEN_' . strtoupper(str_replace('.', '_', $instance));
        return $this->env[$instanceKey] ?? getenv($accountSpecificKey) ?: (getenv($instanceKey) ?: null);
    }

    public function getAccountId(string $instance, string $username, string $accountKey): ?string {
        $token = $this->getToken($instance, $accountKey);
        $url = "https://{$instance}/api/v1/accounts/lookup?acct=" . urlencode($username);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetch/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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

    public function fetchStatuses(string $instance, string $accountId, int $limit, string $accountKey): array {
        $token = $this->getToken($instance, $accountKey);
        // exclude_replies=true schließt direkte Antworten/Erwähnungen direkt serverseitig aus
        $url = "https://{$instance}/api/v1/accounts/{$accountId}/statuses?limit={$limit}&exclude_reblogs=false&exclude_replies=true";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MastoFetch/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}