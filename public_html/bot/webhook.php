<?php
/**
 * Telegram Crypto Card Bot - Main Webhook Handler
 * PHP 8+ | No Frameworks | No Composer
 */

// Load environment variables from .env file
require_once __DIR__ . '/../../secrets/load_env.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/telegram_bot_errors.log');

// Configuration - Use environment variables (Replit Secrets or .env file)
define('BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: getenv('BOT_TOKEN') ?: '');
define('STROW_BASE', 'https://strowallet.com/api');
define('STROW_PUBLIC_KEY', getenv('STROW_PUBLIC_KEY') ?: '');
define('STROW_SECRET_KEY', getenv('STROW_SECRET_KEY') ?: '');
define('STROWALLET_EMAIL', getenv('STROWALLET_EMAIL') ?: '');
define('ADMIN_CHAT_ID', getenv('ADMIN_CHAT_ID') ?: '');
define('SUPPORT_URL', getenv('SUPPORT_URL') ?: 'https://t.me/support');
define('REFERRAL_TEXT', getenv('REFERRAL_TEXT') ?: 'Join me on StroWallet!');
define('TELEGRAM_SECRET_TOKEN', getenv('TELEGRAM_SECRET_TOKEN') ?: '');

// Mock mode for testing (set to true to use demo data)
define('USE_MOCK_DATA', getenv('USE_MOCK_DATA') === 'true' || getenv('USE_MOCK_DATA') === '1');

// Sandbox mode for StroWallet API (bypasses IP whitelist)
define('USE_SANDBOX_MODE', false);

// Database configuration
define('DATABASE_URL', getenv('DATABASE_URL') ?: '');

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
        sendMessage($chatId, "❌ Registration cancelled. You can start again with /register", true);
    } else {
        sendMessage($chatId, "Nothing to cancel.", true);
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
        sendMessage($chatId, "Nothing to continue. Use /register to start.", true);
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

// If user is in registration flow (not idle), route to registration handler
if ($userState && $userState !== 'idle' && $userState !== 'completed') {
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
} elseif ($text === '/invite' || $text === '💸 Invite Friends') {
    handleInvite($chatId);
} elseif ($text === '/support' || $text === '🧑‍💻 Support') {
    handleSupport($chatId);
} else {
    sendMessage($chatId, "ℹ️ Unknown command. Please use the menu buttons below.", true);
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
    
    // Handle admin callbacks (payment method selection) - no registration check needed
    if (strpos($data, 'deposit_method_') === 0) {
        handleAdminDepositMethodSelection($chatId, $userId, $data);
        return;
    }
    
    // For user callbacks, check registration status
    $userData = getUserRegistrationData($userId);
    if (!$userData) {
        sendMessage($chatId, "❌ Please register first using /register", true);
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
        'telebirr' => 'TeleBirr',
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
        case 'telebirr':
            $userMsg .= "Please deposit using TeleBirr:\n\n";
            $userMsg .= "📱 <b>Service:</b> TeleBirr\n";
            $userMsg .= "📞 <b>Phone Number:</b> [Admin will provide]\n";
            break;
        default:
            $userMsg .= "[Admin will provide payment details]\n";
            break;
    }
    
    $userMsg .= "\n⏳ <b>Admin will send you the payment details shortly.</b>";
    sendMessage($targetUserId, $userMsg, false);
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
    
    $userData = getUserRegistrationData($userId);
    $fullName = ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');
    
    // Notify admin with payment method options
    if (!empty(ADMIN_CHAT_ID) && ADMIN_CHAT_ID !== 'your_telegram_admin_chat_id_for_alerts') {
        $adminMsg = "💰 <b>Deposit Request</b>\n\n";
        $adminMsg .= "👤 <b>User:</b> {$fullName}\n";
        $adminMsg .= "🆔 <b>Telegram ID:</b> <code>{$userId}</code>\n\n";
        $adminMsg .= "👇 <b>Select payment method:</b>";
        
        $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
        $payload = [
            'chat_id' => ADMIN_CHAT_ID,
            'text' => $adminMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '🏦 CBE', 'callback_data' => "deposit_method_cbe_{$userId}"],
                        ['text' => '💵 CBE Birr', 'callback_data' => "deposit_method_cbe_birr_{$userId}"]
                    ],
                    [
                        ['text' => '📱 TeleBirr', 'callback_data' => "deposit_method_telebirr_{$userId}"],
                        ['text' => '💳 Other', 'callback_data' => "deposit_method_other_{$userId}"]
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
    
    // Notify user
    $userMsg = "✅ <b>Deposit Request Sent</b>\n\n";
    $userMsg .= "Your deposit request has been sent to the admin.\n\n";
    $userMsg .= "⏳ Please wait for payment instructions.";
    sendMessage($chatId, $userMsg, true);
}

// ==================== COMMAND HANDLERS ====================

function checkKYCStatus($chatId, $userId) {
    $userData = getUserRegistrationData($userId);
    
    if (!$userData || !$userData['is_registered']) {
        sendMessage($chatId, "❌ Please register first using /register", false);
        return false;
    }
    
    $kycStatus = $userData['kyc_status'] ?? 'pending';
    
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
    
    // Check KYC status before allowing card creation
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    // Get user registration data
    $userData = getUserRegistrationData($userId);
    $customerEmail = $userData['email'] ?? null;
    
    if (empty($customerEmail)) {
        sendMessage($chatId, "❌ <b>Configuration Error</b>\n\nEmail not found. Please contact administrator.", false);
        return;
    }
    
    // Verify customer exists in StroWallet
    $customerCheck = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY . '&customerEmail=' . urlencode($customerEmail), 'GET', [], true);
    
    if (isset($customerCheck['error'])) {
        $httpCode = $customerCheck['http_code'] ?? 0;
        error_log("Customer check failed - HTTP $httpCode: " . json_encode($customerCheck));
        
        // Handle different error types
        if ($httpCode === 404) {
            // Customer not found - offer registration options
            $msg = "❌ <b>Customer Not Found</b>\n\n";
            $msg .= "No customer with email <code>$customerEmail</code> exists in StroWallet.\n\n";
            $msg .= "📝 <b>Registration Options:</b>\n\n";
            $msg .= "1️⃣ <b>Quick Setup:</b> /quickregister\n";
            $msg .= "   • Uses pre-configured KYC data\n";
            $msg .= "   • Requires admin to set up environment variables\n\n";
            $msg .= "2️⃣ <b>Manual Setup:</b>\n";
            $msg .= "   • Log into <a href='https://strowallet.com/dashboard'>StroWallet Dashboard</a>\n";
            $msg .= "   • Go to Card Holders → Create New\n";
            $msg .= "   • Complete KYC verification\n\n";
            $msg .= "3️⃣ <b>View All Options:</b> /register";
            sendMessage($chatId, $msg, true);
        } elseif ($httpCode === 401 || $httpCode === 403) {
            // Auth error
            $msg = "❌ <b>Authentication Error</b>\n\n";
            $msg .= "StroWallet API authentication failed.\n\n";
            $msg .= "This is a configuration issue. Please contact the administrator.\n\n";
            $msg .= "🔍 <b>Error Details:</b> " . ($customerCheck['error'] ?? 'Auth failed');
            sendMessage($chatId, $msg, true);
        } else {
            // Network or server error
            $msg = "❌ <b>Service Error</b>\n\n";
            $msg .= "Unable to connect to StroWallet API.\n\n";
            $msg .= "Please try again in a few moments.\n\n";
            $msg .= "🔍 <b>Error:</b> " . ($customerCheck['error'] ?? 'Unknown error');
            sendMessage($chatId, $msg, true);
        }
        return;
    }
    
    // Get Telegram user info for card name
    $userInfo = getTelegramUserInfo($chatId);
    
    $requestData = [
        'name_on_card' => $userInfo['first_name'] . ' ' . $userInfo['last_name'],
        'card_type' => 'visa',
        'public_key' => STROW_PUBLIC_KEY,
        'amount' => '5',
        'customerEmail' => $customerEmail
    ];
    
    $result = callStroWalletAPI('/bitvcard/create-card/', 'POST', $requestData, true);
    
    if (isset($result['error'])) {
        sendErrorMessage($chatId, $result['error'], $result['request_id'] ?? null);
        return;
    }
    
    // Handle both nested and flat response formats
    $cardData = $result['response'] ?? $result['data'] ?? $result;
    
    if (isset($cardData['card_id']) || isset($cardData['id'])) {
        $brand = $cardData['card_brand'] ?? $cardData['brand'] ?? 'Visa';
        $cardNumber = $cardData['card_number'] ?? ('****' . substr($cardData['card_id'] ?? '****', -4));
        $last4 = substr($cardNumber, -4);
        $status = $cardData['card_status'] ?? $cardData['status'] ?? 'active';
        $statusEmoji = getStatusEmoji($status);
        
        $msg = "✅ <b>Card Created Successfully!</b>\n\n";
        $msg .= "💳 <b>Brand:</b> {$brand}\n";
        $msg .= "🔢 <b>Number:</b> {$cardNumber}\n";
        $msg .= "{$statusEmoji} <b>Status:</b> " . ucfirst($status) . "\n\n";
        if (USE_MOCK_DATA) {
            $msg .= "<i>🧪 Demo Mode - Using test data</i>\n\n";
        } elseif (USE_SANDBOX_MODE) {
            $msg .= "<i>🧪 Sandbox Mode - Test environment</i>\n\n";
        }
        $msg .= "ℹ️ Your new virtual card is ready to use!";
        
        sendMessage($chatId, $msg, true);
    } else {
        $msg = "✅ Card creation response received!\n\n";
        $msg .= "Response: " . json_encode($result, JSON_PRETTY_PRINT);
        sendMessage($chatId, $msg, true);
    }
}

function handleMyCards($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    $result = callStroWalletAPI('/bitvcard/fetch-card-detail/', 'GET', [], true);
    
    if (isset($result['error'])) {
        sendErrorMessage($chatId, $result['error'], $result['request_id'] ?? null);
        return;
    }
    
    $cardsData = $result['data'] ?? $result;
    $cards = $cardsData['cards'] ?? ($cardsData['data'] ?? []);
    
    if (is_array($cards) && !empty($cards)) {
        $msg = "💳 <b>Your Virtual Cards</b>\n\n";
        
        foreach ($cards as $index => $card) {
            $brand = $card['brand'] ?? $card['card_brand'] ?? 'Visa';
            $last4 = $card['last4'] ?? $card['last_four'] ?? '****';
            $status = $card['status'] ?? 'unknown';
            $statusEmoji = getStatusEmoji($status);
            
            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🔸 <b>Card #" . ($index + 1) . "</b>\n";
            $msg .= "💳 {$brand} ••••{$last4}\n";
            $msg .= "{$statusEmoji} " . ucfirst($status) . "\n";
        }
        
        $msg .= "\n━━━━━━━━━━━━━━━━━━";
        sendMessage($chatId, $msg, true);
    } else {
        $msg = "📪 <b>No Cards Found</b>\n\n";
        $msg .= "You don't have any virtual cards yet.\n\n";
        $msg .= "💡 Use <b>➕ Create Card</b> to get started!";
        sendMessage($chatId, $msg, true);
    }
}

function handleUserInfo($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    $result = callStroWalletAPI('/user/profile', 'GET', [], false);
    
    if (isset($result['error'])) {
        sendErrorMessage($chatId, $result['error'], $result['request_id'] ?? null);
        return;
    }
    
    $userData = $result['data'] ?? $result;
    
    $name = $userData['name'] ?? $userData['full_name'] ?? 'N/A';
    $phone = $userData['phone'] ?? $userData['phone_number'] ?? 'N/A';
    $kycStatus = $userData['kyc_verified'] ?? $userData['kyc_status'] ?? false;
    $userIdValue = $userData['user_id'] ?? $userData['id'] ?? 'N/A';
    $cardLimit = $userData['cards_count'] ?? 0;
    $maxCards = $userData['max_cards'] ?? 10;
    $points = $userData['points'] ?? 0;
    $referrals = $userData['referrals_count'] ?? 0;
    $joinedDate = $userData['created_at'] ?? $userData['joined_date'] ?? 'N/A';
    $balance = $userData['balance'] ?? $userData['wallet_balance'] ?? '0.00';
    
    $kycEmoji = $kycStatus ? '✅' : '🔴';
    $kycText = $kycStatus ? 'Verified' : 'Not Verified';
    
    $msg = "👤 <b>Here's Your Profile:</b>\n\n";
    $msg .= "🧑 <b>Name:</b> {$name}\n";
    $msg .= "📱 <b>Phone Number:</b> {$phone}\n";
    $msg .= "🆔 <b>KYC Status:</b> {$kycEmoji} {$kycText}\n";
    $msg .= "🔑 <b>User ID:</b> " . maskUserId($userIdValue) . "\n";
    $msg .= "💳 <b>Card Limit:</b> {$cardLimit} / {$maxCards}\n";
    $msg .= "🎯 <b>Points:</b> {$points}\n";
    $msg .= "👥 <b>Referrals:</b> {$referrals}\n";
    $msg .= "📅 <b>Joined On:</b> " . formatDate($joinedDate) . "\n";
    $msg .= "💰 <b>Wallet Balance:</b> $" . number_format((float)$balance, 2) . "\n";
    
    if (!$kycStatus) {
        $msg .= "\n⚠️ <b>Attention!</b> We've noticed you haven't completed your KYC verification yet. Unlock a world of benefits by clicking the button below and completing the process:\n\n";
        $msg .= "• Higher card limits ✅\n";
        $msg .= "• Increased maximum top-up amounts 💵\n";
        $msg .= "• And more benefits! 🎉\n\n";
        $msg .= "Don't miss out on these fantastic features!";
    }
    
    sendMessage($chatId, $msg, true);
}

function handleWallet($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Check KYC status
    if (!checkKYCStatus($chatId, $userId)) {
        return;
    }
    
    $result = callStroWalletAPI('/wallet/balance', 'GET', [], false);
    
    if (isset($result['error'])) {
        sendErrorMessage($chatId, $result['error'], $result['request_id'] ?? null);
        return;
    }
    
    $walletData = $result['data'] ?? $result;
    $balances = $walletData['balances'] ?? $walletData;
    
    $msg = "💰 <b>Your Wallet</b>\n\n";
    
    if (is_array($balances)) {
        foreach ($balances as $currency => $amount) {
            $emoji = getCurrencyEmoji($currency);
            $msg .= "{$emoji} <b>{$currency}:</b> " . formatBalance($amount, $currency) . "\n";
        }
    } else {
        $usdBalance = $walletData['usd_balance'] ?? $walletData['balance'] ?? '0.00';
        $msg .= "💵 <b>USD:</b> $" . number_format((float)$usdBalance, 2) . "\n";
    }
    
    $msg .= "\n━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📥 To deposit USDT (TRC20), use:\n";
    $msg .= "/deposit_trc20";
    
    sendMessage($chatId, $msg, true);
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
        
        sendMessage($chatId, $msg, true);
    } else {
        sendMessage($chatId, "❌ Unable to generate deposit address. Please try again later.", true);
    }
}

