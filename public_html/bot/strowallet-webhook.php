<?php
/**
 * StroWallet Webhook Handler
 * Receives deposit confirmations and other events from StroWallet
 */

// Load environment variables from .env file
require_once __DIR__ . '/../../secrets/load_env.php';

// Configuration - Use environment variables (Replit Secrets or .env file)
define('BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: getenv('BOT_TOKEN') ?: '');
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

// Connect to database for KYC sync
require_once __DIR__ . '/../admin/config/database.php';

// Process event
$eventType = $payload['event'] ?? $payload['type'] ?? 'unknown';
$eventData = $payload['data'] ?? $payload;

// Handle deposit confirmation
if ($eventType === 'deposit_confirmed' || $eventType === 'deposit.confirmed') {
    handleDepositConfirmed($eventData);
} elseif ($eventType === 'card_created' || $eventType === 'card.created') {
    handleCardCreated($eventData);
} elseif ($eventType === 'kyc_updated' || $eventType === 'kyc.updated' || $eventType === 'customer.kyc_updated') {
    handleKYCUpdated($eventData);
} elseif ($eventType === 'kyc_approved' || $eventType === 'kyc.approved') {
    handleKYCApproved($eventData);
} elseif ($eventType === 'kyc_rejected' || $eventType === 'kyc.rejected') {
    handleKYCRejected($eventData);
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

function handleKYCUpdated($data) {
    $email = $data['email'] ?? $data['customer_email'] ?? null;
    $customerId = $data['customer_id'] ?? $data['customerId'] ?? null;
    $kycStatus = strtolower($data['kyc_status'] ?? $data['kycStatus'] ?? 'pending');
    
    if (!$email && !$customerId) {
        return;
    }
    
    // Map StroWallet status to database status
    $dbStatus = match($kycStatus) {
        'verified', 'approved', 'high kyc', 'low kyc' => 'approved',
        'rejected', 'failed' => 'rejected',
        default => 'pending'
    };
    
    // Connect to database
    $pdo = getWebhookDBConnection();
    if (!$pdo) return;
    
    // Update user KYC status in database
    try {
        // Find user by email or customer ID in user_registrations table
        $whereClause = $email ? "email = ?" : "strowallet_customer_id = ?";
        $whereValue = $email ?: $customerId;
        
        $stmt = $pdo->prepare("SELECT telegram_user_id, first_name, last_name FROM user_registrations WHERE $whereClause LIMIT 1");
        $stmt->execute([$whereValue]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            logEvent('kyc_update_no_user', ['email' => $email, 'customer_id' => $customerId]);
            return;
        }
        
        // Update KYC status in user_registrations table
        $updateStmt = $pdo->prepare("
            UPDATE user_registrations 
            SET kyc_status = ?, 
                strowallet_customer_id = COALESCE(?, strowallet_customer_id),
                updated_at = CURRENT_TIMESTAMP 
            WHERE $whereClause
        ");
        $updateStmt->execute([$dbStatus, $customerId, $whereValue]);
        
        // ALSO update the users table (used by admin panel)
        $usersWhereClause = $email ? "email = ?" : "strow_customer_id = ?";
        $usersWhereValue = $email ?: $customerId;
        
        // Check if user exists in users table
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE $usersWhereClause LIMIT 1");
        $checkUser->execute([$usersWhereValue]);
        $existingUser = $checkUser->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            // Update existing user in users table
            $updateUsersStmt = $pdo->prepare("
                UPDATE users 
                SET kyc_status = ?, 
                    strow_customer_id = COALESCE(?, strow_customer_id),
                    updated_at = CURRENT_TIMESTAMP 
                WHERE $usersWhereClause
            ");
            $updateUsersStmt->execute([$dbStatus, $customerId, $usersWhereValue]);
        }
        
        $telegramUserId = $user['telegram_user_id'];
        $fullName = $user['first_name'] . ' ' . $user['last_name'];
        
        // Notify user based on status
        if ($dbStatus === 'approved') {
            notifyUserKYCApproved($telegramUserId);
        } elseif ($dbStatus === 'rejected') {
            notifyUserKYCRejected($telegramUserId, $data['rejection_reason'] ?? 'Not specified');
        }
        
        // Notify admin
        if (!empty(ADMIN_CHAT_ID)) {
            $statusEmoji = match($dbStatus) {
                'approved' => '✅',
                'rejected' => '❌',
                default => '⏳'
            };
            
            $msg = "{$statusEmoji} <b>KYC Status Updated</b>\n\n";
            $msg .= "👤 <b>User:</b> {$fullName}\n";
            $msg .= "📧 <b>Email:</b> " . ($email ?: 'N/A') . "\n";
            $msg .= "🆔 <b>Telegram ID:</b> <code>{$telegramUserId}</code>\n";
            $msg .= "📋 <b>Status:</b> " . ucfirst($dbStatus) . " (StroWallet: {$kycStatus})\n";
            $msg .= "🕐 <b>Updated:</b> " . date('d/m/Y H:i:s');
            
            sendTelegramMessage(ADMIN_CHAT_ID, $msg);
        }
        
        logEvent('kyc_updated_success', [
            'email' => $email, 
            'customer_id' => $customerId, 
            'status' => $dbStatus,
            'strowallet_status' => $kycStatus
        ]);
    } catch (Exception $e) {
        logEvent('kyc_update_error', ['error' => $e->getMessage(), 'data' => $data]);
    }
}

function handleKYCApproved($data) {
    $data['kyc_status'] = 'approved';
    handleKYCUpdated($data);
}

function handleKYCRejected($data) {
    $data['kyc_status'] = 'rejected';
    handleKYCUpdated($data);
}

// ==================== USER NOTIFICATION FUNCTIONS ====================

function notifyUserKYCApproved($telegramUserId) {
    $msg = "🎉 <b>KYC Approved!</b>\n\n";
    $msg .= "✅ Your identity verification has been approved.\n\n";
    $msg .= "You can now create virtual cards and use all features!\n\n";
    $msg .= "📱 Use the menu below to get started:";
    
    // Send message WITH reply keyboard (menu buttons)
    sendTelegramMessageWithKeyboard($telegramUserId, $msg);
}

function notifyUserKYCRejected($telegramUserId, $reason) {
    $msg = "❌ <b>KYC Verification Failed</b>\n\n";
    $msg .= "Your identity verification was not approved.\n\n";
    $msg .= "📋 <b>Reason:</b> {$reason}\n\n";
    $msg .= "Please contact support for assistance.";
    
    sendTelegramMessage($telegramUserId, $msg);
}

function getWebhookDBConnection() {
    $dbUrl = getenv('DATABASE_URL');
    if (empty($dbUrl)) {
        return null;
    }
    
    try {
        $db = parse_url($dbUrl);
        $port = $db['port'] ?? 5432;
        $pdo = new PDO(
            "pgsql:host={$db['host']};port={$port};dbname=" . ltrim($db['path'], '/'),
            $db['user'],
            $db['pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
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

function sendTelegramMessageWithKeyboard($chatId, $text) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'keyboard' => [
                [
                    ['text' => '➕ Create Card'],
                    ['text' => '💳 My Cards']
                ],
                [
                    ['text' => '👤 User Info'],
                    ['text' => '💰 Wallet']
                ],
                [
                    ['text' => '💸 Invite Friends'],
                    ['text' => '🧑‍💻 Support']
                ]
            ],
            'resize_keyboard' => true,
            'is_persistent' => true
        ]
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

function sendTelegramMessageWithButton($chatId, $text, $button) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    [
                        'text' => $button['text'],
                        'callback_data' => $button['callback_data']
                    ]
                ]
            ]
        ]
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
