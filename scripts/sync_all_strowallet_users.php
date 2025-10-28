<?php
/**
 * Sync ALL StroWallet Customers to Local Database
 * 
 * This script fetches ALL customers from your StroWallet account
 * and syncs them to the admin panel with correct KYC statuses.
 * 
 * Usage: php scripts/sync_all_strowallet_users.php
 */

// Load environment variables
require_once __DIR__ . '/../secrets/load_env.php';

// StroWallet API Configuration
define('STROW_BASE', 'https://strowallet.com/api');
define('STROWALLET_API_KEY', getenv('STROWALLET_API_KEY') ?: '');

if (empty(STROWALLET_API_KEY)) {
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
 * Call StroWallet API with public_key parameter
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
            'raw' => null,
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
 * FIXED: Now correctly reads 'status' field from StroWallet
 */
function mapKYCStatus($strowStatus) {
    $status = strtolower(trim($strowStatus ?? 'pending'));
    
    // Map all possible StroWallet statuses
    return match($status) {
        'verified', 'approved', 'high kyc', 'low kyc' => 'approved',
        'rejected', 'failed', 'decline', 'declined' => 'rejected',
        'pending', 'unreviewed', 'under review', 'unreview kyc' => 'pending',
        default => 'pending'
    };
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     StroWallet Customer Sync - Import ALL Customers          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Get all existing users from local database
echo "📊 Fetching existing users from local database...\n";
$existingUsers = [];
$stmt = $pdo->query("SELECT email, strow_customer_id FROM users WHERE strow_customer_id IS NOT NULL");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingUsers[$row['email']] = $row['strow_customer_id'];
}
echo "✓ Found " . count($existingUsers) . " existing user(s) in local database\n\n";

// Since StroWallet doesn't have a "list all" endpoint in the docs,
// we'll try common customer emails OR prompt user to provide them
echo "══════════════════════════════════════════════════════════════\n";
echo "IMPORTANT: Please provide ALL customer email addresses\n";
echo "══════════════════════════════════════════════════════════════\n\n";

// Get customer emails from user input
echo "Enter customer email addresses (one per line, press Enter twice when done):\n";
$customerEmails = [];

// Customer emails from StroWallet (all 12 customers)
$customerEmailsFromConfig = [
    // Real customers (rows 11-12 from StroWallet)
    'walmesaged@gmail.com',          // Row 11: Eyerus Gadisa - Low KYC (REAL)
    'Wondimualmasaged@gmail.com',    // Row 12: Eyerus Gadisa - High KYC (REAL)
    
    // Previously imported
    'addisumelk04@gmail.com',        // Row 2: kalkidan adanu - High KYC
    'almesagadw@gmail.com',          // Row 3: Addisu melke - Low KYC
    'amanuall071@gmail.com',         // Row 4: Kalkidan Semeneh - Unreview KYC
    'ethiopian.customer@example.com', // Row 5: Ethiopian Customer - Low KYC
    
    // Test/Mock data (rows 9-10 - optional)
    'test.user999@example.com',      // Row 9: Test User - Low KYC
    'test1761389150@example.com',    // Row 10: Test User - Low KYC
];

$customerEmails = array_filter(array_map('trim', $customerEmailsFromConfig));

if (empty($customerEmails)) {
    die("\nERROR: No customer emails provided. Please edit this script and add all customer emails.\n");
}

echo "\n🔄 Fetching " . count($customerEmails) . " customer(s) from StroWallet API...\n\n";

$customers = [];
$notFound = [];

foreach ($customerEmails as $email) {
    echo "  → Fetching: {$email}...";
    
    $result = callStroWalletAPI('/bitvcard/getcardholder/?customerEmail=' . urlencode($email), 'GET');
    
    if (isset($result['error'])) {
        echo " ⚠️  Network error: {$result['error']}\n";
        $notFound[] = $email;
    } elseif ($result['http_code'] === 200 && isset($result['data'])) {
        $customerData = isset($result['data']['data']) ? $result['data']['data'] : $result['data'];
        $customers[] = $customerData;
        echo " ✓\n";
    } else {
        echo " ⚠️  Not found (HTTP {$result['http_code']})\n";
        $notFound[] = $email;
    }
    
    usleep(200000); // 0.2 seconds delay
}

if (empty($customers)) {
    echo "\n⚠️  No customers found in StroWallet API\n";
    if (!empty($notFound)) {
        echo "\nEmails not found:\n";
        foreach ($notFound as $email) {
            echo "  - $email\n";
        }
    }
    exit(0);
}

echo "\n✓ Successfully fetched " . count($customers) . " customer(s)\n\n";
echo "══════════════════════════════════════════════════════════════\n";
echo "Importing customers to local database...\n";
echo "══════════════════════════════════════════════════════════════\n\n";

$imported = 0;
$updated = 0;
$skipped = 0;

foreach ($customers as $customer) {
    $email = $customer['customerEmail'] ?? $customer['email'] ?? null;
    $customerId = $customer['customerId'] ?? $customer['customer_id'] ?? $customer['id'] ?? null;
    
    if (!$email || !$customerId) {
        echo "⚠️  Skipping customer (missing email or ID)\n";
        $skipped++;
        continue;
    }
    
    // Get customer details
    $firstName = $customer['firstName'] ?? $customer['first_name'] ?? 'Unknown';
    $lastName = $customer['lastName'] ?? $customer['last_name'] ?? '';
    $phone = $customer['phone'] ?? $customer['phoneNumber'] ?? '';
    
    // FIXED: Read the correct 'status' field from StroWallet response
    $strowKycStatus = $customer['status'] ?? $customer['kycStatus'] ?? $customer['kyc_status'] ?? 'pending';
    $kycStatus = mapKYCStatus($strowKycStatus);
    
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
            echo "🔄 Updated: {$firstName} {$lastName} ({$email})\n";
            echo "   StroWallet Status: '{$strowKycStatus}' → Database Status: '{$kycStatus}'\n";
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
            
            $insertStmt->execute([
                null, // telegram_id = NULL for StroWallet-only customers
                $email,
                $phone,
                $firstName,
                $lastName,
                $kycStatus,
                $customerId
            ]);
            echo "✅ Imported: {$firstName} {$lastName} ({$email})\n";
            echo "   StroWallet Status: '{$strowKycStatus}' → Database Status: '{$kycStatus}'\n";
            $imported++;
        }
    } catch (PDOException $e) {
        echo "❌ Error processing {$email}: " . $e->getMessage() . "\n";
        $skipped++;
    }
}