function handleInvite($chatId) {
    $msg = REFERRAL_TEXT;
    if (empty($msg)) {
        $msg = "💸 <b>Invite Friends & Earn Rewards!</b>\n\n";
        $msg .= "Share your referral link and earn points when your friends join!";
    }
    sendMessage($chatId, $msg, true);
}

function handleSupport($chatId) {
    $msg = "🧑‍💻 <b>Need Help?</b>\n\n";
    $msg .= "Our support team is here to assist you!\n\n";
    
    if (!empty(SUPPORT_URL)) {
        $msg .= "👉 <a href='" . SUPPORT_URL . "'>Contact Support</a>";
    } else {
        $msg .= "Please contact our support team for assistance.";
    }
    
    sendMessage($chatId, $msg, true);
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

function sendMessage($chatId, $text, $showKeyboard = false) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    error_log("SendMessage URL: " . substr($url, 0, 50) . "... (token length: " . strlen(BOT_TOKEN) . ")");
    
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
                $msg .= "Reply with the number:\n\n1️⃣ National ID\n2️⃣ Government ID\n3️⃣ Passport";
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
                    '2' => 'GOVERNMENT_ID',
                    '3' => 'PASSPORT'
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
            if (!isset($idTypeMap[$selectedNumber])) {
                $msg = "❌ Invalid selection. Please reply with 1, 2, or 3.";
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
        $stmt = $pdo->prepare("SELECT * FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        return $stmt->execute([$customerId, $kycStatus, $userId]);
    } catch (PDOException $e) {
        error_log("Error marking registration complete: " . $e->getMessage());
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
    
    error_log("Calling StroWallet API with data: " . json_encode([
        'endpoint' => '/bitvcard/create-user/',
        'email' => $customerData['customerEmail'],
        'phone' => $customerData['phoneNumber'],
        'name' => $customerData['firstName'] . ' ' . $customerData['lastName']
    ]));
    
    // Call StroWallet create-user API
    $result = callStroWalletAPI('/bitvcard/create-user/', 'POST', $customerData, true);
    
    if (isset($result['error'])) {
        error_log("ERROR: StroWallet API call failed: " . $result['error']);
    } else {
        error_log("SUCCESS: StroWallet API call succeeded - Customer ID: " . ($result['customer_id'] ?? 'N/A'));
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
            $prompt .= "1️⃣ National ID\n2️⃣ Government ID\n3️⃣ Passport";
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
