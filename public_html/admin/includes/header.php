<?php
require_once __DIR__ . '/../config/session.php';
requireAdminLogin();
$currentAdmin = getCurrentAdmin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Admin Panel'; ?> - Crypto Card Bot</title>
    <link rel="stylesheet" href="/admin/assets/admin-styles.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">💳</div>
            <div class="sidebar-title">Crypto Card Admin</div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="/admin/dashboard.php" class="sidebar-nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span>📊</span> Dashboard
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="/admin/deposits.php" class="sidebar-nav-link <?php echo $currentPage === 'deposits' ? 'active' : ''; ?>">
                    <span>💰</span> Deposits
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="/admin/kyc.php" class="sidebar-nav-link <?php echo $currentPage === 'kyc' ? 'active' : ''; ?>">
                    <span>✓</span> KYC Verification
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="/admin/settings.php" class="sidebar-nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <span>⚙️</span> Settings
                </a>
            </li>
            <li class="sidebar-nav-item" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--card-border);">
                <a href="/admin/change-password.php" class="sidebar-nav-link <?php echo $currentPage === 'change-password' ? 'active' : ''; ?>">
                    <span>🔐</span> Change Password
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="/admin/logout.php" class="sidebar-nav-link" style="color: #ef4444;">
                    <span>🚪</span> Logout
                </a>
            </li>
        </ul>
        
        <div style="margin-top: auto; padding-top: 2rem; border-top: 1px solid var(--card-border);">
            <div style="padding: 0.75rem 1rem; background: var(--glass-bg); border-radius: var(--radius-sm);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="user-avatar"><?php echo strtoupper(substr($currentAdmin['username'], 0, 1)); ?></div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-primary); truncate;">
                            <?php echo htmlspecialchars($currentAdmin['full_name'] ?: $currentAdmin['username']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $currentAdmin['role'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
