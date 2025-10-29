<?php
/**
 * Enhanced Payment Verification Service
 * Supports both API validation and Receipt URL verification
 * 
 * Features:
 * - TeleBirr/M-Pesa/CBE transaction validation via external API
 * - Receipt URL parsing and verification (new)
 * - Date/time matching for receipts
 * - PostgreSQL integration
 */

require_once __DIR__ . '/ReceiptVerification/ReceiptVerifier.php';

class PaymentServiceEnhanced
{
    private $pdo;
    private string $validationApiBase;
    private string $botToken;
    private ReceiptVerifier $receiptVerifier;
    
    public function __construct($pdo, string $validationApiBase, string $botToken)
    {
        $this->pdo = $pdo;
        $this->validationApiBase = rtrim($validationApiBase, '/');
        $this->botToken = $botToken;
        $this->receiptVerifier = new ReceiptVerifier();
    }
    
    /**
     * NEW: Verify payment via receipt URL
     * Returns parsed transaction details if valid
     */
    public function verifyByReceiptUrl(string $url, bool $mustBeToday = true): array
    {
        $match = [];
        if ($mustBeToday) {
            $match['mustBeToday'] = true;
        }
        
        $result = $this->receiptVerifier->verifyByUrl($url);
        
        if (!$result['ok']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Receipt verification failed',
                'details' => $result
            ];
        }
        
        $parsed = $result['parsed'] ?? [];
        
        if (empty($parsed['transaction_id'])) {
            return [
                'success' => false,
                'message' => 'Transaction ID not found in receipt'
            ];
        }
        
        if (empty($parsed['amount'])) {
            return [
                'success' => false,
                'message' => 'Amount not found in receipt'
            ];
        }
        
        $dateMatch = $this->evaluateDateMatch($parsed, $match);
        
        if (!$dateMatch['ok']) {
            return [
                'success' => false,
                'message' => 'Receipt date verification failed: ' . ($dateMatch['reason'] ?? 'unknown'),
                'date_info' => $dateMatch
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Receipt verified successfully',
            'transaction_id' => $parsed['transaction_id'],
            'amount' => (float)$parsed['amount'],
            'currency' => $parsed['currency'] ?? 'ETB',
            'source' => $parsed['source'] ?? 'unknown',
            'date' => $parsed['date'] ?? null,
            'date_iso' => $parsed['date_iso'] ?? null,
            'date_match' => $dateMatch,
            'parsed_data' => $parsed
        ];
    }
    
    /**
     * NEW: Process deposit from receipt URL
     * Verifies receipt and creates deposit if valid
     */
    public function processDepositByReceiptUrl(
        int $paymentId,
        string $receiptUrl,
        bool $mustBeToday = true
    ): array {
        $verification = $this->verifyByReceiptUrl($receiptUrl, $mustBeToday);
        
        if (!$verification['success']) {
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET validation_status = 'rejected',
                    status = 'rejected',
                    rejected_reason = :reason,
                    verification_attempts = verification_attempts + 1
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':reason' => $verification['message']
            ]);
            
