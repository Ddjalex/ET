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
    
    error_log("Attempting to credit wallet for $customerEmail: $" . $amount . " USD - Note: $description");
    
    // Try multiple endpoints that might work for wallet credits/top-ups
    $endpoints = [
        '/wallet/fund',
        '/wallet/topup', 
        '/wallet/credit',
        '/bitvcard/fund-wallet',
        '/user/fund-wallet'
    ];
    
    $data = [
        'amount' => (float)$amount,
        'currency' => 'USD',
        'receiver' => $customerEmail,
        'customer_email' => $customerEmail,
        'note' => $description,
        'public_key' => $publicKey,
        'mode' => 'sandbox'
    ];
    
    error_log("Request data: " . json_encode($data));
    
    foreach ($endpoints as $endpoint) {
        error_log("Trying endpoint: $endpoint");
        $result = callStroWalletAPI_Admin($endpoint, 'POST', $data);
        
        error_log("Response from $endpoint: " . json_encode($result));
        
        // Check if this endpoint worked
        if (!isset($result['error']) || !str_contains($result['error'], '405')) {
            // Not a 405 error, this might be the right endpoint
            if (isset($result['status']) && $result['status'] === 'success') {
                error_log("✅ Wallet credit successful using endpoint: $endpoint");
                return ['success' => true, 'data' => $result, 'endpoint_used' => $endpoint];
            }
            
            if (isset($result['success']) && $result['success'] === true) {
                error_log("✅ Wallet credit successful using endpoint: $endpoint");
                return ['success' => true, 'data' => $result, 'endpoint_used' => $endpoint];
            }
            
            // If we got a different error (not 405), log it and continue
            if (isset($result['error'])) {
                error_log("❌ Endpoint $endpoint returned error: " . $result['error']);
            }
        }
    }
    
    // If all endpoints failed
    error_log("❌ All endpoints failed. Please contact StroWallet support for the correct wallet credit/top-up endpoint.");
    return [
        'success' => false, 
        'error' => 'Unable to credit wallet. The correct API endpoint needs to be confirmed with StroWallet support.',
        'support_contact' => 'hello@strowallet.com or WhatsApp: +234 913 449 8570'
    ];
}

function getCustomerWalletBalance($customerEmail) {
    $result = callStroWalletAPI_Admin('/wallet/balance', 'GET');
    
    if (isset($result['error'])) {
        return ['success' => false, 'error' => $result['error']];
    }
    
    return ['success' => true, 'data' => $result];
}
