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
        </ul>
        
        <!-- More Options Menu -->
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--card-border); position: relative;">
            <button onclick="toggleMoreMenu()" class="sidebar-nav-link" style="width: 100%; display: flex; align-items: center; justify-content: space-between; background: transparent; border: none; cursor: pointer; padding: 0.75rem 1rem; border-radius: var(--radius-sm); transition: all 0.3s ease;">
                <span style="display: flex; align-items: center; gap: var(--spacing-sm);">
                    <span>⋮</span> More Options
                </span>
                <span id="moreMenuArrow" style="transition: transform 0.3s ease;">▼</span>
            </button>
            
            <div id="moreMenu" style="display: none; margin-top: 0.5rem; background: var(--bg-secondary); border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--card-border);">
                <a href="/admin/change-password.php" class="sidebar-nav-link <?php echo $currentPage === 'change-password' ? 'active' : ''; ?>" style="border-radius: 0;">
                    <span>🔐</span> Change Password
                </a>
                <a href="/admin/logout.php" class="sidebar-nav-link" style="color: #ef4444; border-radius: 0;">
                    <span>🚪</span> Logout
                </a>
            </div>
        </div>
        
        <script>
        function toggleMoreMenu() {
            const menu = document.getElementById('moreMenu');
            const arrow = document.getElementById('moreMenuArrow');
            if (menu.style.display === 'none') {
                menu.style.display = 'block';
                arrow.style.transform = 'rotate(180deg)';
            } else {
                menu.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            }
        }
        </script>
        
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
