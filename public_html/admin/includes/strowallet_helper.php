<?php
/**
 * StroWallet API Helper Functions for Admin Panel
 */

function callStroWalletAPI_Admin($endpoint, $method = 'GET', $data = []) {
    $url = 'https://strowallet.com/api' . $endpoint;
    
    $apiKey = $_ENV['STROWALLET_API_KEY'] ?? getenv('STROWALLET_API_KEY') ?: '';
    
    if (empty($apiKey)) {
        error_log("StroWallet API key not configured");
        return ['error' => 'API key not configured'];
    }
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            $data['mode'] = 'sandbox';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("StroWallet API curl error: " . $curlError);
        return ['error' => 'Connection error: ' . $curlError];
    }
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("StroWallet API HTTP error: $httpCode - Response: $response");
        return ['error' => 'API returned HTTP ' . $httpCode, 'response' => $response];
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("StroWallet API JSON decode error: " . json_last_error_msg());
        return ['error' => 'Invalid JSON response'];
    }
    
    return $result;
}

function creditCustomerWallet($customerEmail, $amount, $description = 'Deposit') {
    $publicKey = $_ENV['STROWALLET_PUBLIC_KEY'] ?? getenv('STROWALLET_PUBLIC_KEY') ?: '';
    
    if (empty($publicKey)) {
        error_log("StroWallet public key not configured");
        return ['success' => false, 'error' => 'Public key not configured'];
    }
    
    $data = [
        'amount' => (float)$amount,
        'currency' => 'USD',
        'receiver' => $customerEmail,
        'note' => $description,
        'public_key' => $publicKey
    ];
    
    error_log("Transferring funds to $customerEmail: $" . $amount . " USD (Sandbox mode) - Note: $description");
    error_log("Request data: " . json_encode($data));
    
    $result = callStroWalletAPI_Admin('/wallet/transfer', 'POST', $data);
    
    error_log("StroWallet API Response: " . json_encode($result));
    
    if (isset($result['error'])) {
        error_log("Wallet transfer failed: " . json_encode($result));
        return ['success' => false, 'error' => $result['error']];
    }
    
    if (isset($result['status']) && $result['status'] === 'success') {
        error_log("Wallet transfer successful to $customerEmail");
        return ['success' => true, 'data' => $result];
    }
    
    if (isset($result['success']) && $result['success'] === true) {
        error_log("Wallet transfer successful to $customerEmail");
        return ['success' => true, 'data' => $result];
    }
    
    error_log("Unexpected response: " . json_encode($result));
    return ['success' => false, 'error' => 'Unexpected API response', 'response' => $result];
}

function getCustomerWalletBalance($customerEmail) {
    $result = callStroWalletAPI_Admin('/wallet/balance', 'GET');
    
    if (isset($result['error'])) {
        return ['success' => false, 'error' => $result['error']];
    }
    
    return ['success' => true, 'data' => $result];
}
