<?php
/**
 * Telegram Crypto Card Bot - Main Webhook Handler
 * PHP 8+ | No Frameworks | No Composer
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/telegram_bot_errors.log');

// Load environment variables from .env file
require_once __DIR__ . '/../../secrets/load_env.php';

// Configuration - Use environment variables from Replit Secrets
// Priority: Replit Secrets > .env file
define('BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('BOT_TOKEN') ?: ''));
define('STROW_BASE', 'https://strowallet.com/api');
define('STROW_PUBLIC_KEY', getenv('STROWALLET_API_KEY') ?: ($_ENV['STROWALLET_API_KEY'] ?? ''));
define('STROW_SECRET_KEY', getenv('STROWALLET_WEBHOOK_SECRET') ?: ($_ENV['STROWALLET_WEBHOOK_SECRET'] ?? ''));
define('STROWALLET_EMAIL', getenv('STROWALLET_EMAIL') ?: ($_ENV['STROWALLET_EMAIL'] ?? ''));
define('ADMIN_CHAT_ID', getenv('ADMIN_CHAT_ID') ?: ($_ENV['ADMIN_CHAT_ID'] ?? ''));
define('SUPPORT_URL', getenv('SUPPORT_URL') ?: ($_ENV['SUPPORT_URL'] ?? ''));
define('REFERRAL_TEXT', getenv('REFERRAL_TEXT') ?: ($_ENV['REFERRAL_TEXT'] ?? 'Join me on StroWallet!'));
define('TELEGRAM_SECRET_TOKEN', getenv('TELEGRAM_SECRET_TOKEN') ?: ($_ENV['TELEGRAM_SECRET_TOKEN'] ?? ''));

// Mock mode for testing (set to true to use demo data)
define('USE_MOCK_DATA', ($_ENV['USE_MOCK_DATA'] ?? getenv('USE_MOCK_DATA')) === 'true' || ($_ENV['USE_MOCK_DATA'] ?? getenv('USE_MOCK_DATA')) === '1');

// Sandbox mode for StroWallet API (bypasses IP whitelist and allows testing without real balance)
define('USE_SANDBOX_MODE', true);

// Database configuration
define('DATABASE_URL', $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '');

// ==================== DATABASE CONNECTION ====================

function getDBConnection() {
    $dbUrl = DATABASE_URL;
    if (empty($dbUrl)) {
        error_log("DATABASE_URL not configured");
        return null;
    }
    
    try {
        $db = parse_url($dbUrl);
        $port = $db['port'] ?? 5432; // Default PostgreSQL port
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

// Load deposit handler - manual admin review only
require_once __DIR__ . '/deposit_handler_v2.php';

// Verify Telegram secret token if configured
if (TELEGRAM_SECRET_TOKEN !== '' && isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])) {
    if ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] !== TELEGRAM_SECRET_TOKEN) {
        http_response_code(403);
        die('Invalid secret token');
    }
}

// Get incoming update
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    die('Invalid JSON');
}

// Handle callback queries (inline button clicks)
$callbackQuery = $update['callback_query'] ?? null;
if ($callbackQuery) {
    handleCallbackQuery($callbackQuery);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Extract message data
$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = trim($message['text'] ?? '');
$userId = $message['from']['id'] ?? null;

// Debug log (after variables are defined)
error_log("Webhook received: " . $input);
error_log("BOT_TOKEN exists: " . (BOT_TOKEN ? 'YES' : 'NO'));
error_log("Chat ID: " . ($chatId ?? 'NONE'));
error_log("Message text: " . ($text ?? 'NONE'));

// Check for photo or document uploads
$photo = $message['photo'] ?? null;
$document = $message['document'] ?? null;
$fileId = null;
$fileUrl = null;

if ($photo && is_array($photo) && count($photo) > 0) {
    // Get the largest photo
    $largestPhoto = end($photo);
    $fileId = $largestPhoto['file_id'] ?? null;
} elseif ($document) {
    $fileId = $document['file_id'] ?? null;
}

// File URL will be set during registration flow when file type is known
$fileUrl = null;

if (!$chatId) {
    http_response_code(200);
    die('OK');
}

// Check if user is in registration flow
$userState = getUserRegistrationState($userId);

// Handle cancel command
if ($text === '/cancel') {
    if ($userState && $userState !== 'idle' && $userState !== 'completed') {
        updateUserRegistrationState($userId, 'idle');
        sendMessage($chatId, "❌ Registration cancelled. You can start again with /register", false);
    } else {
        sendMessage($chatId, "Nothing to cancel.", false);
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

// Handle continue command
if ($text === '/continue') {
    if ($userState && $userState !== 'idle' && $userState !== 'completed') {
        // Re-prompt for the current field
        promptForCurrentField($chatId, $userState, $userId);
    } else {
        sendMessage($chatId, "Nothing to continue. Use /register to start.", false);
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

// Handle /start command - always allow restart
if ($text === '/start' || $text === '🏠 Menu') {
    // Reset registration state if in progress
    if ($userState && $userState !== 'idle' && $userState !== 'completed') {
        updateUserRegistrationState($userId, 'idle');
    }
    handleStart($chatId, $userId);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Handle /register command - always allow restart
if ($text === '/register') {
    handleRegisterStart($chatId, $userId);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Check if user is awaiting deposit amount input
if ($userState === 'awaiting_amount') {
    processDepositAmount_v2($chatId, $userId, $text);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Check if user is awaiting screenshot upload
if ($userState === 'awaiting_screenshot') {
    if ($photo && is_array($photo) && count($photo) > 0) {
        handleDepositScreenshot_v2($chatId, $userId, $fileId);
    } else {
        sendMessageWithReturn($chatId, "📸 Please send a screenshot of your payment confirmation.", '❌ Cancel Deposit', 'cancel');
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

// Check if user is awaiting transaction ID - all deposits go to manual admin review
if ($userState === 'awaiting_transaction_id') {
    if ($text) {
        // Route all deposits to manual admin review (no automatic verification)
        handleDepositTransactionId_v2($chatId, $userId, $text);
    } else {
        sendMessage($chatId, "📝 Please enter your transaction ID or reference number.", false);
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

// If user is in registration flow (not idle), route to registration handler
if ($userState && $userState !== 'idle' && $userState !== 'completed' && !in_array($userState, ['awaiting_amount', 'awaiting_screenshot', 'awaiting_transaction_id'])) {
    handleRegistrationFlow($chatId, $userId, $text, $userState, $fileId);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Route commands and button presses
if ($text === '/quickregister') {
    handleQuickRegister($chatId, $userId);
} elseif ($text === '/create_card' || $text === '➕ Create Card') {
    handleCreateCard($chatId, $userId);
} elseif ($text === '/cards' || $text === '💳 My Cards') {
    handleMyCards($chatId, $userId);
} elseif ($text === '/userinfo' || $text === '👤 User Info') {
    handleUserInfo($chatId, $userId);
} elseif ($text === '/wallet' || $text === '💰 Wallet') {
    handleWallet($chatId, $userId);
} elseif ($text === '/deposit_trc20') {
    handleDepositTRC20($chatId, $userId);
} elseif ($text === '/deposit_etb' || $text === '💵 Deposit ETB') {
    handleDepositETB($chatId, $userId);
} elseif ($text === '/invite' || $text === '💸 Invite Friends') {
    handleInvite($chatId, $userId);
} elseif ($text === '/support' || $text === '🧑‍💻 Support') {
    handleSupport($chatId, $userId);
} elseif ($text === '/status' || $text === '/checkkyc' || $text === '📋 Check Status') {
    handleCheckStatus($chatId, $userId);
} else {
    sendMessage($chatId, "ℹ️ Unknown command. Please use the menu buttons below.", true, $userId);
}

http_response_code(200);
echo 'OK';
exit;

// ==================== CALLBACK QUERY HANDLER ====================

function handleCallbackQuery($callbackQuery) {
    $callbackId = $callbackQuery['id'] ?? null;
    $chatId = $callbackQuery['message']['chat']['id'] ?? null;
    $userId = $callbackQuery['from']['id'] ?? null;
    $data = $callbackQuery['data'] ?? '';
    
    // Answer the callback to remove loading state
    answerCallbackQuery($callbackId);
    
    // Handle "Return to Menu" button - reset state and show menu
    if ($data === 'return_to_menu' || $data === 'cancel') {
        setUserDepositState($userId, null);
        sendMessage($chatId, "✅ Returned to main menu. How can I help you?", true, $userId);
        return;
    }
    
    // Handle giveaway entry tracking - no registration check needed
    if (strpos($data, 'giveaway_') === 0) {
        handleGiveawayEntry($chatId, $userId, $data);
        return;
    }
    
    // Handle admin callbacks (payment method selection) - no registration check needed
    if (strpos($data, 'deposit_method_') === 0) {
        handleAdminDepositMethodSelection($chatId, $userId, $data);
        return;
    }
    
    // Handle user deposit payment method selection - no registration check needed
    if (strpos($data, 'user_deposit_') === 0) {
        handleUserDepositPaymentSelection_v2($chatId, $userId, $data);
        return;
    }
    
    // Handle ETB payment method selection - no registration check needed
    if (strpos($data, 'etb_payment_') === 0) {
        handleETBPaymentMethodSelection($chatId, $userId, $data);
        return;
    }
    
    // For user callbacks, check registration status
    $userData = getUserRegistrationData($userId);
    if (!$userData) {
        sendMessage($chatId, "❌ Please register first using /register", false);
        return;
    }
    
    $kycStatus = $userData['kyc_status'] ?? 'pending';
    
    // Route based on callback data
    if ($data === 'create_card') {
        if ($kycStatus !== 'approved') {
            sendMessage($chatId, "⏳ Your KYC is still under review. Please wait for approval.", false);
            return;
        }
        handleCreateCardCallback($chatId, $userId);
    } elseif ($data === 'deposit_wallet') {
        if ($kycStatus !== 'approved') {
            sendMessage($chatId, "⏳ Your KYC is still under review. Please wait for approval before requesting deposits.", false);
            return;
        }
        requestAdminDeposit($chatId, $userId);
    }
}

function handleGiveawayEntry($chatId, $userId, $callbackData) {
    // Parse callback data: giveaway_{broadcast_id}_{encoded_data}
    $parts = explode('_', $callbackData, 3);
    if (count($parts) < 2) {
        sendMessage($chatId, "❌ Invalid giveaway data.", false);
        return;
    }
    
    $broadcastId = (int)$parts[1];
    $buttonData = isset($parts[2]) ? base64_decode($parts[2]) : '';
    
    // Get database connection
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Database connection failed. Please try again later.", false);
        return;
    }
    
    try {
        // Check if broadcast exists and is a giveaway
        $stmt = $db->prepare("SELECT id, title, is_giveaway, giveaway_ends_at FROM broadcasts WHERE id = ? AND is_giveaway = true");
        $stmt->execute([$broadcastId]);
        $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$broadcast) {
            sendMessage($chatId, "❌ This giveaway is no longer available.", false);
            return;
        }
        
        // Check if giveaway has ended
        if ($broadcast['giveaway_ends_at'] && strtotime($broadcast['giveaway_ends_at']) < time()) {
            sendMessage($chatId, "❌ This giveaway has ended. Thank you for your interest!", false);
            return;
        }
        
        // Get or create user in users table
        $stmt = $db->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $dbUserId = $user['id'] ?? null;
        
        // Check if user already entered
        if ($dbUserId) {
            $stmt = $db->prepare("SELECT id FROM giveaway_entries WHERE broadcast_id = ? AND telegram_user_id = ?");
            $stmt->execute([$broadcastId, $userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                sendMessage($chatId, "✅ You're already entered in this giveaway! Good luck! 🍀", false);
                return;
            }
        }
        
        // Insert giveaway entry
        $stmt = $db->prepare("INSERT INTO giveaway_entries (broadcast_id, user_id, telegram_user_id, button_data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$broadcastId, $dbUserId, $userId, $buttonData]);
        
        // Get total entries
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM giveaway_entries WHERE broadcast_id = ?");
        $stmt->execute([$broadcastId]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Send confirmation message
        $endDate = $broadcast['giveaway_ends_at'] ? date('F j, Y', strtotime($broadcast['giveaway_ends_at'])) : 'soon';
        $message = "🎉 <b>You're in!</b>\n\n";
        $message .= "✅ Successfully entered the <b>" . htmlspecialchars($broadcast['title']) . "</b> giveaway!\n\n";
        $message .= "📊 Total entries: <b>{$count}</b>\n";
        $message .= "📅 Winners announced: <b>{$endDate}</b>\n\n";
        $message .= "🍀 Good luck!";
        
        sendMessage($chatId, $message, false);
        
    } catch (Exception $e) {
        error_log("Giveaway entry error: " . $e->getMessage());
        sendMessage($chatId, "❌ Failed to enter giveaway. Please try again later.", false);
    }
}

function handleAdminDepositMethodSelection($chatId, $userId, $callbackData) {
    // Parse callback data: deposit_method_{method}_{targetUserId}
    $parts = explode('_', $callbackData);
    if (count($parts) < 4) {
        return;
    }
    
    // Get method and target user ID
    $method = $parts[2]; // cbe, birr, telebirr, other
    $targetUserId = $parts[3];
    
    // Fix the cbe_birr parsing first
    if ($method === 'cbe' && isset($parts[3]) && $parts[3] === 'birr') {
        $method = 'cbe_birr';
        $targetUserId = $parts[4] ?? null;
        if (!$targetUserId) {
            sendMessage($chatId, "❌ Invalid callback data.", false);
            return;
        }
    }
    
    // Map method codes to display names
    $methodNames = [
        'cbe' => 'CBE (Commercial Bank of Ethiopia)',
        'cbe_birr' => 'CBE Birr',
        'boa' => 'BOA (Bank of Abyssinia)',
        'telebirr' => 'TeleBirr',
        'mpesa' => 'M-Pesa',
        'other' => 'Other Payment Method'
    ];
    
    $methodName = $methodNames[$method] ?? ucfirst(str_replace('_', ' ', $method));
    
    // Get user info
    $userData = getUserRegistrationData($targetUserId);
    if (!$userData) {
        sendMessage($chatId, "❌ User not found.", false);
        return;
    }
    
    $fullName = ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');
    
    // Send confirmation to admin
    $adminMsg = "✅ <b>Payment Method Selected</b>\n\n";
    $adminMsg .= "💳 <b>Method:</b> {$methodName}\n";
    $adminMsg .= "👤 <b>User:</b> {$fullName}\n";
    $adminMsg .= "🆔 <b>Telegram ID:</b> <code>{$targetUserId}</code>\n\n";
    $adminMsg .= "📝 <b>Next:</b> Please provide payment instructions to the user.";
    sendMessage($chatId, $adminMsg, false);
    
    // Send payment instructions to user
    $userMsg = "💰 <b>Deposit Instructions</b>\n\n";
    $userMsg .= "💳 <b>Payment Method:</b> {$methodName}\n\n";
    $userMsg .= "📋 <b>Instructions:</b>\n";
    
    // Customize instructions based on payment method
    switch ($method) {
        case 'cbe':
            $userMsg .= "Please deposit to CBE account:\n\n";
            $userMsg .= "🏦 <b>Bank:</b> Commercial Bank of Ethiopia\n";
            $userMsg .= "👤 <b>Account Name:</b> [Admin will provide]\n";
            $userMsg .= "🔢 <b>Account Number:</b> [Admin will provide]\n";
            break;
        case 'cbe_birr':
        case 'birr':
            $userMsg .= "Please deposit using CBE Birr:\n\n";
            $userMsg .= "📱 <b>Service:</b> CBE Birr\n";
            $userMsg .= "📞 <b>Phone Number:</b> [Admin will provide]\n";
            break;
        case 'boa':
            $userMsg .= "Please deposit to BOA account:\n\n";
            $userMsg .= "🏦 <b>Bank:</b> Bank of Abyssinia\n";
            $userMsg .= "👤 <b>Account Name:</b> [Admin will provide]\n";
            $userMsg .= "🔢 <b>Account Number:</b> [Admin will provide]\n";
            break;
        case 'telebirr':
            $userMsg .= "Please deposit using TeleBirr:\n\n";
            $userMsg .= "📱 <b>Service:</b> TeleBirr\n";
            $userMsg .= "📞 <b>Phone Number:</b> [Admin will provide]\n";
            break;
        case 'mpesa':
            $userMsg .= "Please deposit using M-Pesa:\n\n";
            $userMsg .= "📱 <b>Service:</b> M-Pesa\n";
            $userMsg .= "📞 <b>Phone Number:</b> [Admin will provide]\n";
            break;
        default:
            $userMsg .= "[Admin will provide payment details]\n";
            break;
    }
    
    $userMsg .= "\n⏳ <b>Admin will send you the payment details shortly.</b>";
    sendMessage($targetUserId, $userMsg, false);
}

function handleUserDepositPaymentSelection($chatId, $userId, $callbackData) {
    // Parse callback data: user_deposit_{method}_{usdAmount}_{etbAmountBeforeFee}
    $parts = explode('_', $callbackData);
    if (count($parts) < 5) {
        sendMessage($chatId, "❌ Invalid payment selection.", false);
        return;
    }
    
    $methodCode = $parts[2]; // telebirr, cbebirr, mpesa, bank
    $usdAmount = floatval($parts[3]);
    $etbAmountBeforeFee = floatval($parts[4]);
    
    // Get deposit fee from settings
    $db = getDBConnection();
    $depositFee = 500; // Default 500 ETB
    if ($db) {
        try {
            $stmt = $db->query("SELECT value FROM settings WHERE key = 'deposit_fee'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $feeData = json_decode($row['value'], true);
                $depositFee = $feeData['flat'] ?? 500;
            }
        } catch (Exception $e) {
            error_log("Error fetching deposit fee: " . $e->getMessage());
        }
    }
    
    // Calculate total amount including fee
    $totalEtbAmount = $etbAmountBeforeFee + $depositFee;
    
    // Get payment accounts from database
    $paymentAccounts = [];
    if ($db) {
        try {
            $stmt = $db->query("SELECT value FROM settings WHERE key = 'deposit_accounts'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $accounts = json_decode($row['value'], true);
                foreach ($accounts as $account) {
                    $key = strtolower(str_replace([' ', '-'], '', $account['method']));
                    $paymentAccounts[$key] = $account;
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching payment accounts: " . $e->getMessage());
        }
    }
    
    // Map method codes to payment account keys
    $methodMap = [
        'telebirr' => 'telebirr',
        'cbebirr' => 'cbebirr',
        'mpesa' => 'm-pesa',
        'bank' => 'bank'
    ];
    
    $accountKey = $methodMap[$methodCode] ?? $methodCode;
    $account = $paymentAccounts[$accountKey] ?? null;
    
    if (!$account) {
        sendMessage($chatId, "❌ Payment method not configured. Please contact support.", false);
        return;
    }
    
    // Map method icons
    $icons = [
        'telebirr' => '📱',
        'cbebirr' => '💵',
        'mpesa' => '💳',
        'bank' => '🏦'
    ];
    
    $icon = $icons[$methodCode] ?? '💳';
    $methodName = $account['method'];
    $accountName = $account['account_name'];
    $accountNumber = $account['account_number'];
    
    // Send payment instructions to user (fee already included in total)
    $userMsg = "💰 <b>Payment Instructions</b>\n\n";
    $userMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $userMsg .= "💵 <b>Amount (USD):</b> $" . number_format($usdAmount, 2) . "\n";
    $userMsg .= "💰 <b>Total to Pay:</b> <b>" . number_format($totalEtbAmount, 2) . " ETB</b>\n";
    $userMsg .= "{$icon} <b>Payment Method:</b> {$methodName}\n\n";
    $userMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $userMsg .= "Please send <b>" . number_format($totalEtbAmount, 2) . " ETB</b> to:\n\n";
    $userMsg .= "📞 <b>Phone:</b> {$accountNumber}\n";
    $userMsg .= "👤 <b>Name:</b> {$accountName}\n\n";
    $userMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $userMsg .= "📸 <b>After payment, please send:</b>\n";
    $userMsg .= "1️⃣ Screenshot of payment confirmation\n";
    $userMsg .= "2️⃣ Transaction ID/Reference number\n\n";
    $userMsg .= "📝 Send screenshot now, then enter transaction ID.";
    
    sendMessage($chatId, $userMsg, false);
    
    // Set user state to wait for screenshot
    setUserDepositState($userId, 'awaiting_screenshot');
    
    // Store deposit info with payment method
    storeDepositInfo($userId, $usdAmount, $etbAmountBeforeFee / $usdAmount, $totalEtbAmount, $methodName);
    
    // Get user info
    $userData = getUserRegistrationData($userId);
    $fullName = ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');
    
    // Notify admin about the deposit request
    if (!empty(ADMIN_CHAT_ID) && ADMIN_CHAT_ID !== 'your_telegram_admin_chat_id_for_alerts') {
        $adminMsg = "💰 <b>New Deposit Request</b>\n\n";
        $adminMsg .= "👤 <b>User:</b> {$fullName}\n";
        $adminMsg .= "🆔 <b>Telegram ID:</b> <code>{$userId}</code>\n\n";
        $adminMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $adminMsg .= "💵 <b>Amount (USD):</b> $" . number_format($usdAmount, 2) . "\n";
        $adminMsg .= "💰 <b>Total (ETB):</b> " . number_format($totalEtbAmount, 2) . " ETB\n";
        $adminMsg .= "{$icon} <b>Payment Method:</b> {$methodName}\n";
        $adminMsg .= "📞 <b>Account:</b> {$accountNumber}\n\n";
        $adminMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $adminMsg .= "⏳ <b>Status:</b> Waiting for user payment proof";
        
        sendMessage(ADMIN_CHAT_ID, $adminMsg, false);
    }
}

function answerCallbackQuery($callbackId, $text = '') {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/answerCallbackQuery';
    $payload = ['callback_query_id' => $callbackId];
    if ($text) {
        $payload['text'] = $text;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function handleCreateCardCallback($chatId, $userId) {
    sendTypingAction($chatId);
    
    $msg = "💳 <b>Create Virtual Card</b>\n\n";
    $msg .= "To create a card, you need to deposit funds to your wallet first.\n\n";
    $msg .= "👇 Click the button below to request a deposit:";
    
    // Send message with deposit button
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $payload = [
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => '💰 Deposit to Wallet', 'callback_data' => 'deposit_wallet']
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function requestAdminDeposit($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Set user state to awaiting deposit amount
    setUserDepositState($userId, 'awaiting_amount');
    
    // Ask user for dollar amount
    $msg = "💵 <b>Deposit to Wallet</b>\n\n";
    $msg .= "How much would you like to deposit?\n\n";
    $msg .= "💡 <b>Please enter the amount in USD</b>\n";
    $msg .= "Example: 10 or 50.5\n\n";
    $msg .= "📌 Minimum: $5 USD";
    
    sendMessage($chatId, $msg, false);
}

function processDepositAmount($chatId, $userId, $amount) {
    // Clean and validate amount - remove spaces, commas, and $ signs
    $cleanedAmount = str_replace([',', ' ', '$'], '', trim($amount));
    
    // Validate it's a valid number
    if (!is_numeric($cleanedAmount)) {
        $msg = "❌ <b>Invalid Format</b>\n\n";
        $msg .= "Please enter a numeric value (e.g., 100 or 1,000).\n\n";
        $msg .= "Try again:";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    $usdAmount = floatval($cleanedAmount);
    
    if ($usdAmount < 5) {
        $msg = "❌ <b>Invalid Amount</b>\n\n";
        $msg .= "Minimum deposit is $5 USD.\n\n";
        $msg .= "Please enter a valid amount:";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    if ($usdAmount > 10000) {
        $msg = "❌ <b>Amount Too Large</b>\n\n";
        $msg .= "Maximum deposit is $10,000 USD.\n\n";
        $msg .= "Please enter a valid amount:";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    // Fetch exchange rate from settings
    $exchangeRate = getExchangeRate();
    
    // Validate exchange rate is valid
    if ($exchangeRate <= 0) {
        error_log("Invalid exchange rate: $exchangeRate");
        $msg = "❌ <b>Configuration Error</b>\n\n";
        $msg .= "Exchange rate is not configured properly.\n\n";
        $msg .= "Please try again later or contact support.";
        sendMessage($chatId, $msg, false);
        
        // Alert admin about configuration issue
        if (!empty(ADMIN_CHAT_ID) && ADMIN_CHAT_ID !== 'your_telegram_admin_chat_id_for_alerts') {
            $adminMsg = "⚠️ <b>Configuration Error</b>\n\n";
            $adminMsg .= "Exchange rate is invalid or zero: $exchangeRate\n\n";
            $adminMsg .= "Please update the exchange rate in admin settings.";
            sendMessage(ADMIN_CHAT_ID, $adminMsg, false);
        }
        
        // Reset user state
        setUserDepositState($userId, null);
        return;
    }
    
    // Calculate Ethiopian Birr amount
    $etbAmount = $usdAmount * $exchangeRate;
    
    // Get deposit fee from settings
    $depositFee = 500; // Default 500 ETB
    $db = getDBConnection();
    if ($db) {
        try {
            $stmt = $db->query("SELECT value FROM settings WHERE key = 'deposit_fee'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $feeData = json_decode($row['value'], true);
                $depositFee = $feeData['flat'] ?? 500;
            }
        } catch (Exception $e) {
            error_log("Error fetching deposit fee: " . $e->getMessage());
        }
    }
    
    // Calculate total amount including fee
    $totalEtbAmount = $etbAmount + $depositFee;
    
    // Store deposit info temporarily
    storeDepositInfo($userId, $usdAmount, $exchangeRate, $etbAmount);
    
    // Clear deposit state
    setUserDepositState($userId, null);
    
    // Show deposit summary to user with payment method options (WITH total including fee)
    $userMsg = "💰 <b>Deposit Summary</b>\n\n";
    $userMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $userMsg .= "💵 <b>USD Amount:</b> $" . number_format($usdAmount, 2) . "\n";
    $userMsg .= "💸 <b>Amount to Pay:</b> " . number_format($totalEtbAmount, 2) . " ETB\n\n";
    $userMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $userMsg .= "👇 <b>Select your payment method:</b>";
    
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $payload = [
        'chat_id' => $chatId,
        'text' => $userMsg,
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => '📱 TeleBirr', 'callback_data' => "user_deposit_telebirr_{$usdAmount}_{$etbAmount}"],
                    ['text' => '💵 CBE Birr', 'callback_data' => "user_deposit_cbebirr_{$usdAmount}_{$etbAmount}"]
                ],
                [
                    ['text' => '💳 M-Pesa', 'callback_data' => "user_deposit_mpesa_{$usdAmount}_{$etbAmount}"],
                    ['text' => '🏦 Bank Transfer', 'callback_data' => "user_deposit_bank_{$usdAmount}_{$etbAmount}"]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    
    // Admin will be notified after user selects payment method
}

function handleDepositScreenshot($chatId, $userId, $fileId) {
    // Save screenshot file ID in temp data
    $db = getDBConnection();
    if ($db) {
        try {
            // Get existing deposit info
            $stmt = $db->prepare("SELECT temp_data FROM user_registrations WHERE telegram_user_id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $depositData = $row && $row['temp_data'] ? json_decode($row['temp_data'], true) : [];
            $depositData['screenshot_file_id'] = $fileId;
            
            // Update with screenshot
            $stmt = $db->prepare("UPDATE user_registrations SET temp_data = ? WHERE telegram_user_id = ?");
            $stmt->execute([json_encode($depositData), $userId]);
        } catch (Exception $e) {
            error_log("Error saving screenshot: " . $e->getMessage());
        }
    }
    
    // Change state to await transaction ID
    setUserDepositState($userId, 'awaiting_transaction_id');
    
    // Ask for transaction ID
    $msg = "✅ <b>Screenshot received!</b>\n\n";
    $msg .= "📝 Now please enter your <b>Transaction ID</b> or <b>Reference Number</b>.\n\n";
    $msg .= "💡 <i>Example: TXN123456789</i>";
    
    sendMessage($chatId, $msg, false);
}

function handleDepositTransactionId($chatId, $userId, $transactionId) {
    // Get deposit info from temp data
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Database error. Please try again later.", false);
        return;
    }
    
    try {
        // Get deposit data
        $stmt = $db->prepare("SELECT temp_data FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || !$row['temp_data']) {
            sendMessage($chatId, "❌ Deposit session expired. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        $depositData = json_decode($row['temp_data'], true);
        $depositData['transaction_id'] = trim($transactionId);
        
        // Clear deposit state
        setUserDepositState($userId, null);
        
        // Send confirmation to user
        $msg = "✅ <b>Deposit Proof Submitted!</b>\n\n";
        $msg .= "💰 <b>Amount:</b> $" . number_format($depositData['usd_amount'], 2) . " USD\n";
        $msg .= "💸 <b>Total Paid:</b> " . number_format($depositData['etb_amount'], 2) . " ETB\n";
        $msg .= "📱 <b>Method:</b> " . ($depositData['payment_method'] ?? 'N/A') . "\n";
        $msg .= "🔖 <b>Transaction ID:</b> <code>" . htmlspecialchars($transactionId) . "</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⏳ <b>Your deposit is being verified.</b>\n\n";
        $msg .= "You'll be notified once the admin approves your deposit.";
        
        sendMessage($chatId, $msg, true, $userId);
        
        // Get user info
        $userData = getUserRegistrationData($userId);
        $fullName = ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');
        
        // Send full details to admin
        if (!empty(ADMIN_CHAT_ID) && ADMIN_CHAT_ID !== 'your_telegram_admin_chat_id_for_alerts') {
            $adminMsg = "💰 <b>Deposit Proof Received!</b>\n\n";
            $adminMsg .= "👤 <b>User:</b> {$fullName}\n";
            $adminMsg .= "🆔 <b>Telegram ID:</b> <code>{$userId}</code>\n\n";
            $adminMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
            $adminMsg .= "💵 <b>Amount (USD):</b> $" . number_format($depositData['usd_amount'], 2) . "\n";
            $adminMsg .= "💸 <b>Total (ETB):</b> " . number_format($depositData['etb_amount'], 2) . " ETB\n";
            $adminMsg .= "📱 <b>Method:</b> " . ($depositData['payment_method'] ?? 'N/A') . "\n";
            $adminMsg .= "🔖 <b>Transaction ID:</b> <code>" . htmlspecialchars($transactionId) . "</code>\n\n";
            $adminMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
            $adminMsg .= "📸 <b>Screenshot:</b> See photo below\n\n";
            $adminMsg .= "👇 <b>Please verify and approve</b>";
            
            sendMessage(ADMIN_CHAT_ID, $adminMsg, false);
            
            // Forward screenshot to admin
            if (isset($depositData['screenshot_file_id'])) {
                $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendPhoto';
                $payload = [
                    'chat_id' => ADMIN_CHAT_ID,
                    'photo' => $depositData['screenshot_file_id'],
                    'caption' => "📸 Payment proof from {$fullName}\n🔖 TXN: " . htmlspecialchars($transactionId)
                ];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Clear temp data
        $stmt = $db->prepare("UPDATE user_registrations SET temp_data = NULL WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        
    } catch (Exception $e) {
        error_log("Error processing transaction ID: " . $e->getMessage());
        sendMessage($chatId, "❌ Error processing your submission. Please contact support.", false);
    }
}

// ==================== COMMAND HANDLERS ====================

function checkKYCStatus($chatId, $userId) {
    $userData = getUserRegistrationData($userId);
    
    if (!$userData || !$userData['is_registered']) {
        sendMessage($chatId, "❌ Please register first using /register", false);
        return false;
    }
    
    $kycStatus = $userData['kyc_status'] ?? 'pending';
    $email = $userData['email'] ?? '';
    
    // Always fetch latest KYC status from StroWallet if user has email
    if ($email) {
        error_log("checkKYCStatus: Fetching latest status from StroWallet for user $userId");
        
        $customerCheck = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY . '&customerEmail=' . urlencode($email), 'GET', [], true);
        
        if (!isset($customerCheck['error']) && isset($customerCheck['data'])) {
            $customerData = $customerCheck['data'];
            $fetchedKycStatus = $customerData['status'] ?? $customerData['kyc_status'] ?? $customerData['kycStatus'] ?? 'pending';
            
            // Map StroWallet KYC status to our format
            // StroWallet uses: "high kyc", "low kyc", "medium kyc" as status values
            $statusLower = strtolower(trim($fetchedKycStatus));
            if (strpos($statusLower, 'high') !== false || strpos($statusLower, 'verified') !== false || strpos($statusLower, 'approved') !== false) {
                $kycStatus = 'approved';
            } elseif (strpos($statusLower, 'rejected') !== false || strpos($statusLower, 'failed') !== false || strpos($statusLower, 'decline') !== false) {
                $kycStatus = 'rejected';
            } else {
                $kycStatus = 'pending';
            }
            
            // Update local database with latest status
            $db = getDBConnection();
            if ($db) {
                try {
                    $stmt = $db->prepare("UPDATE user_registrations SET kyc_status = ?, updated_at = CURRENT_TIMESTAMP WHERE telegram_user_id = ?");
                    $stmt->execute([$kycStatus, $userId]);
                    error_log("checkKYCStatus: Updated user $userId status to $kycStatus (from StroWallet: $fetchedKycStatus)");
                } catch (Exception $e) {
                    error_log("checkKYCStatus: Failed to update status: " . $e->getMessage());
                }
            }
        }
    }
    
    if ($kycStatus === 'rejected') {
        sendMessage($chatId, "❌ <b>KYC Verification Failed</b>\n\nYour KYC was rejected. Please contact support.", false);
        return false;
    }
    
    if ($kycStatus !== 'approved') {
        $msg = "⏳ <b>KYC Under Review</b>\n\n";
        $msg .= "Your registration is being verified.\n\n";
        $msg .= "🔔 You'll be notified once approved.\n\n";
        $msg .= "⏱️ <i>This usually takes a few hours.</i>";
        sendMessage($chatId, $msg, false);
        return false;
    }
    
    return true;
}

function handleStart($chatId, $userId = null) {
    // Check if user is registered
    $userData = getUserRegistrationData($userId);
    
    if (!$userData || !$userData['is_registered']) {
        // New/unregistered user - prompt for registration
        $welcomeMsg = "👋 <b>Welcome to Crypto Card Bot!</b>\n\n";
        $welcomeMsg .= "🎉 Manage your virtual crypto cards with ease.\n\n";
        $welcomeMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $welcomeMsg .= "⚠️ <b>Registration Required</b>\n\n";
        $welcomeMsg .= "To create virtual cards and use this bot, you need to complete a one-time registration.\n\n";
        $welcomeMsg .= "📋 <b>What we'll collect:</b>\n";
        $welcomeMsg .= "• Personal information (name, DOB, phone)\n";
        $welcomeMsg .= "• Address details\n";
        $welcomeMsg .= "• ID verification (KYC documents)\n\n";
        $welcomeMsg .= "⏱️ <b>Time:</b> About 5 minutes\n";
        $welcomeMsg .= "🔒 <b>Security:</b> All data encrypted & secure\n\n";
        $welcomeMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $welcomeMsg .= "🚀 <b>Ready to get started?</b>\n\n";
        $welcomeMsg .= "Send /register to begin your registration!";
        
        sendMessage($chatId, $welcomeMsg, false);
    } else {
        // Registered user - check KYC status
        $kycStatus = $userData['kyc_status'] ?? 'pending';
        
        if ($kycStatus === 'approved') {
            // KYC approved - show full menu
            $welcomeMsg = "🎉 <b>Welcome Back!</b>\n\n";
            $welcomeMsg .= "🚀 Manage your virtual cards and crypto wallet with ease.\n\n";
            $welcomeMsg .= "📱 Use the menu below to get started:";
            sendMessage($chatId, $welcomeMsg, true);
        } elseif ($kycStatus === 'rejected') {
            // KYC rejected
            $welcomeMsg = "❌ <b>KYC Verification Failed</b>\n\n";
            $welcomeMsg .= "Your KYC verification was rejected.\n\n";
            $welcomeMsg .= "Please contact support for assistance.";
            sendMessage($chatId, $welcomeMsg, false);
        } else {
            // KYC pending
            $welcomeMsg = "⏳ <b>KYC Under Review</b>\n\n";
            $welcomeMsg .= "Your registration is being verified by StroWallet.\n\n";
            $welcomeMsg .= "🔔 You will be notified once approved.\n\n";
            $welcomeMsg .= "⏱️ <i>This usually takes a few hours.</i>\n\n";
            $welcomeMsg .= "━━━━━━━━━━━━━━━━━━\n\n";
            $welcomeMsg .= "🚫 Menu buttons will be available after approval.";
            sendMessage($chatId, $welcomeMsg, false);
        }
    }
}

function handleRegisterStart($chatId, $userId) {
    // Check if already registered
    $userData = getUserRegistrationData($userId);
    if ($userData && $userData['is_registered']) {
        $kycStatus = $userData['kyc_status'] ?? 'pending';
        $msg = "✅ <b>Already Registered!</b>\n\n";
        
        if ($kycStatus === 'approved') {
            $msg .= "You're all set! You can now create cards using ➕ <b>Create Card</b>";
            sendMessage($chatId, $msg, true);
        } elseif ($kycStatus === 'rejected') {
            $msg .= "⚠️ Your KYC verification was rejected. Please contact support.";
            sendMessage($chatId, $msg, false);
        } else {
            $msg .= "⏳ Your KYC is under review. You'll be notified once approved.";
            sendMessage($chatId, $msg, false);
        }
        return;
    }
    
    // Check if in progress
    if ($userData && $userData['registration_state'] !== 'idle' && $userData['registration_state'] !== 'completed') {
        $msg = "⏳ <b>Registration In Progress</b>\n\n";
        $msg .= "You have an incomplete registration.\n\n";
        $msg .= "Choose an option:\n";
        $msg .= "• Send /continue to resume\n";
        $msg .= "• Send /cancel to start over";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    // Start new registration
    initializeUserRegistration($userId, $chatId);
    
    $msg = "📝 <b>Let's Register You!</b>\n\n";
    $msg .= "I'll guide you through collecting your information for KYC verification.\n\n";
    $msg .= "This is required to create virtual cards in StroWallet.\n\n";
    $msg .= "📌 <b>What I'll ask for:</b>\n";
    $msg .= "• Personal info (name, date of birth, phone)\n";
    $msg .= "• Address details\n";
    $msg .= "• ID verification (type, number, photos)\n\n";
    $msg .= "⏱️ <b>Time:</b> About 5 minutes\n";
    $msg .= "❌ <b>Cancel:</b> Send /cancel anytime\n\n";
    $msg .= "Ready? Let's start!\n\n";
    $msg .= "👤 <b>What's your first name?</b>";
    
    updateUserRegistrationState($userId, 'awaiting_first_name');
    sendMessage($chatId, $msg, false);
}

function handleCreateCard($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    // Get user from database to check wallet
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendMessage($chatId, "❌ User not found. Please register first using /register", false);
        return;
    }
    
    // Check wallet balance
    $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    $walletBalance = $wallet ? (float)$wallet['balance_usd'] : 0.00;
    
    // Minimum card creation amount (e.g., $5)
    $minimumAmount = 5.00;
    
    if ($walletBalance < $minimumAmount) {
        // User needs to deposit first
        handleCreateCardCallback($chatId, $userId);
        return;
    }
    
    // User has sufficient balance - proceed with card creation
    // Prepare card holder's name (customer's name)
    $nameOnCard = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (empty($nameOnCard)) {
        $nameOnCard = 'Card Holder';
    }
    
    // Calculate StroWallet card creation fee: $1.40 + 1% of amount
    // Customer pays only this fee, card is funded from Addisu's balance
    $cardCreationFee = 1.40 + ($walletBalance * 0.01);
    $cardCreationFee = round($cardCreationFee, 2);
    
    // Check if customer has enough for the fee
    if ($walletBalance < $cardCreationFee) {
        $msg = "❌ <b>Insufficient Balance</b>\n\n";
        $msg .= "You need at least $" . number_format($cardCreationFee, 2) . " to cover the card creation fee.\n\n";
        $msg .= "Your balance: $" . number_format($walletBalance, 2) . "\n\n";
        $msg .= "Please deposit more funds.";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    // Calculate card amount (customer balance minus fee)
    $cardAmount = $walletBalance - $cardCreationFee;
    $cardAmount = round($cardAmount, 2);
    
    // Send "processing" message to customer
    $processingMsg = "⏳ <b>Creating Your Card...</b>\n\n";
    $processingMsg .= "💳 Processing virtual Visa card\n";
    $processingMsg .= "💰 Card Amount: $" . number_format($cardAmount, 2) . "\n";
    $processingMsg .= "💵 Creation Fee: $" . number_format($cardCreationFee, 2) . "\n\n";
    $processingMsg .= "Please wait a moment...";
    sendMessage($chatId, $processingMsg, false);
    
    // Prepare API parameters - using Addisu's StroWallet account
    $cardParams = [
        'name_on_card' => $nameOnCard,
        'card_type' => 'visa',
        'public_key' => STROW_PUBLIC_KEY,
        'amount' => (string)$cardAmount,  // Card funded amount
        'customerEmail' => STROWALLET_EMAIL  // Addisu's account creates the card
    ];
    
    // Add sandbox mode if enabled
    if (USE_SANDBOX_MODE) {
        $cardParams['mode'] = 'sandbox';
    }
    
    // Create card using Addisu's StroWallet account (real card creation)
    $result = callStroWalletAPI('/bitvcard/create-card', 'POST', $cardParams, true);
    
    if (isset($result['error'])) {
        sendErrorMessage($chatId, $result['error'], $result['request_id'] ?? null);
        return;
    }
    
    // Extract card details from StroWallet response
    $cardId = $result['response']['card_id'] ?? $result['card_id'] ?? null;
    $cardStatus = $result['response']['card_status'] ?? $result['status'] ?? 'created';
    $cardBrand = $result['response']['card_brand'] ?? $result['card_brand'] ?? 'Visa';
    $cardNumber = $result['response']['card_number'] ?? $result['card_number'] ?? null;
    
    // Get wallet record
    $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $walletRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    $balanceBefore = $walletRecord ? (float)$walletRecord['balance_usd'] : 0.00;
    $balanceAfter = $balanceBefore - $walletBalance;  // Deduct full amount (fee + card amount)
    
    // Deduct full balance from customer's local wallet
    $stmt = $db->prepare("UPDATE wallets SET balance_usd = balance_usd - ? WHERE user_id = ?");
    $stmt->execute([$walletBalance, $user['id']]);
    
    // Record wallet transaction with correct column name
    $stmt = $db->prepare("INSERT INTO wallet_transactions (user_id, amount_usd, transaction_type, description, balance_before_usd, balance_after_usd, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user['id'], -$walletBalance, 'card_creation', "Card created: {$nameOnCard} (Fee: $" . number_format($cardCreationFee, 2) . ")", $balanceBefore, $balanceAfter]);
    
    // Store card in database linked to customer
    if ($cardId) {
        $stmt = $db->prepare("INSERT INTO cards (user_id, strow_card_id, card_type, card_brand, amount, status, name_on_card, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user['id'], $cardId, 'virtual', $cardBrand, $cardAmount, $cardStatus, $nameOnCard]);
    }
    
    // Send success message to customer
    $msg = "✅ <b>Card Created Successfully!</b>\n\n";
    $msg .= "💳 Your virtual {$cardBrand} card is ready!\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 <b>Name:</b> {$nameOnCard}\n";
    $msg .= "💰 <b>Card Amount:</b> $" . number_format($cardAmount, 2) . "\n";
    $msg .= "💵 <b>Creation Fee:</b> $" . number_format($cardCreationFee, 2) . "\n";
    if ($cardId) {
        $msg .= "🆔 <b>Card ID:</b> <code>{$cardId}</code>\n";
    }
    $msg .= "📊 <b>Status:</b> " . ucfirst($cardStatus) . "\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "💵 <b>Your new wallet balance:</b> $" . number_format($balanceAfter, 2) . "\n\n";
    $msg .= "Use /cards to view your card details.";
    
    sendMessage($chatId, $msg, true, $userId);
}

function handleMyCards($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    // Get user from database
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendMessage($chatId, "❌ User not found. Please register first using /register", false);
        return;
    }
    
    // Fetch customer's cards from local database
    $stmt = $db->prepare("SELECT * FROM cards WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($cards)) {
        $msg = "💳 <b>Your Virtual Cards</b>\n\n";
        
        foreach ($cards as $index => $card) {
            $brand = $card['card_brand'] ?? 'Visa';
            $status = $card['status'] ?? 'active';
            $statusEmoji = getStatusEmoji($status);
            $cardId = $card['strow_card_id'] ?? 'N/A';
            $amount = $card['amount'] ?? 0;
            $nameOnCard = $card['name_on_card'] ?? 'Card Holder';
            
            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🔸 <b>Card #" . ($index + 1) . "</b>\n";
            $msg .= "💳 {$brand} Card\n";
            $msg .= "👤 {$nameOnCard}\n";
            $msg .= "💰 Funded: $" . number_format($amount, 2) . "\n";
            $msg .= "🆔 ID: <code>{$cardId}</code>\n";
            $msg .= "{$statusEmoji} " . ucfirst($status) . "\n";
        }
        
        $msg .= "\n━━━━━━━━━━━━━━━━━━";
        sendMessage($chatId, $msg, true, $userId);
    } else {
        $msg = "📪 <b>No Cards Found</b>\n\n";
        $msg .= "You don't have any virtual cards yet.\n\n";
        $msg .= "💡 Use <b>➕ Create Card</b> to get started!";
        sendMessage($chatId, $msg, true, $userId);
    }
}

function handleUserInfo($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    // Get user from database
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Service temporarily unavailable. Please try again later.", false);
        return;
    }
    
    try {
        // Fetch user data from database
        $stmt = $db->prepare("SELECT u.*, w.balance_usd, w.balance_etb 
                             FROM users u 
                             LEFT JOIN wallets w ON u.id = w.user_id 
                             WHERE u.telegram_id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            sendMessage($chatId, "❌ User not found. Please register first using /register", false);
            return;
        }
        
        // Get card count
        $stmt = $db->prepare("SELECT COUNT(*) as card_count FROM cards WHERE user_id = ?");
        $stmt->execute([$userData['id']]);
        $cardData = $stmt->fetch(PDO::FETCH_ASSOC);
        $cardCount = $cardData['card_count'] ?? 0;
        
        // Format user data
        $name = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')) ?: 'N/A';
        $phone = $userData['phone'] ?? 'N/A';
        $email = $userData['email'] ?? 'N/A';
        $kycStatus = ($userData['kyc_status'] ?? 'pending') === 'approved';
        $userIdValue = $userData['strow_customer_id'] ?? 'N/A';
        $balance = $userData['balance_usd'] ?? 0.00;
        $joinedDate = $userData['created_at'] ?? 'N/A';
        
        $kycEmoji = $kycStatus ? '✅' : '🔴';
        $kycText = $kycStatus ? 'Approved' : 'Pending';
        
        $msg = "👤 <b>Your Profile</b>\n\n";
        $msg .= "🧑 <b>Name:</b> {$name}\n";
        $msg .= "📧 <b>Email:</b> {$email}\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🆔 <b>KYC Status:</b> {$kycEmoji} {$kycText}\n";
        $msg .= "💳 <b>Total Cards:</b> {$cardCount}\n";
        $msg .= "💰 <b>Wallet Balance:</b> $" . number_format((float)$balance, 2) . "\n";
        $msg .= "📅 <b>Member Since:</b> " . formatDate($joinedDate) . "\n";
        
        if (!$kycStatus) {
            $msg .= "\n⚠️ <b>Note:</b> Complete KYC verification to unlock all features!";
        }
        
        sendMessage($chatId, $msg, true, $userId);
        
    } catch (Exception $e) {
        error_log("Error in handleUserInfo: " . $e->getMessage());
        sendMessage($chatId, "❌ Error loading profile. Please try again later.", false);
    }
}

function handleWallet($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    // Get user from database
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendMessage($chatId, "❌ User not found. Please register first using /register", false);
        return;
    }
    
    // Get wallet balance from local database
    $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $balanceUSD = $wallet ? (float)$wallet['balance_usd'] : 0.00;
    $balanceETB = $wallet ? (float)$wallet['balance_etb'] : 0.00;
    
    $msg = "💰 <b>Your Wallet</b>\n\n";
    $msg .= "💵 <b>USD:</b> $" . number_format($balanceUSD, 2) . "\n";
    $msg .= "💴 <b>ETB:</b> " . number_format($balanceETB, 2) . " Birr\n";
    $msg .= "\n━━━━━━━━━━━━━━━━━━\n";
    
    if ($balanceUSD > 0) {
        $msg .= "✅ You have funds available!\n";
        $msg .= "💳 Use /create_card to create a virtual card\n\n";
        sendMessage($chatId, $msg, true, $userId);
    } else {
        $msg .= "📥 To deposit to wallet, click button below\n";
        
        // Create inline button for deposit
        $inlineKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💵 Deposit to Wallet', 'callback_data' => 'deposit_etb']
                ]
            ]
        ];
        
        sendMessageWithKeyboard($chatId, $msg, $inlineKeyboard);
    }
}

function handleDepositTRC20($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    $result = callStroWalletAPI('/wallet/deposit-address', 'POST', ['currency' => 'USDT', 'network' => 'TRC20'], false);
    
    if (isset($result['error'])) {
        sendErrorMessage($chatId, $result['error'], $result['request_id'] ?? null);
        return;
    }
    
    $addressData = $result['data'] ?? $result;
    $address = $addressData['address'] ?? $addressData['deposit_address'] ?? null;
    
    if ($address) {
        $msg = "📥 <b>USDT TRC20 Deposit Address</b>\n\n";
        $msg .= "🔑 <code>{$address}</code>\n\n";
        $msg .= "⚠️ <b>Important:</b>\n";
        $msg .= "• Only send USDT on TRC20 network\n";
        $msg .= "• Minimum deposit may apply\n";
        $msg .= "• Funds will appear after network confirmation\n\n";
        $msg .= "💡 Tap the address to copy it!";
        
        sendMessage($chatId, $msg, true, $userId);
    } else {
        sendMessage($chatId, "❌ Unable to generate deposit address. Please try again later.", true);
    }
}

function handleInvite($chatId, $userId = null) {
    $msg = REFERRAL_TEXT;
    if (empty($msg)) {
        $msg = "💸 <b>Invite Friends & Earn Rewards!</b>\n\n";
        $msg .= "Share your referral link and earn points when your friends join!";
    }
    sendMessage($chatId, $msg, true, $userId);
}

function handleSupport($chatId, $userId = null) {
    $msg = "🧑‍💻 <b>Need Help?</b>\n\n";
    $msg .= "Our support team is here to assist you!\n\n";
    
    if (!empty(SUPPORT_URL)) {
        $msg .= "👉 <a href='" . SUPPORT_URL . "'>Contact Support</a>";
    } else {
        $msg .= "Please contact our support team for assistance.";
    }
    
    sendMessage($chatId, $msg, true, $userId);
}

function handleDepositETB($chatId, $userId = null) {
    sendTypingAction($chatId);
    
    // Get deposit accounts from database
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Service temporarily unavailable. Please try again later.", true, $userId);
        return;
    }
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'deposit_accounts'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['value'])) {
            $msg = "⚠️ <b>Deposit Methods Not Available</b>\n\n";
            $msg .= "No deposit methods are currently configured.\n\n";
            $msg .= "Please contact support for assistance.";
            sendMessage($chatId, $msg, true, $userId);
            return;
        }
        
        $accounts = json_decode($result['value'], true);
        
        if (empty($accounts)) {
            $msg = "⚠️ <b>Deposit Methods Not Available</b>\n\n";
            $msg .= "No deposit methods are currently configured.\n\n";
            $msg .= "Please contact support for assistance.";
            sendMessage($chatId, $msg, true, $userId);
            return;
        }
        
        // Build inline keyboard with payment methods
        $keyboard = [];
        foreach ($accounts as $account) {
            $method = $account['method'] ?? 'Unknown';
            $accountId = $account['id'] ?? '';
            
            if (!empty($accountId)) {
                $keyboard[] = [
                    ['text' => $method, 'callback_data' => 'etb_payment_' . $accountId]
                ];
            }
        }
        
        // Send message with payment method options
        $msg = "💵 <b>Deposit ETB</b>\n\n";
        $msg .= "Please select your preferred payment method:\n\n";
        $msg .= "👇 Click on a payment method below to see the account details.";
        
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => $keyboard
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
    } catch (Exception $e) {
        error_log("Error fetching deposit accounts: " . $e->getMessage());
        sendMessage($chatId, "❌ Error loading payment methods. Please try again later.", true, $userId);
    }
}

function handleETBPaymentMethodSelection($chatId, $userId, $callbackData) {
    sendTypingAction($chatId);
    
    // Extract account ID from callback data
    $accountId = str_replace('etb_payment_', '', $callbackData);
    
    // Get deposit accounts from database
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Service temporarily unavailable. Please try again later.", false);
        return;
    }
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'deposit_accounts'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['value'])) {
            sendMessage($chatId, "❌ Payment method not found.", false);
            return;
        }
        
        $accounts = json_decode($result['value'], true);
        
        // Find the selected account
        $selectedAccount = null;
        foreach ($accounts as $account) {
            if (($account['id'] ?? '') === $accountId) {
                $selectedAccount = $account;
                break;
            }
        }
        
        if (!$selectedAccount) {
            sendMessage($chatId, "❌ Payment method not found.", false);
            return;
        }
        
        // Display payment method details
        $method = $selectedAccount['method'] ?? 'Unknown';
        $accountName = $selectedAccount['account_name'] ?? 'N/A';
        $accountNumber = $selectedAccount['account_number'] ?? 'N/A';
        $instructions = $selectedAccount['instructions'] ?? '';
        
        $msg = "💳 <b>{$method}</b>\n\n";
        $msg .= "📋 <b>Payment Details:</b>\n\n";
        $msg .= "👤 <b>Account Name:</b>\n";
        $msg .= "<code>{$accountName}</code>\n\n";
        $msg .= "📞 <b>Account/Phone Number:</b>\n";
        $msg .= "<code>{$accountNumber}</code>\n\n";
        
        if (!empty($instructions)) {
            $msg .= "📝 <b>Instructions:</b>\n";
            $msg .= "{$instructions}\n\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "💡 <b>How to Deposit:</b>\n";
        $msg .= "1. Send money to the above account\n";
        $msg .= "2. Keep your transaction receipt\n";
        $msg .= "3. Contact support with your receipt\n\n";
        $msg .= "⏱️ <i>Deposits are usually processed within 1-2 hours</i>";
        
        sendMessage($chatId, $msg, false);
        
    } catch (Exception $e) {
        error_log("Error showing payment method details: " . $e->getMessage());
        sendMessage($chatId, "❌ Error loading payment details. Please try again later.", false);
    }
}

function handleCheckStatus($chatId, $userId) {
    // Get user registration data
    $userData = getUserRegistrationData($userId);
    
    if (!$userData) {
        $msg = "❌ <b>Not Registered</b>\n\n";
        $msg .= "You haven't registered yet!\n\n";
        $msg .= "📝 Use /register to get started.";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    $kycStatus = $userData['kyc_status'] ?? 'pending';
    $isRegistered = $userData['is_registered'] ?? false;
    $firstName = $userData['first_name'] ?? '';
    $lastName = $userData['last_name'] ?? '';
    $email = $userData['email'] ?? '';
    $registrationState = $userData['registration_state'] ?? '';
    $strowalletCustomerId = $userData['strowallet_customer_id'] ?? '';
    
    // Always fetch latest status from StroWallet if user has email
    if ($email) {
        error_log("Fetching latest customer status from StroWallet for user $userId (email: $email)");
        
        $customerCheck = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY . '&customerEmail=' . urlencode($email), 'GET', [], true);
        
        if (!isset($customerCheck['error']) && isset($customerCheck['data'])) {
            // Extract customer data from response
            $customerData = $customerCheck['data'];
            $strowalletCustomerId = $customerData['customerId'] ?? $customerData['customer_id'] ?? $customerData['id'] ?? '';
            $fetchedKycStatus = $customerData['status'] ?? $customerData['kyc_status'] ?? $customerData['kycStatus'] ?? 'pending';
            
            // Map StroWallet KYC status to our format
            // StroWallet uses: "high kyc", "low kyc", "medium kyc" as status values
            $statusLower = strtolower(trim($fetchedKycStatus));
            if (strpos($statusLower, 'high') !== false || strpos($statusLower, 'verified') !== false || strpos($statusLower, 'approved') !== false) {
                $kycStatus = 'approved';
            } elseif (strpos($statusLower, 'rejected') !== false || strpos($statusLower, 'failed') !== false || strpos($statusLower, 'decline') !== false) {
                $kycStatus = 'rejected';
            } else {
                // 'pending', 'under_review', 'unreview', 'low kyc', 'medium kyc', etc.
                $kycStatus = 'pending';
            }
            
            // Update local database with latest StroWallet data
            if ($strowalletCustomerId) {
                $db = getDBConnection();
                if ($db) {
                    try {
                        $stmt = $db->prepare("UPDATE user_registrations SET strowallet_customer_id = ?, kyc_status = ?, updated_at = CURRENT_TIMESTAMP WHERE telegram_user_id = ?");
                        $stmt->execute([$strowalletCustomerId, $kycStatus, $userId]);
                        error_log("Updated user $userId: strowallet_customer_id=$strowalletCustomerId, kyc_status=$kycStatus (from StroWallet: $fetchedKycStatus)");
                    } catch (Exception $e) {
                        error_log("Failed to update user registration: " . $e->getMessage());
                    }
                }
            }
            
            error_log("Fetched KYC status from StroWallet: $fetchedKycStatus (mapped to: $kycStatus)");
        } else {
            error_log("Failed to fetch customer from StroWallet: " . json_encode($customerCheck));
        }
    }
    
    $msg = "📋 <b>Account Status</b>\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
    
    // Show user info
    if ($firstName) {
        $msg .= "👤 <b>Name:</b> {$firstName} {$lastName}\n";
    }
    if ($email) {
        $msg .= "📧 <b>Email:</b> {$email}\n";
    }
    $msg .= "🆔 <b>User ID:</b> <code>{$userId}</code>\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
    
    // Check if user has started registration but not completed submission
    if ($registrationState === 'awaiting_confirmation') {
        $msg .= "⏳ <b>Registration Status:</b> Awaiting Confirmation\n\n";
        $msg .= "📝 Please review your information and send <b>CONFIRM</b> to complete registration.";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    // Check if registration data is incomplete (no email = never started registration)
    if (!$email) {
        $msg .= "⚠️ <b>Registration Status:</b> Incomplete\n\n";
        $msg .= "📝 Please complete your registration using /register";
        sendMessage($chatId, $msg, false);
        return;
    }
    
    // Show KYC status (even if strowallet_customer_id is missing)
    $msg .= "🔐 <b>KYC Verification Status:</b>\n\n";
    
    switch ($kycStatus) {
        case 'approved':
            $msg .= "✅ <b>Status:</b> Verified\n";
            $msg .= "🎉 Your account is fully verified!\n\n";
            $msg .= "You can now:\n";
            $msg .= "• ➕ Create virtual cards\n";
            $msg .= "• 💰 Deposit funds\n";
            $msg .= "• 💳 Manage your cards";
            break;
            
        case 'rejected':
            $msg .= "❌ <b>Status:</b> Rejected\n\n";
            $msg .= "Your KYC verification was rejected.\n\n";
            $msg .= "📞 Please contact support for assistance:\n";
            $msg .= "Use /support to get help.";
            break;
            
        case 'pending':
        default:
            $msg .= "⏳ <b>Status:</b> Under Review\n\n";
            $msg .= "Your documents are being reviewed by our compliance team.\n\n";
            $msg .= "⏱️ <b>Typical verification time:</b> 24-48 hours\n\n";
            $msg .= "📱 You'll receive a notification once your verification is complete.\n\n";
            $msg .= "💡 <b>Tip:</b> Use /status anytime to check your current status.";
            break;
    }
    
    sendMessage($chatId, $msg, false);
}

// ==================== STROWALLET API ====================

function callStroWalletAPI($endpoint, $method = 'GET', $data = [], $useAdminKey = true) {
    // Use mock data if enabled
    if (USE_MOCK_DATA) {
        usleep(500000); // Simulate API delay (0.5 seconds)
        
        if (strpos($endpoint, '/create-card') !== false) {
            return getMockCardData();
        } elseif (strpos($endpoint, '/fetch-card-detail') !== false) {
            return getMockCardsList();
        } elseif (strpos($endpoint, '/user/profile') !== false || strpos($endpoint, '/getcardholder') !== false) {
            return getMockUserInfo();
        } elseif (strpos($endpoint, '/wallet/balance') !== false) {
            return getMockWalletBalance();
        } elseif (strpos($endpoint, '/deposit-address') !== false) {
            return getMockDepositAddress();
        }
        
        // Default mock response
        return ['success' => true, 'message' => 'Mock response'];
    }
    
    // Real API call
    $url = STROW_BASE . $endpoint;
    
    // Prepare headers with Authorization Bearer token
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . STROW_SECRET_KEY
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            // Add sandbox mode if enabled
            if (USE_SANDBOX_MODE && !isset($data['mode'])) {
                $data['mode'] = 'sandbox';
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['error' => 'Network error', 'details' => $curlError];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 400) {
        $errorMsg = 'Request failed';
        if ($httpCode === 401 || $httpCode === 403) {
            $errorMsg = 'Auth failed';
        } elseif ($httpCode === 404) {
            $errorMsg = 'Wrong endpoint';
        }
        
        // Log the full error for debugging
        error_log("StroWallet API Error - HTTP $httpCode: " . $response);
        
        $requestId = $result['request_id'] ?? $result['requestId'] ?? $result['trace_id'] ?? null;
        
        return [
            'error' => $errorMsg,
            'request_id' => $requestId,
            'http_code' => $httpCode
        ];
    }
    
    return $result ?? ['error' => 'Invalid response'];
}

// ==================== TELEGRAM API ====================

/**
 * Check if reply keyboard should be shown to user
 * Keyboard only shows if user's KYC is approved
 */
