<?php
/**
 * User Registration Endpoint
 * POST /routes/register_user.php
 * Accepts: telegram_id, email, phone, first_name, last_name
 * Returns: JSON with user data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../models/UserModel.php';

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
    
    $userModel = new UserModel();
    $result = $userModel->registerUser($data);
    
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    $statusCode = 500;
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Missing field') !== false) {
        $statusCode = 400;
    } elseif (strpos($errorMsg, 'already exists') !== false) {
        $statusCode = 409;
    } elseif (strpos($errorMsg, 'Missing column') !== false || strpos($errorMsg, 'Table') !== false) {
        $statusCode = 503;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'ok' => false,
        'error' => $errorMsg
    ]);
}
