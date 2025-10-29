<?php
/**
 * Enhanced Deposit Handler with Automatic Verification
 * 
 * Features:
 * - Accept receipt URLs from users
 * - Automatic payment verification
 * - Auto-approval without manual admin intervention
 * - StroWallet account crediting
 */

require_once __DIR__ . '/AutoDepositProcessor.php';
require_once __DIR__ . '/PaymentService.php';

/**
 * Get Auto Deposit Processor instance
 */
function getAutoDepositProcessor() {
    $db = getDBConnection();
    if (!$db) {
        return null;
    }
    
    return new AutoDepositProcessor($db, BOT_TOKEN);
}

/**
 * Handle deposit transaction submission (can be ID or URL)
 */
function handleDepositTransactionSubmission($chatId, $userId, $transactionInput) {
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Database error. Please try again later.", false);
        return;
    }
    
    try {
        // Get payment ID from temp data
        $stmt = $db->prepare("SELECT temp_data FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || !$row['temp_data']) {
            sendMessage($chatId, "❌ Payment session expired. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        $depositData = json_decode($row['temp_data'], true);
        $paymentId = $depositData['payment_id'] ?? null;
        
        if (!$paymentId) {
            sendMessage($chatId, "❌ Payment information not found. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        // Check if input is a URL (receipt link)
        $isUrl = filter_var(trim($transactionInput), FILTER_VALIDATE_URL);
        
        if ($isUrl) {
            // Process with automatic verification
            handleDepositReceiptUrl($chatId, $userId, $paymentId, trim($transactionInput), $depositData);
        } else {
            // Process as transaction ID (old method - manual review)
            handleDepositTransactionId_v2($chatId, $userId, trim($transactionInput));
        }
        
    } catch (Exception $e) {
        error_log("Error in handleDepositTransactionSubmission: " . $e->getMessage());
        sendMessage($chatId, "❌ An error occurred. Please try again.", false);
    }
}

/**
 * Handle deposit receipt URL - automatic verification
 */
function handleDepositReceiptUrl(int $chatId, int $userId, int $paymentId, string $receiptUrl, array $depositData) {
    $db = getDBConnection();
    
    // Show processing message
    $processingMsg = "🔄 <b>Verifying your payment...</b>\n\n";
    $processingMsg .= "Please wait while we automatically verify your receipt.";
    sendMessage($chatId, $processingMsg, false);
    
    // Get auto deposit processor
    $processor = getAutoDepositProcessor();
    if (!$processor) {
        sendMessage($chatId, "❌ Service temporarily unavailable.", false);
        return;
    }
    
    // Process deposit with automatic verification
    $result = $processor->processDepositWithReceipt($paymentId, $receiptUrl, true);
    
    // Clear deposit state
    setUserDepositState($userId, null);
    
    // Clear temp data
    $stmt = $db->prepare("UPDATE user_registrations SET temp_data = NULL WHERE telegram_user_id = ?");
    $stmt->execute([$userId]);
    
    if ($result['success']) {
        // Auto-approved!
        $msg = "✅ <b>Payment Verified & Approved!</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "💰 <b>Amount Credited:</b> $" . number_format($result['amount_usd'], 2) . " USD\n";
        $msg .= "🔖 <b>Transaction ID:</b> <code>" . htmlspecialchars($result['transaction_id']) . "</code>\n";
        $msg .= "📱 <b>Method:</b> " . ($depositData['payment_method'] ?? 'N/A') . "\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "✨ <b>Verification Status:</b>\n";
        $msg .= "✓ Amount verified\n";
        $msg .= "✓ Receiver verified\n";
        $msg .= "✓ Date/time verified\n";
        $msg .= "✓ Payment processed\n\n";
        $msg .= "💳 Your account has been credited automatically!\n\n";
        $msg .= "You can now use your balance to create virtual cards.";
        
        sendMessage($chatId, $msg, true, $userId);
    } else {
        // Verification failed - send to manual review
        $msg = "⚠️ <b>Automatic Verification Failed</b>\n\n";
        $msg .= "📋 <b>Reason:</b> " . ($result['message'] ?? 'Unknown error') . "\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📤 <b>Sent to Manual Review</b>\n\n";
        $msg .= "• Your payment details have been submitted\n";
        $msg .= "• Our admin team will review it manually\n";
        $msg .= "• You'll be notified once approved\n\n";
        $msg .= "💬 Contact support if you need help.";
        
        sendMessage($chatId, $msg, true, $userId);
        
        // Update payment to manual review
        $stmt = $db->prepare("
            UPDATE deposit_payments 
            SET status = 'pending_review',
                receipt_url = :receipt_url,
                validation_status = 'failed_auto_verification',
                rejected_reason = :reason
            WHERE id = :payment_id
        ");
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':receipt_url' => $receiptUrl,
            ':reason' => $result['message'] ?? 'Auto-verification failed'
        ]);
        
        // Notify admin for manual review
        $adminChatId = getenv('ADMIN_CHAT_ID');
        if (!empty($adminChatId) && $adminChatId !== 'your_telegram_admin_chat_id_for_alerts') {
            $adminMsg = "⚠️ <b>Deposit Needs Manual Review</b>\n\n";
            $adminMsg .= "👤 User ID: {$userId}\n";
            $adminMsg .= "💵 Amount: $" . number_format($depositData['usd_amount'], 2) . " USD\n";
            $adminMsg .= "💸 Total Paid: " . number_format($depositData['etb_amount'], 2) . " ETB\n";
            $adminMsg .= "📱 Method: " . ($depositData['payment_method'] ?? 'N/A') . "\n\n";
            $adminMsg .= "🔗 Receipt URL: <code>" . htmlspecialchars($receiptUrl) . "</code>\n\n";
            $adminMsg .= "❌ Auto-verification failed:\n" . ($result['message'] ?? 'Unknown error') . "\n\n";
            $adminMsg .= "⏳ Please verify in admin panel.";
            
            sendMessage($adminChatId, $adminMsg, false);
        }
    }
}

/**
 * Updated handleDepositTransactionId to support both URL and ID
 * This is backward compatible with the old system
 */
function handleDepositTransactionIdEnhanced($chatId, $userId, $input) {
    // Route to the new handler that supports both URLs and transaction IDs
    handleDepositTransactionSubmission($chatId, $userId, $input);
}

/**
 * Modified handleDepositScreenshot to ask for receipt URL or transaction ID
 */
function handleDepositScreenshot_auto($chatId, $userId, $fileId) {
    $db = getDBConnection();
    if (!$db) {
        sendMessage($chatId, "❌ Database error. Please try again later.", false);
        return;
    }
    
    try {
        // Get payment ID from temp data
        $stmt = $db->prepare("SELECT temp_data FROM user_registrations WHERE telegram_user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || !$row['temp_data']) {
            sendMessage($chatId, "❌ Payment session expired. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        $depositData = json_decode($row['temp_data'], true);
        $paymentId = $depositData['payment_id'] ?? null;
        
        if (!$paymentId) {
            sendMessage($chatId, "❌ Payment information not found. Please start over.", false);
            setUserDepositState($userId, null);
            return;
        }
        
        // Get file URL (optional)
        $fileUrl = getFileUrl($fileId);
        
        // Update payment with screenshot via PaymentService
        $paymentService = getPaymentService();
        if (!$paymentService) {
            sendMessage($chatId, "❌ Service temporarily unavailable.", false);
            return;
        }
        
        $result = $paymentService->addScreenshot($paymentId, $fileId, $fileUrl);
        
        if (!$result['success']) {
            sendMessage($chatId, "❌ Failed to save screenshot. Please try again.", false);
            return;
        }
        
        // Change state to await transaction information
        setUserDepositState($userId, 'awaiting_transaction_id');
        
        // Ask for receipt URL or transaction ID
        $msg = "✅ <b>Screenshot received!</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "📝 Now please send ONE of the following:\n\n";
        $msg .= "🔗 <b>Option 1: Receipt URL (Recommended)</b>\n";
        $msg .= "Send the receipt link from:\n";
        $msg .= "• TeleBirr receipt URL\n";
        $msg .= "• CBE receipt URL\n";
        $msg .= "• M-Pesa receipt link\n\n";
        $msg .= "✨ <i>Receipt URLs enable instant automatic verification!</i>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🔖 <b>Option 2: Transaction ID</b>\n";
        $msg .= "Send your transaction reference number\n";
        $msg .= "• Example: TXN123456789\n\n";
        $msg .= "<i>Note: Transaction IDs require manual admin review</i>";
        
        sendMessage($chatId, $msg, false);
        
    } catch (Exception $e) {
        error_log("Error in handleDepositScreenshot_auto: " . $e->getMessage());
        sendMessage($chatId, "❌ An error occurred. Please try again.", false);
    }
}
