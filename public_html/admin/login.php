<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

startAdminSession();

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $admin = dbFetchOne(
            "SELECT * FROM admin_users WHERE username = ? AND status = 'active'",
            [$username]
        );
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            loginAdmin($admin);
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            error_log("Failed login attempt for username: {$username} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Crypto Card Bot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
            animation: backgroundPulse 15s ease-in-out infinite;
        }
        body::after {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            width: 900px;
            height: 560px;
            background-image: url('assets/virtual-card-bg.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.15;
            transform: translate(-50%, -50%) rotate(-5deg);
            z-index: 0;
            animation: floatCard 20s ease-in-out infinite;
            pointer-events: none;
            filter: drop-shadow(0 0 30px rgba(255, 255, 255, 0.2));
        }
        @keyframes backgroundPulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        @keyframes floatCard {
            0%, 100% { 
                transform: translate(-50%, -50%) rotate(-5deg) scale(1);
                opacity: 0.15;
            }
            50% { 
                transform: translate(-50%, -52%) rotate(-3deg) scale(1.05);
                opacity: 0.22;
            }
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 55px 45px;
            border-radius: 24px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 80px rgba(102, 126, 234, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 460px;
            animation: slideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            background-size: 200% 100%;
            border-radius: 24px 24px 0 0;
            animation: shimmer 3s linear infinite;
        }
        .logo {
            text-align: center;
            margin-bottom: 35px;
        }
        .logo-icon {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            margin-bottom: 18px;
            box-shadow: 
                0 8px 24px rgba(102, 126, 234, 0.4),
                0 0 40px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            animation: pulse 3s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .logo-icon:hover {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.6);
        }
        h1 {
            color: #1a202c;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 800;
            font-family: 'Poppins', sans-serif;
            text-align: center;
            letter-spacing: -0.5px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 40px;
            font-size: 15px;
            text-align: center;
            font-weight: 500;
        }
        .form-group {
            margin-bottom: 26px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            color: #2d3748;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f7fafc;
            font-family: 'Inter', sans-serif;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 
                0 0 0 4px rgba(102, 126, 234, 0.15),
                0 4px 12px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: #718096;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        .password-toggle:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .password-toggle svg {
            width: 22px;
            height: 22px;
        }
        button[type="submit"] {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 8px 20px rgba(102, 126, 234, 0.4),
                0 0 40px rgba(102, 126, 234, 0.2);
            position: relative;
            overflow: hidden;
        }
        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        button[type="submit"]:hover::before {
            left: 100%;
        }
        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 12px 28px rgba(102, 126, 234, 0.5),
                0 0 60px rgba(102, 126, 234, 0.4);
        }
        button[type="submit"]:active {
            transform: translateY(-1px);
        }
        .error {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            color: #c53030;
            padding: 16px 18px;
            border-radius: 12px;
            margin-bottom: 26px;
            border-left: 4px solid #fc8181;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(197, 48, 48, 0.15);
            animation: slideIn 0.4s ease-out;
        }
        .error::before {
            content: "⚠";
            font-size: 20px;
            min-width: 20px;
        }
        .footer {
            margin-top: 35px;
            padding-top: 28px;
            text-align: center;
            color: #718096;
            font-size: 13px;
            border-top: 2px solid #e2e8f0;
        }
        .footer p {
            margin-bottom: 10px;
        }
        .footer strong {
            color: #2d3748;
            font-weight: 700;
        }
        .footer .warning {
            color: #e53e3e;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">🔐</div>
            <h1>Admin Login</h1>
            <p class="subtitle">Crypto Card Bot Admin Panel</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username" placeholder="Enter your username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required autocomplete="current-password" style="padding-right: 52px;" placeholder="Enter your password">
                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg id="eyeOffIcon" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="footer">
            <p>Default credentials: <strong>admin</strong> / <strong>admin123</strong></p>
            <p class="warning">⚠ Change password immediately after first login</p>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }
    </script>
</body>
</html>
