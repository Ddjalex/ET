<?php
$pageTitle = 'Deposits Management';
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
        $depositId = intval($_POST['deposit_id'] ?? 0);
        $adminId = $currentAdmin['id'];
        
        $db = getDBConnection();
        
        try {
            $db->beginTransaction();
            
            if ($action === 'approve') {
                $deposit = dbFetchOne("SELECT d.*, w.id as wallet_id, w.balance_usd FROM deposits d 
                                      JOIN wallets w ON d.wallet_id = w.id 
                                      WHERE d.id = ? AND d.status IN ('pending', 'payment_submitted')", [$depositId], $db);
                
                if (!$deposit) {
                    throw new Exception('Deposit not found or already processed');
                }
                
                dbQuery("UPDATE deposits SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?", 
                       [$adminId, $depositId], $db);
                
                $balanceBefore = $deposit['balance_usd'];
                $balanceAfter = $balanceBefore + $deposit['usd_amount'];
                
                dbQuery("INSERT INTO wallet_transactions (wallet_id, user_id, transaction_type, amount_usd, amount_etb, 
                        balance_before_usd, balance_after_usd, reference, description, status) 
                        VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, ?, 'completed')",
                       [$deposit['wallet_id'], $deposit['user_id'], $deposit['usd_amount'], $deposit['etb_amount_quote'],
                        $balanceBefore, $balanceAfter, 'DEP-' . $depositId, 'Deposit approved by admin'], $db);
                
                $transactionId = dbLastInsertId();
                
                dbQuery("UPDATE wallets SET balance_usd = balance_usd + ?, updated_at = NOW() WHERE id = ?",
                       [$deposit['usd_amount'], $deposit['wallet_id']], $db);
                
                dbQuery("UPDATE deposits SET wallet_transaction_id = ? WHERE id = ?", [$transactionId, $depositId], $db);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, action_description, payload) 
                        VALUES (?, 'approve_deposit', 'deposits', ?, 'Approved deposit', ?)",
                       [$adminId, $depositId, json_encode(['amount_usd' => $deposit['usd_amount'], 'user_id' => $deposit['user_id']])], $db);
                
                $db->commit();
                
                $message = "Deposit approved successfully! $" . number_format($deposit['usd_amount'], 2) . " added to user wallet.";
                $messageType = 'success';
                
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                
                if (empty($reason)) {
                    throw new Exception('Rejection reason is required');
                }
                
                $deposit = dbFetchOne("SELECT * FROM deposits WHERE id = ? AND status IN ('pending', 'payment_submitted')", [$depositId], $db);
                
                if (!$deposit) {
                    throw new Exception('Deposit not found or already processed');
                }
                
                dbQuery("UPDATE deposits SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?",
                       [$adminId, $reason, $depositId], $db);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, action_description, payload) 
                        VALUES (?, 'reject_deposit', 'deposits', ?, 'Rejected deposit', ?)",
                       [$adminId, $depositId, json_encode(['reason' => $reason, 'user_id' => $deposit['user_id']])], $db);
                
                $db->commit();
                
                $message = 'Deposit rejected successfully.';
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
    'approved' => "d.status = 'approved'",
    'rejected' => "d.status = 'rejected'",
    'all' => "1=1",
    default => "d.status IN ('pending', 'payment_submitted')"
};

$deposits = dbFetchAll("
    SELECT d.*, u.first_name, u.last_name, u.email, u.telegram_id, u.phone,
           w.balance_usd as current_balance
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    JOIN wallets w ON d.wallet_id = w.id
    WHERE $whereClause
    ORDER BY d.created_at DESC
    LIMIT 100
");

$statusCounts = dbFetchOne("
    SELECT 
        COUNT(*) FILTER (WHERE status IN ('pending', 'payment_submitted')) as pending,
        COUNT(*) FILTER (WHERE status = 'approved') as approved,
        COUNT(*) FILTER (WHERE status = 'rejected') as rejected,
        COUNT(*) as total
    FROM deposits
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

.deposit-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: 24px;
    margin-bottom: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.deposit-card::before {
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

.deposit-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl), var(--shadow-glow);
    border-color: rgba(102, 126, 234, 0.6);
}

.deposit-card:hover::before {
    transform: scaleY(1);
}

.deposit-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}