function shouldShowKeyboard($userId) {
    if (!$userId) {
        return false;
    }
    
    $userData = getUserRegistrationData($userId);
    if (!$userData || !$userData['is_registered']) {
        return false;
    }
    
    $kycStatus = $userData['kyc_status'] ?? 'pending';
    return $kycStatus === 'approved';
}

function sendMessage($chatId, $text, $showKeyboard = false, $userId = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    error_log("SendMessage URL: " . substr($url, 0, 50) . "... (token length: " . strlen(BOT_TOKEN) . ")");
    
    // Auto-determine keyboard display if userId provided
    if ($userId !== null && $showKeyboard === true) {
        $showKeyboard = shouldShowKeyboard($userId);
    }
    
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($showKeyboard) {
        $payload['reply_markup'] = json_encode([
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
        ]);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log all send attempts
    error_log("Sending message to chat $chatId - HTTP $httpCode");
    
    // Log if there's an error
    if ($httpCode !== 200) {
        error_log("Telegram API Error: HTTP $httpCode - Response: $response");
    } else {
        error_log("Message sent successfully: " . substr($text, 0, 50));
    }
}

function sendMessageWithReturn($chatId, $text, $buttonText = '🔙 Return to Menu', $callbackData = 'return_to_menu') {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => $buttonText, 'callback_data' => $callbackData]
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Sending message with return button to chat $chatId - HTTP $httpCode");
    
    if ($httpCode !== 200) {
        error_log("Telegram API Error: HTTP $httpCode - Response: $response");
    }
}