            return $verification;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id");
        $stmt->execute([':payment_id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }
        
        $verifiedAmountETB = $verification['amount'];
        $expectedAmountETB = $payment['total_etb'];
        
        if (abs($verifiedAmountETB - $expectedAmountETB) > 1.0) {
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET validation_status = 'rejected',
                    status = 'rejected',
                    rejected_reason = :reason,
                    verification_attempts = verification_attempts + 1
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':reason' => "Amount mismatch: expected {$expectedAmountETB} ETB, got {$verifiedAmountETB} ETB"
            ]);
            
            return [
                'success' => false,
                'message' => 'Amount mismatch',
                'expected' => $expectedAmountETB,
                'received' => $verifiedAmountETB
            ];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM deposit_payments 
                WHERE transaction_number = :txn 
                AND status = 'completed'
            ");
            $stmt->execute([':txn' => $verification['transaction_id']]);
            
            if ($stmt->fetchColumn() > 0) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Duplicate transaction'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE deposit_payments 
                SET transaction_number = :txn,
                    validation_status = 'verified',
                    status = 'verified',
                    validation_response = :response,
                    verified_at = NOW(),
                    verification_attempts = verification_attempts + 1,
                    receipt_url = :url
                WHERE id = :payment_id
            ");
            
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':txn' => $verification['transaction_id'],
                ':response' => json_encode($verification),
                ':url' => $receiptUrl
            ]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Receipt verified and payment updated',
                'transaction_id' => $verification['transaction_id'],
                'amount_etb' => $verifiedAmountETB,
                'verification_data' => $verification
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Evaluate date matching rules
     */
    private function evaluateDateMatch(array $parsed, array $match): array
    {
        $tz = getenv('APP_TZ') ?: 'Africa/Addis_Ababa';
        $result = ['ok' => null, 'reason' => null];
        $dateIso = $parsed['date_iso'] ?? null;
        $dateLocal = $parsed['date_local'] ?? null;
        $result['parsed_date_iso'] = $dateIso;
        $result['parsed_date_local'] = $dateLocal;
        
        if (!$dateIso && !$dateLocal) {
            $result['ok'] = false;
            $result['reason'] = 'No date found in receipt';
            return $result;
        }
        
        try {
            $receipt = new DateTimeImmutable($dateIso ?? $dateLocal);
            $receiptUtc = $receipt->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $result['ok'] = false;
            $result['reason'] = 'Failed to parse receipt date';
            return $result;
        }
        
        if (!empty($match['mustBeToday'])) {
            $now = new DateTimeImmutable('now', new DateTimeZone($tz));
            $result['today_local'] = $now->format('Y-m-d');
            $receiptLocal = $receiptUtc->setTimezone(new DateTimeZone($tz));
            $result['receipt_local_date'] = $receiptLocal->format('Y-m-d');
            $result['ok'] = ($result['receipt_local_date'] === $result['today_local']);
            if (!$result['ok']) $result['reason'] = 'Receipt is not from today (local)';
        }
        
        if (!empty($match['dateBetween']) && is_array($match['dateBetween'])) {
            $from = $match['dateBetween']['from'] ?? null;
            $to = $match['dateBetween']['to'] ?? null;
            if ($from) $result['window_from'] = $from;
            if ($to) $result['window_to'] = $to;
            try {
                $ok = true;
                if ($from) {
                    $fromDt = new DateTimeImmutable($from);
                    if ($receiptUtc < $fromDt) $ok = false;
                }
                if ($to) {
                    $toDt = new DateTimeImmutable($to);
                    if ($receiptUtc > $toDt) $ok = false;
                }
                $result['ok'] = ($result['ok'] === null) ? $ok : ($result['ok'] && $ok);
                if (!$ok) $result['reason'] = 'Receipt date outside allowed window';
            } catch (Exception $e) {
                $result['ok'] = false;
                $result['reason'] = 'Invalid dateBetween window';
            }
        }
        
        if ($result['ok'] === null) {
            $result['ok'] = true;
            $result['reason'] = 'No date matching rule applied';
        }
        return $result;
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
     * OLD METHOD: Validate transaction via external API
     * Kept for backward compatibility
     */
    public function addTransactionAndVerify(int $paymentId, string $transactionNumber): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id");
            $stmt->execute([':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                return ['success' => false, 'message' => 'Payment not found'];
            }
            
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
            
            $validationResult = $this->validateTransaction(
                $payment['payment_method'],
                $transactionNumber
            );
            
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
    
    private function validateTransaction(string $provider, string $transactionNumber): array
    {
        $provider = strtolower(str_replace(['_', '-'], '', $provider));
        if ($provider === 'mpesa') {
            $provider = 'm-pesa';
        }
        
        $url = $this->validationApiBase . '/api/' . $provider . '/' . urlencode($transactionNumber);
        
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
    
    public function processVerifiedDeposit(int $paymentId): array
    {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id AND validation_status = 'verified' AND status = 'verified'");
            $stmt->execute([':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Payment not found or not verified'];
            }
            
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
    
    public function getPayment(int $paymentId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM deposit_payments WHERE id = :payment_id");
        $stmt->execute([':payment_id' => $paymentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
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
