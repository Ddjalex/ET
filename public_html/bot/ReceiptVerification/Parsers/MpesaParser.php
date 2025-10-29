<?php
/**
 * M-Pesa Receipt Parser
 * Parses M-Pesa transaction receipts from Ethiopia
 */

class MpesaParser
{
    /**
     * Check if this parser can handle the given URL/HTML
     */
    public static function canParse(string $url, string $html): bool
    {
        // Check URL domain
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        
        // M-Pesa domains (adjust based on actual M-Pesa Ethiopia URLs)
        $mpesaDomains = [
            'mpesa.et',
            'safaricom.et',
            'm-pesa.et',
            'mpesa.ethiotelecom.et'
        ];
        
        foreach ($mpesaDomains as $domain) {
            if (str_ends_with($host, $domain)) {
                return true;
            }
        }
        
        // Check HTML content for M-Pesa signatures
        if (str_contains(strtolower($html), 'm-pesa') || 
            str_contains(strtolower($html), 'mpesa')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse M-Pesa receipt HTML using DOM parser
     */
    public static function parse(string $html, string $url): array
    {
        $result = [
            'source' => 'm-pesa',
            'transaction_id' => null,
            'amount' => null,
            'currency' => 'ETB',
            'date' => null,
            'sender_name' => null,
            'sender_account' => null,
            'receiver_name' => null,
            'receiver_account' => null,
        ];
        
        // Try JSON parsing first (if response is JSON)
        if (str_starts_with(trim($html), '{') || str_starts_with(trim($html), '[')) {
            $data = json_decode($html, true);
            if (is_array($data)) {
                return self::parseJsonFormat($data, $result);
            }
        }
        
        // Parse HTML using DOM
        $result = self::parseHtmlDom($html, $result);
        
        // Fallback to regex if DOM parsing didn't find enough data
        if (empty($result['transaction_id']) || empty($result['amount'])) {
            $result = self::parseHtmlRegex($html, $result);
        }
        
        return $result;
    }
    
    /**
     * Parse HTML using DOM Document (handles structured HTML like tables)
     */
    private static function parseHtmlDom(string $html, array $result): array
    {
        // Suppress warnings from malformed HTML
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        libxml_clear_errors();
        
        // Extract all text content with their labels
        $textContent = self::extractTextContent($dom);
        
        // Field mapping with multiple possible labels (including M-Pesa business labels)
        $fieldPatterns = [
            'transaction_id' => ['Transaction ID', 'Transaction Code', 'Reference', 'Ref No', 'Receipt No', 'TransactionID', 'Trans ID', 'Receipt', 'Confirmation'],
            'amount' => ['Amount', 'Total', 'Paid', 'Transaction Amount', 'Value', 'Trans Amount'],
            'date' => ['Date', 'Transaction Date', 'DateTime', 'Timestamp', 'Time', 'Completion Time', 'Trans Date'],
            'sender_name' => ['From', 'Sender', 'Customer', 'Payer', 'Sender Name', 'First Name', 'Customer Name'],
            'sender_account' => ['From Number', 'Sender MSISDN', 'Sender Phone', 'Customer Phone', 'MSISDN', 'Mobile Number'],
            'receiver_name' => ['To', 'Recipient', 'Merchant', 'Receiver', 'Recipient Name', 'Business Name', 'Organisation', 'Organization', 'Merchant Name', 'Till Name', 'PayBill Name'],
            'receiver_account' => ['To Number', 'Recipient MSISDN', 'Recipient Phone', 'Merchant Phone', 'Business Number', 'Till Number', 'PayBill Number', 'Paybill', 'Till', 'Business', 'Account Number'],
        ];
        
        foreach ($fieldPatterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                $value = self::findValueByLabel($textContent, $pattern);
                if ($value !== null && empty($result[$field])) {
                    // Process the value based on field type
                    if ($field === 'amount') {
                        // Remove currency symbols and extract number
                        $value = preg_replace('/[^\d,.]/', '', $value);
                        $value = str_replace(',', '', $value);
                    } elseif (str_contains($field, 'account')) {
                        // For accounts, accept phone numbers (10-15 digits) OR short codes (5-9 digits)
                        if (!preg_match('/^\d{5,15}$/', $value)) {
                            // Not a valid account number format - skip
                            continue;
                        }
                    }
                    
                    $result[$field] = $value;
                    break;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Extract text content from DOM with structure preservation
     */
    private static function extractTextContent(\DOMDocument $dom): array
    {
        $content = [];
        
        // Get all text nodes and their associated labels from tables, divs, spans
        $xpath = new \DOMXPath($dom);
        
        // Extract from table rows (common receipt format)
        $rows = $xpath->query('//tr | //div | //p');
        foreach ($rows as $row) {
            $cells = $xpath->query('.//td | .//th | .//span | .//strong | .//b | .//div', $row);
            if ($cells->length >= 2) {
                $label = trim($cells->item(0)->textContent);
                $value = trim($cells->item(1)->textContent);
                if (!empty($label) && !empty($value)) {
                    $content[$label] = $value;
                }
            } elseif ($cells->length === 1) {
                // Single cell, check if it contains label: value pattern
                $text = trim($cells->item(0)->textContent);
                if (strpos($text, ':') !== false) {
                    list($label, $value) = explode(':', $text, 2);
                    $content[trim($label)] = trim($value);
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Find value by label in text content
     */
    private static function findValueByLabel(array $textContent, string $labelPattern): ?string
    {
        foreach ($textContent as $label => $value) {
            // Case-insensitive partial match
            if (stripos($label, $labelPattern) !== false || stripos($labelPattern, $label) !== false) {
                return $value;
            }
        }
        return null;
    }
    
    /**
     * Fallback regex-based HTML parsing
     */
    private static function parseHtmlRegex(string $html, array $result): array
    {
        // Remove extra whitespace and normalize
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Pattern 1: Transaction ID / Reference
        if (preg_match('/(?:Transaction\s+ID|Reference|Ref\s+No|Receipt\s+No)[:\s]*<[^>]+>([A-Z0-9]{6,20})/i', $html, $matches)) {
            $result['transaction_id'] = trim($matches[1]);
        } elseif (preg_match('/(?:Transaction\s+ID|Reference|Ref\s+No|Receipt\s+No)[:\s]+([A-Z0-9]{6,20})/i', $html, $matches)) {
            $result['transaction_id'] = trim($matches[1]);
        }
        
        // Pattern 2: Amount
        if (preg_match('/(?:Amount|Total|Paid)[:\s]*<[^>]+>(?:ETB|Br)?\s*([\d,]+\.?\d*)/i', $html, $matches)) {
            $result['amount'] = str_replace(',', '', $matches[1]);
        } elseif (preg_match('/(?:Amount|Total|Paid)[:\s]+(?:ETB|Br)?\s*([\d,]+\.?\d*)/i', $html, $matches)) {
            $result['amount'] = str_replace(',', '', $matches[1]);
        }
        
        // Pattern 3: Date/Time
        if (preg_match('/(\d{4}[\-\/]\d{1,2}[\-\/]\d{1,2}\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)/i', $html, $matches)) {
            $result['date'] = trim($matches[1]);
        } elseif (preg_match('/(\d{1,2}[\-\/]\d{1,2}[\-\/]\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)/i', $html, $matches)) {
            $result['date'] = trim($matches[1]);
        }
        
        return $result;
    }
    
    /**
     * Parse JSON format M-Pesa receipts
     */
    private static function parseJsonFormat(array $data, array $result): array
    {
        // Common JSON field mappings
        $fieldMap = [
            'transaction_id' => ['TransactionID', 'transactionId', 'txnId', 'txId', 'reference', 'receiptNo'],
            'amount' => ['Amount', 'amount', 'transAmount', 'totalAmount', 'value'],
            'date' => ['TransactionDate', 'transactionDate', 'date', 'timestamp', 'dateTime'],
            'sender_name' => ['CustomerName', 'customerName', 'senderName', 'payerName'],
            'sender_account' => ['CustomerMSISDN', 'customerPhone', 'senderPhone', 'fromAccount'],
            'receiver_name' => ['RecipientName', 'receiverName', 'merchantName'],
            'receiver_account' => ['RecipientMSISDN', 'receiverPhone', 'merchantPhone', 'toAccount'],
        ];
        
        foreach ($fieldMap as $key => $possibleKeys) {
            foreach ($possibleKeys as $jsonKey) {
                if (isset($data[$jsonKey]) && !empty($data[$jsonKey])) {
                    $result[$key] = $data[$jsonKey];
                    break;
                }
            }
        }
        
        return $result;
    }
}
