<?php

class ProxyService {
    private ?string $proxyHost;
    private ?int $proxyPort;
    private ?string $proxyType;
    private ?string $proxyAuth;
    private bool $enabled;
    private array $fallbackProxies;
    private bool $autoFallback;
    
    public function __construct() {
        $this->proxyHost = getenv('PROXY_HOST') ?: null;
        $this->proxyPort = (int)(getenv('PROXY_PORT') ?: 0);
        $this->proxyType = getenv('PROXY_TYPE') ?: 'HTTP';
        $this->proxyAuth = getenv('PROXY_AUTH') ?: null;
        $this->enabled = (getenv('PROXY_ENABLED') === 'true' || getenv('PROXY_ENABLED') === '1') && $this->proxyHost;
        $this->autoFallback = getenv('PROXY_AUTO_FALLBACK') === 'true' || getenv('PROXY_AUTO_FALLBACK') === '1';
        
        $fallbackEnv = getenv('PROXY_FALLBACK_LIST') ?: '';
        $this->fallbackProxies = $this->parseFallbackList($fallbackEnv);
    }
    
    private function parseFallbackList(string $list): array {
        if (empty($list)) return [];
        
        $proxies = [];
        $items = explode(';', $list);
        
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            
            if (preg_match('/^([^:]+):(\d+)(?:\|(http|socks4|socks5))?(?:\|([^|]+))?$/i', $item, $m)) {
                $proxies[] = [
                    'host' => $m[1],
                    'port' => (int)$m[2],
                    'type' => strtoupper($m[3] ?? 'HTTP'),
                    'auth' => $m[4] ?? null,
                ];
            }
        }
        
