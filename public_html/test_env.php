<?php
// Test environment variables loading from .env file
require_once __DIR__ . '/../secrets/load_env.php';

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Environment Variables Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .masked { color: #666; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Environment Variables Test</h1>
        <p><strong>.env file loaded from:</strong> <code>secrets/.env</code></p>
        
        <table>
            <tr>
                <th>Variable Name</th>
                <th>Status</th>
                <th>Value (Masked)</th>
            </tr>
            <?php
            $vars = [
                'BOT_TOKEN' => true,
                'STROW_ADMIN_KEY' => true,
                'STROW_PERSONAL_KEY' => true,
                'DATABASE_URL' => true,
                'ADMIN_CHAT_ID' => false,
                'SUPPORT_URL' => false,
                'REFERRAL_TEXT' => false
            ];
            
            foreach ($vars as $var => $mask) {
                $value = getenv($var);
                $exists = !empty($value);
                
                echo "<tr>";
                echo "<td><strong>{$var}</strong></td>";
                
                if ($exists) {
                    echo "<td class='success'>✓ SET</td>";
                    if ($mask) {
                        $masked = substr($value, 0, 15) . '...' . substr($value, -8);
                        echo "<td class='masked'>{$masked}</td>";
                    } else {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                } else {
                    echo "<td class='error'>✗ NOT SET</td>";
                    echo "<td>-</td>";
                }
                
                echo "</tr>";
            }
            ?>
        </table>
        
        <p style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196F3;">
            <strong>ℹ️ Note:</strong> All sensitive values are masked for security. 
            The .env file is successfully loaded if you see "✓ SET" for the variables above.
        </p>
    </div>
</body>
</html>
