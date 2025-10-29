<?php
// Very small router for payment endpoints (plain PHP).
// Place this file under /routes and include it from public/index.php
use App\Services\PaymentService;
require_once __DIR__ . '/../services/PaymentService.php';

$service = new PaymentService();
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json; charset=utf-8');

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($uri === '/api/payments/validate' && $method === 'GET') {
    $provider = $_GET['provider'] ?? '';
    $txn = $_GET['txn'] ?? '';
    if ($provider === 'telebirr') {
        echo json_encode($service->validateTelebirrTransaction($txn));
    } elseif ($provider === 'cbe') {
        echo json_encode($service->validateCBETransaction($txn));
    } else {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Invalid provider']);
    }
    exit;
}

if ($uri === '/api/payments/deposit' && $method === 'POST') {
    $data = getJsonInput();
    $userId = (int)($data['userId'] ?? 0);
    $amount = (float)($data['amount'] ?? 0);
    $methodName = strtolower(trim($data['method'] ?? ''));
    $txn = $data['transactionNumber'] ?? null;
    echo json_encode($service->processDeposit($userId, $amount, $methodName, $txn));
    exit;
}

if ($uri === '/api/payments/withdraw' && $method === 'POST') {
    $data = getJsonInput();
    $userId = (int)($data['userId'] ?? 0);
    $amount = (float)($data['amount'] ?? 0);
    $methodName = strtolower(trim($data['method'] ?? ''));
    $account = trim($data['account'] ?? '');
    $name = trim($data['name'] ?? '');
    echo json_encode($service->createWithdrawalRequest($userId, $amount, $methodName, $account, $name));
    exit;
}

// 404 fallback for this route file
http_response_code(404);
echo json_encode(['success'=>false,'message'=>'Not found']);
