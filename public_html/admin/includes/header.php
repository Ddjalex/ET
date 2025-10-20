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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 20px;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-info {
            text-align: right;
        }
        .user-info .name {
            font-weight: 600;
        }
        .user-info .role {
            font-size: 12px;
            opacity: 0.9;
        }
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .layout {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 20px 0;
        }
        .sidebar nav a {
            display: block;
            padding: 12px 30px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar nav a:hover {
            background: #f5f7fa;
            color: #667eea;
        }
        .sidebar nav a.active {
            background: #f5f7fa;
            color: #667eea;
            border-left-color: #667eea;
            font-weight: 600;
        }
        .content {
            flex: 1;
            padding: 30px;
            max-width: 1400px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        .page-header .subtitle {
            color: #666;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card .label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }
        .stat-card .change {
            font-size: 12px;
            margin-top: 5px;
        }
        .stat-card .change.positive {
            color: #10b981;
        }
        .stat-card .change.negative {
            color: #ef4444;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        .card h3 {
            margin-bottom: 20px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f5f7fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        tr:hover td {
            background: #fafafa;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge.info {
            background: #dbeafe;
            color: #1e40af;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .alert.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>💳 Crypto Card Bot - Admin Panel</h1>
            <div class="user-menu">
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($currentAdmin['full_name'] ?: $currentAdmin['username']); ?></div>
                    <div class="role"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $currentAdmin['role']))); ?></div>
                </div>
                <a href="/admin/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    <div class="layout">
        <div class="sidebar">
            <nav>
                <a href="/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">📊 Dashboard</a>
                <a href="/admin/deposits.php" class="<?php echo $currentPage === 'deposits' ? 'active' : ''; ?>">💰 Deposits</a>
                <a href="/admin/kyc.php" class="<?php echo $currentPage === 'kyc' ? 'active' : ''; ?>">👥 Users</a>
                <a href="/admin/cards.php" class="<?php echo $currentPage === 'cards' ? 'active' : ''; ?>">💳 Cards</a>
                <a href="/admin/transactions.php" class="<?php echo $currentPage === 'transactions' ? 'active' : ''; ?>">📈 Transactions</a>
                <a href="/admin/settings.php" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a>
            </nav>
        </div>
        <div class="content">
