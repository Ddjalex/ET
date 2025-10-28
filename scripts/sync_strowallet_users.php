<?php
/**
 * Sync Existing StroWallet Users to Local Database
 * 
 * This script fetches all customers from StroWallet API
 * and imports them into the local database so they appear in the admin panel.
 * 
 * Usage: php scripts/sync_strowallet_users.php
 */

// Load environment variables
require_once __DIR__ . '/../secrets/load_env.php';

// StroWallet API Configuration
define('STROW_BASE', 'https://strowallet.com/api');
define('STROW_PUBLIC_KEY', getenv('STROWALLET_API_KEY') ?: getenv('STROW_PUBLIC_KEY') ?: '');
define('STROW_SECRET_KEY', getenv('STROWALLET_WEBHOOK_SECRET') ?: getenv('STROW_SECRET_KEY') ?: '');

if (empty(STROW_PUBLIC_KEY)) {
    die("ERROR: STROWALLET_API_KEY not found in environment variables\n");
}

// Get database connection
$dbUrl = getenv('DATABASE_URL');
if (empty($dbUrl)) {
    die("ERROR: DATABASE_URL not found in environment variables\n");
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
    die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
}

/**
 * Call StroWallet API
 */
function callStroWalletAPI($endpoint, $method = 'GET', $data = []) {
    $url = STROW_BASE . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
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
    $status = strtolower($strowStatus ?? 'pending');
    return match($status) {
        'verified', 'approved', 'high kyc', 'low kyc' => 'approved',
        'rejected', 'failed' => 'rejected',
        default => 'pending'
    };
}

echo "🔄 Fetching all customers from StroWallet API...\n";

// Fetch all cardholders/customers from StroWallet
$result = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY, 'GET');

if ($result['http_code'] !== 200) {
    die("ERROR: Failed to fetch customers from StroWallet API (HTTP {$result['http_code']})\n");
}

$response = $result['data'];
$customers = [];

// Handle different response formats
if (isset($response['data']) && is_array($response['data'])) {
    // Response has 'data' wrapper
    if (isset($response['data'][0])) {
        // Array of customers
        $customers = $response['data'];
    } else {
        // Single customer
        $customers = [$response['data']];
    }
} elseif (isset($response['customers']) && is_array($response['customers'])) {
    $customers = $response['customers'];
} elseif (is_array($response) && isset($response[0])) {
    // Direct array of customers
    $customers = $response;
}

if (empty($customers)) {
    echo "⚠️  No customers found in StroWallet API\n";
    echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

echo "✓ Found " . count($customers) . " customer(s) in StroWallet\n\n";

$imported = 0;
$updated = 0;
$skipped = 0;

foreach ($customers as $customer) {
    $email = $customer['email'] ?? null;
    $customerId = $customer['customerId'] ?? $customer['customer_id'] ?? $customer['id'] ?? null;
    
    if (!$email || !$customerId) {
        echo "⚠️  Skipping customer (missing email or ID): " . json_encode($customer) . "\n";
        $skipped++;
        continue;
    }
    
    $firstName = $customer['firstName'] ?? $customer['first_name'] ?? 'Unknown';
    $lastName = $customer['lastName'] ?? $customer['last_name'] ?? '';
    $phone = $customer['phone'] ?? $customer['phoneNumber'] ?? '';
    $kycStatus = mapKYCStatus($customer['kycStatus'] ?? $customer['kyc_status'] ?? 'pending');
    
    // Check if user already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    try {
        if ($existingUser) {
            // Update existing user
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?,
                    last_name = ?,
                    phone = ?,
                    kyc_status = ?,
                    strow_customer_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE email = ?
            ");
            $updateStmt->execute([
                $firstName,
                $lastName,
                $phone,
                $kycStatus,
                $customerId,
                $email
            ]);
            echo "🔄 Updated: {$firstName} {$lastName} ({$email}) - Status: {$kycStatus}\n";
            $updated++;
        } else {
            // Insert new user
            $insertStmt = $pdo->prepare("
                INSERT INTO users (
                    telegram_id, 
                    email, 
                    phone, 
                    first_name, 
                    last_name, 
                    kyc_status, 
                    strow_customer_id,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            
            // Use a placeholder telegram_id (0) for users not yet linked to Telegram
            $telegramId = 0;
            
            $insertStmt->execute([
                $telegramId,
                $email,
                $phone,
                $firstName,
                $lastName,
                $kycStatus,
                $customerId
            ]);
            echo "✅ Imported: {$firstName} {$lastName} ({$email}) - Status: {$kycStatus}\n";
            $imported++;
        }
    } catch (PDOException $e) {
        echo "❌ Error processing {$email}: " . $e->getMessage() . "\n";
        $skipped++;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Sync Complete!\n";
echo "   📥 Imported: {$imported} new user(s)\n";
echo "   🔄 Updated: {$updated} existing user(s)\n";
echo "   ⏭️  Skipped: {$skipped} user(s)\n";
echo str_repeat("=", 60) . "\n\n";

echo "💡 Note: Users imported from StroWallet have telegram_id = 0\n";
echo "   They will be linked to Telegram when they register via the bot.\n\n";

echo "🌐 Check your admin panel: /admin/kyc.php\n";
