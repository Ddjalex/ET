<?php
// Session management for Admin Panel

// Start secure session
function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 1); // HTTPS only
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
    }
}

// Check if admin is logged in
function isAdminLoggedIn() {
    startAdminSession();
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

// Require admin login (redirect if not logged in)
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

// Get current admin user
function getCurrentAdmin() {
    startAdminSession();
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? null,
        'email' => $_SESSION['admin_email'] ?? null,
        'role' => $_SESSION['admin_role'] ?? 'admin',
        'full_name' => $_SESSION['admin_full_name'] ?? ''
    ];
}

// Login admin user
function loginAdmin($adminUser) {
    startAdminSession();
    $_SESSION['admin_id'] = $adminUser['id'];
    $_SESSION['admin_username'] = $adminUser['username'];
    $_SESSION['admin_email'] = $adminUser['email'];
    $_SESSION['admin_role'] = $adminUser['role'];
    $_SESSION['admin_full_name'] = $adminUser['full_name'];
    $_SESSION['login_time'] = time();
    
    // Update last login
    require_once __DIR__ . '/database.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    dbQuery(
        "UPDATE admin_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
        [$ip, $adminUser['id']]
    );
}

// Logout admin user
function logoutAdmin() {
    startAdminSession();
    $_SESSION = [];
    session_destroy();
}

// Generate CSRF token
function generateCSRFToken() {
    startAdminSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    startAdminSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if admin has permission
function hasPermission($permission) {
    $admin = getCurrentAdmin();
    if (!$admin) return false;
    
    // Super admin has all permissions
    if ($admin['role'] === 'super_admin') return true;
    
    // Define role permissions
    $permissions = [
        'admin' => ['view_users', 'approve_deposits', 'manage_cards', 'view_settings'],
        'viewer' => ['view_users', 'view_settings']
    ];
    
    return in_array($permission, $permissions[$admin['role']] ?? []);
}
