<?php
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

// Health
if ($uri === '/api/health' && $method === 'GET') {
    echo json_encode($service->health());
    exit;
}

// Verify a receipt URL with optional date match rules
if ($uri === '/api/payments/verify-receipt' && $method === 'POST') {
    $data = getJsonInput();
    $url = trim($data['url'] ?? '');
    $match = is_array($data['match'] ?? null) ? $data['match'] : [];
    echo json_encode($service->verifyReceiptUrl($url, $match));
    exit;
}

// Deposit by verifying a receipt URL (+ optional match rules)
if ($uri === '/api/payments/deposit-by-receipt' && $method === 'POST') {
    $data = getJsonInput();
    $userId = (int)($data['userId'] ?? 0);
    $url = trim($data['url'] ?? '');
    $match = is_array($data['match'] ?? null) ? $data['match'] : [];
    echo json_encode($service->depositByReceiptUrl($userId, $url, $match));
    exit;
}

// Withdrawal request
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

// 404
http_response_code(404);
echo json_encode(['success'=>false,'message'=>'Not found']);
