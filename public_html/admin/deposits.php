<?php
$pageTitle = 'Deposits Management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$messageType = '';

// Handle deposit approval/rejection
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
                // Fetch deposit
                $deposit = dbFetchOne("SELECT d.*, w.id as wallet_id, w.balance_usd FROM deposits d 
                                      JOIN wallets w ON d.wallet_id = w.id 
                                      WHERE d.id = ? AND d.status IN ('pending', 'payment_submitted')", [$depositId], $db);
                
                if (!$deposit) {
                    throw new Exception('Deposit not found or already processed');
                }
                
                // Update deposit status
                dbQuery("UPDATE deposits SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?", 
                       [$adminId, $depositId], $db);
                
                // Create wallet transaction
                $balanceBefore = $deposit['balance_usd'];
                $balanceAfter = $balanceBefore + $deposit['usd_amount'];
                
                dbQuery("INSERT INTO wallet_transactions (wallet_id, user_id, transaction_type, amount_usd, amount_etb, 
                        balance_before_usd, balance_after_usd, reference, description, status) 
                        VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, ?, 'completed')",
                       [$deposit['wallet_id'], $deposit['user_id'], $deposit['usd_amount'], $deposit['etb_amount_quote'],
                        $balanceBefore, $balanceAfter, 'DEP-' . $depositId, 'Deposit approved by admin'], $db);
                
                $transactionId = dbLastInsertId();
                
                // Update wallet balance
                dbQuery("UPDATE wallets SET balance_usd = balance_usd + ?, updated_at = NOW() WHERE id = ?",
                       [$deposit['usd_amount'], $deposit['wallet_id']], $db);
                
                // Link transaction to deposit
                dbQuery("UPDATE deposits SET wallet_transaction_id = ? WHERE id = ?", [$transactionId, $depositId], $db);
                
                // Log admin action
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
                
                // Fetch deposit
                $deposit = dbFetchOne("SELECT * FROM deposits WHERE id = ? AND status IN ('pending', 'payment_submitted')", [$depositId], $db);
                
                if (!$deposit) {
                    throw new Exception('Deposit not found or already processed');
                }
                
                // Update deposit status
                dbQuery("UPDATE deposits SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?",
                       [$adminId, $reason, $depositId], $db);
                
                // Log admin action
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

// Fetch deposits
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

// Count by status
$statusCounts = dbFetchOne("
    SELECT 
        COUNT(*) FILTER (WHERE status IN ('pending', 'payment_submitted')) as pending,
        COUNT(*) FILTER (WHERE status = 'approved') as approved,
        COUNT(*) FILTER (WHERE status = 'rejected') as rejected,
        COUNT(*) as total
    FROM deposits
");
?>

<div class="page-header">
    <h2>💰 Deposits Management</h2>
    <p class="subtitle">Review and approve user deposit requests</p>
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

    <?php if (count($deposits) === 0): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>No deposits found in this category.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>USD Amount</th>
                    <th>ETB Amount</th>
                    <th>Exchange Rate</th>
                    <th>Wallet Balance</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deposits as $deposit): ?>
                <tr>
                    <td><strong>#<?php echo $deposit['id']; ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($deposit['email']); ?></small><br>
                        <small>Tel: <?php echo htmlspecialchars($deposit['phone']); ?></small>
                    </td>
                    <td><strong>$<?php echo number_format($deposit['usd_amount'], 2); ?></strong></td>
                    <td>ETB <?php echo number_format($deposit['total_etb_to_pay'], 2); ?></td>
                    <td><?php echo number_format($deposit['exchange_rate'], 2); ?></td>
                    <td>$<?php echo number_format($deposit['current_balance'], 2); ?></td>
                    <td>
                        <?php if ($deposit['status'] === 'pending'): ?>
                            <span class="badge warning">Pending</span>
                        <?php elseif ($deposit['status'] === 'payment_submitted'): ?>
                            <span class="badge info">Payment Submitted</span>
                        <?php elseif ($deposit['status'] === 'approved'): ?>
                            <span class="badge success">Approved</span>
                        <?php elseif ($deposit['status'] === 'rejected'): ?>
                            <span class="badge danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></td>
                    <td>
                        <?php if (in_array($deposit['status'], ['pending', 'payment_submitted'])): ?>
                            <button onclick="showDepositDetails(<?php echo $deposit['id']; ?>)" class="btn btn-primary btn-sm">Review</button>
                        <?php else: ?>
                            <button onclick="showDepositDetails(<?php echo $deposit['id']; ?>)" class="btn btn-sm">View</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal for deposit details -->
<div id="depositModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin-bottom: 20px;">Deposit Details</h3>
        <div id="depositDetailsContent"></div>
    </div>
</div>

<script>
function showDepositDetails(depositId) {
    // Find deposit in the data
    const deposits = <?php echo json_encode($deposits); ?>;
    const deposit = deposits.find(d => d.id === depositId);
    
    if (!deposit) return;
    
    const canApprove = ['pending', 'payment_submitted'].includes(deposit.status);
    
    const content = `
        <div style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <strong>User:</strong><br>
                    ${deposit.first_name} ${deposit.last_name}<br>
                    ${deposit.email}<br>
                    Phone: ${deposit.phone}
                </div>
                <div>
                    <strong>Telegram ID:</strong><br>
                    ${deposit.telegram_id}
                </div>
            </div>
            
            <div style="background: #f5f7fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <strong>USD Amount:</strong><br>
                        <span style="font-size: 24px; color: #667eea;">$${parseFloat(deposit.usd_amount).toFixed(2)}</span>
                    </div>
                    <div>
                        <strong>ETB to Pay:</strong><br>
                        <span style="font-size: 24px; color: #764ba2;">ETB ${parseFloat(deposit.total_etb_to_pay).toFixed(2)}</span>
                    </div>
                </div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                    <strong>Exchange Rate:</strong> 1 USD = ${parseFloat(deposit.exchange_rate).toFixed(2)} ETB
                </div>
            </div>
            
            ${deposit.payment_proof_url ? `
                <div style="margin-bottom: 15px;">
                    <strong>Payment Proof:</strong><br>
                    <a href="${deposit.payment_proof_url}" target="_blank" style="color: #667eea;">View Payment Proof</a>
                </div>
            ` : ''}
            
            ${deposit.payment_reference ? `
                <div style="margin-bottom: 15px;">
                    <strong>Payment Reference:</strong> ${deposit.payment_reference}
                </div>
            ` : ''}
            
            ${deposit.rejection_reason ? `
                <div style="background: #fee2e2; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <strong>Rejection Reason:</strong><br>
                    ${deposit.rejection_reason}
                </div>
            ` : ''}
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; display: flex; gap: 10px; justify-content: space-between;">
                <button onclick="closeModal()" class="btn">Close</button>
                ${canApprove ? `
                    <div style="display: flex; gap: 10px;">
                        <button onclick="showRejectForm(${depositId})" class="btn btn-danger">Reject</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this deposit?')">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="deposit_id" value="${depositId}">
                            <button type="submit" class="btn btn-success">Approve Deposit</button>
                        </form>
                    </div>
                ` : ''}
            </div>
            
            <div id="rejectForm${depositId}" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="deposit_id" value="${depositId}">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">Rejection Reason:</label>
                    <textarea name="rejection_reason" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 80px;"></textarea>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <button type="button" onclick="hideRejectForm(${depositId})" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </form>
            </div>
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

// Close modal when clicking outside
document.getElementById('depositModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