function sendTypingAction($chatId) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendChatAction';
    $payload = ['chat_id' => $chatId, 'action' => 'typing'];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function sendErrorMessage($chatId, $error, $requestId = null) {
    $msg = "❌ <b>Error:</b> {$error}\n";
    if ($requestId) {
        $msg .= "\n🔍 <b>Request ID:</b> <code>{$requestId}</code>\n";
    }
    $msg .= "\nPlease try again later.";
    
    if (!empty(SUPPORT_URL)) {
        $msg .= "\n\n🧑‍💻 Need help? <a href='" . SUPPORT_URL . "'>Contact Support</a>";
    }
    
    sendMessage($chatId, $msg, true);
}

// ==================== HELPER FUNCTIONS ====================

function getStatusEmoji($status) {
    $status = strtolower($status);
    return match($status) {
        'active' => '✅',
        'inactive', 'frozen' => '❄️',
        'blocked' => '🚫',
        'pending' => '⏳',
        default => 'ℹ️'
    };
}

function getCurrencyEmoji($currency) {
    $currency = strtoupper($currency);
    return match($currency) {
        'USD' => '💵',
        'USDT' => '💎',
        'BTC' => '₿',
        'ETH' => 'Ξ',
        'NGN' => '🇳🇬',
        default => '💰'
    };
}

