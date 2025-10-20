<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// Fetch statistics
$stats = [
    'total_users' => dbFetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0,
    'pending_kyc' => dbFetchOne("SELECT COUNT(*) as count FROM users WHERE kyc_status = 'pending'")['count'] ?? 0,
    'pending_deposits' => dbFetchOne("SELECT COUNT(*) as count FROM deposits WHERE status IN ('pending', 'payment_submitted')")['count'] ?? 0,
    'total_cards' => dbFetchOne("SELECT COUNT(*) as count FROM cards")['count'] ?? 0,
    'active_cards' => dbFetchOne("SELECT COUNT(*) as count FROM cards WHERE status = 'active'")['count'] ?? 0,
    'total_wallet_balance' => dbFetchOne("SELECT COALESCE(SUM(balance_usd), 0) as total FROM wallets")['total'] ?? 0,
];

// Fetch pending deposits
$pendingDeposits = dbFetchAll("
    SELECT d.*, u.first_name, u.last_name, u.email, u.telegram_id
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    WHERE d.status IN ('pending', 'payment_submitted')
    ORDER BY d.created_at DESC
    LIMIT 10
");

// Fetch pending KYC
$pendingKYC = dbFetchAll("
    SELECT id, telegram_id, first_name, last_name, email, phone, kyc_submitted_at
    FROM users
    WHERE kyc_status = 'pending'
    ORDER BY kyc_submitted_at DESC
    LIMIT 10
");

// Fetch recent activities
$recentActivities = dbFetchAll("
    SELECT aa.*, au.full_name as admin_name
    FROM admin_actions aa
    JOIN admin_users au ON aa.admin_id = au.id
    ORDER BY aa.created_at DESC
    LIMIT 10
");
?>

<style>
    .dashboard-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    .dashboard-header h2 {
        margin: 0;
        font-size: 32px;
        font-weight: 700;
    }
    .dashboard-header .subtitle {
        margin-top: 8px;
        opacity: 0.95;
        font-size: 16px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border-left: 4px solid #667eea;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 50%;
        transform: translate(30%, -30%);
    }
    .stat-card .icon {
        font-size: 32px;
        margin-bottom: 12px;
        display: inline-block;
    }
    .stat-card .label {
        font-size: 13px;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .stat-card .value {
        font-size: 36px;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 8px;
    }
    .stat-card .change {
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .stat-card .change.warning {
        background: #fff5f5;
        color: #c53030;
    }
    .stat-card .change.success {
        background: #f0fff4;
        color: #38a169;
    }
    .stat-card.highlight {
        border-left-color: #f6ad55;
        background: linear-gradient(135deg, #fff 0%, #fffaf0 100%);
    }
    .stat-card.success {
        border-left-color: #48bb78;
    }
    .stat-card.info {
        border-left-color: #4299e1;
    }
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    .quick-action-btn {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        text-decoration: none;
        color: #2d3748;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }
    .quick-action-btn .icon {
        font-size: 28px;
        margin-bottom: 8px;
    }
    .quick-action-btn .text {
        font-weight: 600;
        font-size: 14px;
    }
    .section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    .section-header h3 {
        font-size: 22px;
        color: #1a202c;
        margin: 0;
    }
    .section-header .badge {
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .empty-state {
        text-align: center;
        padding: 60px 40px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    .empty-state .icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.8;
    }
    .empty-state h3 {
        font-size: 24px;
        color: #1a202c;
        margin-bottom: 10px;
    }
    .empty-state p {
        color: #718096;
        font-size: 16px;
    }
</style>

<div class="dashboard-header">
    <h2>👋 Welcome back, <?php echo htmlspecialchars($currentAdmin['full_name'] ?: $currentAdmin['username']); ?>!</h2>
    <p class="subtitle">Here's what's happening with your crypto card bot today</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="icon">👥</div>
        <div class="label">Total Users</div>
        <div class="value"><?php echo number_format($stats['total_users']); ?></div>
    </div>
    
    <div class="stat-card <?php echo $stats['pending_kyc'] > 0 ? 'highlight' : ''; ?>">
        <div class="icon">✅</div>
        <div class="label">Pending KYC</div>
        <div class="value"><?php echo number_format($stats['pending_kyc']); ?></div>
        <?php if ($stats['pending_kyc'] > 0): ?>
            <div class="change warning">⚠ Requires attention</div>
        <?php endif; ?>
    </div>
    
    <div class="stat-card <?php echo $stats['pending_deposits'] > 0 ? 'highlight' : ''; ?>">
        <div class="icon">💰</div>
        <div class="label">Pending Deposits</div>
        <div class="value"><?php echo number_format($stats['pending_deposits']); ?></div>
        <?php if ($stats['pending_deposits'] > 0): ?>
            <div class="change warning">⚠ Needs approval</div>
        <?php endif; ?>
    </div>
    
    <div class="stat-card success">
        <div class="icon">💵</div>
        <div class="label">Total Wallet Balance</div>
        <div class="value">$<?php echo number_format($stats['total_wallet_balance'], 2); ?></div>
    </div>
    
    <div class="stat-card info">
        <div class="icon">💳</div>
        <div class="label">Total Cards</div>
        <div class="value"><?php echo number_format($stats['total_cards']); ?></div>
    </div>
    
    <div class="stat-card success">
        <div class="icon">✨</div>
        <div class="label">Active Cards</div>
        <div class="value"><?php echo number_format($stats['active_cards']); ?></div>
    </div>
</div>

<div class="quick-actions">
    <a href="/admin/deposits.php" class="quick-action-btn">
        <div class="icon">💸</div>
        <div class="text">Approve Deposits</div>
    </a>
    <a href="/admin/kyc.php" class="quick-action-btn">
        <div class="icon">🆔</div>
        <div class="text">Verify KYC</div>
    </a>
    <a href="/admin/settings.php" class="quick-action-btn">
        <div class="icon">⚙️</div>
        <div class="text">Settings</div>
    </a>
</div>

<?php if (count($pendingDeposits) > 0): ?>
<div class="card">
    <div class="section-header">
        <h3>💸 Pending Deposits</h3>
        <span class="badge"><?php echo count($pendingDeposits); ?></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>USD Amount</th>
                <th>ETB Amount</th>
                <th>Rate</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingDeposits as $deposit): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?></strong><br>
                    <small><?php echo htmlspecialchars($deposit['email']); ?></small>
                </td>
                <td><strong style="color: #38a169;">$<?php echo number_format($deposit['usd_amount'], 2); ?></strong></td>
                <td>ETB <?php echo number_format($deposit['total_etb_to_pay'], 2); ?></td>
                <td><?php echo number_format($deposit['exchange_rate'], 2); ?></td>
                <td>
                    <?php if ($deposit['status'] === 'pending'): ?>
                        <span class="badge warning">⏳ Pending</span>
                    <?php elseif ($deposit['status'] === 'payment_submitted'): ?>
                        <span class="badge info">📋 Payment Submitted</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></td>
                <td>
                    <a href="/admin/deposits.php?id=<?php echo $deposit['id']; ?>" class="btn btn-primary btn-sm">Review →</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 20px; text-align: center;">
        <a href="/admin/deposits.php" class="btn btn-primary">View All Deposits →</a>
    </p>
</div>
<?php endif; ?>

<?php if (count($pendingKYC) > 0): ?>
<div class="card" style="margin-top: 20px;">
    <div class="section-header">
        <h3>🆔 Pending KYC Verification</h3>
        <span class="badge"><?php echo count($pendingKYC); ?></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Telegram ID</th>
                <th>Submitted</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingKYC as $user): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                <td><code><?php echo htmlspecialchars($user['telegram_id']); ?></code></td>
                <td><?php echo $user['kyc_submitted_at'] ? date('M d, Y H:i', strtotime($user['kyc_submitted_at'])) : 'N/A'; ?></td>
                <td>
                    <a href="/admin/kyc.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Review →</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 20px; text-align: center;">
        <a href="/admin/kyc.php" class="btn btn-primary">View All KYC Requests →</a>
    </p>
</div>
<?php endif; ?>

<?php if (count($pendingDeposits) === 0 && count($pendingKYC) === 0): ?>
<div class="empty-state">
    <div class="icon">✅</div>
    <h3>All Caught Up!</h3>
    <p>No pending deposits or KYC verifications at the moment.<br>Great job keeping things running smoothly!</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
