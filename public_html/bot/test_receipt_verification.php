<?php
/**
 * Test Receipt URL Verification
 * 
 * This script demonstrates how to use the new receipt URL verification feature
 */

require_once __DIR__ . '/../../secrets/load_env.php';
require_once __DIR__ . '/PaymentServiceEnhanced.php';

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("ERROR: DATABASE_URL not found\n");
}

preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+?)(\?.*)?$/', $dbUrl, $matches);
$user = $matches[1] ?? 'postgres';
$pass = $matches[2] ?? '';
$host = $matches[3] ?? 'localhost';
$dbname = $matches[4] ?? 'postgres';

$connString = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s sslmode=prefer",
    $host, $dbname, $user, $pass
);

$conn = @pg_connect($connString);
if (!$conn) {
    die("ERROR: Failed to connect to database\n");
}

$pdo = new PDO(
    "pgsql:host={$host};port=5432;dbname={$dbname};sslmode=prefer",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$botToken = getenv('BOT_TOKEN') ?: 'test_token';
$validationApiBase = getenv('VALIDATION_API_BASE_URL') ?: 'https://api.example.com';

$paymentService = new PaymentServiceEnhanced($pdo, $validationApiBase, $botToken);

echo "=== Receipt URL Verification Test ===\n\n";

echo "Example 1: Verify Telebirr Receipt URL\n";
echo "---------------------------------------\n";
$testUrl1 = 'https://transactioninfo.ethiotelecom.et/receipt/ABC123XYZ';
echo "Testing URL: {$testUrl1}\n";
$result = $paymentService->verifyByReceiptUrl($testUrl1, mustBeToday: false);
echo "Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "Message: " . $result['message'] . "\n";
if ($result['success']) {
    echo "Transaction ID: " . $result['transaction_id'] . "\n";
    echo "Amount: " . $result['amount'] . " " . $result['currency'] . "\n";
}
echo "\n";

echo "Example 2: Verify CBE Receipt URL\n";
echo "---------------------------------------\n";
$testUrl2 = 'https://apps.cbe.com.et:100/?id=FT25302BLC7739256208';
echo "Testing URL: {$testUrl2}\n";
$result = $paymentService->verifyByReceiptUrl($testUrl2, mustBeToday: false);
echo "Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "Message: " . $result['message'] . "\n";
if ($result['success']) {
    echo "Transaction ID: " . $result['transaction_id'] . "\n";
    echo "Amount: " . $result['amount'] . " " . $result['currency'] . "\n";
}
echo "\n";

echo "=== How to Use in Your Bot ===\n\n";
echo "1. When user sends a receipt URL (Telebirr or CBE)\n";
echo "2. Call: \$paymentService->processDepositByReceiptUrl(\$paymentId, \$receiptUrl, mustBeToday: true)\n";
echo "3. The system will:\n";
echo "   - Fetch the receipt page\n";
echo "   - Parse transaction ID, amount, and date\n";
echo "   - Verify the date is today (or matches your criteria)\n";
echo "   - Check if amount matches the expected amount\n";
echo "   - Update the payment record if verified\n";
echo "4. You can then process the verified deposit\n\n";

echo "Supported Domains:\n";
echo "  - transactioninfo.ethiotelecom.et (Telebirr)\n";
echo "  - apps.cbe.com.et (CBE)\n";
echo "  - www.combanketh.et (CBE)\n\n";

echo "Date Verification Options:\n";
echo "  - mustBeToday: true/false - Requires receipt to be from today\n";
echo "  - Or use custom date ranges in match parameter\n\n";

pg_close($conn);
echo "Test complete!\n";