function formatBalance($amount, $currency) {
    $currency = strtoupper($currency);
    if ($currency === 'USD' || $currency === 'USDT') {
        return '$' . number_format((float)$amount, 2);
    }
    return number_format((float)$amount, 2) . ' ' . $currency;
}

function maskUserId($userId) {
    $userId = (string)$userId;
    if (strlen($userId) <= 8) {
        return $userId;
    }
    return substr($userId, 0, 4) . '***' . substr($userId, -4);
}

function formatDate($date) {
    if (empty($date) || $date === 'N/A') {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : $date;
}

// ==================== REGISTRATION FLOW HANDLER ====================

function getRegistrationProgress($currentState) {
    $steps = [
        'awaiting_first_name' => ['step' => 1, 'total' => 15, 'name' => 'First Name'],
        'awaiting_last_name' => ['step' => 2, 'total' => 15, 'name' => 'Last Name'],
        'awaiting_dob' => ['step' => 3, 'total' => 15, 'name' => 'Date of Birth'],
        'awaiting_phone' => ['step' => 4, 'total' => 15, 'name' => 'Phone Number'],
        'awaiting_email' => ['step' => 5, 'total' => 15, 'name' => 'Email'],
        'awaiting_house_number' => ['step' => 6, 'total' => 15, 'name' => 'House Number'],
        'awaiting_address' => ['step' => 7, 'total' => 15, 'name' => 'Street Address'],
        'awaiting_city' => ['step' => 8, 'total' => 15, 'name' => 'City'],
        'awaiting_state' => ['step' => 9, 'total' => 15, 'name' => 'State/Province'],
        'awaiting_zip' => ['step' => 10, 'total' => 15, 'name' => 'ZIP Code'],
        'awaiting_country' => ['step' => 11, 'total' => 15, 'name' => 'Country'],
        'awaiting_id_type' => ['step' => 12, 'total' => 15, 'name' => 'ID Type'],
        'awaiting_id_number' => ['step' => 13, 'total' => 15, 'name' => 'ID Number'],
        'awaiting_id_image' => ['step' => 14, 'total' => 15, 'name' => 'ID Image'],
        'awaiting_user_photo' => ['step' => 15, 'total' => 15, 'name' => 'User Photo'],
        'awaiting_confirmation' => ['step' => 16, 'total' => 16, 'name' => 'Review & Confirm']
    ];
    
    $progress = $steps[$currentState] ?? ['step' => 0, 'total' => 15, 'name' => 'Unknown'];
    $percentage = round(($progress['step'] / $progress['total']) * 100);
    $bar = str_repeat('▓', (int)($percentage / 10)) . str_repeat('░', 10 - (int)($percentage / 10));
    
    return "📊 Step {$progress['step']}/{$progress['total']} | {$bar} {$percentage}%";
}

function downloadAndStoreTelegramFile($fileId, $userId, $fileType) {
    if (!$fileId || !BOT_TOKEN) {
        return null;
    }
    
    try {
        // Get file info from Telegram
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getFile?file_id=" . urlencode($fileId);
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!$data || !$data['ok'] || !isset($data['result']['file_path'])) {
            error_log("Failed to get file info for fileId: $fileId");
            return null;
        }
        
        $telegramFilePath = $data['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $telegramFilePath;
        
        // Download file content
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent === false) {
            error_log("Failed to download file from Telegram");
            return null;
        }
        
        // Create uploads directory inside public_html for direct access
        $uploadsDir = __DIR__ . '/../uploads/kyc_documents';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        
        // Generate secure filename
        $extension = pathinfo($telegramFilePath, PATHINFO_EXTENSION) ?: 'jpg';
        $secureFilename = $userId . '_' . $fileType . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $localPath = $uploadsDir . '/' . $secureFilename;
        
        // Save file locally
        if (file_put_contents($localPath, $fileContent) === false) {
            error_log("Failed to save file locally");
            return null;
        }
        
        // Return the public URL accessible from web server
        $domain = getenv('REPLIT_DEV_DOMAIN');
        $publicUrl = 'https://' . $domain . '/uploads/kyc_documents/' . $secureFilename;
        
        // Store file_id in database for reference (not the URL with token)
        storeFileReference($userId, $fileType, $fileId, $publicUrl);
        
        return $publicUrl;
    } catch (Exception $e) {
        error_log("Error in downloadAndStoreTelegramFile: " . $e->getMessage());
        return null;
    }
}

