<?php

class TelebirrParser {
    public static function canParse(string $url, string $html): bool {
        return str_contains(parse_url($url, PHP_URL_HOST) ?? '', 'ethiotelecom.et')
            || str_contains($html, 'telebirr');
    }

    public static function parse(string $html, string $url): array {
        $out = [
            'source' => 'telebirr',
            'transaction_id' => null,
            'amount' => null,
            'currency' => 'ETB',
            'date' => null,
            'sender_name' => null,
            'sender_account' => null,
            'receiver_name' => null,
            'receiver_account' => null,
        ];

        // Try to extract key fields using multiple patterns
        $patterns = [
            'transaction_id' => '/(Txn|Transaction)\s*(ID|Id|No)[:\s]*([A-Z0-9\-]{8,})/i',
            'amount' => '/(?:Amount|Paid)[:\s]*([0-9\,\.]+)\s*(ETB|Birr)?/i',
            'date' => '/(Date|Time)[:\s]*([0-9:\-\s\/]+[APMapm\s]*)/',
            'sender_name' => '/Sender[:\s]*([\p{L}\s\.\-]+)</u',
            'receiver_name' => '/(Receiver|To|Merchant)[:\s]*([\p{L}\s\.\-]+)</u',
            'sender_account' => '/(?:From Account|MSISDN|Phone)[:\s]*([0-9\*\+\-\s]{6,})/i',
            'receiver_account' => '/(?:To Account|Account)[:\s]*([0-9\*\-\s]{4,})/i',
        ];

        foreach ($patterns as $key => $regex) {
            if (preg_match($regex, $html, $m)) {
                $val = trim($m[count($m)-1]);
                $out[$key] = $val;
            }
        }

        // Fallback: look for obvious txn id in URL
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (!$out['transaction_id'] && preg_match('/([A-Z0-9]{8,})$/', $path, $m)) {
            $out['transaction_id'] = $m[1];
        }

        // Normalize amount (remove commas)
        if ($out['amount']) {
            $out['amount'] = preg_replace('/,/', '', $out['amount']);
        }

        return $out;
    }
}
