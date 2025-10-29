<?php
namespace App\Services;

use App\Config\Env;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/Parsers/TelebirrParser.php';
require_once __DIR__ . '/Parsers/CbeParser.php';

class ReceiptVerifier {
    private array $parsers;
    private array $allowed;

    public function __construct() {
        $this->parsers = [
            new \App\Services\Parsers\TelebirrParser(),
            new \App\Services\Parsers\CbeParser(),
        ];
        $envAllowed = Env::get('ALLOWED_DOMAINS', 'transactioninfo.ethiotelecom.et,apps.cbe.com.et,www.combanketh.et');
        $this->allowed = array_map('trim', explode(',', $envAllowed));
    }

    public function verifyByUrl(string $url): array {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok'=>false,'message'=>'Invalid URL'];
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !$this->isAllowedHost($host)) {
            return ['ok'=>false,'message'=>'Domain not allowed', 'host'=>$host, 'allowed'=>$this->allowed];
        }

        $resp = $this->fetch($url);
        if (!$resp['ok']) return $resp;

        $html = $resp['body'];
        $contentType = $resp['contentType'] ?? '';

        // If JSON, try to pick fields directly
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($html, true);
            if (is_array($data)) {
                $parsed = [
                    'source' => $host,
                    'transaction_id' => $data['transactionId'] ?? $data['txnId'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'currency' => $data['currency'] ?? 'ETB',
                    'date' => $data['date'] ?? $data['timestamp'] ?? null,
                    'sender_name' => $data['sender']['name'] ?? null,
                    'sender_account' => $data['sender']['account'] ?? null,
                    'receiver_name' => $data['receiver']['name'] ?? null,
                    'receiver_account' => $data['receiver']['account'] ?? null,
                ];
                $parsed = $this->withNormalizedDates($parsed);
                return ['ok'=>true, 'parsed'=>$parsed, 'raw_html'=>$this->truncate($html)];
            }
        }

        // HTML path: try parsers
        foreach ($this->parsers as $p) {
            if ($p::canParse($url, $html)) {
                $parsed = $p::parse($html, $url);
                $parsed = $this->withNormalizedDates($parsed);
                $missing = [];
                foreach ($parsed as $k=>$v) {
                    if ($v === null) $missing[] = $k;
                }
                return ['ok'=>true,'parsed'=>$parsed,'missing_fields'=>$missing,'raw_html'=>$this->truncate($html)];
            }
        }

        return ['ok'=>false,'message'=>'Could not parse receipt HTML','raw_html'=>$this->truncate($html)];
    }

    private function withNormalizedDates(array $parsed): array {
        $tz = Env::get('APP_TZ', 'Africa/Addis_Ababa');
        $dateStr = $parsed['date'] ?? null;
        if ($dateStr) {
            $norm = $this->parseDate($dateStr, $tz);
            if ($norm) {
                $parsed['date_iso'] = $norm['iso_utc'];
                $parsed['date_local'] = $norm['iso_local'];
            }
        }
        return $parsed;
    }

    private function parseDate(string $input, string $tz): ?array {
        $input = trim($input);
        // Try common formats quickly
        $candidates = [$input];
        // Replace common Telebirr/CBE variations
        $candidates[] = preg_replace('/\s+/', ' ', $input);
        foreach ($candidates as $cand) {
            $ts = strtotime($cand);
            if ($ts !== false) {
                try {
                    $dtLocal = new \DateTimeImmutable('@'.$ts);
                    $dtLocal = $dtLocal->setTimezone(new \DateTimeZone($tz));
                    $dtUtc = $dtLocal->setTimezone(new \DateTimeZone('UTC'));
                    return [
                        'iso_local' => $dtLocal->format(\DateTimeInterface::ATOM),
                        'iso_utc' => $dtUtc->format(\DateTimeInterface::ATOM),
                    ];
                } catch (\Exception $e) {}
            }
        }
        // Try to extract numeric patterns like 2025-10-29 12:34:56
        if (preg_match('/(\d{4}[\-\/]\d{1,2}[\-\/]\d{1,2}[ T]\d{1,2}:\d{2}(:\d{2})?)/', $input, $m)) {
            $ts = strtotime($m[1]);
            if ($ts !== false) {
                try {
                    $dtLocal = new \DateTimeImmutable('@'.$ts);
                    $dtLocal = $dtLocal->setTimezone(new \DateTimeZone($tz));
                    $dtUtc = $dtLocal->setTimezone(new \DateTimeZone('UTC'));
                    return [
                        'iso_local' => $dtLocal->format(\DateTimeInterface::ATOM),
                        'iso_utc' => $dtUtc->format(\DateTimeInterface::ATOM),
                    ];
                } catch (\Exception $e) {}
            }
        }
        return null;
    }

    private function isAllowedHost(string $host): bool {
        foreach ($this->allowed as $allowed) {
            if ($allowed && str_ends_with($host, $allowed)) return true;
        }
        return false;
    }

    private function fetch(string $url): array {
        $ch = curl_init($url);
        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'PaymentVerifier/1.1 (+PHP cURL)',
            CURLOPT_HEADERFUNCTION => function($ch, $hdr) use (&$headers) {
                $parts = explode(':', $hdr, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($hdr);
            }
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok'=>false,'message'=>'Fetch failed','error'=>$err];
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ct = $headers['content-type'] ?? curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        curl_close($ch);

        if ($status >= 400) {
            return ['ok'=>false,'message'=>'Remote returned error','status'=>$status];
        }
        if (strlen($body) > 1024*1024) {
            $body = substr($body, 0, 1024*1024);
        }
        return ['ok'=>true,'body'=>$body,'contentType'=>$ct];
    }

    private function truncate(string $s, int $max=50_000): string {
        if (strlen($s) <= $max) return $s;
        return substr($s, 0, $max) . "\n<!-- truncated -->";
    }
}
