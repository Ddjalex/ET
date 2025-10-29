<?php

require_once __DIR__ . '/ProxyService.php';
require_once __DIR__ . '/ReceiptVerification/ReceiptVerifier.php';

header('Content-Type: application/json');

$secret = $_GET['secret'] ?? '';
$expectedSecret = getenv('ADMIN_SECRET') ?: getenv('TELEGRAM_SECRET_TOKEN') ?: '';

if (empty($expectedSecret) || $secret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$proxyService = new ProxyService();
$receiptVerifier = new ReceiptVerifier();

$healthCheck = [
    'timestamp' => date('c'),
    'proxy_config' => $proxyService->getConfig(),
    'proxy_health' => $proxyService->checkHealth(),
    'receipt_verifier_status' => $receiptVerifier->getProxyStatus(),
];

if ($proxyService->isEnabled()) {
    $testUrls = [
        'google' => 'https://www.google.com',
        'telebirr' => 'https://transactioninfo.ethiotelecom.et',
    ];
    
    $healthCheck['test_results'] = [];
    
    foreach ($testUrls as $name => $url) {
        $startTime = microtime(true);
        $result = $proxyService->fetch($url, ['timeout' => 10]);
        $duration = round((microtime(true) - $startTime) * 1000);
        
        $healthCheck['test_results'][$name] = [
            'url' => $url,
            'ok' => $result['ok'],
            'duration_ms' => $duration,
            'status' => $result['status'] ?? null,
            'proxy' => $result['proxy'] ?? null,
            'error' => $result['error'] ?? null,
        ];
    }
}

$overallStatus = $proxyService->isEnabled() 
    ? ($proxyService->checkHealth()['ok'] ? 'healthy' : 'unhealthy')
    : 'disabled';

http_response_code($overallStatus === 'healthy' || $overallStatus === 'disabled' ? 200 : 503);

echo json_encode([
    'ok' => $overallStatus !== 'unhealthy',
    'status' => $overallStatus,
    'health' => $healthCheck
], JSON_PRETTY_PRINT);
