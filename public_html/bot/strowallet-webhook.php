<?php
/**
 * StroWallet Webhook Handler
 * Receives deposit confirmations and other events from StroWallet
 */

// Load environment variables from .env file
require_once __DIR__ . '/../../secrets/load_env.php';

// Configuration - Use environment variables (Replit Secrets or .env file)
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('ADMIN_CHAT_ID', getenv('ADMIN_CHAT_ID') ?: '');
define('STROW_WEBHOOK_SECRET', getenv('STROW_WEBHOOK_SECRET') ?: '');

// Get incoming webhook payload
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

// Optional: Verify HMAC signature if configured
if (!empty(STROW_WEBHOOK_SECRET)) {
    $signature = $_SERVER['HTTP_X_STROWALLET_SIGNATURE'] ?? '';
    if (!empty($signature)) {
        $expectedSignature = hash_hmac('sha256', $input, STROW_WEBHOOK_SECRET);
        if (!hash_equals($expectedSignature, $signature)) {
            http_response_code(403);
            die('Invalid signature');
        }
    }
}

if (!$payload) {
    http_response_code(400);
    die('Invalid JSON');
}

// Process event
$eventType = $payload['event'] ?? $payload['type'] ?? 'unknown';
$eventData = $payload['data'] ?? $payload;

// Handle deposit confirmation
if ($eventType === 'deposit_confirmed' || $eventType === 'deposit.confirmed') {
    handleDepositConfirmed($eventData);
} elseif ($eventType === 'card_created' || $eventType === 'card.created') {
    handleCardCreated($eventData);
} else {
    // Log unknown events (optional)
    logEvent($eventType, $eventData);
}

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);
exit;

// ==================== EVENT HANDLERS ====================

function handleDepositConfirmed($data) {
    $amount = $data['amount'] ?? '0.00';
    $currency = $data['currency'] ?? 'USD';
    $userId = $data['user_id'] ?? 'Unknown';
    $txHash = $data['transaction_hash'] ?? $data['tx_hash'] ?? null;
    $network = $data['network'] ?? 'Unknown';
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    // Format admin alert message
    $msg = "💰 <b>Deposit Confirmed</b>\n\n";
    $msg .= "💵 <b>Amount:</b> " . formatAmount($amount, $currency) . "\n";
    $msg .= "🪙 <b>Currency:</b> {$currency}\n";
    $msg .= "🌐 <b>Network:</b> {$network}\n";
    $msg .= "👤 <b>User ID:</b> " . maskId($userId) . "\n";
    
    if ($txHash) {
        $maskedHash = maskTxHash($txHash);
        $msg .= "🔗 <b>TX Hash:</b> <code>{$maskedHash}</code>\n";
    }
    
    $msg .= "🕐 <b>Time:</b> " . formatTimestamp($timestamp);
    
    // Send alert to admin
    if (!empty(ADMIN_CHAT_ID)) {
        sendTelegramMessage(ADMIN_CHAT_ID, $msg);
    }
}

function handleCardCreated($data) {
    $userId = $data['user_id'] ?? 'Unknown';
    $cardBrand = $data['brand'] ?? $data['card_brand'] ?? 'Visa';
    $last4 = $data['last4'] ?? '****';
    
    // Send notification to admin if needed
    if (!empty(ADMIN_CHAT_ID)) {
        $msg = "💳 <b>New Card Created</b>\n\n";
        $msg .= "👤 <b>User:</b> " . maskId($userId) . "\n";
        $msg .= "💳 <b>Card:</b> {$cardBrand} ••••{$last4}";
        
        sendTelegramMessage(ADMIN_CHAT_ID, $msg);
    }
}

// ==================== HELPER FUNCTIONS ====================

function sendTelegramMessage($chatId, $text) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

function formatAmount($amount, $currency) {
    $currency = strtoupper($currency);
    if ($currency === 'USD' || $currency === 'USDT') {
        return '$' . number_format((float)$amount, 2);
    }
    return number_format((float)$amount, 2) . ' ' . $currency;
}

function maskId($id) {
    $id = (string)$id;
    if (strlen($id) <= 8) {
        return $id;
    }
    return substr($id, 0, 4) . '***' . substr($id, -4);
}

function maskTxHash($hash) {
    $hash = (string)$hash;
    if (strlen($hash) <= 16) {
        return $hash;
    }
    return substr($hash, 0, 8) . '...' . substr($hash, -8);
}

function formatTimestamp($timestamp) {
    $time = strtotime($timestamp);
    return $time ? date('d/m/Y H:i:s', $time) : $timestamp;
}

function logEvent($eventType, $data) {
    // Optional: Log to file for debugging
    // Uncomment if needed for development
    /*
    $logFile = __DIR__ . '/../../logs/webhook.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logEntry = date('Y-m-d H:i:s') . " | Event: {$eventType} | Data: " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    */
}