function storeFileReference($userId, $fileType, $fileId, $publicUrl) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $fieldMap = [
            'id_image' => ['url_field' => 'id_image_url', 'id_field' => 'id_image_file_id'],
            'user_photo' => ['url_field' => 'user_photo_url', 'id_field' => 'user_photo_file_id']
        ];
        
        if (!isset($fieldMap[$fileType])) {
            return false;
        }
        
        $urlField = $fieldMap[$fileType]['url_field'];
        
        $stmt = $pdo->prepare("
            UPDATE user_registrations 
            SET {$urlField} = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE telegram_user_id = ?
        ");
        return $stmt->execute([$publicUrl, $userId]);
    } catch (PDOException $e) {
        error_log("Error storing file reference: " . $e->getMessage());
        return false;
    }
}

function handleRegistrationFlow($chatId, $userId, $text, $currentState, $fileId = null) {
    sendTypingAction($chatId);
    
    // Add progress indicator
    $progress = getRegistrationProgress($currentState);
    
    switch ($currentState) {
        case 'awaiting_first_name':
            if (strlen($text) < 2) {
                sendMessage($chatId, "$progress\n\n❌ Please enter a valid first name (at least 2 characters).", false);
                return;
            }
            updateUserField($userId, 'first_name', $text);
            updateUserRegistrationState($userId, 'awaiting_last_name');
            $nextProgress = getRegistrationProgress('awaiting_last_name');
            sendMessage($chatId, "$nextProgress\n\n✅ Got it!\n\n👤 <b>What's your last name?</b>", false);
            break;
            
        case 'awaiting_last_name':
            if (strlen($text) < 2) {
                sendMessage($chatId, "$progress\n\n❌ Please enter a valid last name (at least 2 characters).", false);
                return;
            }
            updateUserField($userId, 'last_name', $text);
            updateUserRegistrationState($userId, 'awaiting_dob');
            $nextProgress = getRegistrationProgress('awaiting_dob');
            $msg = "$nextProgress\n\n✅ Great!\n\n📅 <b>What's your date of birth?</b>\n\n";
            $msg .= "Format: <code>MM/DD/YYYY</code>\n";
            $msg .= "Example: <code>01/15/1990</code>";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_dob':
            if (!validateDateFormat($text)) {
                sendMessage($chatId, "❌ Invalid date format. Please use MM/DD/YYYY\nExample: 01/15/1990", false);
                return;
            }
            updateUserField($userId, 'date_of_birth', $text);
            updateUserRegistrationState($userId, 'awaiting_phone');
            $msg = "✅ Perfect!\n\n📱 <b>What's your phone number?</b>\n\n";
            $msg .= "Format: International without '+'\n";
            $msg .= "Example: <code>2348012345678</code>";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_phone':
            $phone = preg_replace('/[^0-9]/', '', $text);
            if (strlen($phone) < 10) {
                sendMessage($chatId, "❌ Invalid phone number. Use international format without '+'\nExample: 2348012345678", false);
                return;
            }
            updateUserField($userId, 'phone', $phone);
            updateUserRegistrationState($userId, 'awaiting_email');
            sendMessage($chatId, "✅ Good!\n\n📧 <b>What's your email address?</b>", false);
            break;
            
        case 'awaiting_email':
            if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                sendMessage($chatId, "❌ Invalid email address. Please enter a valid email.", false);
                return;
            }
            
            // Check if this email exists in the users table (StroWallet sync)
            $pdo = getDBConnection();
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$text]);
                    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingUser) {
                        // User exists in StroWallet! Link telegram_id and complete registration
                        $updateStmt = $pdo->prepare("UPDATE users SET telegram_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $updateStmt->execute([$userId, $existingUser['id']]);
                        
                        // Clear registration state
                        updateUserRegistrationState($userId, 'completed');
                        
                        $kycStatus = $existingUser['kyc_status'] ?? 'pending';
                        
                        if ($kycStatus === 'approved') {
                            $msg = "🎉 <b>Welcome Back!</b>\n\n";
                            $msg .= "✅ We found your verified StroWallet account!\n\n";
                            $msg .= "Your Telegram account has been linked successfully.\n\n";
                            $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
                            $msg .= "🚀 You're all set! Use the menu below to get started.";
                            sendMessage($chatId, $msg, true);
                        } elseif ($kycStatus === 'rejected') {
                            $msg = "❌ <b>Account Found</b>\n\n";
                            $msg .= "Your StroWallet account was found, but your KYC verification was rejected.\n\n";
                            $msg .= "Please contact support for assistance.";
                            sendMessage($chatId, $msg, false);
                        } else {
                            $msg = "✅ <b>Account Linked!</b>\n\n";
                            $msg .= "We found your StroWallet account and linked it to Telegram.\n\n";
                            $msg .= "⏳ Your KYC is currently under review.\n\n";
                            $msg .= "🔔 You'll be notified once approved.";
                            sendMessage($chatId, $msg, false);
                        }
                        return;
                    }
                } catch (PDOException $e) {
                    error_log("Error checking existing user: " . $e->getMessage());
                }
            }
            
            // Email not found in users table - continue with normal registration
            updateUserField($userId, 'email', $text);
            updateUserRegistrationState($userId, 'awaiting_house_number');
            sendMessage($chatId, "✅ Excellent!\n\n🏠 <b>What's your house/apartment number?</b>\nExample: 12B", false);
            break;
            
        case 'awaiting_house_number':
            updateUserField($userId, 'house_number', $text);
            updateUserRegistrationState($userId, 'awaiting_address');
            sendMessage($chatId, "✅ Thanks!\n\n📍 <b>What's your street address?</b>\nExample: 123 Main Street", false);
            break;
            
        case 'awaiting_address':
            if (strlen($text) < 5) {
                sendMessage($chatId, "❌ Please enter a valid street address.", false);
                return;
            }
            updateUserField($userId, 'address_line1', $text);
            updateUserRegistrationState($userId, 'awaiting_city');
            sendMessage($chatId, "✅ Got it!\n\n🏙️ <b>Which city do you live in?</b>", false);
            break;
            
        case 'awaiting_city':
            updateUserField($userId, 'address_city', $text);
            updateUserRegistrationState($userId, 'awaiting_state');
            sendMessage($chatId, "✅ Great!\n\n🗺️ <b>Which state/province?</b>", false);
            break;
            
        case 'awaiting_state':
            updateUserField($userId, 'address_state', $text);
            updateUserRegistrationState($userId, 'awaiting_zip');
            sendMessage($chatId, "✅ Good!\n\n📮 <b>What's your ZIP/postal code?</b>", false);
            break;
            
        case 'awaiting_zip':
            updateUserField($userId, 'address_zip', $text);
            updateUserRegistrationState($userId, 'awaiting_country');
            $msg = "✅ Perfect!\n\n🌍 <b>Country (2-letter code)?</b>\n\n";
            $msg .= "Examples: NG, US, UK, CA";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_country':
            $country = strtoupper(substr($text, 0, 2));
            if (strlen($country) !== 2) {
                sendMessage($chatId, "❌ Please enter a valid 2-letter country code (e.g., NG, US, UK, ET)", false);
                return;
            }
            updateUserField($userId, 'address_country', $country);
            updateUserRegistrationState($userId, 'awaiting_id_type');
            $msg = "✅ Excellent!\n\n🆔 <b>What type of ID do you have?</b>\n\n";
            
            // Show country-specific ID options with numbers
            if ($country === 'ET') {
                $msg .= "Reply with the number:\n\n1️⃣ National ID\n2️⃣ Passport";
            } elseif ($country === 'NG') {
                $msg .= "Reply with the number:\n\n1️⃣ BVN\n2️⃣ NIN\n3️⃣ Passport";
            } else {
                $msg .= "Reply with the number:\n\n1️⃣ National ID\n2️⃣ Driver License\n3️⃣ Passport";
            }
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_id_type':
            // Get user's country to determine ID type options
            $userData = getUserRegistrationData($userId);
            $userCountry = $userData['address_country'] ?? '';
            
            // Map numbers to ID types based on country
            $idTypeMap = [];
            if ($userCountry === 'ET') {
                $idTypeMap = [
                    '1' => 'NATIONAL_ID',
                    '2' => 'PASSPORT'
                ];
            } elseif ($userCountry === 'NG') {
                $idTypeMap = [
                    '1' => 'BVN',
                    '2' => 'NIN',
                    '3' => 'PASSPORT'
                ];
            } else {
                $idTypeMap = [
                    '1' => 'NATIONAL_ID',
                    '2' => 'DRIVER_LICENSE',
                    '3' => 'PASSPORT'
                ];
            }
            
            // Check if user entered a valid number
            $selectedNumber = trim($text);
            $maxOptions = count($idTypeMap);
            if (!isset($idTypeMap[$selectedNumber])) {
                $msg = "❌ Invalid selection. Please reply with a number from 1 to $maxOptions.";
                sendMessage($chatId, $msg, false);
                break;
            }
            
            // Get the ID type name from the number
            $idType = $idTypeMap[$selectedNumber];
            
            // Keep valid ID types array for backward compatibility
            $validIdTypes = array_values($idTypeMap);
            
            if (!in_array($idType, $validIdTypes)) {
                sendMessage($chatId, "❌ Invalid ID type for your country. Choose from: " . implode(', ', $validIdTypes), false);
                return;
            }
            
            // Format ID type name for display (replace underscores with spaces, title case)
            $idTypeDisplay = ucwords(str_replace('_', ' ', strtolower($idType)));
            
            updateUserField($userId, 'id_type', $idType);
            updateUserRegistrationState($userId, 'awaiting_id_number');
            sendMessage($chatId, "✅ Got it! You selected: <b>$idTypeDisplay</b>\n\n🔢 <b>What's your ID number?</b>", false);
            break;
            
        case 'awaiting_id_number':
            updateUserField($userId, 'id_number', $text);
            updateUserRegistrationState($userId, 'awaiting_id_image');
            $msg = "✅ Good!\n\n📸 <b>Upload your ID document image</b>\n\n";
            $msg .= "Please send the HTTPS URL of your ID image.\n";
            $msg .= "Example: https://example.com/id.jpg";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_id_image':
            $idImageUrl = null;
            
            // If user uploaded a file (photo/document), download it securely
            if ($fileId) {
                $idImageUrl = downloadAndStoreTelegramFile($fileId, $userId, 'id_image');
                if (!$idImageUrl) {
                    sendMessage($chatId, "$progress\n\n❌ Failed to process your image. Please try again or send an HTTPS URL.", false);
                    return;
                }
                updateUserField($userId, 'id_front_photo_url', $idImageUrl);
            } 
            // If user sent a URL, validate it
            elseif ($text && filter_var($text, FILTER_VALIDATE_URL) && preg_match('/^https:\/\//i', $text)) {
                $idImageUrl = $text;
                updateUserField($userId, 'id_front_photo_url', $idImageUrl);
            } 
            // Invalid input
            else {
                $msg = "$progress\n\n";
                $msg .= "❌ Please upload your ID image or send a valid HTTPS URL.\n\n";
                $msg .= "💡 You can:\n";
                $msg .= "• Send a photo directly from your device\n";
                $msg .= "• Send a document file\n";
                $msg .= "• Or paste an HTTPS URL";
                sendMessage($chatId, $msg, false);
                return;
            }
            
            updateUserRegistrationState($userId, 'awaiting_user_photo');
            
            $msg = "$progress\n\n";
            $msg .= "✅ ID image received!\n\n";
            $msg .= "🤳 <b>Now upload your selfie</b>\n\n";
            $msg .= "📸 <b>Please take a selfie photo using your camera and send it directly.</b>\n\n";
            $msg .= "🔒 <i>For security, only direct photos are accepted.</i>";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_user_photo':
            $userPhotoUrl = null;
            
            // Only accept direct photo/document uploads for security (no URLs)
            if ($fileId) {
                $userPhotoUrl = downloadAndStoreTelegramFile($fileId, $userId, 'user_photo');
                if (!$userPhotoUrl) {
                    sendMessage($chatId, "$progress\n\n❌ Failed to process your selfie. Please try again.\n\n📸 Take a selfie using your camera and send the photo.", false);
                    return;
                }
                updateUserField($userId, 'selfie_photo_url', $userPhotoUrl);
            }
            // Reject URL uploads for selfies (security requirement)
            else {
                $msg = "$progress\n\n";
                $msg .= "❌ <b>Please upload a selfie photo directly</b>\n\n";
                $msg .= "📸 Use your camera to take a selfie and send it.\n\n";
                $msg .= "🔒 <i>For security purposes, URL links are not accepted for selfies.</i>";
                sendMessage($chatId, $msg, false);
                return;
            }
            
            updateUserRegistrationState($userId, 'awaiting_confirmation');
            
            // Show review page
            showRegistrationReview($chatId, $userId);
            break;
            
        case 'awaiting_confirmation':
            $text = strtolower(trim($text));
            
            if ($text === 'confirm' || $text === 'yes' || $text === '✅') {
                // All data collected and confirmed, now create customer in StroWallet
                sendMessage($chatId, "⏳ Creating your account in StroWallet...", false);
                $result = createStroWalletCustomerFromDB($userId);
                
                if (isset($result['error'])) {
                    updateUserRegistrationState($userId, 'failed');
                    $msg = "❌ <b>Registration Failed</b>\n\n";
                    $msg .= "Error: " . $result['error'] . "\n\n";
                    $msg .= "Please check your information and try /register again.";
                    sendMessage($chatId, $msg, false);
                } else {
                    // Mark as completed with KYC pending
                    markUserRegistrationComplete($userId, $result['customer_id'] ?? '', 'pending');
                    
                    $msg = "⏳ <b>KYC Under Review</b>\n\n";
                    $msg .= "✅ Your registration has been submitted to StroWallet.\n\n";
                    $msg .= "📋 Your documents are being verified.\n\n";
                    $msg .= "🔔 You will be notified once your KYC is approved.\n\n";
                    $msg .= "⏱️ <i>This usually takes a few hours.</i>\n\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
                    $msg .= "🚫 Menu buttons will be available after KYC approval.";
                    sendMessage($chatId, $msg, false);
                    
                    // Notify admin about new registration
                    notifyAdminNewRegistration($userId, $userData['first_name'] ?? '', $userData['last_name'] ?? '');
                }
            } elseif ($text === 'edit' || $text === 'cancel' || $text === '❌') {
                updateUserRegistrationState($userId, 'idle');
                sendMessage($chatId, "❌ Registration cancelled. Start over with /register", true);
            } else {
                sendMessage($chatId, "Please reply with 'CONFIRM' to proceed or 'CANCEL' to start over.", false);
            }
            break;
    }
}

