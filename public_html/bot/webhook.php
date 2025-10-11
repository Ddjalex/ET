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

// Route commands and button presses
if ($text === '/start' || $text === '🏠 Menu') {
    handleStart($chatId);
} elseif ($text === '/register') {
    handleRegister($chatId);
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

function handleRegister($chatId) {
    $msg = "📝 <b>Customer Registration</b>\n\n";
    $msg .= "To create virtual cards, you need to register as a customer in StroWallet.\n\n";
    $msg .= "<b>Two Options:</b>\n\n";
    $msg .= "1️⃣ <b>Manual Registration (Recommended)</b>\n";
    $msg .= "   • Log into <a href='https://strowallet.com/dashboard'>StroWallet Dashboard</a>\n";
    $msg .= "   • Go to Card Holders → Create New\n";
    $msg .= "   • Complete full KYC verification\n";
    $msg .= "   • Use email: <code>" . STROWALLET_EMAIL . "</code>\n\n";
    $msg .= "2️⃣ <b>Quick Setup (Uses Environment Config)</b>\n";
    $msg .= "   • Uses pre-configured data from environment variables\n";
    $msg .= "   • Requires admin setup with real KYC documents\n";
    $msg .= "   • Send /quickregister to proceed\n\n";
    $msg .= "⚠️ <b>Important:</b> Real KYC documents are required for compliance.\n";
    $msg .= "Never use fake or placeholder data.";
    
    sendMessage($chatId, $msg, true);
}

function handleCreateCard($chatId, $userId) {
    sendTypingAction($chatId);
    
    // Use configured email from secrets
    $customerEmail = STROWALLET_EMAIL;
    
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
