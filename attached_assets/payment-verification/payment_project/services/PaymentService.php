<?php
namespace App\Services;

use App\Config\DB;
use App\Config\Env;
use PDO;
use Exception;

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ReceiptVerifier.php';

class PaymentService {
    private PDO $pdo;
    private ReceiptVerifier $verifier;

    public function __construct() {
        $this->pdo = DB::pdo();
        $this->verifier = new ReceiptVerifier();
    }

    public function health(): array {
        return ['success'=>true,'message'=>'ok', 'version'=> Env::get('APP_VERSION','1.2.0')];
    }

    public function verifyReceiptUrl(string $url, array $match = []): array {
        $res = $this->verifier->verifyByUrl($url);
        if (!$res['ok']) return $res;

        $parsed = $res['parsed'] ?? [];
        $res['date_match'] = $this->evaluateDateMatch($parsed, $match);
        return $res;
    }

    public function depositByReceiptUrl(int $userId, string $url, array $match = []): array {
        $verify = $this->verifyReceiptUrl($url, $match);
        if (!($verify['ok'] ?? false)) {
            return ['success'=>false,'message'=>'Verification failed', 'details'=>$verify];
        }
        $p = $verify['parsed'] ?? [];
        if (empty($p['amount'])) {
            return ['success'=>false,'message'=>'Amount not found in receipt','details'=>$verify];
        }
        $amount = (float)$p['amount'];
        $txnId = $p['transaction_id'] ?? null;
        if (!$txnId) {
            return ['success'=>false,'message'=>'Transaction ID not found'];
        }

        // optional: if match rules provided and not ok, reject
        if (isset($verify['date_match']['ok']) && $verify['date_match']['ok'] === false) {
            return ['success'=>false,'message'=>'Receipt date/time does not match expected window','date_match'=>$verify['date_match']];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM payments WHERE external_txn_id = ?");
            $stmt->execute([$txnId]);
            if ($stmt->fetchColumn() > 0) {
                $this->pdo->rollBack();
                return ['success'=>false,'message'=>'Duplicate transaction'];
            }

            $stmt = $this->pdo->prepare("INSERT INTO payments (user_id, amount, currency, method, external_txn_id, meta_json, status)
                VALUES (?, ?, ?, ?, ?, ?, 'completed')");
            $currency = $p['currency'] ?? 'ETB';
            $method = $p['source'] ?? 'unknown';
            $meta = json_encode(['receipt_url'=>$url,'parsed'=>$p,'date_match'=>$verify['date_match'] ?? null], JSON_UNESCAPED_UNICODE);
            $stmt->execute([$userId, $amount, $currency, $method, $txnId, $meta]);

            $this->pdo->exec("UPDATE users SET balance = COALESCE(balance,0) + {$amount} WHERE id = {$userId}");

            $this->pdo->commit();
            return ['success'=>true,'message'=>'Deposit recorded','transaction_id'=>$txnId,'amount'=>$amount,'currency'=>$currency,'date_match'=>$verify['date_match'] ?? null];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success'=>false,'message'=>'DB error','error'=>$e->getMessage()];
        }
    }

    private function evaluateDateMatch(array $parsed, array $match): array {
        $tz = Env::get('APP_TZ', 'Africa/Addis_Ababa');
        $result = ['ok'=>null,'reason'=>null];
        $dateIso = $parsed['date_iso'] ?? null;
        $dateLocal = $parsed['date_local'] ?? null;
        $result['parsed_date_iso'] = $dateIso;
        $result['parsed_date_local'] = $dateLocal;

        if (!$dateIso && !$dateLocal) {
            $result['ok'] = false;
            $result['reason'] = 'No date found in receipt';
            return $result;
        }

        // Use UTC for comparisons
        try {
            $receipt = new \DateTimeImmutable($dateIso ?? $dateLocal);
            $receiptUtc = $receipt->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            $result['ok'] = false;
            $result['reason'] = 'Failed to parse receipt date';
            return $result;
        }

        // mustBeToday (in local tz)
        if (!empty($match['mustBeToday'])) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
            $result['today_local'] = $now->format('Y-m-d');
            $receiptLocal = $receiptUtc->setTimezone(new \DateTimeZone($tz));
            $result['receipt_local_date'] = $receiptLocal->format('Y-m-d');
            $result['ok'] = ($result['receipt_local_date'] === $result['today_local']);
            if (!$result['ok']) $result['reason'] = 'Receipt is not from today (local)';
        }

        // dateBetween { from, to }
        if (!empty($match['dateBetween']) && is_array($match['dateBetween'])) {
            $from = $match['dateBetween']['from'] ?? null;
            $to = $match['dateBetween']['to'] ?? null;
            if ($from) $result['window_from'] = $from;
            if ($to) $result['window_to'] = $to;
            try {
                $ok = true;
                if ($from) {
                    $fromDt = new \DateTimeImmutable($from);
                    if ($receiptUtc < $fromDt) $ok = false;
                }
                if ($to) {
                    $toDt = new \DateTimeImmutable($to);
                    if ($receiptUtc > $toDt) $ok = false;
                }
                $result['ok'] = ($result['ok'] === null) ? $ok : ($result['ok'] && $ok);
                if (!$ok) $result['reason'] = 'Receipt date outside allowed window';
            } catch (\Exception $e) {
                $result['ok'] = false;
                $result['reason'] = 'Invalid dateBetween window';
            }
        }

        // If no rules provided, ok remains null -> treat as not enforced
        if ($result['ok'] === null) {
            $result['ok'] = true;
            $result['reason'] = 'No date matching rule applied';
        }
        return $result;
    }

    public function createWithdrawalRequest(int $userId, float $amount, string $methodName, string $account, string $name): array {
        if ($amount <= 0) return ['success'=>false,'message'=>'Invalid amount'];
        $stmt = $this->pdo->prepare("INSERT INTO withdrawals (user_id, amount, method, account, name, status) VALUES (?,?,?,?,?,'pending')");
        $stmt->execute([$userId, $amount, $methodName, $account, $name]);
        return ['success'=>true,'message'=>'Withdrawal requested'];
    }
}
