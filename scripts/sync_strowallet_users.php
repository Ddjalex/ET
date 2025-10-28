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
define('STROW_PUBLIC_KEY', getenv('STROW_PUBLIC_KEY') ?: '');
define('STROW_SECRET_KEY', getenv('STROW_SECRET_KEY') ?: '');

if (empty(STROW_PUBLIC_KEY)) {
    die("ERROR: STROW_PUBLIC_KEY not found in environment variables\n");
}

// ==================== CONFIGURE YOUR EXISTING CUSTOMERS HERE ====================
// Add the email addresses of customers already registered in StroWallet
$existingCustomerEmails = [
    'test1761389150@example.com',
    'walmesaged@gmail.com',
    'wondimualmasaged@gmail.com',
    // Add more email addresses here as needed
];
// ================================================================================

if (empty($existingCustomerEmails)) {
    die("ERROR: No customer emails configured. Please edit this script and add customer emails.\n");
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

echo "🔄 Fetching " . count($existingCustomerEmails) . " customer(s) from StroWallet API...\n\n";

$customers = [];

foreach ($existingCustomerEmails as $email) {
    echo "  → Fetching: {$email}...";
    
    $result = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY . '&customerEmail=' . urlencode($email), 'GET');
    
    if ($result['http_code'] === 200 && isset($result['data']['data'])) {
        $customerData = $result['data']['data'];
        $customers[] = $customerData;
        echo " ✓\n";
    } else {
        echo " ⚠️  Not found (HTTP {$result['http_code']})\n";
    }
    
    // Small delay to avoid rate limiting
    usleep(200000); // 0.2 seconds
}

if (empty($customers)) {
    echo "\n⚠️  No customers found in StroWallet API\n";
    exit(0);
}

echo "\n✓ Successfully fetched " . count($customers) . " customer(s)\n\n";

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