        return $proxies;
    }
    
    public function isEnabled(): bool {
        return $this->enabled;
    }
    
    public function fetch(string $url, array $options = []): array {
        if (!$this->enabled) {
            return $this->directFetch($url, $options);
        }
        
        $result = $this->fetchViaProxy($url, $options, $this->getPrimaryProxyConfig());
        
        if (!$result['ok'] && $this->autoFallback && !empty($this->fallbackProxies)) {
            foreach ($this->fallbackProxies as $fallbackProxy) {
                $result = $this->fetchViaProxy($url, $options, $fallbackProxy);
                if ($result['ok']) {
                    $result['used_fallback'] = true;
                    $result['fallback_proxy'] = $fallbackProxy['host'] . ':' . $fallbackProxy['port'];
                    break;
                }
            }
        }
        
        if (!$result['ok'] && $this->autoFallback) {
            $result = $this->directFetch($url, $options);
            if ($result['ok']) {
                $result['used_direct'] = true;
                $result['reason'] = 'All proxies failed, used direct connection';
            }
        }
        
        return $result;
    }
    
    private function getPrimaryProxyConfig(): array {
        return [
            'host' => $this->proxyHost,
            'port' => $this->proxyPort,
            'type' => $this->proxyType,
            'auth' => $this->proxyAuth,
        ];
    }
    
    private function fetchViaProxy(string $url, array $options, array $proxyConfig): array {
        $ch = curl_init($url);
        $headers = [];
        
        $timeout = $options['timeout'] ?? 15;
        $userAgent = $options['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $followLocation = $options['follow_location'] ?? true;
        $maxRedirs = $options['max_redirects'] ?? 5;
        $verifySSL = $options['verify_ssl'] ?? true;
        
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => $maxRedirs,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySSL,
            CURLOPT_SSL_VERIFYHOST => $verifySSL ? 2 : 0,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HEADERFUNCTION => function($ch, $hdr) use (&$headers) {
                $parts = explode(':', $hdr, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($hdr);
            }
        ];
        
        $proxyUrl = $proxyConfig['host'] . ':' . $proxyConfig['port'];
        $curlOptions[CURLOPT_PROXY] = $proxyUrl;
        
        $proxyType = strtoupper($proxyConfig['type']);
        switch ($proxyType) {
            case 'SOCKS4':
                $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4;
                break;
            case 'SOCKS5':
                $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                break;
            case 'HTTP':
            default:
                $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
                break;
        }
        
        if (!empty($proxyConfig['auth'])) {
            $curlOptions[CURLOPT_PROXYUSERPWD] = $proxyConfig['auth'];
        }
        
        if (!empty($options['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options['headers'];
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $startTime = microtime(true);
        $body = curl_exec($ch);
        $fetchTime = round((microtime(true) - $startTime) * 1000);
        
        if ($body === false) {
            $err = curl_error($ch);
            $errNo = curl_errno($ch);
            curl_close($ch);
            return [
                'ok' => false,
                'message' => 'Proxy fetch failed',
                'error' => $err,
                'error_code' => $errNo,
                'proxy' => $proxyUrl,
                'fetch_time_ms' => $fetchTime
            ];
        }
        
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ct = $headers['content-type'] ?? curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        if ($status >= 400) {
            return [
                'ok' => false,
                'message' => 'Remote returned error',
                'status' => $status,
                'proxy' => $proxyUrl,
                'fetch_time_ms' => $fetchTime
            ];
        }
        
        if (strlen($body) > 1024*1024) {
            $body = substr($body, 0, 1024*1024);
        }
        
        return [
            'ok' => true,
            'body' => $body,
            'contentType' => $ct,
            'status' => $status,
            'effective_url' => $effectiveUrl,
            'proxy' => $proxyUrl,
            'proxy_type' => $proxyType,
            'fetch_time_ms' => $fetchTime,
            'headers' => $headers
        ];
    }
    
    private function directFetch(string $url, array $options = []): array {
        $ch = curl_init($url);
        $headers = [];
        
        $timeout = $options['timeout'] ?? 10;
        $userAgent = $options['user_agent'] ?? 'PaymentVerifier/1.1 (+PHP cURL)';
        $followLocation = $options['follow_location'] ?? true;
        $maxRedirs = $options['max_redirects'] ?? 5;
        $verifySSL = $options['verify_ssl'] ?? true;
        
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => $maxRedirs,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySSL,
            CURLOPT_SSL_VERIFYHOST => $verifySSL ? 2 : 0,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HEADERFUNCTION => function($ch, $hdr) use (&$headers) {
                $parts = explode(':', $hdr, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($hdr);
            }
        ];
        
        if (!empty($options['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options['headers'];
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'message' => 'Fetch failed', 'error' => $err];
        }
        
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ct = $headers['content-type'] ?? curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        curl_close($ch);
        
        if ($status >= 400) {
            return ['ok' => false, 'message' => 'Remote returned error', 'status' => $status];
        }
        
        if (strlen($body) > 1024*1024) {
            $body = substr($body, 0, 1024*1024);
        }
        
        return ['ok' => true, 'body' => $body, 'contentType' => $ct];
    }
    
    public function checkHealth(): array {
        if (!$this->enabled) {
            return [
                'ok' => false,
                'message' => 'Proxy service is disabled',
                'enabled' => false
            ];
        }
        
        $testUrl = getenv('PROXY_HEALTH_URL') ?: 'https://www.google.com';
        $result = $this->fetchViaProxy($testUrl, ['timeout' => 10], $this->getPrimaryProxyConfig());
        
        $healthStatus = [
            'ok' => $result['ok'],
            'enabled' => true,
            'proxy' => $this->proxyHost . ':' . $this->proxyPort,
            'proxy_type' => $this->proxyType,
            'test_url' => $testUrl,
            'response_time_ms' => $result['fetch_time_ms'] ?? null,
        ];
        
        if (!$result['ok']) {
            $healthStatus['error'] = $result['error'] ?? $result['message'] ?? 'Unknown error';
            $healthStatus['error_code'] = $result['error_code'] ?? null;
        }
        
        if ($this->autoFallback) {
            $healthStatus['fallback_enabled'] = true;
            $healthStatus['fallback_proxies_count'] = count($this->fallbackProxies);
        }
        
        return $healthStatus;
    }
    
    public function getConfig(): array {
        return [
            'enabled' => $this->enabled,
            'proxy_host' => $this->proxyHost,
            'proxy_port' => $this->proxyPort,
            'proxy_type' => $this->proxyType,
            'has_auth' => !empty($this->proxyAuth),
            'auto_fallback' => $this->autoFallback,
            'fallback_count' => count($this->fallbackProxies),
        ];
    }
}
