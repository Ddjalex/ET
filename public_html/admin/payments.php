<?php
$pageTitle = 'Payment Verification';
$currentPage = 'payments';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $paymentId = intval($_POST['payment_id'] ?? 0);
        $adminId = $currentAdmin['id'];
        
        $db = getDBConnection();
        
        try {
            $db->beginTransaction();
            
            if ($action === 'approve') {
                $payment = dbFetchOne("SELECT dp.*, u.telegram_id, u.first_name, u.last_name 
                                      FROM deposit_payments dp 
                                      JOIN users u ON dp.user_id = u.id 
                                      WHERE dp.id = ? AND dp.status = 'pending'", [$paymentId], $db);
                
                if (!$payment) {
                    throw new Exception('Payment not found or already processed');
                }
                
                dbQuery("UPDATE deposit_payments SET status = 'approved', verified_by = 'admin', verified_at = NOW(), completed_at = NOW() WHERE id = ?", 
                       [$paymentId], $db);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, action_description, payload) 
                        VALUES (?, 'approve_payment', 'deposit_payments', ?, 'Approved payment', ?)",
                       [$adminId, $paymentId, json_encode(['amount_usd' => $payment['amount_usd'], 'user_id' => $payment['user_id']])], $db);
                
                $db->commit();
                
                $message = "Payment approved successfully! $" . number_format($payment['amount_usd'], 2) . " approved for user.";
                $messageType = 'success';
                
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                
                if (empty($reason)) {
                    throw new Exception('Rejection reason is required');
                }
                
                $payment = dbFetchOne("SELECT * FROM deposit_payments WHERE id = ? AND status = 'pending'", [$paymentId], $db);
                
                if (!$payment) {
                    throw new Exception('Payment not found or already processed');
                }
                
                dbQuery("UPDATE deposit_payments SET status = 'rejected', rejected_reason = ?, updated_at = NOW() WHERE id = ?",
                       [$reason, $paymentId], $db);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, action_description, payload) 
                        VALUES (?, 'reject_payment', 'deposit_payments', ?, 'Rejected payment', ?)",
                       [$adminId, $paymentId, json_encode(['reason' => $reason, 'user_id' => $payment['user_id']])], $db);
                
                $db->commit();
                
                $message = 'Payment rejected successfully.';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$filter = $_GET['filter'] ?? 'pending';
$whereClause = match($filter) {
    'approved' => "dp.status = 'approved'",
    'rejected' => "dp.status = 'rejected'",
    'all' => "1=1",
    default => "dp.status = 'pending'"
};

$payments = dbFetchAll("
    SELECT dp.*, u.first_name, u.last_name, u.email, u.telegram_id, u.phone
    FROM deposit_payments dp
    JOIN users u ON dp.user_id = u.id
    WHERE $whereClause
    ORDER BY dp.created_at DESC
    LIMIT 100
");

$statusCounts = dbFetchOne("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'pending') as pending,
        COUNT(*) FILTER (WHERE status = 'approved') as approved,
        COUNT(*) FILTER (WHERE status = 'rejected') as rejected,
        COUNT(*) as total
    FROM deposit_payments
");
?>

<style>
.filter-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 12px 24px;
    background: var(--glass-bg);
    border: 2px solid var(--card-border);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-tab:hover {
    background: var(--glass-bg);
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.filter-tab.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    box-shadow: var(--shadow-glow);
}

.filter-count {
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
}

.payment-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: 24px;
    margin-bottom: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.payment-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary-gradient);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl), var(--shadow-glow);
    border-color: rgba(102, 126, 234, 0.6);
}

.payment-card:hover::before {
    transform: scaleY(1);
}

.payment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}