.deposit-id {
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

.deposit-footer {
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
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
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

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.info-item {
    background: var(--glass-bg);
    padding: 16px;
    border-radius: var(--radius-md);
    border: 1px solid var(--card-border);
}

.info-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

.info-value {
    font-size: 1.1rem;
    color: var(--text-primary);
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-text {
    color: var(--text-muted);
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .deposit-header {
        flex-direction: column;
    }
    
    .amount-section {
        grid-template-columns: 1fr;
    }
    
    .deposit-footer {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="admin-main">
    <div class="admin-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="header-title">
            <div class="header-icon" style="background: rgba(255,255,255,0.2);">💰</div>
            <div>
                <h1 style="color: white; margin: 0;">Deposits Management</h1>
                <p style="color: rgba(255,255,255,0.95); font-size: 0.95rem; margin: 0.25rem 0 0 0;">
                    Review and approve user deposit requests
                </p>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="animation: slideUp 0.4s ease-out;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="filter-tabs">
        <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
            ⏳ Pending
            <span class="filter-count"><?php echo $statusCounts['pending'] ?? 0; ?></span>
        </a>
        <a href="?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
            ✓ Approved
            <span class="filter-count"><?php echo $statusCounts['approved'] ?? 0; ?></span>
        </a>
        <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
            ✗ Rejected
            <span class="filter-count"><?php echo $statusCounts['rejected'] ?? 0; ?></span>
        </a>
        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
            📊 All
            <span class="filter-count"><?php echo $statusCounts['total'] ?? 0; ?></span>
        </a>
    </div>

    <?php if (count($deposits) === 0): ?>
        <div class="empty-state">
            <div class="empty-icon">💰</div>
            <p class="empty-text">No deposits found in this category</p>
        </div>
    <?php else: ?>
        <?php foreach ($deposits as $deposit): ?>
        <div class="deposit-card">
            <div class="deposit-header">
                <div class="deposit-id">#<?php echo $deposit['id']; ?></div>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?>
                    </div>
                    <div class="user-details">
                        📧 <?php echo htmlspecialchars($deposit['email']); ?><br>
                        📱 <?php echo htmlspecialchars($deposit['phone']); ?>
                    </div>
                </div>
                <div>
                    <?php if ($deposit['status'] === 'pending'): ?>
                        <span class="badge badge-warning" style="font-size: 0.9rem; padding: 8px 16px;">⏳ Pending</span>
                    <?php elseif ($deposit['status'] === 'payment_submitted'): ?>
                        <span class="badge badge-info" style="font-size: 0.9rem; padding: 8px 16px;">💳 Payment Submitted</span>
                    <?php elseif ($deposit['status'] === 'approved'): ?>
                        <span class="badge badge-success" style="font-size: 0.9rem; padding: 8px 16px;">✓ Approved</span>
                    <?php elseif ($deposit['status'] === 'rejected'): ?>
                        <span class="badge badge-danger" style="font-size: 0.9rem; padding: 8px 16px;">✗ Rejected</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="amount-section">
                <div class="amount-box">
                    <div class="amount-label">USD Amount</div>
                    <div class="amount-value">$<?php echo number_format($deposit['usd_amount'], 2); ?></div>
                </div>
                <div class="amount-box">
                    <div class="amount-label">ETB Amount</div>
                    <div class="amount-value">ETB <?php echo number_format($deposit['total_etb_to_pay'], 2); ?></div>
                </div>
                <div class="amount-box">
                    <div class="amount-label">Exchange Rate</div>
                    <div class="amount-value"><?php echo number_format($deposit['exchange_rate'], 2); ?></div>
                </div>
                <div class="amount-box">
                    <div class="amount-label">Wallet Balance</div>
                    <div class="amount-value">$<?php echo number_format($deposit['current_balance'], 2); ?></div>
                </div>
            </div>

            <div class="deposit-footer">
                <div style="color: var(--text-muted); font-size: 0.9rem;">
                    🕐 Created: <?php echo date('M d, Y', strtotime($deposit['created_at'])); ?> at <?php echo date('H:i', strtotime($deposit['created_at'])); ?>
                </div>
                <?php if (in_array($deposit['status'], ['pending', 'payment_submitted'])): ?>
                    <button onclick="showDepositDetails(<?php echo $deposit['id']; ?>)" class="btn btn-primary">
                        👁️ Review Deposit
                    </button>
                <?php else: ?>
                    <button onclick="showDepositDetails(<?php echo $deposit['id']; ?>)" class="btn btn-outline">
                        📋 View Details
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="depositModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Deposit Details</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); transition: color 0.2s;" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">✕</button>
        </div>
        <div id="depositDetailsContent"></div>
    </div>
</div>

<script>
function showDepositDetails(depositId) {
    const deposits = <?php echo json_encode($deposits); ?>;
    const deposit = deposits.find(d => d.id === depositId);
    
    if (!deposit) return;
    
    const canApprove = ['pending', 'payment_submitted'].includes(deposit.status);
    
    const content = `
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">User Name</div>
                <div class="info-value">${deposit.first_name} ${deposit.last_name}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value">${deposit.email}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Phone</div>
                <div class="info-value">${deposit.phone}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Telegram ID</div>
                <div class="info-value">${deposit.telegram_id}</div>
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%); padding: 25px; border-radius: var(--radius-lg); margin-bottom: 25px; border: 1px solid rgba(102, 126, 234, 0.3);">
            <div class="info-grid">
                <div>
                    <div class="info-label" style="color: var(--text-primary);">USD Amount</div>
                    <div style="font-size: 2rem; font-weight: 900; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        $${parseFloat(deposit.usd_amount).toFixed(2)}
                    </div>
                </div>
                <div>
                    <div class="info-label" style="color: var(--text-primary);">ETB to Pay</div>
                    <div style="font-size: 2rem; font-weight: 900; color: var(--text-primary);">
                        ETB ${parseFloat(deposit.total_etb_to_pay).toFixed(2)}
                    </div>
                </div>
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(102, 126, 234, 0.3);">
                <strong style="color: var(--text-primary);">Exchange Rate:</strong> 
                <span style="color: var(--text-secondary);">1 USD = ${parseFloat(deposit.exchange_rate).toFixed(2)} ETB</span>
            </div>
        </div>
        
        ${deposit.payment_proof_url ? `
            <div class="info-item" style="margin-bottom: 20px;">
                <div class="info-label">Payment Proof</div>
                <a href="${deposit.payment_proof_url}" target="_blank" class="btn btn-outline" style="margin-top: 10px;">
                    📎 View Payment Proof
                </a>
            </div>
        ` : ''}
        
        ${deposit.payment_reference ? `
            <div class="info-item" style="margin-bottom: 20px;">
                <div class="info-label">Payment Reference</div>
                <div class="info-value">${deposit.payment_reference}</div>
            </div>
        ` : ''}
        
        ${deposit.rejection_reason ? `
            <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%); padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.3);">
                <div class="info-label" style="color: var(--accent-red);">Rejection Reason</div>
                <div style="color: var(--text-primary); margin-top: 10px;">${deposit.rejection_reason}</div>
            </div>
        ` : ''}
        
        <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid var(--card-border); display: flex; gap: 12px; justify-content: space-between; flex-wrap: wrap;">
            <button onclick="closeModal()" class="btn btn-outline">Close</button>
            ${canApprove ? `
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <button onclick="showRejectForm(${depositId})" class="btn btn-danger">✗ Reject</button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this deposit?')">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="deposit_id" value="${depositId}">
                        <button type="submit" class="btn btn-success">✓ Approve Deposit</button>
                    </form>
                </div>
            ` : ''}
        </div>
        
        <div id="rejectForm${depositId}" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--card-border);">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="deposit_id" value="${depositId}">
                <div class="form-group">
                    <label class="form-label">Rejection Reason</label>
                    <textarea name="rejection_reason" required class="form-control" placeholder="Enter the reason for rejecting this deposit..."></textarea>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="hideRejectForm(${depositId})" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-danger">✗ Confirm Rejection</button>
                </div>
            </form>
        </div>
    `;
    
    document.getElementById('depositDetailsContent').innerHTML = content;
    document.getElementById('depositModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('depositModal').style.display = 'none';
}

function showRejectForm(depositId) {
    document.getElementById('rejectForm' + depositId).style.display = 'block';
}

function hideRejectForm(depositId) {
    document.getElementById('rejectForm' + depositId).style.display = 'none';
}

document.getElementById('depositModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
