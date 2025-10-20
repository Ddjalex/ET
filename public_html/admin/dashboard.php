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

<div class="page-header">
    <h2>Dashboard Overview</h2>
    <p class="subtitle">Welcome back, <?php echo htmlspecialchars($currentAdmin['full_name'] ?: $currentAdmin['username']); ?>!</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Users</div>
        <div class="value"><?php echo number_format($stats['total_users']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Pending KYC</div>
        <div class="value"><?php echo number_format($stats['pending_kyc']); ?></div>
        <?php if ($stats['pending_kyc'] > 0): ?>
            <div class="change warning">⚠️ Requires attention</div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="label">Pending Deposits</div>
        <div class="value"><?php echo number_format($stats['pending_deposits']); ?></div>
        <?php if ($stats['pending_deposits'] > 0): ?>
            <div class="change warning">⚠️ Requires approval</div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="label">Total Wallet Balance</div>
        <div class="value">$<?php echo number_format($stats['total_wallet_balance'], 2); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Cards</div>
        <div class="value"><?php echo number_format($stats['total_cards']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Active Cards</div>
        <div class="value"><?php echo number_format($stats['active_cards']); ?></div>
    </div>
</div>

<?php if (count($pendingDeposits) > 0): ?>
<div class="card">
    <h3>⏳ Pending Deposits (<?php echo count($pendingDeposits); ?>)</h3>
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
                <td><strong>$<?php echo number_format($deposit['usd_amount'], 2); ?></strong></td>
                <td>ETB <?php echo number_format($deposit['total_etb_to_pay'], 2); ?></td>
                <td><?php echo number_format($deposit['exchange_rate'], 2); ?></td>
                <td>
                    <?php if ($deposit['status'] === 'pending'): ?>
                        <span class="badge warning">Pending</span>
                    <?php elseif ($deposit['status'] === 'payment_submitted'): ?>
                        <span class="badge info">Payment Submitted</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></td>
                <td>
                    <a href="/admin/deposits.php?id=<?php echo $deposit['id']; ?>" class="btn btn-primary btn-sm">Review</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 15px; text-align: center;">
        <a href="/admin/deposits.php" class="btn btn-primary">View All Deposits →</a>
    </p>
</div>
<?php endif; ?>

<?php if (count($pendingKYC) > 0): ?>
<div class="card">
    <h3>✅ Pending KYC Verification (<?php echo count($pendingKYC); ?>)</h3>
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
                <td><?php echo htmlspecialchars($user['telegram_id']); ?></td>
                <td><?php echo $user['kyc_submitted_at'] ? date('M d, Y H:i', strtotime($user['kyc_submitted_at'])) : 'N/A'; ?></td>
                <td>
                    <a href="/admin/kyc.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Review</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 15px; text-align: center;">
        <a href="/admin/kyc.php" class="btn btn-primary">View All KYC Requests →</a>
    </p>
</div>
<?php endif; ?>

<?php if (count($pendingDeposits) === 0 && count($pendingKYC) === 0): ?>
<div class="card">
    <div style="text-align: center; padding: 40px;">
        <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
        <h3>All Caught Up!</h3>
        <p style="color: #666; margin-top: 10px;">No pending deposits or KYC verifications at the moment.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
