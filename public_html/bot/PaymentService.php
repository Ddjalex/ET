<?php
/**
 * Payment Verification Service for TeleBirr, M-Pesa, and CBE
 * Adapted for PostgreSQL and Telegram Bot integration
 */

class PaymentService
{
    private $pdo;
    private string $validationApiBase;
    private string $botToken;
    
    public function __construct($pdo, string $validationApiBase, string $botToken)
    {
        $this->pdo = $pdo;
        $this->validationApiBase = rtrim($validationApiBase, '/');
        $this->botToken = $botToken;
    }
    
    /**
     * Create a new deposit payment record
     */
    public function createDepositPayment(
        int $userId,
        int $telegramId,
        float $amountUSD,
        string $paymentMethod,
        float $exchangeRate = 135.00,
        float $depositFeeETB = 500.00
    ): array {
        try {
            $amountETB = $amountUSD * $exchangeRate;
            $totalETB = $amountETB + $depositFeeETB;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO deposit_payments 
                (user_id, telegram_id, amount_usd, amount_etb, exchange_rate, deposit_fee_etb, total_etb, payment_method, status)
                VALUES (:user_id, :telegram_id, :amount_usd, :amount_etb, :exchange_rate, :deposit_fee, :total_etb, :method, 'pending')
                RETURNING id, total_etb, amount_etb, deposit_fee_etb
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':telegram_id' => $telegramId,
                ':amount_usd' => $amountUSD,
                ':amount_etb' => $amountETB,
                ':exchange_rate' => $exchangeRate,
                ':deposit_fee' => $depositFeeETB,
                ':total_etb' => $totalETB,
                ':method' => strtolower($paymentMethod)
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'payment_id' => $result['id'],
                'amount_etb' => $result['amount_etb'],
                'deposit_fee_etb' => $result['deposit_fee_etb'],
                'total_etb' => $result['total_etb']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update payment with screenshot
     */
    public function addScreenshot(int $paymentId, string $fileId, ?string $fileUrl = null): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET screenshot_file_id = :file_id, 
                    screenshot_url = :file_url,
                    status = 'screenshot_submitted'
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':file_id' => $fileId,
                ':file_url' => $fileUrl
            ]);
            
            return ['success' => true, 'message' => 'Screenshot saved'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to save screenshot: ' . $e->getMessage()];
        }
    }
    
    /**
     * Add transaction ID to payment (for manual review)
     */
    public function addTransactionId(int $paymentId, string $transactionNumber): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET transaction_number = :txn, 
                    status = 'pending_review'
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':txn' => $transactionNumber
            ]);
            
            return ['success' => true, 'message' => 'Transaction ID saved'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to save transaction ID: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update payment with transaction number and verify
     */
    public function addTransactionAndVerify(int $paymentId, string $transactionNumber): array
    {
        try {
            // Get payment details
            $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id");
            $stmt->execute([':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['success' => false, 'message' => 'Payment not found'];
            }
            
            // Update with transaction number
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET transaction_number = :txn, 
                    status = 'transaction_submitted',
                    validation_status = 'validating'
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':txn' => $transactionNumber
            ]);
            
            // Validate transaction based on payment method
            $validationResult = $this->validateTransaction(
                $payment['payment_method'],
                $transactionNumber
            );
            
            // Update validation status
            $validationStatus = $validationResult['success'] ? 'verified' : 'rejected';
            $paymentStatus = $validationResult['success'] ? 'verified' : 'rejected';
            
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET validation_status = :val_status,
                    status = :status,
                    validation_response = :response,
                    verification_attempts = verification_attempts + 1,
                    verified_at = :verified_at,
                    rejected_reason = :rejected_reason
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':val_status' => $validationStatus,
                ':status' => $paymentStatus,
                ':response' => json_encode($validationResult),
                ':verified_at' => $validationResult['success'] ? date('Y-m-d H:i:s') : null,
                ':rejected_reason' => $validationResult['success'] ? null : ($validationResult['message'] ?? 'Verification failed')
            ]);
            
            return $validationResult;
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to verify transaction: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate transaction via external API
     */
    private function validateTransaction(string $provider, string $transactionNumber): array
    {
        // Normalize provider name
        $provider = strtolower(str_replace(['_', '-'], '', $provider));
        if ($provider === 'mpesa') {
            $provider = 'm-pesa';
        }
        
        $url = $this->validationApiBase . '/api/' . $provider . '/' . urlencode($transactionNumber);
        
        // Try POST first, then fallback to GET
        $response = $this->httpRequest('POST', $url);
        if ($response['error'] || $response['status'] >= 400) {
            $response = $this->httpRequest('GET', $url);
        }
        
        if ($response['error']) {
            return [
                'success' => false,
                'message' => 'Validation API error: ' . $response['error'],
                'status' => $response['status']
            ];
        }
        
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return [
                'success' => false,
                'message' => 'Invalid response from validation API'
            ];
        }
        
        // Check if validation was successful
        $ok = (bool)($data['ok'] ?? false);
        if (!$ok) {
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Transaction validation failed',
                'data' => $data
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Transaction verified successfully',
            'data' => $data,
            'amount' => $data['amount'] ?? null,
            'txId' => $data['txId'] ?? null
        ];
    }
    
    /**
     * Process verified deposit - credit user's StroWallet
     */
    public function processVerifiedDeposit(int $paymentId): array
    {
        try {
            $this->pdo->beginTransaction();
            
            // Get payment details
            $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id AND validation_status = 'verified' AND status = 'verified'");
            $stmt->execute([':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Payment not found or not verified'];
            }
            
            // Record in deposits table
            $stmt = $this->pdo->prepare("
                INSERT INTO deposits 
                (user_id, amount_usd, amount_local, exchange_rate, payment_method, transaction_ref, status)
                VALUES (:user_id, :amount_usd, :amount_local, :exchange_rate, :method, :txn, 'completed')
            ");
            
            $stmt->execute([
                ':user_id' => $payment['user_id'],
                ':amount_usd' => $payment['amount_usd'],
                ':amount_local' => $payment['total_etb'],
                ':exchange_rate' => $payment['exchange_rate'],
                ':method' => $payment['payment_method'],
                ':txn' => $payment['transaction_number']
            ]);
            
            // Update payment status
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET status = 'completed', completed_at = NOW()
                WHERE id = :payment_id
            ");
            $stmt->execute([':payment_id' => $paymentId]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Deposit processed successfully',
                'amount_usd' => $payment['amount_usd']
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Failed to process deposit: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get payment by ID
     */
    public function getPayment(int $paymentId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id");
        $stmt->execute([':payment_id' => $paymentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get pending payment for user
     */
    public function getPendingPayment(int $telegramId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM deposit_payments 
            WHERE telegram_id = :telegram_id 
            AND status IN ('pending', 'screenshot_submitted', 'transaction_submitted')
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':telegram_id' => $telegramId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Cancel payment
     */
    public function cancelPayment(int $paymentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET status = 'cancelled'
                WHERE id = :payment_id
            ");
            $stmt->execute([':payment_id' => $paymentId]);
            
            return ['success' => true, 'message' => 'Payment cancelled'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to cancel payment: ' . $e->getMessage()];
        }
    }
    
    /**
     * HTTP request helper with TLS verification ALWAYS enabled
     */
    private function httpRequest(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'User-Agent: TelegramBot/1.0',
            'Connection: keep-alive'
        ];
        
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            // SECURITY: TLS verification ALWAYS enabled
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];
        
        if ($payload) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $status ?: 0,
            'error' => $err ?: '',
            'body' => $body ?: ''
        ];
    }
}
