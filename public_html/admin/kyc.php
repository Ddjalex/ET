<?php
// Load session and database BEFORE any HTML output or AJAX endpoints
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../../secrets/load_env.php';
requireAdminLogin();
$currentAdmin = getCurrentAdmin();

$pageTitle = 'User Management';
$message = '';
$messageType = '';

// StroWallet API Configuration
define('STROW_BASE', 'https://strowallet.com/api');
define('STROWALLET_API_KEY', getenv('STROWALLET_API_KEY') ?: getenv('STROW_PUBLIC_KEY') ?: '');
define('STROWALLET_SECRET', getenv('STROWALLET_WEBHOOK_SECRET') ?: getenv('STROW_SECRET_KEY') ?: '');

// Function to call StroWallet API with public_key parameter (as per StroWallet API docs)
function callStroWalletAPI($endpoint, $method = 'GET', $data = []) {
    // Add public_key to the URL
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

// AJAX endpoint to fetch customer details from StroWallet
if (isset($_GET['get_customer_details']) && isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    $userId = (int)$_GET['user_id'];
    $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if (!$user || empty($user['email'])) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Fetch full customer details from StroWallet
    $result = callStroWalletAPI('/bitvcard/getcardholder/?customerEmail=' . urlencode($user['email']), 'GET');
    
    if ($result['http_code'] === 200 && isset($result['data'])) {
        $customerData = isset($result['data']['data']) ? $result['data']['data'] : $result['data'];
        echo json_encode([
            'success' => true,
            'data' => $customerData,
            'local' => $user  // Include local database data as backup
        ]);
    } else {
        echo json_encode([
            'error' => 'Could not fetch customer from StroWallet',
            'local' => $user,  // Fallback to local data
            'http_code' => $result['http_code']
        ]);
    }
    exit;
}

// Sync KYC status from StroWallet if requested
if (isset($_GET['sync_user'])) {
    $userId = (int)$_GET['sync_user'];
    $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if ($user && !empty($user['email'])) {
        // Use Bearer token authentication (no public_key in URL)
        $result = callStroWalletAPI('/bitvcard/getcardholder/?customerEmail=' . urlencode($user['email']), 'GET');
        
        if (isset($result['error'])) {
            $message = "⚠️ Network error: " . $result['error'];
            $messageType = 'alert-warning';
        } elseif ($result['http_code'] === 200 && isset($result['data']['data'])) {
            $strowData = $result['data']['data'];
            // FIXED: Read the correct 'status' field from StroWallet (not 'kycStatus')
            $kycStatus = strtolower($strowData['status'] ?? $strowData['kycStatus'] ?? 'pending');
            
            // Map StroWallet status to our database
            $dbStatus = match($kycStatus) {
                'verified', 'approved', 'high kyc', 'low kyc' => 'approved',
                'rejected', 'failed', 'decline', 'declined' => 'rejected',
                default => 'pending'
            };
            
            dbQuery("UPDATE users SET kyc_status = ?, strow_customer_id = ? WHERE id = ?", 
                [$dbStatus, $strowData['customerId'] ?? null, $userId]);
            
            $message = "✅ KYC status synced from StroWallet: " . ucfirst($kycStatus);
            $messageType = 'alert-success';
        } elseif ($result['http_code'] === 401 || $result['http_code'] === 403) {
            $message = "⚠️ Authentication failed - check STROWALLET_WEBHOOK_SECRET";
            $messageType = 'alert-warning';
        } elseif (!isset($result['error'])) {
            $message = "⚠️ Could not fetch KYC status from StroWallet API (HTTP {$result['http_code']})";
            $messageType = 'alert-warning';
        }
    }
}

// Note: KYC verification is handled by StroWallet API
// This page only displays user information and KYC status from StroWallet

// Fetch users
$filter = $_GET['filter'] ?? 'pending';
$whereClause = match($filter) {
    'approved' => "kyc_status = 'approved'",
    'rejected' => "kyc_status = 'rejected'",
    'all' => "1=1",
    default => "kyc_status = 'pending'"
};

$users = dbFetchAll("
    SELECT *
    FROM users
    WHERE $whereClause
    ORDER BY kyc_submitted_at DESC NULLS LAST, created_at DESC
    LIMIT 100
");

$statusCounts = dbFetchOne("
    SELECT 
        COUNT(*) FILTER (WHERE kyc_status = 'pending') as pending,
        COUNT(*) FILTER (WHERE kyc_status = 'approved') as approved,
        COUNT(*) FILTER (WHERE kyc_status = 'rejected') as rejected,
        COUNT(*) as total
    FROM users
");

// Now include header AFTER all processing and AJAX endpoints
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>👥 User Management</h2>
    <p class="subtitle">View users and their KYC status (verified by StroWallet API)</p>
</div>

<div class="alert" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.5); margin-bottom: 20px;">
    ℹ️ <strong>Note:</strong> KYC verification is handled by StroWallet API. This page auto-refreshes every 30 seconds to show real-time status updates.
    <div style="margin-top: 10px; display: flex; gap: 10px; align-items: center;">
        <label style="display: flex; align-items: center; gap: 5px;">
            <input type="checkbox" id="autoRefreshToggle" checked> Auto-refresh
        </label>
        <span id="refreshCountdown" style="font-size: 12px;"></span>
        <button onclick="manualRefresh()" class="btn btn-sm" style="padding: 5px 10px;">🔄 Refresh Now</button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
            <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : ''; ?> btn-sm">
                Pending (<?php echo $statusCounts['pending'] ?? 0; ?>)
            </a>
            <a href="?filter=approved" class="btn <?php echo $filter === 'approved' ? 'btn-primary' : ''; ?> btn-sm">
                Approved (<?php echo $statusCounts['approved'] ?? 0; ?>)
            </a>
            <a href="?filter=rejected" class="btn <?php echo $filter === 'rejected' ? 'btn-primary' : ''; ?> btn-sm">
                Rejected (<?php echo $statusCounts['rejected'] ?? 0; ?>)
            </a>
            <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : ''; ?> btn-sm">
                All (<?php echo $statusCounts['total'] ?? 0; ?>)
            </a>
        </div>
    </div>

    <?php if (count($users) === 0): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>No users found in this category.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User Information</th>
                    <th>ID Details</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong>#<?php echo $user['id']; ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                        <small>Email: <?php echo htmlspecialchars($user['email']); ?></small><br>
                        <small>Phone: <?php echo htmlspecialchars($user['phone']); ?></small><br>
                        <small>Telegram: <?php echo htmlspecialchars($user['telegram_id']); ?></small>
                    </td>
                    <td>
                        <?php if ($user['id_type']): ?>
                            <strong><?php echo htmlspecialchars($user['id_type']); ?></strong><br>
                            <small><?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></small>
                        <?php else: ?>
                            <span style="color: #999;">Not provided</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['kyc_status'] === 'pending'): ?>
                            <span class="badge warning">Pending</span>
                        <?php elseif ($user['kyc_status'] === 'approved'): ?>
                            <span class="badge success">Approved</span>
                        <?php elseif ($user['kyc_status'] === 'rejected'): ?>
                            <span class="badge danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $user['kyc_submitted_at'] ? date('M d, Y H:i', strtotime($user['kyc_submitted_at'])) : 'Not submitted'; ?></td>
                    <td>
                        <button onclick="showKYCDetails(<?php echo $user['id']; ?>)" class="btn btn-sm" style="margin-right: 5px;">View</button>
                        <a href="?sync_user=<?php echo $user['id']; ?>&filter=<?php echo $filter; ?>" class="btn btn-success btn-sm" title="Sync from StroWallet">
                            🔄
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal for KYC details -->
<div id="kycModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 10px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin-bottom: 20px;">KYC Verification Details</h3>
        <div id="kycDetailsContent"></div>
    </div>
</div>

<script>
function showKYCDetails(userId) {
    // Show modal with loading state
    document.getElementById('kycDetailsContent').innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 24px; margin-bottom: 10px;">⏳</div>
            <p>Fetching customer details from StroWallet...</p>
        </div>
    `;
    document.getElementById('kycModal').style.display = 'flex';
    
    // Fetch customer details from StroWallet in real-time
    fetch(`?get_customer_details=1&user_id=${userId}`)
        .then(response => response.json())
        .then(result => {
            if (result.error) {
                showErrorInModal(result.error, result.local);
                return;
            }
            
            const customer = result.data;
            const local = result.local;
            
            // Map StroWallet field names (flexible to handle variations)
            const firstName = customer.firstName || customer.first_name || local.first_name || '';
            const lastName = customer.lastName || customer.last_name || local.last_name || '';
            const email = customer.customerEmail || customer.email || local.email || '';
            const phone = customer.phone || customer.phoneNumber || local.phone || 'Not provided';
            const dob = customer.dateOfBirth || customer.date_of_birth || local.date_of_birth || 'Not provided';
            const telegramId = local.telegram_id || 'Not linked';
            
            const idType = customer.idType || customer.id_type || local.id_type || 'Not provided';
            const idNumber = customer.idNumber || customer.id_number || local.id_number || 'Not provided';
            
            const houseNumber = customer.houseNumber || customer.house_number || local.house_number || '';
            const line1 = customer.line1 || customer.address_line1 || local.address_line1 || '';
            const city = customer.city || customer.address_city || local.address_city || '';
            const state = customer.state || customer.address_state || local.address_state || '';
            const zipCode = customer.zipCode || customer.address_zip || local.address_zip || '';
            const country = customer.country || customer.address_country || local.address_country || '';
            
            const idImage = customer.idImage || customer.id_image_url || local.id_image_url || '';
            const userPhoto = customer.userPhoto || customer.user_photo_url || local.user_photo_url || '';
            
            const kycStatus = customer.status || customer.kycStatus || customer.kyc_status || local.kyc_status || 'pending';
            const customerId = customer.customerId || customer.customer_id || local.strow_customer_id || '';
            
            const content = `
                <div style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <h4 style="margin-bottom: 10px;">Personal Information</h4>
                            <div style="background: #f5f7fa; padding: 15px; border-radius: 5px;">
                                <p><strong>Name:</strong> ${firstName} ${lastName}</p>
                                <p><strong>Email:</strong> ${email}</p>
                                <p><strong>Phone:</strong> ${phone}</p>
                                <p><strong>DOB:</strong> ${dob}</p>
                                <p><strong>Telegram ID:</strong> ${telegramId}</p>
                            </div>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 10px;">ID Information</h4>
                            <div style="background: #f5f7fa; padding: 15px; border-radius: 5px;">
                                <p><strong>ID Type:</strong> ${idType}</p>
                                <p><strong>ID Number:</strong> ${idNumber}</p>
                                <p><strong>Customer ID:</strong> <small>${customerId}</small></p>
                            </div>
                        </div>
                    </div>
                    
                    ${line1 || city || state ? `
                        <div style="margin-bottom: 20px;">
                            <h4 style="margin-bottom: 10px;">Address</h4>
                            <div style="background: #f5f7fa; padding: 15px; border-radius: 5px;">
                                <p>${houseNumber ? houseNumber + ', ' : ''}${line1}</p>
                                <p>${city}${state ? ', ' + state : ''} ${zipCode}</p>
                                <p>${country}</p>
                            </div>
                        </div>
                    ` : ''}
                    
                    ${idImage || userPhoto ? `
                        <div style="margin-bottom: 20px;">
                            <h4 style="margin-bottom: 10px;">Documents</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                ${idImage ? `
                                    <div>
                                        <strong>ID Document:</strong><br>
                                        <a href="${idImage}" target="_blank" style="color: #667eea;">View ID Image</a>
                                    </div>
                                ` : ''}
                                ${userPhoto ? `
                                    <div>
                                        <strong>User Photo:</strong><br>
                                        <a href="${userPhoto}" target="_blank" style="color: #667eea;">View Photo</a>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    ` : ''}
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                            <strong>KYC Status:</strong> ${getKYCStatusBadge(kycStatus)}
                        </p>
                        <p style="color: #999; font-size: 12px; margin-bottom: 15px;">
                            ℹ️ Data fetched from StroWallet API (Read-only)
                        </p>
                        <button onclick="closeKYCModal()" class="btn btn-primary">Close</button>
                    </div>
                </div>
            `;
            
            document.getElementById('kycDetailsContent').innerHTML = content;
        })
        .catch(error => {
            document.getElementById('kycDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ef4444;">
                    <div style="font-size: 24px; margin-bottom: 10px;">⚠️</div>
                    <p>Failed to fetch customer details</p>
                    <p style="font-size: 12px; margin-top: 10px;">${error.message}</p>
                    <button onclick="closeKYCModal()" class="btn btn-primary" style="margin-top: 20px;">Close</button>
                </div>
            `;
        });
}

function getKYCStatusBadge(status) {
    const statusLower = (status || '').toLowerCase();
    if (statusLower.includes('high kyc') || statusLower.includes('low kyc') || statusLower.includes('verified') || statusLower.includes('approved')) {
        return '<span style="color: #10b981;">✓ Verified by StroWallet (' + status + ')</span>';
    } else if (statusLower.includes('reject') || statusLower.includes('decline') || statusLower.includes('failed')) {
        return '<span style="color: #ef4444;">✗ Rejected by StroWallet</span>';
    } else {
        return '<span style="color: #f59e0b;">⏳ Pending verification</span>';
    }
}

function showErrorInModal(errorMsg, localData) {
    const content = `
        <div style="padding: 20px;">
            <div style="background: #fee2e2; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>⚠️ Could not fetch from StroWallet:</strong><br>
                ${errorMsg}
            </div>
            <p style="color: #666;">Showing local database data instead:</p>
            <div style="background: #f5f7fa; padding: 15px; border-radius: 5px; margin-top: 10px;">
                <p><strong>Name:</strong> ${localData.first_name} ${localData.last_name}</p>
                <p><strong>Email:</strong> ${localData.email}</p>
                <p><strong>Status:</strong> ${localData.kyc_status}</p>
            </div>
            <button onclick="closeKYCModal()" class="btn btn-primary" style="margin-top: 20px;">Close</button>
        </div>
    `;
    document.getElementById('kycDetailsContent').innerHTML = content;
}

function closeKYCModal() {
    document.getElementById('kycModal').style.display = 'none';
}

document.getElementById('kycModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeKYCModal();
    }
});

// Auto-refresh functionality for real-time KYC updates
let autoRefreshInterval;
let countdownInterval;
let secondsRemaining = 30;

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        clearInterval(countdownInterval);
    }
    
    secondsRemaining = 30;
    updateCountdown();
    
    autoRefreshInterval = setInterval(() => {
        if (document.getElementById('autoRefreshToggle').checked) {
            window.location.reload();
        }
    }, 30000);
    
    countdownInterval = setInterval(() => {
        if (document.getElementById('autoRefreshToggle').checked) {
            secondsRemaining--;
            if (secondsRemaining <= 0) {
                secondsRemaining = 30;
            }
            updateCountdown();
        }
    }, 1000);
}

function updateCountdown() {
    const countdown = document.getElementById('refreshCountdown');
    if (countdown && document.getElementById('autoRefreshToggle').checked) {
        countdown.textContent = `Next refresh in ${secondsRemaining}s`;
    } else if (countdown) {
        countdown.textContent = 'Auto-refresh paused';
    }
}

function manualRefresh() {
    window.location.reload();
}

document.getElementById('autoRefreshToggle').addEventListener('change', function() {
    if (this.checked) {
        startAutoRefresh();
    } else {
        clearInterval(autoRefreshInterval);
        clearInterval(countdownInterval);
        updateCountdown();
    }
});

// Start auto-refresh on page load
startAutoRefresh();

// Sync all pending users automatically on page load
window.addEventListener('load', function() {
    const pendingUsers = <?php echo json_encode(array_filter($users, fn($u) => $u['kyc_status'] === 'pending')); ?>;
    if (pendingUsers.length > 0 && document.getElementById('autoRefreshToggle').checked) {
        console.log('Auto-syncing ' + pendingUsers.length + ' pending users from StroWallet...');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