// ==================== REGISTRATION REVIEW ====================

function showRegistrationReview($chatId, $userId) {
    $userData = getUserRegistrationData($userId);
    
    if (!$userData) {
        sendMessage($chatId, "❌ Error loading your data. Please try /register again.", true);
        return;
    }
    
    $msg = "📋 <b>Review Your Information</b>\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $msg .= "👤 <b>Personal Details</b>\n";
    $msg .= "• Name: {$userData['first_name']} {$userData['last_name']}\n";
    $msg .= "• DOB: {$userData['date_of_birth']}\n";
    $msg .= "• Phone: " . ($userData['phone'] ?? $userData['phone_number'] ?? '') . "\n";
    $msg .= "• Email: " . ($userData['email'] ?? $userData['customer_email'] ?? '') . "\n\n";
    
    $msg .= "🏠 <b>Address</b>\n";
    $msg .= "• House: {$userData['house_number']}\n";
    $msg .= "• Street: {$userData['address_line1']}\n";
    $msg .= "• City: " . ($userData['address_city'] ?? $userData['city'] ?? '') . "\n";
    $msg .= "• State: " . ($userData['address_state'] ?? $userData['state'] ?? '') . "\n";
    $msg .= "• ZIP: " . ($userData['address_zip'] ?? $userData['zip_code'] ?? '') . "\n";
    $msg .= "• Country: {$userData['address_country']}\n\n";
    
    $msg .= "🆔 <b>Identification</b>\n";
    $msg .= "• Type: {$userData['id_type']}\n";
    $msg .= "• Number: {$userData['id_number']}\n";
    
    $idImageUrl = $userData['id_front_photo_url'] ?? $userData['id_image_url'] ?? '';
    $selfieUrl = $userData['selfie_photo_url'] ?? $userData['user_photo_url'] ?? '';
    
    $msg .= "• ID Image: " . ($idImageUrl && strlen($idImageUrl) > 50 ? substr($idImageUrl, 0, 47) . '...' : $idImageUrl) . "\n";
    $msg .= "• Photo: " . ($selfieUrl && strlen($selfieUrl) > 50 ? substr($selfieUrl, 0, 47) . '...' : $selfieUrl) . "\n\n";
    
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "✅ Reply <b>CONFIRM</b> to create your account\n";
    $msg .= "❌ Reply <b>CANCEL</b> to start over";
    
    sendMessage($chatId, $msg, false);
}

