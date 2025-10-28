#!/usr/bin/env php
<?php
/**
 * Import Single StroWallet Customer and Link Telegram ID
 * 
 * This script allows you to manually import a StroWallet customer
 * and link their Telegram ID to the bot database.
 * 
 * Usage: php scripts/import_single_user.php
 */

require_once __DIR__ . '/../secrets/load_env.php';

// StroWallet API Configuration
define('STROW_BASE', 'https://strowallet.com/api');
define('STROWALLET_API_KEY', getenv('STROWALLET_API_KEY') ?: '');

if (empty(STROWALLET_API_KEY)) {
    die("❌ ERROR: STROWALLET_API_KEY not found in environment variables\n");
}

// Database configuration
$dbUrl = getenv('DATABASE_URL');
if (empty($dbUrl)) {
    die("❌ ERROR: DATABASE_URL not found\n");
}

try {
    $db = parse_url($dbUrl);
    $port = $db['port'] ?? 5432;
    $pdo = new PDO(
        "pgsql:host={$db['host']};port={$port};dbname=" . ltrim($db['path'], '/'),
        $db['user'],
        $db['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";
} catch (PDOException $e) {
    die("❌ ERROR: Database connection failed: " . $e->getMessage() . "\n");
}

/**
 * Call StroWallet API
 */
function callStroWalletAPI($endpoint, $method = 'GET', $data = []) {
    $separator = (strpos($endpoint, '?') !== false) ? '&' : '?';
    $url = STROW_BASE . $endpoint . $separator . 'public_key=' . urlencode(STROWALLET_API_KEY);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'http_code' => 0,
            'data' => null,
            'raw' => $response,
            'error' => $curlError
        ];
    }
    
    return [
        'http_code' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response
    ];
}

/**
 * Map StroWallet KYC status to database status
 */
function mapKYCStatus($strowStatus) {
    $status = strtolower(trim($strowStatus ?? 'pending'));
    
    return match($status) {
        'verified', 'approved', 'high kyc', 'low kyc' => 'approved',
        'rejected', 'failed', 'decline', 'declined' => 'rejected',
        'pending', 'unreviewed', 'under review', 'unreview kyc' => 'pending',
        default => 'pending'
    };
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         Import StroWallet Customer to Bot Database          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Get customer email
echo "Enter StroWallet customer email: ";
$email = trim(fgets(STDIN));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("❌ Invalid email address\n");
}

// Get Telegram ID
echo "Enter Telegram User ID (numeric): ";
$telegramId = trim(fgets(STDIN));

if (empty($telegramId) || !is_numeric($telegramId)) {
    die("❌ Invalid Telegram ID\n");
}

echo "\n📡 Fetching customer from StroWallet...\n";

// Fetch customer from StroWallet
$result = callStroWalletAPI('/bitvcard/getcardholder/?customerEmail=' . urlencode($email), 'GET');

if ($result['http_code'] !== 200) {
    echo "\n❌ Failed to fetch customer from StroWallet\n";
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Response: " . ($result['raw'] ?? 'No response') . "\n\n";
    
    echo "Would you like to create a user manually? (y/n): ";
    $createManually = trim(fgets(STDIN));
    
    if (strtolower($createManually) !== 'y') {
        die("Aborted.\n");
    }
    
    // Manual user creation
    echo "\nManual User Creation:\n";
    echo "Enter first name: ";
    $firstName = trim(fgets(STDIN));
    echo "Enter last name: ";
    $lastName = trim(fgets(STDIN));
    echo "Enter phone: ";
    $phone = trim(fgets(STDIN));
    echo "Enter KYC status (pending/approved/rejected): ";
    $kycStatus = trim(fgets(STDIN));
    
    $customerData = [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'customerEmail' => $email,
        'phone' => $phone,
        'status' => $kycStatus,
        'customerId' => null
    ];
} else {
    $responseData = $result['data'];
    $customerData = $responseData['data'] ?? $responseData;
    
    if (empty($customerData)) {
        die("❌ No customer data found in response\n");
    }
    
    echo "✓ Customer found!\n\n";
    echo "Name: " . ($customerData['firstName'] ?? 'N/A') . " " . ($customerData['lastName'] ?? 'N/A') . "\n";
    echo "Email: " . ($customerData['customerEmail'] ?? $email) . "\n";
    echo "Phone: " . ($customerData['phone'] ?? 'N/A') . "\n";
    echo "KYC Status: " . ($customerData['status'] ?? 'N/A') . "\n\n";
}

// Check if user already exists
$stmt = $pdo->prepare("SELECT id, email, telegram_id FROM users WHERE email = ?");
$stmt->execute([$email]);
$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingUser) {
    echo "⚠️  User already exists in database (ID: {$existingUser['id']})\n";
    
    if ($existingUser['telegram_id']) {
        echo "Current Telegram ID: {$existingUser['telegram_id']}\n";
        echo "Update to new Telegram ID? (y/n): ";
        $update = trim(fgets(STDIN));
        
        if (strtolower($update) !== 'y') {
            die("Aborted.\n");
        }
    }
    
    // Update existing user
    $stmt = $pdo->prepare("
        UPDATE users 
        SET telegram_id = ?, 
            first_name = ?, 
            last_name = ?, 
            phone = ?, 
            kyc_status = ?, 
            strow_customer_id = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE email = ?
    ");
    
    $stmt->execute([
        $telegramId,
        $customerData['firstName'] ?? $customerData['first_name'] ?? '',
        $customerData['lastName'] ?? $customerData['last_name'] ?? '',
        $customerData['phone'] ?? $customerData['phoneNumber'] ?? '',
        mapKYCStatus($customerData['status'] ?? $customerData['kycStatus'] ?? 'pending'),
        $customerData['customerId'] ?? $customerData['customer_id'] ?? null,
        $email
    ]);
    
    echo "\n✅ User updated successfully!\n";
} else {
    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            telegram_id, email, phone, first_name, last_name,
            kyc_status, strow_customer_id, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $telegramId,
        $email,
        $customerData['phone'] ?? $customerData['phoneNumber'] ?? '',
        $customerData['firstName'] ?? $customerData['first_name'] ?? '',
        $customerData['lastName'] ?? $customerData['last_name'] ?? '',
        mapKYCStatus($customerData['status'] ?? $customerData['kycStatus'] ?? 'pending'),
        $customerData['customerId'] ?? $customerData['customer_id'] ?? null
    ]);
    
    echo "\n✅ User imported successfully!\n";
}

echo "\n══════════════════════════════════════════════════════════════\n";
echo "User Details:\n";
echo "══════════════════════════════════════════════════════════════\n";
echo "Telegram ID: $telegramId\n";
echo "Email: $email\n";
echo "Name: " . ($customerData['firstName'] ?? '') . " " . ($customerData['lastName'] ?? '') . "\n";
echo "KYC Status: " . mapKYCStatus($customerData['status'] ?? 'pending') . "\n";
echo "\n✅ The user can now use the bot with their Telegram account!\n";
echo "══════════════════════════════════════════════════════════════\n";
