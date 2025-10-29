<?php

require_once __DIR__ . '/ProxyService.php';
require_once __DIR__ . '/ReceiptVerification/ReceiptVerifier.php';

header('Content-Type: text/plain; charset=utf-8');

echo "==============================================\n";
echo "  Ethiopian Proxy System Test\n";
echo "==============================================\n\n";

echo "Test Started: " . date('Y-m-d H:i:s') . "\n\n";

echo "--- 1. Proxy Service Configuration ---\n";
$proxyService = new ProxyService();
$config = $proxyService->getConfig();
echo "Enabled: " . ($config['enabled'] ? 'YES' : 'NO') . "\n";
echo "Proxy Host: " . ($config['proxy_host'] ?? 'Not set') . "\n";
echo "Proxy Port: " . ($config['proxy_port'] ?? 'Not set') . "\n";
echo "Proxy Type: " . ($config['proxy_type'] ?? 'Not set') . "\n";
echo "Has Authentication: " . ($config['has_auth'] ? 'YES' : 'NO') . "\n";
echo "Auto Fallback: " . ($config['auto_fallback'] ? 'YES' : 'NO') . "\n";
echo "Fallback Proxies: " . ($config['fallback_count'] ?? 0) . "\n\n";

if (!$config['enabled']) {
    echo "⚠️  Proxy is DISABLED. To enable:\n";
    echo "   1. Edit secrets/.env\n";
    echo "   2. Set PROXY_ENABLED=\"true\"\n";
    echo "   3. Configure PROXY_HOST and PROXY_PORT\n";
    echo "   4. Restart the workflow\n\n";
    echo "See PROXY_SETUP_GUIDE.md for detailed setup instructions.\n\n";
}

echo "--- 2. Proxy Health Check ---\n";
$health = $proxyService->checkHealth();
echo "Status: " . ($health['ok'] ? '✓ HEALTHY' : '✗ UNHEALTHY') . "\n";
if (isset($health['proxy'])) {
    echo "Proxy: " . $health['proxy'] . "\n";
}
if (isset($health['test_url'])) {
    echo "Test URL: " . $health['test_url'] . "\n";
}
if (isset($health['response_time_ms'])) {
    echo "Response Time: " . $health['response_time_ms'] . "ms\n";
}
if (isset($health['error'])) {
    echo "Error: " . $health['error'] . "\n";
}
echo "\n";

if ($config['enabled'] && $health['ok']) {
    echo "--- 3. Test Fetching via Proxy ---\n";
    
    $testUrls = [
        ['name' => 'Google', 'url' => 'https://www.google.com'],
        ['name' => 'TeleBirr Info Page', 'url' => 'https://transactioninfo.ethiotelecom.et'],
    ];
    
    foreach ($testUrls as $test) {
        echo "\nTesting: {$test['name']} ({$test['url']})\n";
        $startTime = microtime(true);
        $result = $proxyService->fetch($test['url'], ['timeout' => 15]);
        $duration = round((microtime(true) - $startTime) * 1000);
        
        if ($result['ok']) {
            echo "  ✓ SUCCESS\n";
            echo "  Status: " . ($result['status'] ?? 'N/A') . "\n";
            echo "  Duration: {$duration}ms\n";
            echo "  Proxy Used: " . ($result['proxy'] ?? 'Direct') . "\n";
            echo "  Content Length: " . strlen($result['body']) . " bytes\n";
            if (isset($result['used_fallback'])) {
                echo "  ⚠️  Used Fallback: " . $result['fallback_proxy'] . "\n";
            }
            if (isset($result['used_direct'])) {
                echo "  ⚠️  Used Direct Connection (All proxies failed)\n";
            }
        } else {
            echo "  ✗ FAILED\n";
            echo "  Error: " . ($result['error'] ?? $result['message'] ?? 'Unknown') . "\n";
            echo "  Duration: {$duration}ms\n";
        }
    }
    echo "\n";
}

echo "--- 4. Receipt Verifier Integration ---\n";
$verifier = new ReceiptVerifier();
$verifierStatus = $verifier->getProxyStatus();
echo "Receipt Verifier Proxy Enabled: " . ($verifierStatus['enabled'] ? 'YES' : 'NO') . "\n";
echo "Proxy will be used for receipt URL fetching: " . ($config['enabled'] ? 'YES' : 'NO') . "\n\n";

if ($config['enabled']) {
    echo "--- 5. Test Receipt Verification (Simulated) ---\n";
    echo "To test actual receipt verification:\n";
    echo "1. Send a TeleBirr/CBE/M-Pesa receipt URL to your bot\n";
    echo "2. Check bot logs for 'via_proxy: true'\n";
    echo "3. Verify receipt is successfully parsed\n\n";
}

echo "==============================================\n";
echo "  Test Complete\n";
echo "==============================================\n\n";

if ($config['enabled'] && $health['ok']) {
    echo "✓ Proxy system is working correctly!\n\n";
    echo "Next steps:\n";
    echo "1. Test with real receipt URLs via Telegram bot\n";
    echo "2. Monitor proxy performance\n";
    echo "3. Check admin panel for verification results\n\n";
} elseif ($config['enabled'] && !$health['ok']) {
    echo "✗ Proxy is enabled but not healthy!\n\n";
    echo "Troubleshooting:\n";
    echo "1. Check proxy server is running\n";
    echo "2. Verify PROXY_HOST and PROXY_PORT are correct\n";
    echo "3. Test authentication credentials\n";
    echo "4. Check firewall allows connections from Replit\n";
    echo "5. Review PROXY_SETUP_GUIDE.md\n\n";
} else {
    echo "ℹ️  Proxy is disabled (using direct connections)\n\n";
    echo "To enable proxy support:\n";
    echo "1. Review PROXY_SETUP_GUIDE.md\n";
    echo "2. Obtain an Ethiopian proxy server\n";
    echo "3. Configure secrets/.env with proxy details\n";
    echo "4. Run this test again\n\n";
}

echo "For detailed setup instructions: PROXY_SETUP_GUIDE.md\n";
echo "For health monitoring: /bot/proxy-health.php?secret=YOUR_SECRET\n\n";