// ==================== DATABASE HELPER FUNCTIONS ====================

function getUserRegistrationState($userId) {
    if (!$userId) return null;
    
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT registration_state FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['registration_state'] : 'idle';
    } catch (PDOException $e) {
        error_log("Error getting user state: " . $e->getMessage());
        return 'idle';
    }
}

function getUserRegistrationData($userId) {
    if (!$userId) return null;
    
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    try {
        // First, check users table (for StroWallet-synced/imported users) - prioritize this
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            // Return data in format compatible with user_registrations structure
            return [
                'id' => $userData['id'],
                'telegram_user_id' => $userId,
                'is_registered' => true,
                'kyc_status' => $userData['kyc_status'] ?? 'pending',
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'strow_customer_id' => $userData['strow_customer_id'] ?? null
            ];
        }
        
        // If not found in users table, check user_registrations table (for bot-registered users)
        $stmt = $pdo->prepare("SELECT * FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        $regData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($regData) {
            return $regData;
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Error getting user data: " . $e->getMessage());
        return null;
    }
}

function initializeUserRegistration($userId, $chatId) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_registrations (telegram_user_id, registration_state, kyc_status)
            VALUES (?, 'idle', 'pending')
            ON CONFLICT (telegram_user_id) DO UPDATE SET 
                registration_state = 'idle',
                kyc_status = COALESCE(user_registrations.kyc_status, 'pending'),
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error initializing registration: " . $e->getMessage());
        return false;
    }
}

function updateUserRegistrationState($userId, $state) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE user_registrations 
            SET registration_state = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE telegram_user_id = ?
        ");
        return $stmt->execute([$state, $userId]);
    } catch (PDOException $e) {
        error_log("Error updating state: " . $e->getMessage());
        return false;
    }
}

function updateUserField($userId, $field, $value) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    $allowedFields = ['first_name', 'last_name', 'date_of_birth', 'phone', 'email',
                      'house_number', 'address_line1', 'address_city', 'address_state', 'address_zip', 'address_country',
                      'id_type', 'id_number', 'id_front_photo_url', 'id_back_photo_url', 'selfie_photo_url'];
    
    if (!in_array($field, $allowedFields)) {
        error_log("Invalid field: $field");
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE user_registrations 
            SET $field = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE telegram_user_id = ?
        ");
        return $stmt->execute([$value, $userId]);
    } catch (PDOException $e) {
        error_log("Error updating field $field: " . $e->getMessage());
        return false;
    }
}

function markUserRegistrationComplete($userId, $customerId = '', $kycStatus = 'pending') {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE user_registrations 
            SET registration_state = 'completed',
                is_registered = TRUE,
                strowallet_customer_id = ?,
                kyc_status = ?,
                completed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP 
            WHERE telegram_user_id = ?
        ");
        $success = $stmt->execute([$customerId, $kycStatus, $userId]);
        
        if ($success) {
            copyUserToUsersTable($userId);
        }
        
        return $success;
    } catch (PDOException $e) {
        error_log("Error marking registration complete: " . $e->getMessage());
        return false;
    }
}