.payment-id {
    font-size: 1.5rem;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.user-info {
    flex: 1;
}

.user-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.user-details {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.amount-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.amount-box {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    padding: 20px;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.amount-box:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
}

.amount-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

.amount-value {
    font-size: 1.75rem;
    font-weight: 900;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.payment-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin: 20px 0;
    padding: 16px;
    background: var(--glass-bg);
    border-radius: var(--radius-md);
    border: 1px solid var(--card-border);
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
}

.detail-value {
    font-size: 0.95rem;
    color: var(--text-primary);
    font-weight: 500;
}

.screenshot-section {
    margin: 20px 0;
    padding: 16px;
    background: var(--glass-bg);
    border-radius: var(--radius-md);
    border: 1px solid var(--card-border);
}

.screenshot-img {
    max-width: 400px;
    width: 100%;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: transform 0.3s ease;
}

.screenshot-img:hover {
    transform: scale(1.05);
}

.payment-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--card-border);
    flex-wrap: wrap;
    gap: 12px;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-out;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    padding: 35px;
    border-radius: var(--radius-xl);
    max-width: 500px;
    width: 90%;
    box-shadow: var(--shadow-xl), var(--shadow-glow);
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--card-border);
}

.modal-title {
    font-size: 1.75rem;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--card-bg);
    border-radius: var(--radius-xl);
    border: 1px solid var(--glass-border);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-text {
    font-size: 1.2rem;
    color: var(--text-muted);
    font-weight: 600;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<div style="flex: 1; padding: 2rem; margin-left: 240px; margin-top: 70px;">
    <div style="max-width: 1400px; margin: 0 auto;">
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 2.5rem; font-weight: 900; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.5rem;">
                💳 Payment Verification
            </h1>
            <p style="color: var(--text-muted); font-size: 1.1rem;">Review and approve/reject user payment submissions</p>
        </div>

        <?php if ($message): ?>
            <div style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; background: <?php echo $messageType === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; border: 1px solid <?php echo $messageType === 'success' ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>; border-radius: var(--radius-lg); color: <?php echo $messageType === 'success' ? '#10b981' : '#ef4444'; ?>; font-weight: 600;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                ⏳ Pending
                <span class="filter-count"><?php echo $statusCounts['pending'] ?? 0; ?></span>
            </a>
            <a href="?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                ✅ Approved
                <span class="filter-count"><?php echo $statusCounts['approved'] ?? 0; ?></span>
            </a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                ❌ Rejected
                <span class="filter-count"><?php echo $statusCounts['rejected'] ?? 0; ?></span>
            </a>
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                📋 All
                <span class="filter-count"><?php echo $statusCounts['total'] ?? 0; ?></span>
            </a>
        </div>

        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <div class="empty-icon">💳</div>
                <div class="empty-text">No payments found</div>
            </div>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="user-info">
                            <div class="payment-id">#<?php echo $payment['id']; ?></div>
                            <div class="user-name"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                            <div class="user-details">
                                📱 <?php echo htmlspecialchars($payment['phone'] ?? 'N/A'); ?> | 
                                📧 <?php echo htmlspecialchars($payment['email'] ?? 'N/A'); ?> |
                                💬 TG: <?php echo htmlspecialchars($payment['telegram_id']); ?>
                            </div>
                        </div>
                        <div>
                            <?php
                            $statusStyles = [
                                'pending' => ['bg' => 'rgba(234, 179, 8, 0.15)', 'border' => 'rgba(234, 179, 8, 0.4)', 'text' => '#f59e0b', 'icon' => '⏳'],
                                'approved' => ['bg' => 'rgba(34, 197, 94, 0.15)', 'border' => 'rgba(34, 197, 94, 0.4)', 'text' => '#10b981', 'icon' => '✅'],
                                'rejected' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'border' => 'rgba(239, 68, 68, 0.4)', 'text' => '#ef4444', 'icon' => '❌']
                            ];
                            $status = $payment['status'];
                            $style = $statusStyles[$status] ?? $statusStyles['pending'];
                            ?>
                            <span style="padding: 8px 16px; background: <?php echo $style['bg']; ?>; color: <?php echo $style['text']; ?>; border: 1px solid <?php echo $style['border']; ?>; border-radius: var(--radius-md); font-weight: 700; font-size: 0.9rem;">
                                <?php echo $style['icon']; ?> <?php echo ucfirst($status); ?>
                            </span>
                        </div>
                    </div>

                    <div class="amount-section">
                        <div class="amount-box">
                            <div class="amount-label">💵 USD Amount</div>
                            <div class="amount-value">$<?php echo number_format($payment['amount_usd'], 2); ?></div>
                        </div>
                        <div class="amount-box">
                            <div class="amount-label">💸 ETB Amount</div>
                            <div class="amount-value"><?php echo number_format($payment['amount_etb'], 2); ?> ETB</div>
                        </div>
                        <div class="amount-box">
                            <div class="amount-label">💰 Total Paid</div>
                            <div class="amount-value"><?php echo number_format($payment['total_etb'], 2); ?> ETB</div>
                        </div>
                    </div>

                    <div class="payment-details">
                        <div class="detail-item">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['payment_method']))); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Exchange Rate</div>
                            <div class="detail-value"><?php echo number_format($payment['exchange_rate'], 2); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Deposit Fee</div>
                            <div class="detail-value"><?php echo number_format($payment['deposit_fee_etb'], 2); ?> ETB</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Transaction ID</div>
                            <div class="detail-value"><?php echo htmlspecialchars($payment['transaction_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Submitted</div>
                            <div class="detail-value"><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></div>
                        </div>
                    </div>

                    <?php if ($payment['screenshot_url']): ?>
                        <div class="screenshot-section">
                            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">📸 Payment Screenshot:</div>
                            <a href="<?php echo htmlspecialchars($payment['screenshot_url']); ?>" target="_blank">
                                <img src="<?php echo htmlspecialchars($payment['screenshot_url']); ?>" alt="Payment Screenshot" class="screenshot-img">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($payment['notes']): ?>
                        <div style="margin-top: 16px; padding: 16px; background: var(--glass-bg); border-radius: var(--radius-md); border: 1px solid var(--card-border);">
                            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">📝 Notes:</div>
                            <div style="color: var(--text-muted);"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($payment['rejected_reason']): ?>
                        <div style="margin-top: 16px; padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.3);">
                            <div style="font-weight: 700; color: #ef4444; margin-bottom: 8px;">❌ Rejection Reason:</div>
                            <div style="color: var(--text-muted);"><?php echo nl2br(htmlspecialchars($payment['rejected_reason'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="payment-footer">
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Payment ID: #<?php echo $payment['id']; ?> | User ID: <?php echo $payment['user_id']; ?>
                        </div>

                        <?php if ($payment['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 12px;">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this payment?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <button type="submit" style="padding: 12px 24px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                                        ✅ Approve Payment
                                    </button>
                                </form>
                                <button onclick="showRejectModal(<?php echo $payment['id']; ?>)" style="padding: 12px 24px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                                    ❌ Reject Payment
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="rejectModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">❌ Reject Payment</div>
            <button onclick="closeRejectModal()" style="background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: color 0.3s;">✕</button>
        </div>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="payment_id" id="rejectPaymentId">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-primary);">Rejection Reason *</label>
                <textarea name="rejection_reason" required style="width: 100%; min-height: 120px; padding: 12px; background: var(--glass-bg); border: 1px solid var(--card-border); border-radius: var(--radius-md); color: var(--text-primary); font-family: inherit; font-size: 1rem; resize: vertical;" placeholder="Enter reason for rejection..."></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeRejectModal()" style="padding: 12px 24px; background: var(--glass-bg); border: 1px solid var(--card-border); border-radius: var(--radius-md); color: var(--text-primary); font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="padding: 12px 24px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                    Reject Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(paymentId) {
    document.getElementById('rejectPaymentId').value = paymentId;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    document.getElementById('rejectForm').reset();
}

document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
