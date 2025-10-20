<?php
$pageTitle = 'KYC Verification';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$messageType = '';

// Handle KYC approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = intval($_POST['user_id'] ?? 0);
        $adminId = $currentAdmin['id'];
        
        try {
            if ($action === 'approve') {
                dbQuery("UPDATE users SET kyc_status = 'approved', kyc_approved_at = NOW() WHERE id = ?", [$userId]);
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, action_description) 
                        VALUES (?, 'approve_kyc', 'users', ?, 'Approved KYC verification')", [$adminId, $userId]);
                
                $message = 'KYC approved successfully!';
                $messageType = 'success';
                
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                if (empty($reason)) {
                    throw new Exception('Rejection reason is required');
                }
                
                dbQuery("UPDATE users SET kyc_status = 'rejected', kyc_rejected_at = NOW(), kyc_rejection_reason = ? WHERE id = ?", 
                       [$reason, $userId]);
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, action_description, payload) 
                        VALUES (?, 'reject_kyc', 'users', ?, 'Rejected KYC verification', ?)",
                       [$adminId, $userId, json_encode(['reason' => $reason])]);
                
                $message = 'KYC rejected successfully.';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch users for KYC verification
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
    <h2>✅ KYC Verification</h2>
    <p class="subtitle">Review and verify user identity documents</p>
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
                        <?php if ($user['kyc_status'] === 'pending'): ?>
                            <button onclick="showKYCDetails(<?php echo $user['id']; ?>)" class="btn btn-primary btn-sm">Review</button>
                        <?php else: ?>
                            <button onclick="showKYCDetails(<?php echo $user['id']; ?>)" class="btn btn-sm">View</button>
                        <?php endif; ?>
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
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; display: flex; gap: 10px; justify-content: space-between;">
                <button onclick="closeKYCModal()" class="btn">Close</button>
                ${canApprove ? `
                    <div style="display: flex; gap: 10px;">
                        <button onclick="showKYCRejectForm(${userId})" class="btn btn-danger">Reject</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this KYC?')">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="user_id" value="${userId}">
                            <button type="submit" class="btn btn-success">Approve KYC</button>
                        </form>
                    </div>
                ` : ''}
            </div>
            
            <div id="kycRejectForm${userId}" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" value="${userId}">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">Rejection Reason:</label>
                    <textarea name="rejection_reason" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 80px;"></textarea>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <button type="button" onclick="hideKYCRejectForm(${userId})" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('kycDetailsContent').innerHTML = content;
    document.getElementById('kycModal').style.display = 'flex';
}

function closeKYCModal() {
    document.getElementById('kycModal').style.display = 'none';
}

function showKYCRejectForm(userId) {
    document.getElementById('kycRejectForm' + userId).style.display = 'block';
}

function hideKYCRejectForm(userId) {
    document.getElementById('kycRejectForm' + userId).style.display = 'none';
}

document.getElementById('kycModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeKYCModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
