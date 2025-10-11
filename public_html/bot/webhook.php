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

// Configuration - Use environment variables (Replit Secrets)
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
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
        $pdo = new PDO(
            "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/'),
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

// Debug log
error_log("Webhook received: " . $input);
error_log("BOT_TOKEN exists: " . (BOT_TOKEN ? 'YES' : 'NO'));
error_log("Chat ID: " . ($chatId ?? 'NONE'));
error_log("Message text: " . ($text ?? 'NONE'));

if (!$update) {
    http_response_code(400);
    die('Invalid JSON');
}

// Extract message data
$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = trim($message['text'] ?? '');
$userId = $message['from']['id'] ?? null;

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
        promptForCurrentField($chatId, $userState);
    } else {
        sendMessage($chatId, "Nothing to continue. Use /register to start.", true);
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

// If user is in registration flow (not idle), route to registration handler
if ($userState && $userState !== 'idle' && $userState !== 'completed') {
    handleRegistrationFlow($chatId, $userId, $text, $userState);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Route commands and button presses
if ($text === '/start' || $text === '🏠 Menu') {
    handleStart($chatId);
} elseif ($text === '/register') {
    handleRegisterStart($chatId, $userId);
} elseif ($text === '/quickregister') {
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

// ==================== COMMAND HANDLERS ====================

function handleStart($chatId) {
    $welcomeMsg = "🎉 <b>Welcome to Crypto Card Bot!</b>\n\n";
    $welcomeMsg .= "🚀 Manage your virtual cards and crypto wallet with ease.\n\n";
    $welcomeMsg .= "📱 Use the menu below to get started:";
    
    sendMessage($chatId, $welcomeMsg, true);
}

function handleRegisterStart($chatId, $userId) {
    // Check if already registered
    $userData = getUserRegistrationData($userId);
    if ($userData && $userData['is_registered']) {
        $msg = "✅ <b>Already Registered!</b>\n\n";
        $msg .= "You're all set! You can now create cards using ➕ <b>Create Card</b>";
        sendMessage($chatId, $msg, true);
        return;
    }
    
    // Check if in progress
    if ($userData && $userData['registration_state'] !== 'idle' && $userData['registration_state'] !== 'completed') {
        $msg = "⏳ <b>Registration In Progress</b>\n\n";
        $msg .= "You have an incomplete registration.\n\n";
        $msg .= "Choose an option:\n";
        $msg .= "• Send /continue to resume\n";
        $msg .= "• Send /cancel to start over";
        sendMessage($chatId, $msg, true);
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
    
    // Check if user is registered in database
    $userData = getUserRegistrationData($userId);
    $customerEmail = null;
    
    if ($userData && $userData['is_registered']) {
        // Use email from user's registration
        $customerEmail = $userData['customer_email'];
    } else {
        // Fallback to configured email from secrets
        $customerEmail = STROWALLET_EMAIL;
    }
    
    if (empty($customerEmail)) {
        sendMessage($chatId, "❌ <b>Configuration Error</b>\n\nSTROWALLET_EMAIL is not configured. Please contact administrator.", true);
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
    
    // Try without Authorization header - only public_key in body
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
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

function handleRegistrationFlow($chatId, $userId, $text, $currentState) {
    sendTypingAction($chatId);
    
    switch ($currentState) {
        case 'awaiting_first_name':
            if (strlen($text) < 2) {
                sendMessage($chatId, "❌ Please enter a valid first name (at least 2 characters).", false);
                return;
            }
            updateUserField($userId, 'first_name', $text);
            updateUserRegistrationState($userId, 'awaiting_last_name');
            sendMessage($chatId, "✅ Got it!\n\n👤 <b>What's your last name?</b>", false);
            break;
            
        case 'awaiting_last_name':
            if (strlen($text) < 2) {
                sendMessage($chatId, "❌ Please enter a valid last name (at least 2 characters).", false);
                return;
            }
            updateUserField($userId, 'last_name', $text);
            updateUserRegistrationState($userId, 'awaiting_dob');
            $msg = "✅ Great!\n\n📅 <b>What's your date of birth?</b>\n\n";
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
            updateUserField($userId, 'phone_number', $phone);
            updateUserRegistrationState($userId, 'awaiting_email');
            sendMessage($chatId, "✅ Good!\n\n📧 <b>What's your email address?</b>", false);
            break;
            
        case 'awaiting_email':
            if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                sendMessage($chatId, "❌ Invalid email address. Please enter a valid email.", false);
                return;
            }
            updateUserField($userId, 'customer_email', $text);
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
            updateUserField($userId, 'city', $text);
            updateUserRegistrationState($userId, 'awaiting_state');
            sendMessage($chatId, "✅ Great!\n\n🗺️ <b>Which state/province?</b>", false);
            break;
            
        case 'awaiting_state':
            updateUserField($userId, 'state', $text);
            updateUserRegistrationState($userId, 'awaiting_zip');
            sendMessage($chatId, "✅ Good!\n\n📮 <b>What's your ZIP/postal code?</b>", false);
            break;
            
        case 'awaiting_zip':
            updateUserField($userId, 'zip_code', $text);
            updateUserRegistrationState($userId, 'awaiting_country');
            $msg = "✅ Perfect!\n\n🌍 <b>Country (2-letter code)?</b>\n\n";
            $msg .= "Examples: NG, US, UK, CA";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_country':
            $country = strtoupper(substr($text, 0, 2));
            if (strlen($country) !== 2) {
                sendMessage($chatId, "❌ Please enter a valid 2-letter country code (e.g., NG, US, UK)", false);
                return;
            }
            updateUserField($userId, 'country', $country);
            updateUserRegistrationState($userId, 'awaiting_id_type');
            $msg = "✅ Excellent!\n\n🆔 <b>What type of ID do you have?</b>\n\n";
            $msg .= "Options:\n• BVN\n• NIN\n• PASSPORT";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_id_type':
            $idType = strtoupper($text);
            if (!in_array($idType, ['BVN', 'NIN', 'PASSPORT'])) {
                sendMessage($chatId, "❌ Invalid ID type. Choose: BVN, NIN, or PASSPORT", false);
                return;
            }
            updateUserField($userId, 'id_type', $idType);
            updateUserRegistrationState($userId, 'awaiting_id_number');
            sendMessage($chatId, "✅ Got it!\n\n🔢 <b>What's your ID number?</b>", false);
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
            if (!filter_var($text, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\//i', $text)) {
                sendMessage($chatId, "❌ Please send a valid HTTPS URL to your ID image.", false);
                return;
            }
            updateUserField($userId, 'id_image_url', $text);
            updateUserRegistrationState($userId, 'awaiting_user_photo');
            $msg = "✅ Perfect!\n\n🤳 <b>Upload your selfie/photo</b>\n\n";
            $msg .= "Please send the HTTPS URL of your photo.\n";
            $msg .= "Example: https://example.com/selfie.jpg";
            sendMessage($chatId, $msg, false);
            break;
            
        case 'awaiting_user_photo':
            if (!filter_var($text, FILTER_VALIDATE_URL) || !preg_match('/^https:\/\//i', $text)) {
                sendMessage($chatId, "❌ Please send a valid HTTPS URL to your photo.", false);
                return;
            }
            updateUserField($userId, 'user_photo_url', $text);
            
            // All data collected, now create customer in StroWallet
            $result = createStroWalletCustomerFromDB($userId);
            
            if (isset($result['error'])) {
                updateUserRegistrationState($userId, 'failed');
                $msg = "❌ <b>Registration Failed</b>\n\n";
                $msg .= "Error: " . $result['error'] . "\n\n";
                $msg .= "Please check your information and try /register again.";
                sendMessage($chatId, $msg, true);
            } else {
                // Mark as completed
                markUserRegistrationComplete($userId, $result['customer_id'] ?? '');
                
                $msg = "✅ <b>Registration Successful!</b>\n\n";
                $msg .= "🎉 Your customer account has been created in StroWallet.\n\n";
                $msg .= "You can now create virtual cards using ➕ <b>Create Card</b>";
                sendMessage($chatId, $msg, true);
            }
            break;
    }
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
            INSERT INTO user_registrations (telegram_user_id, telegram_chat_id, registration_state)
            VALUES (?, ?, 'idle')
            ON CONFLICT (telegram_user_id) DO UPDATE SET 
                telegram_chat_id = EXCLUDED.telegram_chat_id,
                registration_state = 'idle',
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$userId, $chatId]);
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
    
    $allowedFields = ['first_name', 'last_name', 'date_of_birth', 'phone_number', 'customer_email',
                      'house_number', 'address_line1', 'city', 'state', 'zip_code', 'country',
                      'id_type', 'id_number', 'id_image_url', 'user_photo_url'];
    
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

function markUserRegistrationComplete($userId, $customerId = '') {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE user_registrations 
            SET registration_state = 'completed',
                is_registered = TRUE,
                strowallet_customer_id = ?,
                completed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP 
            WHERE telegram_user_id = ?
        ");
        return $stmt->execute([$customerId, $userId]);
    } catch (PDOException $e) {
        error_log("Error marking registration complete: " . $e->getMessage());
        return false;
    }
}

function createStroWalletCustomerFromDB($userId) {
    $userData = getUserRegistrationData($userId);
    
    if (!$userData) {
        return ['error' => 'User data not found'];
    }
    
    // Validate required fields
    $required = ['first_name', 'last_name', 'date_of_birth', 'phone_number', 'customer_email',
                 'house_number', 'address_line1', 'city', 'state', 'zip_code', 'country',
                 'id_type', 'id_number', 'id_image_url', 'user_photo_url'];
    
    foreach ($required as $field) {
        if (empty($userData[$field])) {
            return ['error' => "Missing required field: $field"];
        }
    }
    
    // Prepare customer data for StroWallet API
    $customerData = [
        'public_key' => STROW_PUBLIC_KEY,
        'houseNumber' => $userData['house_number'],
        'firstName' => $userData['first_name'],
        'lastName' => $userData['last_name'],
        'idNumber' => $userData['id_number'],
        'customerEmail' => $userData['customer_email'],
        'phoneNumber' => $userData['phone_number'],
        'dateOfBirth' => $userData['date_of_birth'],
        'idImage' => $userData['id_image_url'],
        'userPhoto' => $userData['user_photo_url'],
        'line1' => $userData['address_line1'],
        'state' => $userData['state'],
        'zipCode' => $userData['zip_code'],
        'city' => $userData['city'],
        'country' => $userData['country'],
        'idType' => $userData['id_type']
    ];
    
    // Call StroWallet create-user API
    return callStroWalletAPI('/bitvcard/create-user/', 'POST', $customerData, true);
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

function promptForCurrentField($chatId, $state) {
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
        'awaiting_country' => "🌍 <b>Country (2-letter code)?</b>\n\nExamples: NG, US, UK, CA",
        'awaiting_id_type' => "🆔 <b>What type of ID do you have?</b>\n\nOptions:\n• BVN\n• NIN\n• PASSPORT",
        'awaiting_id_number' => "🔢 <b>What's your ID number?</b>",
        'awaiting_id_image' => "📸 <b>Upload your ID document image</b>\n\nPlease send the HTTPS URL of your ID image.\nExample: https://example.com/id.jpg",
        'awaiting_user_photo' => "🤳 <b>Upload your selfie/photo</b>\n\nPlease send the HTTPS URL of your photo.\nExample: https://example.com/selfie.jpg"
    ];
    
    $prompt = $prompts[$state] ?? "Please provide the requested information.";
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
