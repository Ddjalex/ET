<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../../secrets/load_env.php';

$message = '';
$messageType = '';

// StroWallet API Configuration
define('STROW_BASE', 'https://strowallet.com/api');
define('STROW_PUBLIC_KEY', getenv('STROW_PUBLIC_KEY') ?: '');
define('STROW_SECRET_KEY', getenv('STROW_SECRET_KEY') ?: '');

// Function to call StroWallet API
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

// Sync KYC status from StroWallet if requested
if (isset($_GET['sync_user'])) {
    $userId = (int)$_GET['sync_user'];
    $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if ($user && !empty($user['email'])) {
        $result = callStroWalletAPI('/bitvcard/getcardholder/?public_key=' . STROW_PUBLIC_KEY . '&customerEmail=' . urlencode($user['email']), 'GET');
        
        if ($result['http_code'] === 200 && isset($result['data']['data'])) {
            $strowData = $result['data']['data'];
            $kycStatus = strtolower($strowData['kycStatus'] ?? 'pending');
            
            // Map StroWallet status to our database
            $dbStatus = match($kycStatus) {
                'verified', 'approved' => 'approved',
                'rejected', 'failed' => 'rejected',
                default => 'pending'
            };
            
            dbQuery("UPDATE users SET kyc_status = ?, strow_customer_id = ? WHERE id = ?", 
                [$dbStatus, $strowData['customerId'] ?? null, $userId]);
            
            $message = "✅ KYC status synced from StroWallet: " . ucfirst($kycStatus);
            $messageType = 'alert-success';
        } else {
            $message = "⚠️ Could not fetch KYC status from StroWallet API";
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
?>

<div class="page-header">
    <h2>👥 User Management</h2>
    <p class="subtitle">View users and their KYC status (verified by StroWallet API)</p>
</div>

<div class="alert" style="background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.5); margin-bottom: 20px;">
    ℹ️ <strong>Note:</strong> KYC verification is handled by StroWallet API. Click 🔄 to sync real-time status from StroWallet for each user.
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
    const users = <?php echo json_encode($users); ?>;
    const user = users.find(u => u.id === userId);
    
    if (!user) return;
    
    const canApprove = user.kyc_status === 'pending';
    
    const content = `
        <div style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <h4 style="margin-bottom: 10px;">Personal Information</h4>
                    <div style="background: #f5f7fa; padding: 15px; border-radius: 5px;">
                        <p><strong>Name:</strong> ${user.first_name} ${user.last_name}</p>
                        <p><strong>Email:</strong> ${user.email}</p>
                        <p><strong>Phone:</strong> ${user.phone}</p>
                        <p><strong>DOB:</strong> ${user.date_of_birth || 'Not provided'}</p>
                        <p><strong>Telegram ID:</strong> ${user.telegram_id}</p>
                    </div>
                </div>
                <div>
                    <h4 style="margin-bottom: 10px;">ID Information</h4>
                    <div style="background: #f5f7fa; padding: 15px; border-radius: 5px;">
                        <p><strong>ID Type:</strong> ${user.id_type || 'Not provided'}</p>
                        <p><strong>ID Number:</strong> ${user.id_number || 'Not provided'}</p>
                    </div>
                </div>
            </div>
            
            ${user.address_line1 ? `
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px;">Address</h4>
                    <div style="background: #f5f7fa; padding: 15px; border-radius: 5px;">
                        <p>${user.address_line1}${user.house_number ? ', ' + user.house_number : ''}</p>
                        <p>${user.address_city || ''}, ${user.address_state || ''} ${user.address_zip || ''}</p>
                        <p>${user.address_country || ''}</p>
                    </div>
                </div>
            ` : ''}
            
            ${user.id_image_url || user.user_photo_url ? `
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px;">Documents</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        ${user.id_image_url ? `
                            <div>
                                <strong>ID Document:</strong><br>
                                <a href="${user.id_image_url}" target="_blank" style="color: #667eea;">View ID Image</a>
                            </div>
                        ` : ''}
                        ${user.user_photo_url ? `
                            <div>
                                <strong>User Photo:</strong><br>
                                <a href="${user.user_photo_url}" target="_blank" style="color: #667eea;">View Photo</a>
                            </div>
                        ` : ''}
                    </div>
                </div>
            ` : ''}
            
            ${user.kyc_rejection_reason ? `
                <div style="background: #fee2e2; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <strong>Previous Rejection Reason:</strong><br>
                    ${user.kyc_rejection_reason}
                </div>
            ` : ''}
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                    <strong>KYC Status:</strong> ${user.kyc_status === 'approved' ? '<span style="color: #10b981;">✓ Verified by StroWallet</span>' : user.kyc_status === 'rejected' ? '<span style="color: #ef4444;">✗ Rejected by StroWallet</span>' : '<span style="color: #f59e0b;">⏳ Pending verification</span>'}
                </p>
                <button onclick="closeKYCModal()" class="btn btn-primary">Close</button>
            </div>
        </div>
    `;
    
    document.getElementById('kycDetailsContent').innerHTML = content;
    document.getElementById('kycModal').style.display = 'flex';
}

function closeKYCModal() {
    document.getElementById('kycModal').style.display = 'none';
}

document.getElementById('kycModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeKYCModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