function copyUserToUsersTable($userId) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $userData = getUserRegistrationData($userId);
        if (!$userData) {
            error_log("Cannot copy user to users table: user data not found for $userId");
            return false;
        }
        
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $checkStmt->execute([$userId]);
        if ($checkStmt->fetch()) {
            error_log("User already exists in users table: $userId");
            return true;
        }
        
        $idImageUrl = $userData['id_front_photo_url'] ?? $userData['id_image_url'] ?? '';
        $userPhotoUrl = $userData['selfie_photo_url'] ?? $userData['user_photo_url'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO users (
                telegram_id, email, phone, first_name, last_name,
                kyc_status, kyc_submitted_at, strow_customer_id,
                id_type, id_number, id_image_url, user_photo_url,
                address_line1, address_city, address_state, address_zip, address_country,
                house_number, date_of_birth, status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, CURRENT_TIMESTAMP, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        
        return $stmt->execute([
            $userId,
            $userData['email'],
            $userData['phone'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['kyc_status'] ?? 'pending',
            $userData['strowallet_customer_id'] ?? '',
            $userData['id_type'],
            $userData['id_number'],
            $idImageUrl,
            $userPhotoUrl,
            $userData['address_line1'],
            $userData['address_city'] ?? $userData['city'] ?? '',
            $userData['address_state'] ?? $userData['state'] ?? '',
            $userData['address_zip'] ?? $userData['zip_code'] ?? '',
            $userData['address_country'],
            $userData['house_number'],
            $userData['date_of_birth']
        ]);
    } catch (PDOException $e) {
        error_log("Error copying user to users table: " . $e->getMessage());
        return false;
    }
}

function notifyAdminNewRegistration($userId, $firstName, $lastName) {
    $adminChatId = ADMIN_CHAT_ID;
    if (empty($adminChatId) || $adminChatId === 'your_telegram_admin_chat_id_for_alerts') {
        return; // Admin notifications not configured
    }
    
    $msg = "🆕 <b>New Registration!</b>\n\n";
    $msg .= "👤 <b>User:</b> $firstName $lastName\n";
    $msg .= "🆔 <b>Telegram ID:</b> <code>$userId</code>\n";
    $msg .= "⏳ <b>KYC Status:</b> Under Review\n\n";
    $msg .= "📋 Check the admin panel for details.";
    
    sendMessage($adminChatId, $msg, false);
}

function createStroWalletCustomerFromDB($userId) {
    error_log("=== createStroWalletCustomerFromDB START for user $userId ===");
    $userData = getUserRegistrationData($userId);
    
    if (!$userData) {
        error_log("ERROR: User data not found for user $userId");
        return ['error' => 'User data not found'];
    }
    
    error_log("User data retrieved: " . json_encode(array_keys($userData)));
    
    // Validate required fields (using correct database column names)
    $required = ['first_name', 'last_name', 'date_of_birth', 'phone', 'email',
                 'house_number', 'address_line1', 'address_city', 'address_state', 'address_zip', 'address_country',
                 'id_type', 'id_number'];
    
    foreach ($required as $field) {
        if (empty($userData[$field])) {
            error_log("ERROR: Missing required field: $field");
            return ['error' => "Missing required field: $field"];
        }
    }
    
    // Get photo URLs (support both old and new field names for backward compatibility)
    $idImageUrl = $userData['id_front_photo_url'] ?? $userData['id_image_url'] ?? '';
    $userPhotoUrl = $userData['selfie_photo_url'] ?? $userData['user_photo_url'] ?? '';
    
    // Validate photos are present
    if (empty($idImageUrl)) {
        error_log("ERROR: Missing ID image");
        return ['error' => 'Missing required field: id_image'];
    }
    if (empty($userPhotoUrl)) {
        error_log("ERROR: Missing user photo");
        return ['error' => 'Missing required field: user_photo'];
    }
    
    error_log("All required fields validated successfully");
    
    // Prepare customer data for StroWallet API (using correct database column names)
    $customerData = [
        'public_key' => STROW_PUBLIC_KEY,
        'houseNumber' => $userData['house_number'],
        'firstName' => $userData['first_name'],
        'lastName' => $userData['last_name'],
        'idNumber' => $userData['id_number'],
        'customerEmail' => $userData['email'],
        'phoneNumber' => $userData['phone'],
        'dateOfBirth' => $userData['date_of_birth'],
        'idImage' => $idImageUrl,
        'userPhoto' => $userPhotoUrl,
        'line1' => $userData['address_line1'],
        'state' => $userData['address_state'],
        'zipCode' => $userData['address_zip'],
        'city' => $userData['address_city'],
        'country' => $userData['address_country'],
        'idType' => $userData['id_type']
    ];
    
    $publicKeyPreview = STROW_PUBLIC_KEY ? substr(STROW_PUBLIC_KEY, 0, 10) . '...' . substr(STROW_PUBLIC_KEY, -10) : 'NOT SET';
    error_log("API Configuration - Public Key: " . $publicKeyPreview);
    error_log("Calling StroWallet API /bitvcard/create-user/ with full customer data:");
    error_log("Customer Data: " . json_encode($customerData, JSON_PRETTY_PRINT));
    
    // Call StroWallet create-user API
    $result = callStroWalletAPI('/bitvcard/create-user/', 'POST', $customerData, true);
    
    error_log("StroWallet API Response: " . json_encode($result));
    
    if (isset($result['error'])) {
        error_log("ERROR: StroWallet API call failed: " . json_encode($result));
        return $result;
    }
    
    // Extract customer ID from response (check various possible field names)
    $customerId = $result['customer_id'] ?? $result['customerId'] ?? $result['id'] ?? $result['data']['id'] ?? null;
    
    if ($customerId) {
        error_log("SUCCESS: Customer created in StroWallet with ID: " . $customerId);
        
        // Update database with StroWallet customer ID
        $db = getDBConnection();
        if ($db) {
            // Update user_registrations table
            $stmt = $db->prepare("UPDATE user_registrations SET strowallet_customer_id = ?, kyc_status = ?, updated_at = CURRENT_TIMESTAMP WHERE telegram_user_id = ?");
            $stmt->execute([$customerId, 'pending', $userId]);
            
            // Also create/update record in users table for admin panel
            // Check if user already exists in users table
            $checkStmt = $db->prepare("SELECT id FROM users WHERE telegram_id = ? LIMIT 1");
            $checkStmt->execute([$userId]);
            $existingUser = $checkStmt->fetch();
            
            if ($existingUser) {
                // Update existing user
                $updateStmt = $db->prepare("
                    UPDATE users SET 
                        email = ?, phone = ?, first_name = ?, last_name = ?,
                        date_of_birth = ?, house_number = ?, address_line1 = ?,
                        address_city = ?, address_state = ?, address_zip = ?, address_country = ?,
                        id_type = ?, id_number = ?, id_image_url = ?, user_photo_url = ?,
                        kyc_status = ?, strow_customer_id = ?, kyc_submitted_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE telegram_id = ?
                ");
                $updateStmt->execute([
                    $userData['email'], $userData['phone'], $userData['first_name'], $userData['last_name'],
                    $userData['date_of_birth'], $userData['house_number'], $userData['address_line1'],
                    $userData['address_city'], $userData['address_state'], $userData['address_zip'], $userData['address_country'],
                    $userData['id_type'], $userData['id_number'], $idImageUrl, $userPhotoUrl,
                    'pending', $customerId, $userId
                ]);
                error_log("Updated existing user in users table for telegram_id: $userId");
            } else {
                // Insert new user
                $insertStmt = $db->prepare("
                    INSERT INTO users (
                        telegram_id, email, phone, first_name, last_name, date_of_birth,
                        house_number, address_line1, address_city, address_state, address_zip, address_country,
                        id_type, id_number, id_image_url, user_photo_url,
                        kyc_status, strow_customer_id, kyc_submitted_at, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([
                    $userId, $userData['email'], $userData['phone'], $userData['first_name'], $userData['last_name'],
                    $userData['date_of_birth'], $userData['house_number'], $userData['address_line1'],
                    $userData['address_city'], $userData['address_state'], $userData['address_zip'], $userData['address_country'],
                    $userData['id_type'], $userData['id_number'], $idImageUrl, $userPhotoUrl,
                    'pending', $customerId
                ]);
                error_log("Created new user in users table for telegram_id: $userId");
            }
        }
    } else {
        error_log("WARNING: Customer created but no ID returned. Response: " . json_encode($result));
    }
    
    error_log("=== createStroWalletCustomerFromDB END ===");
    
    return $result;
}

// ==================== VALIDATION FUNCTIONS ====================

function validateDateFormat($date) {
    // Check MM/DD/YYYY format
    if (!preg_match('/^(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])\/\d{4}$/', $date)) {
        return false;
    }
    
    // Validate actual date
    $parts = explode('/', $date);
    return checkdate((int)$parts[0], (int)$parts[1], (int)$parts[2]);
}

function promptForCurrentField($chatId, $state, $userId = null) {
    $prompts = [
        'awaiting_first_name' => "👤 <b>What's your first name?</b>",
        'awaiting_last_name' => "👤 <b>What's your last name?</b>",
        'awaiting_dob' => "📅 <b>What's your date of birth?</b>\n\nFormat: <code>MM/DD/YYYY</code>\nExample: <code>01/15/1990</code>",
        'awaiting_phone' => "📱 <b>What's your phone number?</b>\n\nFormat: International without '+'\nExample: <code>2348012345678</code>",
        'awaiting_email' => "📧 <b>What's your email address?</b>",
        'awaiting_house_number' => "🏠 <b>What's your house/apartment number?</b>\nExample: 12B",
        'awaiting_address' => "📍 <b>What's your street address?</b>\nExample: 123 Main Street",
        'awaiting_city' => "🏙️ <b>Which city do you live in?</b>",
        'awaiting_state' => "🗺️ <b>Which state/province?</b>",
        'awaiting_zip' => "📮 <b>What's your ZIP/postal code?</b>",
        'awaiting_country' => "🌍 <b>Country (2-letter code)?</b>\n\nExamples: NG, US, UK, ET, CA",
        'awaiting_id_number' => "🔢 <b>What's your ID number?</b>",
        'awaiting_id_image' => "📸 <b>Upload your ID document image</b>\n\n💡 You can:\n• Send a photo directly from your device\n• Send a document file\n• Or paste an HTTPS URL",
        'awaiting_user_photo' => "🤳 <b>Upload your selfie</b>\n\n📸 Please take a selfie photo using your camera and send it directly.\n\n🔒 <i>For security, only direct photos are accepted.</i>"
    ];
    
    $prompt = $prompts[$state] ?? "Please provide the requested information.";
    
    // For ID type, show country-specific options with numbers
    if ($state === 'awaiting_id_type' && $userId) {
        $userData = getUserRegistrationData($userId);
        $country = $userData['address_country'] ?? '';
        
        $prompt = "🆔 <b>What type of ID do you have?</b>\n\n";
        $prompt .= "Reply with the number:\n\n";
        if ($country === 'ET') {
            $prompt .= "1️⃣ National ID\n2️⃣ Passport";
        } elseif ($country === 'NG') {
            $prompt .= "1️⃣ BVN\n2️⃣ NIN\n3️⃣ Passport";
        } else {
            $prompt .= "1️⃣ National ID\n2️⃣ Driver License\n3️⃣ Passport";
        }
    }
    
    sendMessage($chatId, "↩️ <b>Continuing registration...</b>\n\n" . $prompt, false);
}

// ==================== CUSTOMER MANAGEMENT ====================

function handleQuickRegister($chatId, $userId) {
    sendTypingAction($chatId);
    
    $customerEmail = STROWALLET_EMAIL;
    
    if (empty($customerEmail)) {
        sendMessage($chatId, "❌ <b>Configuration Error</b>\n\nSTROWALLET_EMAIL is not configured. Please contact administrator.", true);
        return;
    }
    
    // Check if customer already exists
    $customerCheck = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY . '&customerEmail=' . urlencode($customerEmail), 'GET', [], true);
    
    if (!isset($customerCheck['error'])) {
        $msg = "✅ <b>Customer Already Exists</b>\n\n";
        $msg .= "A customer with email <code>$customerEmail</code> is already registered.\n\n";
        $msg .= "You can now create cards using ➕ <b>Create Card</b>";
        sendMessage($chatId, $msg, true);
        return;
    }
    
    // Create customer using environment config
    $result = createStroWalletCustomer($customerEmail);
    
    if (isset($result['error'])) {
        $msg = "❌ <b>Registration Failed</b>\n\n";
        $msg .= "Could not create customer in StroWallet.\n\n";
        $msg .= "📝 <b>Error:</b> " . $result['error'] . "\n\n";
        $msg .= "Please use manual registration or contact administrator.";
        sendMessage($chatId, $msg, true);
        return;
    }
    
    $msg = "✅ <b>Customer Registered Successfully!</b>\n\n";
    $msg .= "🎉 Your customer account has been created in StroWallet.\n\n";
    $msg .= "📧 <b>Email:</b> <code>$customerEmail</code>\n\n";
    $msg .= "You can now create virtual cards using ➕ <b>Create Card</b>";
    sendMessage($chatId, $msg, true);
}

function createStroWalletCustomer($customerEmail) {
    // Get required environment variables
    $firstName = getenv('CUSTOMER_FIRST_NAME') ?: 'User';
    $lastName = getenv('CUSTOMER_LAST_NAME') ?: 'Account';
    $phoneNumber = getenv('CUSTOMER_PHONE') ?: '2348000000000';
    $dateOfBirth = getenv('CUSTOMER_DOB') ?: '01/01/1990';
    $houseNumber = getenv('CUSTOMER_HOUSE_NUMBER') ?: '1';
    $line1 = getenv('CUSTOMER_ADDRESS') ?: '123 Main Street';
    $city = getenv('CUSTOMER_CITY') ?: 'Lagos';
    $state = getenv('CUSTOMER_STATE') ?: 'Lagos';
    $zipCode = getenv('CUSTOMER_ZIP') ?: '100001';
    $country = getenv('CUSTOMER_COUNTRY') ?: 'NG';
    $idType = getenv('CUSTOMER_ID_TYPE') ?: 'PASSPORT';
    $idNumber = getenv('CUSTOMER_ID_NUMBER') ?: 'A00000000';
    $idImage = getenv('CUSTOMER_ID_IMAGE') ?: '';
    $userPhoto = getenv('CUSTOMER_PHOTO') ?: '';
    
    // Validate required fields
    if (empty($idImage) || empty($userPhoto)) {
        return [
            'error' => 'Missing KYC documents. Please set CUSTOMER_ID_IMAGE and CUSTOMER_PHOTO environment variables with valid URLs.'
        ];
    }
    
    // Prepare customer data according to StroWallet API docs
    $customerData = [
        'public_key' => STROW_PUBLIC_KEY,
        'houseNumber' => $houseNumber,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'idNumber' => $idNumber,
        'customerEmail' => $customerEmail,
        'phoneNumber' => $phoneNumber,
        'dateOfBirth' => $dateOfBirth,
        'idImage' => $idImage,
        'userPhoto' => $userPhoto,
        'line1' => $line1,
        'state' => $state,
        'zipCode' => $zipCode,
        'city' => $city,
        'country' => $country,
        'idType' => $idType
    ];
    
    // Call StroWallet create-user API
    return callStroWalletAPI('/bitvcard/create-user/', 'POST', $customerData, true);
}

function getTelegramUserInfo($chatId) {
    // Get user info from Telegram API
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getChat';
    $payload = ['chat_id' => $chatId];
    
    $ch = curl_init($url . '?' . http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $user = $data['result'] ?? [];
    
    $firstName = $user['first_name'] ?? 'User';
    $lastName = $user['last_name'] ?? '';
    $username = $user['username'] ?? '';
    
    // Generate email from Telegram username or user ID
    $email = !empty($username) ? $username . '@telegram.user' : 'user_' . $chatId . '@telegram.user';
    
    return [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'username' => $username,
        'email' => strtolower($email),
        'chat_id' => $chatId
    ];
}


// ==================== DEPOSIT HELPER FUNCTIONS ====================

function getExchangeRate() {
    $db = getDBConnection();
    if (!$db) {
        error_log("Failed to get DB connection for exchange rate");
        return 130.50; // Default fallback rate
    }
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'exchange_rate_usd_to_etb'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['value'])) {
            $data = json_decode($result['value'], true);
            $rate = $data['rate'] ?? 130.50;
            error_log("Fetched exchange rate from database: $rate");
            return floatval($rate);
        }
    } catch (Exception $e) {
        error_log("Error fetching exchange rate: " . $e->getMessage());
    }
    
    return 130.50; // Default fallback rate
}

function setUserDepositState($userId, $state) {
    $db = getDBConnection();
    if (!$db) return false;
    
    try {
        // Check if record exists
        $stmt = $db->prepare("SELECT id FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            // Update existing record
            $stmt = $db->prepare("UPDATE user_registrations SET registration_state = ?, updated_at = CURRENT_TIMESTAMP WHERE telegram_user_id = ?");
            $stmt->execute([$state, $userId]);
        } else {
            // Insert new record for users who were imported directly
            $stmt = $db->prepare("INSERT INTO user_registrations (telegram_user_id, registration_state, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$userId, $state]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error setting deposit state: " . $e->getMessage());
        return false;
    }
}

function getUserDepositState($userId) {
    $userData = getUserRegistrationData($userId);
    return $userData['registration_state'] ?? null;
}

function storeDepositInfo($userId, $usdAmount, $exchangeRate, $etbAmount, $paymentMethod = '') {
    $db = getDBConnection();
    if (!$db) return false;
    
    try {
        // Store in user_registrations temp data
        $depositData = json_encode([
            'usd_amount' => $usdAmount,
            'exchange_rate' => $exchangeRate,
            'etb_amount' => $etbAmount,
            'payment_method' => $paymentMethod,
            'timestamp' => time()
        ]);
        
        $stmt = $db->prepare("UPDATE user_registrations SET temp_data = ? WHERE telegram_user_id = ?");
        $stmt->execute([$depositData, $userId]);
        
        error_log("Deposit info stored - User: $userId, USD: $usdAmount, ETB: $etbAmount, Method: $paymentMethod");
        
        return true;
    } catch (Exception $e) {
        error_log("Error storing deposit info: " . $e->getMessage());
        return false;
    }
}

// ==================== MOCK DATA FUNCTIONS ====================

function getMockCardData() {
    return [
        'success' => true,
        'data' => [
            'card_id' => 'DEMO_' . strtoupper(substr(md5(time()), 0, 8)),
            'card_brand' => 'Visa',
            'card_number' => '4532********' . rand(1000, 9999),
            'card_status' => 'active',
            'balance' => '5.00',
            'currency' => 'USD',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
}

function getMockCardsList() {
    return [
        'success' => true,
        'data' => [
            [
                'card_id' => 'DEMO_CARD_001',
                'card_brand' => 'Visa',
                'card_number' => '4532********1234',
                'card_status' => 'active',
                'balance' => '25.50',
                'currency' => 'USD'
            ],
            [
                'card_id' => 'DEMO_CARD_002',
                'card_brand' => 'Mastercard',
                'card_number' => '5425********5678',
                'card_status' => 'active',
                'balance' => '100.00',
                'currency' => 'USD'
            ]
        ]
    ];
}

function getMockUserInfo() {
    return [
        'success' => true,
        'data' => [
            'firstName' => 'Demo',
            'lastName' => 'User',
            'customerEmail' => STROWALLET_EMAIL ?: 'demo@strowallet.com',
            'phoneNumber' => '+1234567890',
            'customerId' => 'DEMO_' . strtoupper(substr(md5('user'), 0, 8)),
            'kycStatus' => 'verified',
            'kycLevel' => 2,
            'joined' => date('Y-m-d', strtotime('-30 days')),
            'cardCount' => 2,
            'maxCards' => 10,
            'points' => 150,
            'referrals' => 5
        ]
    ];
}

function getMockWalletBalance() {
    return [
        'success' => true,
        'data' => [
            'balances' => [
                ['currency' => 'USD', 'amount' => '125.50'],
                ['currency' => 'USDT', 'amount' => '200.00']
            ]
        ]
    ];
}

function getMockDepositAddress() {
    return [
        'success' => true,
        'data' => [
            'address' => 'TDemo' . strtoupper(substr(md5(time()), 0, 32)),
            'network' => 'TRC20',
            'currency' => 'USDT',
            'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=TDemo'
        ]
    ];
}
