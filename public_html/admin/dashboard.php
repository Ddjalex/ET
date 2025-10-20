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

<div class="admin-main">
    <!-- Welcome Header -->
    <div class="admin-header" style="background: linear-gradient(135deg, #C9B382 0%, #A89968 100%); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 16px rgba(201, 179, 130, 0.3);">
        <div class="header-title">
            <div class="header-icon" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📊</div>
            <div>
                <h1 style="color: white; margin: 0; font-size: 2rem; font-weight: 700;">Dashboard</h1>
                <p style="color: rgba(255,255,255,0.95); font-size: 0.95rem; margin: 0.5rem 0 0 0; font-weight: 500;">
                    Welcome back, <?php echo htmlspecialchars($currentAdmin['full_name'] ?: $currentAdmin['username']); ?>!
                </p>
            </div>
        </div>
        <div id="live-clock" style="color: rgba(255,255,255,0.95); font-size: 0.9rem; font-weight: 500;">
            <?php echo date('l, F j, Y'); ?> • <?php echo date('g:i A'); ?>
        </div>
    </div>

    <script>
    function updateClock() {
        const now = new Date();
        
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        
        const dayName = days[now.getDay()];
        const monthName = months[now.getMonth()];
        const date = now.getDate();
        const year = now.getFullYear();
        
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        
        const timeString = `${dayName}, ${monthName} ${date}, ${year} • ${hours}:${minutes} ${ampm}`;
        
        document.getElementById('live-clock').textContent = timeString;
    }
    
    updateClock();
    setInterval(updateClock, 1000);
    </script>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">👥</div>
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">⏳</div>
            <div class="stat-label">Pending KYC</div>
            <div class="stat-value"><?php echo number_format($stats['pending_kyc']); ?></div>
            <?php if ($stats['pending_kyc'] > 0): ?>
                <a href="/admin/kyc.php" style="color: var(--accent-gold); font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                    Review Now →
                </a>
            <?php endif; ?>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">💰</div>
            <div class="stat-label">Total Wallet Balance</div>
            <div class="stat-value">$<?php echo number_format($stats['total_wallet_balance'], 2); ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">💸</div>
            <div class="stat-label">Pending Deposits</div>
            <div class="stat-value"><?php echo number_format($stats['pending_deposits']); ?></div>
            <?php if ($stats['pending_deposits'] > 0): ?>
                <a href="/admin/deposits.php" style="color: var(--accent-red); font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                    Process Now →
                </a>
            <?php endif; ?>
        </div>

        <div class="stat-card">
            <div class="stat-icon primary">💳</div>
            <div class="stat-label">Total Cards</div>
            <div class="stat-value"><?php echo number_format($stats['total_cards']); ?></div>
            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">
                <?php echo number_format($stats['active_cards']); ?> active
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">✓</div>
            <div class="stat-label">Card Activation Rate</div>
            <div class="stat-value">
                <?php echo $stats['total_cards'] > 0 ? number_format(($stats['active_cards'] / $stats['total_cards']) * 100, 1) : 0; ?>%
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-bottom: var(--spacing-lg);">
        <div class="card-header">
            <h3 class="card-title">⚡ Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="/admin/deposits.php" class="btn btn-primary" style="text-decoration: none;">
                    💰 Review Deposits
                </a>
                <a href="/admin/kyc.php" class="btn btn-warning" style="text-decoration: none;">
                    ✓ Verify KYC
                </a>
                <a href="/admin/settings.php" class="btn btn-outline" style="text-decoration: none;">
                    ⚙️ Settings
                </a>
                <a href="/admin/change-password.php" class="btn btn-outline" style="text-decoration: none;">
                    🔐 Change Password
                </a>
            </div>
        </div>
    </div>

    <!-- Pending Items Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: var(--spacing-lg); margin-bottom: var(--spacing-lg);">
        
        <!-- Pending Deposits -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💰 Pending Deposits</h3>
                <span class="badge badge-warning"><?php echo count($pendingDeposits); ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($pendingDeposits)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                        No pending deposits
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingDeposits as $deposit): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($deposit['email']); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600;">$<?php echo number_format($deposit['usd_amount'], 2); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo number_format($deposit['total_etb_to_pay'], 2); ?> ETB</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning"><?php echo htmlspecialchars(ucfirst($deposit['status'])); ?></span>
                                        </td>
                                        <td>
                                            <a href="/admin/deposits.php" class="btn btn-primary btn-sm" style="text-decoration: none; font-size: 0.8rem;">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending KYC -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">👤 Pending KYC Verification</h3>
                <span class="badge badge-info"><?php echo count($pendingKYC); ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($pendingKYC)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                        No pending KYC verifications
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingKYC as $user): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($user['kyc_submitted_at']): ?>
                                                <div style="font-size: 0.85rem;">
                                                    <?php echo date('M j, Y', strtotime($user['kyc_submitted_at'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/admin/kyc.php" class="btn btn-primary btn-sm" style="text-decoration: none; font-size: 0.8rem;">Verify</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Admin Activities -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📝 Recent Admin Activities</h3>
        </div>
        <div class="card-body">
            <?php if (empty($recentActivities)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                    No recent activities
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($activity['admin_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo htmlspecialchars(str_replace('_', ' ', $activity['action_type'])); ?></span>
                                    </td>
                                    <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($activity['action_description'] ?? ''); ?></td>
                                    <td style="color: var(--text-muted); font-size: 0.85rem;">
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
