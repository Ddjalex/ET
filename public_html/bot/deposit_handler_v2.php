<?php
/**
 * Deposit Handler - Manual Admin Review Only
 * All deposits are routed to the admin panel for manual verification
 */

/**
 * Updated processDepositAmount - Hide deposit fee calculation
 */
function processDepositAmount_v2($chatId, $userId, $amount) {
    // Clean and validate amount
    $cleanedAmount = str_replace([',', ' ', '$'], '', trim($amount));
    
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
    
    if ($exchangeRate <= 0) {
        error_log("Invalid exchange rate: $exchangeRate");
        $msg = "❌ <b>Configuration Error</b>\n\n";
        $msg .= "Exchange rate is not configured properly.\n\n";
        $msg .= "Please try again later or contact support.";
        sendMessage($chatId, $msg, false);
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
    
    // Clear deposit state
    setUserDepositState($userId, null);
    
    // Show deposit summary WITH total including fee
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // TLS verification enabled
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Updated handleUserDepositPaymentSelection - Create payment via PaymentService
 */
function handleUserDepositPaymentSelection_v2($chatId, $userId, $callbackData) {
    // Parse callback data
    $parts = explode('_', $callbackData);
    if (count($parts) < 5) {
        sendMessage($chatId, "❌ Invalid payment selection.", false);
        return;
    }
    
    $methodCode = $parts[2]; // telebirr, cbebirr, mpesa, bank
    $usdAmount = floatval($parts[3]);
    $etbAmount = floatval($parts[4]);
    
    // Get exchange rate and deposit fee from settings
    $db = getDBConnection();
    $exchangeRate = getExchangeRate();
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
    
    // Calculate total including fee (hidden from user)
    $totalEtbAmount = $etbAmount + $depositFee;
    
    // Get user ID from database
    $stmt = $db->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendMessage($chatId, "❌ User not found. Please register first.", false);
        return;
    }
    
    $dbUserId = $user['id'];
    
    // Map method codes
    $methodMap = [
        'telebirr' => 'telebirr',
        'cbebirr' => 'cbe',
        'mpesa' => 'm-pesa',
        'bank' => 'bank_transfer'
    ];
    
    $paymentMethod = $methodMap[$methodCode] ?? $methodCode;
    
    // Create payment record directly in database for manual review
    try {
        $stmt = $db->prepare("
            INSERT INTO deposit_payments (
                user_id, telegram_id, amount_usd, amount_etb, 
                exchange_rate, deposit_fee_etb, total_etb, 
                payment_method, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            RETURNING id
        ");
        
        $stmt->execute([
            $dbUserId,
            $userId,
            $usdAmount,
            $etbAmount,
            $exchangeRate,
            $depositFee,
            $totalEtbAmount,
            $paymentMethod
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            sendMessage($chatId, "❌ Failed to create payment. Please try again.", false);
            return;
        }
        
        $paymentId = $result['id'];
        
    } catch (Exception $e) {
        error_log("Error creating payment: " . $e->getMessage());
        sendMessage($chatId, "❌ Failed to create payment. Please try again.", false);
        return;
    }
    
    // Get payment accounts from database
    $paymentAccounts = [];
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
    
    // Get account details
    $accountKey = strtolower(str_replace(['-', ' '], '', $paymentMethod));
    $account = $paymentAccounts[$accountKey] ?? $paymentAccounts['telebirr'] ?? null;
    
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
    
    // Send payment instructions (WITHOUT showing fee breakdown)
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
    
    sendMessageWithReturn($chatId, $userMsg, '❌ Cancel Deposit', 'cancel');
    
    // Set user state to wait for screenshot
    setUserDepositState($userId, 'awaiting_screenshot');
    
    // Payment ID is stored in deposit_payments table, no need to store separately
    // We'll retrieve it later based on the most recent pending payment for this user
}

/**
 * Updated handleDepositScreenshot - Use PaymentService
 */
function handleDepositScreenshot_v2($chatId, $userId, $fileId) {
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Database error. Please try again later.", false);
        return;
    }
    
    // Get the most recent pending payment for this user
    try {
        $stmt = $db->prepare("
            SELECT id, amount_usd, total_etb, payment_method 
            FROM deposit_payments 
            WHERE telegram_id = ? AND status = 'pending' AND screenshot_file_id IS NULL
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            sendMessage($chatId, "❌ Payment session expired. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        $paymentId = $payment['id'];
        $depositData = [
            'payment_id' => $payment['id'],
            'usd_amount' => $payment['amount_usd'],
            'etb_amount' => $payment['total_etb'],
            'payment_method' => $payment['payment_method']
        ];
        
        // Get file URL (optional)
        $fileUrl = getFileUrl($fileId);
        
        // Update payment with screenshot directly in database
        try {
            $stmt = $db->prepare("
                UPDATE deposit_payments 
                SET screenshot_file_id = ?, screenshot_url = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$fileId, $fileUrl, $paymentId]);
        } catch (Exception $e) {
            error_log("Error saving screenshot: " . $e->getMessage());
            sendMessage($chatId, "❌ Failed to save screenshot. Please try again.", false);
            return;
        }
        
        // Change state to await transaction ID
        setUserDepositState($userId, 'awaiting_transaction_id');
        
        // Ask for transaction ID
        $msg = "✅ <b>Screenshot received!</b>\n\n";
        $msg .= "📝 Now please enter your <b>Transaction ID</b> or <b>Reference Number</b>.\n\n";
        $msg .= "💡 <i>Example: TXN123456789</i>";
        
        sendMessage($chatId, $msg, false);
        
    } catch (Exception $e) {
        error_log("Error in handleDepositScreenshot_v2: " . $e->getMessage());
        sendMessage($chatId, "❌ An error occurred. Please try again.", false);
    }
}

/**
 * Updated handleDepositTransactionId - Verify transaction via PaymentService
 */
function handleDepositTransactionId_v2($chatId, $userId, $transactionId) {
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Database error. Please try again later.", false);
        return;
    }
    
    try {
        // Get the most recent pending payment for this user
        $stmt = $db->prepare("
            SELECT id, amount_usd, total_etb, payment_method 
            FROM deposit_payments 
            WHERE telegram_id = ? AND status = 'pending' AND screenshot_file_id IS NOT NULL
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            sendMessage($chatId, "❌ Payment session expired. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        $paymentId = $payment['id'];
        $depositData = [
            'payment_id' => $payment['id'],
            'usd_amount' => $payment['amount_usd'],
            'etb_amount' => $payment['total_etb'],
            'payment_method' => $payment['payment_method']
        ];
        
        // Add transaction ID to payment record - all deposits go to manual review
        try {
            $stmt = $db->prepare("
                UPDATE deposit_payments 
                SET transaction_number = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([trim($transactionId), $paymentId]);
        } catch (Exception $e) {
            error_log("Error saving transaction ID: " . $e->getMessage());
            sendMessage($chatId, "❌ Failed to save transaction ID. Please try again.", false);
            return;
        }
        
        // Clear deposit state
        setUserDepositState($userId, null);
        
        // Send payment to manual review (no automatic verification)
        $msg = "✅ <b>Payment Submitted!</b>\n\n";
        $msg .= "💰 <b>Amount:</b> $" . number_format($depositData['usd_amount'], 2) . " USD\n";
        $msg .= "💸 <b>Total Paid:</b> " . number_format($depositData['etb_amount'], 2) . " ETB\n";
        $msg .= "📱 <b>Method:</b> " . ($depositData['payment_method'] ?? 'N/A') . "\n";
        $msg .= "🔖 <b>Transaction ID:</b> <code>" . htmlspecialchars($transactionId) . "</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "⏳ Your payment is under review\n\n";
        $msg .= "💬 Contact support if you need help.";
        
        sendMessage($chatId, $msg, true, $userId);
        
        // Notify admin for manual verification
        if (!empty(ADMIN_CHAT_ID) && ADMIN_CHAT_ID !== 'your_telegram_admin_chat_id_for_alerts') {
            $adminMsg = "💰 <b>New Deposit - Manual Review</b>\n\n";
            $adminMsg .= "👤 User ID: {$userId}\n";
            $adminMsg .= "💵 Amount: $" . number_format($depositData['usd_amount'], 2) . " USD\n";
            $adminMsg .= "💸 Total Paid: " . number_format($depositData['etb_amount'], 2) . " ETB\n";
            $adminMsg .= "📱 Method: " . ($depositData['payment_method'] ?? 'N/A') . "\n";
            $adminMsg .= "🔖 Transaction: <code>" . htmlspecialchars($transactionId) . "</code>\n\n";
            $adminMsg .= "📸 Screenshot and transaction details submitted.\n\n";
            $adminMsg .= "⏳ Please verify in admin panel.";
            
            sendMessage(ADMIN_CHAT_ID, $adminMsg, false);
        }
        
    } catch (Exception $e) {
        error_log("Error in handleDepositTransactionId_v2: " . $e->getMessage());
        setUserDepositState($userId, null);
        
        $msg = "❌ <b>An error occurred</b>\n\n";
        $msg .= "Please try again or contact support.";
        sendMessage($chatId, $msg, false);
    }
}

/**
 * Helper function to get file URL from Telegram
 */
function getFileUrl($fileId) {
    if (empty($fileId) || empty(BOT_TOKEN)) {
        return null;
    }
    
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getFile?file_id=' . urlencode($fileId);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data['result']['file_path'])) {
        return 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $data['result']['file_path'];
    }
    
    return null;
}
