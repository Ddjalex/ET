<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

startAdminSession();
requireAdminLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword === $currentPassword) {
        $error = 'New password must be different from current password.';
    } else {
        // Verify current password
        $admin = dbFetchOne(
            "SELECT * FROM admin_users WHERE id = ?",
            [$_SESSION['admin_id']]
        );
        
        if ($admin && password_verify($currentPassword, $admin['password_hash'])) {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            dbQuery(
                "UPDATE admin_users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$newHash, $_SESSION['admin_id']]
            );
            
            // Log the action
            dbQuery(
                "INSERT INTO admin_actions (admin_id, action_type, action_description, ip_address, user_agent) 
                 VALUES (?, 'password_change', 'Admin changed their password', ?, ?)",
                [
                    $_SESSION['admin_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]
            );
            
            $success = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

$admin = dbFetchOne("SELECT username, email FROM admin_users WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Admin Panel</title>
    <link rel="stylesheet" href="/admin/assets/admin-styles.css">
    <style>
        .password-requirements {
            background: var(--glass-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            margin-top: 0.5rem;
        }
        .password-requirements ul {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
        }
        .password-requirements li {
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        .password-strength {
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 4px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="admin-main">
        <div class="admin-header">
            <div class="header-title">
                <div class="header-icon">🔐</div>
                <div>
                    <h1>Change Password</h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Update your security credentials</p>
                </div>
            </div>
        </div>

        <div style="max-width: 600px; margin: 0 auto;">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>✓</span>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>⚠</span>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        👤 Account Information
                    </h3>
                </div>
                <div class="card-body">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($admin['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($admin['email']); ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        🔑 Update Password
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <div class="form-group">
                            <label class="form-label" for="current_password">Current Password</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-control" 
                                   required
                                   autocomplete="current-password">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">New Password</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-control" 
                                   required
                                   autocomplete="new-password"
                                   onkeyup="checkPasswordStrength()">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="password-requirements">
                                <strong style="color: var(--text-primary);">Password Requirements:</strong>
                                <ul>
                                    <li>Minimum 8 characters</li>
                                    <li>Include uppercase and lowercase letters</li>
                                    <li>Include at least one number</li>
                                    <li>Include at least one special character</li>
                                </ul>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   required
                                   autocomplete="new-password">
                        </div>

                        <div class="flex gap-2" style="margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">
                                🔒 Change Password
                            </button>
                            <a href="/admin/dashboard.php" class="btn btn-outline">
                                ← Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