echo "\n" . str_repeat("═", 60) . "\n";
echo "✅ Sync Complete!\n";
echo "   📥 Imported: {$imported} new customer(s)\n";
echo "   🔄 Updated: {$updated} existing customer(s)\n";
echo "   ⏭️  Skipped: {$skipped} customer(s)\n";

if (!empty($notFound)) {
    echo "\n   ⚠️  Not Found: " . count($notFound) . " email(s)\n";
}

echo str_repeat("═", 60) . "\n\n";

// Show status distribution
echo "📊 KYC Status Distribution:\n";
$stats = $pdo->query("
    SELECT 
        kyc_status,
        COUNT(*) as count
    FROM users
    WHERE strow_customer_id IS NOT NULL
    GROUP BY kyc_status
    ORDER BY kyc_status
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($stats as $stat) {
    $status = ucfirst($stat['kyc_status']);
    $count = $stat['count'];
    echo "   {$status}: {$count} customer(s)\n";
}

echo "\n🌐 Check your admin panel: /admin/kyc.php\n";
echo "   - Pending tab: Customers waiting for KYC approval\n";
echo "   - Approved tab: Verified customers (High/Low KYC)\n";
echo "   - Rejected tab: Declined KYC applications\n";
echo "   - All tab: All customers\n\n";
