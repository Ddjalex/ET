<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Crypto Card Bot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .status {
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: inline-block;
            font-weight: 600;
        }
        .webhook-section {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .webhook-section h3 {
            color: #374151;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .url-box {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
            color: #1f2937;
        }
        .info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info p {
            color: #1e40af;
            line-height: 1.6;
        }
        .feature-list {
            list-style: none;
            margin: 20px 0;
        }
        .feature-list li {
            padding: 8px 0;
            color: #4b5563;
        }
        .feature-list li:before {
            content: "✓ ";
            color: #10b981;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Telegram Crypto Card Bot</h1>
        <p class="subtitle">StroWallet Integration</p>
        
        <div class="status">🟢 Server Running</div>

        <div class="info">
            <p><strong>ℹ️ This is a webhook server.</strong> The bot operates via webhooks - it doesn't have a web interface. Your Telegram bot interacts with these endpoints.</p>
        </div>

        <div class="webhook-section">
            <h3>📱 Telegram Bot Webhook</h3>
            <div class="url-box">
                <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/bot/webhook.php
            </div>
        </div>

        <div class="webhook-section">
            <h3>💳 StroWallet Events Webhook</h3>
            <div class="url-box">
                <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/bot/strowallet-webhook.php
            </div>
        </div>

        <h3 style="margin-top: 30px; color: #374151;">Features</h3>
        <ul class="feature-list">
            <li>Create virtual USD cards</li>
            <li>View all your cards</li>
            <li>Check wallet balance</li>
            <li>User profile management</li>
            <li>USDT TRC20 deposits</li>
            <li>Real-time webhook notifications</li>
        </ul>

        <div class="info">
            <p><strong>🔐 Setup Required:</strong> Configure your Telegram bot webhook to point to the Telegram Bot Webhook URL above, and set your StroWallet webhook to the StroWallet Events Webhook URL.</p>
        </div>
    </div>
</body>
</html>
