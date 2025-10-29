<?php

class CbeParser {
    public static function canParse(string $url, string $html): bool {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return str_contains($host, 'cbe.com.et') || str_contains($host, 'combanketh.et') || str_contains($html, 'CBE');
    }

    public static function parse(string $html, string $url): array {
        $out = [
            'source' => 'cbe',
            'transaction_id' => null,
            'amount' => null,
            'currency' => 'ETB',
            'date' => null,
            'sender_name' => null,
            'sender_account' => null,
            'receiver_name' => null,
            'receiver_account' => null,
        ];

        $patterns = [
            'transaction_id' => '/(FT|TRX|Txn)[\s\:]?([A-Z0-9]{6,})/i',
            'amount' => '/Amount[:\s]*([0-9\,\.]+)\s*(ETB|Birr)?/i',
            'date' => '/(Date|Posting Date)[:\s]*([0-9:\-\s\/]+)/i',
            'sender_account' => '/From\s*Account[:\s]*([0-9\*\-\s]+)/i',
            'receiver_account' => '/To\s*Account[:\s]*([0-9\*\-\s]+)/i',
            'receiver_name' => '/Beneficiary[:\s]*([\p{L}\s\.\-]+)/u',
        ];

        foreach ($patterns as $key => $regex) {
            if (preg_match($regex, $html, $m)) {
                $out[$key] = trim($m[count($m)-1]);
            }
        }

        // Txn id sometimes in URL query ?id=FT23...
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($query, $q);
        if (!$out['transaction_id'] && isset($q['id']) && preg_match('/[A-Z0-9]{8,}/', $q['id'], $m)) {
            $out['transaction_id'] = $m[0];
        }

        if ($out['amount']) {
            $out['amount'] = preg_replace('/,/', '', $out['amount']);
        }

        return $out;
    }
}
