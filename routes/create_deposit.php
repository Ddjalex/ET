<?php
/**
 * Create Deposit Payment Endpoint
 * POST /routes/create_deposit.php
 * Accepts: user_id, telegram_id, amount_usd, amount_etb, total_etb, payment_method
 * Optional: payment_phone, transaction_number, exchange_rate, deposit_fee_etb, notes
 * Returns: JSON with deposit payment ID
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../models/DepositPaymentsModel.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'No data provided. Send JSON or form-data.'
        ]);
        exit;
    }
    
    $depositModel = new DepositPaymentsModel();
    $result = $depositModel->create($data);
    
    http_response_code(201);
    echo json_encode([
        'ok' => true,
        'deposit_payment_id' => $result['deposit_payment_id'],
        'deposit' => $result
    ]);
    
} catch (Exception $e) {
    $statusCode = 500;
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Missing field') !== false || strpos($errorMsg, 'Invalid payment_method') !== false) {
        $statusCode = 400;
    } elseif (strpos($errorMsg, 'Missing column') !== false || strpos($errorMsg, 'Table') !== false) {
        $statusCode = 503;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'ok' => false,
        'error' => $errorMsg
    ]);
}
