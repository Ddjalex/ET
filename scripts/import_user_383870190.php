<?php
/**
 * Import Specific User from StroWallet to Database
 * User: 383870190 (Kalkidan Semeneh - amanuail071@gmail.com)
 * 
 * Usage: php scripts/import_user_383870190.php
 */

require_once __DIR__ . '/../secrets/load_env.php';

define('STROW_BASE', 'https://strowallet.com/api');
define('STROWALLET_API_KEY', getenv('STROWALLET_API_KEY') ?: '');

if (empty(STROWALLET_API_KEY)) {
    die("ERROR: STROWALLET_API_KEY not found in environment variables\n");
}

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

function mapKYCStatus($strowStatus) {
    $status = strtolower($strowStatus ?? 'pending');
    return match($status) {
        'verified', 'approved', 'high kyc', 'low kyc' => 'approved',
        'rejected', 'failed' => 'rejected',
        default => 'pending'
    };
}

$targetEmail = 'amanuail071@gmail.com';
$telegramId = 383870190;

echo "🔄 Fetching user from StroWallet API...\n";
echo "   Email: {$targetEmail}\n";
echo "   Telegram ID: {$telegramId}\n\n";

$result = callStroWalletAPI('/bitvcard/getcardholder/?customerEmail=' . urlencode($targetEmail), 'GET');

if (isset($result['error'])) {
    die("❌ Network error: {$result['error']}\n");
}

if ($result['http_code'] !== 200) {
    die("❌ API error (HTTP {$result['http_code']}): " . ($result['raw'] ?? 'Unknown error') . "\n");
}

if (!isset($result['data'])) {
    die("❌ No data received from StroWallet API\n");
}

$customerData = isset($result['data']['data']) ? $result['data']['data'] : $result['data'];

echo "✓ Successfully fetched customer data from StroWallet\n";
echo "   Raw response:\n";
echo "   " . json_encode($customerData, JSON_PRETTY_PRINT) . "\n\n";

$customerId = $customerData['customerId'] ?? $customerData['customer_id'] ?? $customerData['id'] ?? null;
$firstName = $customerData['firstName'] ?? $customerData['first_name'] ?? 'Kalkidan';
$lastName = $customerData['lastName'] ?? $customerData['last_name'] ?? 'Semeneh';
$phone = $customerData['phone'] ?? $customerData['phoneNumber'] ?? '';
$email = $customerData['customerEmail'] ?? $customerData['email'] ?? $targetEmail;
$kycStatus = mapKYCStatus($customerData['kycStatus'] ?? $customerData['kyc_status'] ?? 'High KYC');

$idImagePath = '/uploads/kyc_documents/383870190_id_image_1761633531_c58f5c7e6dda84f6.jpg';
$userPhotoPath = '/uploads/kyc_documents/383870190_user_photo_1761633556_3968603ed2337023.jpg';

echo "📝 User details to import:\n";
echo "   Customer ID: " . ($customerId ?? 'N/A') . "\n";
echo "   Name: {$firstName} {$lastName}\n";
echo "   Email: {$email}\n";
echo "   Phone: {$phone}\n";
echo "   KYC Status: {$kycStatus}\n";
echo "   Telegram ID: {$telegramId}\n\n";

$checkStmt = $pdo->prepare("SELECT id, telegram_id, kyc_status FROM users WHERE telegram_id = ? OR email = ?");
$checkStmt->execute([$telegramId, $email]);
$existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

try {
    if ($existingUser) {
        echo "🔄 User already exists in database (ID: {$existingUser['id']})\n";
        echo "   Updating user information...\n";
        
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET telegram_id = ?,
                email = ?,
                first_name = ?,
                last_name = ?,
                phone = ?,
                kyc_status = ?,
                strow_customer_id = ?,
                id_image_url = ?,
                user_photo_url = ?,
                kyc_approved_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $telegramId,
            $email,
            $firstName,
            $lastName,
            $phone,
            $kycStatus,
            $customerId,
            $idImagePath,
            $userPhotoPath,
            $existingUser['id']
        ]);
        
        echo "✅ User updated successfully!\n";
    } else {
        echo "➕ Creating new user in database...\n";
        
        $insertStmt = $pdo->prepare("
            INSERT INTO users (
                telegram_id, 
                email, 
                phone, 
                first_name, 
                last_name, 
                kyc_status,
                kyc_approved_at,
                strow_customer_id,
                id_image_url,
                user_photo_url,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $insertStmt->execute([
            $telegramId,
            $email,
            $phone,
            $firstName,
            $lastName,
            $kycStatus,
            $customerId,
            $idImagePath,
            $userPhotoPath
        ]);
        
        $userId = $pdo->lastInsertId();
        echo "✅ User created successfully! (ID: {$userId})\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Import Complete!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    $verifyStmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $verifyStmt->execute([$telegramId]);
    $user = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "📊 Verified user in database:\n";
        echo "   ID: {$user['id']}\n";
        echo "   Telegram ID: {$user['telegram_id']}\n";
        echo "   Name: {$user['first_name']} {$user['last_name']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Phone: {$user['phone']}\n";
        echo "   KYC Status: {$user['kyc_status']}\n";
        echo "   StroWallet Customer ID: {$user['strow_customer_id']}\n";
        echo "\n🌐 Check admin panel: /admin/kyc.php\n";
    }
    
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage() . "\n");
}
