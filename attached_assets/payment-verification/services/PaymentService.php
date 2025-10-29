<?php
namespace App\Services;

use PDO;
use Exception;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';

class PaymentService
{
    private PDO $pdo;
    private string $validationBase;

    public function __construct()
    {
        $this->pdo = \db();
        $this->validationBase = env('VALIDATION_API_BASE_URL', 'http://127.0.0.1:4001');
    }

    public function validateTelebirrTransaction(string $transactionNumber): array
    {
        return $this->callValidationApi('telebirr', $transactionNumber);
    }

    public function validateCBETransaction(string $transactionNumber): array
    {
        return $this->callValidationApi('cbe', $transactionNumber);
    }

    private function callValidationApi(string $provider, string $txn): array
    {
        $url = rtrim($this->validationBase, '/') . '/api/' . $provider . '/' . urlencode($txn);

        // Try POST first (to mimic Node logic), then fallback to GET
        $resp = $this->httpRequest('POST', $url);
        if ($resp['error'] && $resp['status'] >= 400) {
            $resp = $this->httpRequest('GET', $url);
        }
        if ($resp['error']) {
            return [
                'success' => false,
                'message' => 'Validation API error',
                'status'  => $resp['status'],
                'error'   => $resp['error'],
                'body'    => $resp['body'],
            ];
        }

        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Invalid JSON from validation API', 'raw' => $resp['body']];
        }

        // Expect { ok: bool, amount?: number, txId?: string, message?: string }
        $ok = (bool)($data['ok'] ?? false);
        if (!$ok) {
            return ['success' => false, 'message' => $data['message'] ?? 'Transaction not valid', 'data' => $data];
        }

        return ['success' => true, 'data' => $data];
    }

    public function processDeposit(int $userId, float $amount, string $method, ?string $transactionNumber): array
    {
        try {
            // Optionally validate before crediting (Telebirr / CBE)
            if ($method === 'telebirr' || $method === 'cbe') {
                $res = $method === 'telebirr'
                    ? $this->validateTelebirrTransaction($transactionNumber ?? '')
                    : $this->validateCBETransaction($transactionNumber ?? '');

                if (!$res['success']) {
                    return ['success' => false, 'message' => 'Deposit validation failed: ' . ($res['message'] ?? 'unknown')];
                }
            }

            $this->pdo->beginTransaction();

            // Credit balance
            $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + :amt WHERE id = :uid");
            $stmt->execute([':amt' => $amount, ':uid' => $userId]);

            // Insert transaction
            $stmt = $this->pdo->prepare("INSERT INTO transactions (user_id, type, method, amount, transaction_number, status, created_at)
                                         VALUES (:uid, 'deposit', :method, :amount, :txn, 'success', NOW())");
            $stmt->execute([
                ':uid' => $userId,
                ':method' => $method,
                ':amount' => $amount,
                ':txn' => $transactionNumber
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Deposit processed'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Deposit failed: ' . $e->getMessage()];
        }
    }

    public function createWithdrawalRequest(int $userId, float $amount, string $method, string $account, string $name): array
    {
        try {
            $this->pdo->beginTransaction();
            // Record the request (approval/payout handled manually or by another worker)
            $stmt = $this->pdo->prepare("INSERT INTO withdrawal_requests (user_id, amount, method, account, account_name, status, created_at)
                                         VALUES (:uid, :amount, :method, :account, :name, 'pending', NOW())");
            $stmt->execute([
                ':uid' => $userId,
                ':amount' => $amount,
                ':method' => $method,
                ':account' => $account,
                ':name' => $name,
            ]);
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Withdrawal request created'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Withdrawal request failed: ' . $e->getMessage()];
        }
    }

    // Optional manual credit path (e.g., admin approves deposit without API validation)
    public function processManualDeposit(int $userId, float $amount, string $note = ''): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + :amt WHERE id = :uid");
            $stmt->execute([':amt' => $amount, ':uid' => $userId]);

            $stmt = $this->pdo->prepare("INSERT INTO transactions (user_id, type, method, amount, transaction_number, status, note, created_at)
                                         VALUES (:uid, 'deposit', 'manual', :amount, NULL, 'success', :note, NOW())");
            $stmt->execute([ ':uid'=>$userId, ':amount'=>$amount, ':note'=>$note ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Manual deposit processed'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Manual deposit failed: ' . $e->getMessage()];
        }
    }

    private function httpRequest(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'User-Agent: PHPBotValidator/1.0',
            'Connection: keep-alive',
        ];

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            // Enable TLS verification by default (change only if your validation service is HTTP)
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($payload) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status ?: 0,
            'error'  => $err or '',
            'body'   => $body or '',
        ];
    }
}
