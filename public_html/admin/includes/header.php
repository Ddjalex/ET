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
    <!-- Top Header Bar -->
    <header style="position: fixed; top: 0; left: 0; right: 0; height: 70px; background: white; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; z-index: 1000;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #C9B382 0%, #A89968 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                💳
            </div>
            <span style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">Admin Panel</span>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <button onclick="toggleUserMenu()" style="display: flex; align-items: center; gap: 0.75rem; background: #f3f4f6; padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; position: relative;">
                <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #C9B382 0%, #A89968 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px;">
                    <?php echo strtoupper(substr($currentAdmin['username'], 0, 1)); ?>
                </div>
                <div style="text-align: left;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: #1f2937;">
                        <?php echo htmlspecialchars($currentAdmin['full_name'] ?: $currentAdmin['username']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #6b7280;">
                        <?php echo htmlspecialchars(str_replace('_', ' ', $currentAdmin['role'])); ?>
                    </div>
                </div>
                <span style="color: #9ca3af;">▼</span>
                
                <!-- User Dropdown Menu -->
                <div id="userMenu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; overflow: hidden;">
                    <a href="/admin/change-password.php" style="display: block; padding: 0.75rem 1rem; color: #374151; text-decoration: none; transition: background 0.2s; border-bottom: 1px solid #f3f4f6;">
                        🔐 Change Password
                    </a>
                    <a href="/admin/logout.php" style="display: block; padding: 0.75rem 1rem; color: #dc2626; text-decoration: none; transition: background 0.2s;">
                        🚪 Log Out
                    </a>
                </div>
            </button>
        </div>
    </header>
    
    <script>
    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        const userMenu = document.getElementById('userMenu');
        const button = event.target.closest('button[onclick="toggleUserMenu()"]');
        if (!button && userMenu && userMenu.style.display === 'block') {
            userMenu.style.display = 'none';
        }
    });
    </script>
    
    <!-- Sidebar Navigation -->
    <aside style="position: fixed; left: 0; top: 70px; width: 240px; height: calc(100vh - 70px); background: #0a0a0a; border-right: 1px solid #1a1a1a; padding: 1.5rem 0; overflow-y: auto; z-index: 100;">
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 0.75rem; padding: 0 1rem;">
                <a href="/admin/dashboard.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: #ffffff; text-decoration: none; background: <?php echo $currentPage === 'dashboard' ? 'linear-gradient(135deg, rgba(201, 179, 130, 0.25), rgba(168, 153, 104, 0.25))' : 'rgba(201, 179, 130, 0.1)'; ?>; border: 1px solid <?php echo $currentPage === 'dashboard' ? 'rgba(201, 179, 130, 0.5)' : 'rgba(201, 179, 130, 0.2)'; ?>; border-radius: 12px; font-weight: 600; transition: all 0.3s; box-shadow: <?php echo $currentPage === 'dashboard' ? '0 4px 12px rgba(201, 179, 130, 0.3)' : 'none'; ?>;">
                    <span style="font-size: 1.25rem;">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li style="margin-bottom: 0.75rem; padding: 0 1rem;">
                <a href="/admin/payments.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: #ffffff; text-decoration: none; background: <?php echo $currentPage === 'payments' ? 'linear-gradient(135deg, rgba(201, 179, 130, 0.25), rgba(168, 153, 104, 0.25))' : 'rgba(201, 179, 130, 0.1)'; ?>; border: 1px solid <?php echo $currentPage === 'payments' ? 'rgba(201, 179, 130, 0.5)' : 'rgba(201, 179, 130, 0.2)'; ?>; border-radius: 12px; font-weight: 600; transition: all 0.3s; box-shadow: <?php echo $currentPage === 'payments' ? '0 4px 12px rgba(201, 179, 130, 0.3)' : 'none'; ?>;">
                    <span style="font-size: 1.25rem;">💳</span>
                    <span>Payment Verification</span>
                </a>
            </li>
            <li style="margin-bottom: 0.75rem; padding: 0 1rem;">
                <a href="/admin/kyc.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: #ffffff; text-decoration: none; background: <?php echo $currentPage === 'kyc' ? 'linear-gradient(135deg, rgba(201, 179, 130, 0.25), rgba(168, 153, 104, 0.25))' : 'rgba(201, 179, 130, 0.1)'; ?>; border: 1px solid <?php echo $currentPage === 'kyc' ? 'rgba(201, 179, 130, 0.5)' : 'rgba(201, 179, 130, 0.2)'; ?>; border-radius: 12px; font-weight: 600; transition: all 0.3s; box-shadow: <?php echo $currentPage === 'kyc' ? '0 4px 12px rgba(201, 179, 130, 0.3)' : 'none'; ?>;">
                    <span style="font-size: 1.25rem;">✓</span>
                    <span>KYC Verification</span>
                </a>
            </li>
            <li style="margin-bottom: 0.75rem; padding: 0 1rem;">
                <a href="/admin/broadcaster.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: #ffffff; text-decoration: none; background: <?php echo $currentPage === 'broadcaster' ? 'linear-gradient(135deg, rgba(201, 179, 130, 0.25), rgba(168, 153, 104, 0.25))' : 'rgba(201, 179, 130, 0.1)'; ?>; border: 1px solid <?php echo $currentPage === 'broadcaster' ? 'rgba(201, 179, 130, 0.5)' : 'rgba(201, 179, 130, 0.2)'; ?>; border-radius: 12px; font-weight: 600; transition: all 0.3s; box-shadow: <?php echo $currentPage === 'broadcaster' ? '0 4px 12px rgba(201, 179, 130, 0.3)' : 'none'; ?>;">
                    <span style="font-size: 1.25rem;">📢</span>
                    <span>Broadcaster</span>
                </a>
            </li>
            <li style="margin-bottom: 0.75rem; padding: 0 1rem;">
                <a href="/admin/settings.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: #ffffff; text-decoration: none; background: <?php echo $currentPage === 'settings' ? 'linear-gradient(135deg, rgba(201, 179, 130, 0.25), rgba(168, 153, 104, 0.25))' : 'rgba(201, 179, 130, 0.1)'; ?>; border: 1px solid <?php echo $currentPage === 'settings' ? 'rgba(201, 179, 130, 0.5)' : 'rgba(201, 179, 130, 0.2)'; ?>; border-radius: 12px; font-weight: 600; transition: all 0.3s; box-shadow: <?php echo $currentPage === 'settings' ? '0 4px 12px rgba(201, 179, 130, 0.3)' : 'none'; ?>;">
                    <span style="font-size: 1.25rem;">⚙️</span>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content Area -->
    <main style="margin-left: 240px; margin-top: 70px; padding: 2rem; min-height: calc(100vh - 70px); background: #0a0a0a; position: relative; color: #ffffff;">
