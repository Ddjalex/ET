# Telegram Crypto Card Bot

A production-ready Telegram bot for managing virtual crypto cards through StroWallet API. Built with native PHP 8+ (no frameworks, no Composer) and designed for cPanel deployment.

## Features

✅ **Virtual Card Management**
- Create virtual USD cards
- View all your cards with details
- See card brand, last 4 digits, and status

💰 **Wallet Operations**
- View wallet balances (USD/USDT)
- Generate USDT TRC20 deposit addresses
- Receive real-time deposit confirmations

👤 **User Profile**
- View complete profile information
- KYC status verification
- Card limits, points, and referrals tracking

🎁 **Additional Features**
- Customizable referral/invite system
- Direct support link integration
- Persistent reply keyboard UI
- HTML-formatted messages with emojis

## Project Structure

```
.
├── public_html/
│   └── bot/
│       ├── webhook.php              # Main Telegram bot webhook
│       └── strowallet-webhook.php   # StroWallet events webhook
├── secrets/
│   ├── .env                         # Your configuration (create from .env.example)
│   └── .env.example                 # Configuration template
├── scripts/
│   └── test_endpoints.sh            # API connectivity tester
└── README.md                        # This file
```

## Quick Start

### 1. Prerequisites

- PHP 8.0 or higher
- cURL extension enabled
- HTTPS-enabled hosting (cPanel recommended)
- Telegram Bot Token ([Get from @BotFather](https://t.me/BotFather))
- StroWallet API Keys ([Get from StroWallet Dashboard](https://strowallet.com/user/api-key))

### 2. Installation

1. **Clone/upload the files to your cPanel**
   ```bash
   # Upload to your hosting via FTP/SSH
   # Ensure secrets/ is OUTSIDE public_html for security
   ```

2. **Configure environment variables**
   ```bash
   cd secrets/
   cp .env.example .env
   nano .env  # Edit with your actual values
   ```

3. **Set proper permissions**
   ```bash
   chmod 600 secrets/.env
   chmod 755 public_html/bot/
   chmod 644 public_html/bot/*.php
   ```

### 3. Configuration

Edit `secrets/.env` with your credentials:

```ini
# Telegram Bot Configuration
BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz

# StroWallet API Configuration
STROW_BASE=https://strowallet.com/api
STROW_ADMIN_KEY=your_admin_api_key
STROW_PERSONAL_KEY=your_personal_api_key
STROW_PUBLIC_KEY=optional_public_key

# Admin & Support
ADMIN_CHAT_ID=your_telegram_id_for_alerts
SUPPORT_URL=https://t.me/your_support
REFERRAL_TEXT=Join me on StroWallet! Use my link: https://strowallet.com/ref/YOUR_CODE

# Optional Security
TELEGRAM_SECRET_TOKEN=optional_webhook_secret
STROW_WEBHOOK_SECRET=optional_hmac_secret
```

### 4. Test API Connectivity

Before setting up webhooks, verify your API keys work:

```bash
cd scripts/
./test_endpoints.sh
```

You should see green ✓ marks for successful tests.

### 5. Set Up Telegram Webhook

Set your Telegram webhook to point to your bot endpoint:

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://yourdomain.com/bot/webhook.php",
    "secret_token": "your_optional_secret_token"
  }'
```

**Important:** Replace `<YOUR_BOT_TOKEN>` and `yourdomain.com` with your actual values.

Verify webhook is set:
```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo"
```

### 6. Configure StroWallet Webhook (Optional)

To receive deposit confirmations:

1. Log in to [StroWallet Dashboard](https://strowallet.com/dashboard)
2. Go to **API Settings** → **Webhooks**
3. Add webhook URL: `https://yourdomain.com/bot/strowallet-webhook.php`
4. Select events: `deposit_confirmed`, `card_created`
5. (Optional) Enable HMAC signature and add the secret to `.env`

## cPanel Deployment Guide

### Step 1: Upload Files

1. **Via File Manager:**
   - Upload `public_html/` folder contents to your website's `public_html/` directory
   - Upload `secrets/` folder to your home directory (NOT inside public_html)

2. **Via SSH/FTP:**
   ```bash
   # Your final structure should look like:
   /home/username/
   ├── public_html/
   │   └── bot/
   │       ├── webhook.php
   │       └── strowallet-webhook.php
   └── secrets/
       └── .env
   ```

### Step 2: PHP Version Check

1. Go to cPanel → **Select PHP Version**
2. Select **PHP 8.0** or higher
3. Ensure these extensions are enabled:
   - ✅ curl
   - ✅ json
   - ✅ mbstring

### Step 3: SSL Certificate

1. Go to cPanel → **SSL/TLS Status**
2. Ensure your domain has a valid SSL certificate (Let's Encrypt recommended)
3. Telegram webhooks require HTTPS

### Step 4: Test Your Endpoints

1. **Test Telegram Webhook:**
   ```bash
   curl -X POST "https://yourdomain.com/bot/webhook.php" \
     -H "Content-Type: application/json" \
     -d '{"message":{"chat":{"id":123},"text":"/start"}}'
   ```
   Should return: `OK`

2. **Test StroWallet Webhook:**
   ```bash
   curl -X POST "https://yourdomain.com/bot/strowallet-webhook.php" \
     -H "Content-Type: application/json" \
     -d '{"event":"deposit_confirmed","data":{"amount":"100"}}'
   ```
   Should return: `{"status":"success","message":"Webhook processed"}`

## Bot Commands

The bot supports both slash commands and keyboard buttons:

| Command | Button | Description |
|---------|--------|-------------|
| `/start` | - | Initialize bot and show keyboard |
| `/create_card` | ➕ Create Card | Create a new virtual card |
| `/cards` | 💳 My Cards | View all your cards |
| `/userinfo` | 👤 User Info | View profile details |
| `/wallet` | 💰 Wallet | Check wallet balance |
| `/deposit_trc20` | - | Get USDT deposit address |
| `/invite` | 💸 Invite Friends | Share referral link |
| `/support` | 🧑‍💻 Support | Contact support |

## API Key Management

### Getting Your API Keys

1. **Sign up** at [StroWallet](https://strowallet.com)
2. Navigate to **Dashboard** → **API Keys**
3. Generate two separate keys:
   - **Admin Key**: For card creation/management (`STROW_ADMIN_KEY`)
   - **Personal Key**: For wallet/profile access (`STROW_PERSONAL_KEY`)

### Key Rotation

To rotate your API keys:

1. Generate new keys in StroWallet dashboard
2. Update `.env` file with new keys
3. No need to restart (PHP reads .env on each request)
4. Revoke old keys in dashboard after testing

## Troubleshooting

### Bot Not Responding

**Issue:** Bot doesn't respond to commands

**Solutions:**
1. Check webhook is set correctly:
   ```bash
   curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
   ```
2. Verify `.env` file exists and has correct `BOT_TOKEN`
3. Check PHP error logs in cPanel → **Error Logs**
4. Ensure SSL certificate is valid (Telegram requires HTTPS)

### API Authentication Errors

**Issue:** Getting "Auth failed" errors

**Solutions:**
1. Verify API keys are correct in `.env`
2. **Check IP Whitelist:** StroWallet requires whitelisting your server's IP address
   - Log into StroWallet dashboard
   - Go to Settings → API Keys → Security/IP Whitelist
   - Add your server's IP address (for Replit: check current IP with `curl ifconfig.me`)
   - Save changes
3. Run test script: `./scripts/test_endpoints.sh`
4. Check if keys are expired/revoked in StroWallet dashboard
5. Ensure no extra spaces in `.env` file

### Webhook Not Receiving Events

**Issue:** StroWallet webhook not triggering

**Solutions:**
1. Verify webhook URL in StroWallet dashboard
2. Check URL is accessible publicly: `curl https://yourdomain.com/bot/strowallet-webhook.php`
3. Review webhook logs in StroWallet dashboard
4. Ensure `ADMIN_CHAT_ID` is set for notifications

### "Configuration file not found" Error

**Issue:** PHP shows "Configuration file not found"

**Solutions:**
1. Verify `.env` file is in correct location: `secrets/.env`
2. Check file path in PHP: `__DIR__ . '/../../secrets/.env'`
3. Adjust path if your directory structure differs
4. Ensure file has read permissions: `chmod 600 secrets/.env`

### Messages Not Formatted Correctly

**Issue:** HTML tags showing in messages

**Solutions:**
1. Verify `parse_mode=HTML` is set in Telegram API calls
2. Check for unescaped special characters (`<`, `>`, `&`)
3. Review Telegram's [HTML formatting guide](https://core.telegram.org/bots/api#html-style)

## Security Best Practices

1. **Never commit `.env` file** to version control
2. **Store secrets outside public_html** directory
3. **Use HTTPS only** for all webhooks
4. **Enable Telegram secret token** verification
5. **Implement StroWallet HMAC** signature verification
6. **Restrict file permissions**:
   ```bash
   chmod 600 secrets/.env
   chmod 644 public_html/bot/*.php
   ```
7. **Regularly rotate API keys**

## Webhook Security

### Telegram Secret Token

Add extra security by verifying Telegram webhook requests:

1. Generate a random secret token:
   ```bash
   openssl rand -hex 32
   ```

2. Add to `.env`:
   ```ini
   TELEGRAM_SECRET_TOKEN=your_generated_token
   ```

3. Include in webhook setup:
   ```bash
   curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url":"https://yourdomain.com/bot/webhook.php","secret_token":"your_generated_token"}'
   ```

### StroWallet HMAC Verification

Enable webhook signature verification:

1. Get your webhook secret from StroWallet dashboard
2. Add to `.env`:
   ```ini
   STROW_WEBHOOK_SECRET=your_webhook_secret
   ```
3. The bot will automatically verify signatures when configured

## Development & Testing

### Local Testing (Optional)

You can test locally using PHP's built-in server:

```bash
cd public_html/bot
php -S localhost:8000
```

Use ngrok or similar for webhook testing:
```bash
ngrok http 8000
# Use the HTTPS URL for webhook
```

### API Testing Script

Test your StroWallet API connectivity:

```bash
cd scripts/
./test_endpoints.sh
```

This validates:
- ✅ Admin API key (card operations)
- ✅ Personal API key (wallet/profile)
- ✅ Base URL configuration
- ✅ Network connectivity

## Monitoring & Maintenance

### Log Management

To enable webhook logging (for debugging):

Uncomment the `logEvent()` function in `strowallet-webhook.php`:

```php
function logEvent($eventType, $data) {
    $logFile = __DIR__ . '/../../logs/webhook.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logEntry = date('Y-m-d H:i:s') . " | Event: {$eventType} | Data: " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
```

### Admin Alerts

Receive Telegram notifications for:
- ✅ Deposit confirmations
- ✅ New card creations
- ✅ API errors (when configured)

Set your `ADMIN_CHAT_ID` in `.env` to enable alerts.

## Support & Resources

- **StroWallet API Docs:** [https://strowallet.readme.io](https://strowallet.readme.io)
- **Telegram Bot API:** [https://core.telegram.org/bots/api](https://core.telegram.org/bots/api)
- **PHP Documentation:** [https://www.php.net/docs.php](https://www.php.net/docs.php)

## License

This project is provided as-is for production use. Customize as needed for your specific requirements.

## Credits

Built with ❤️ using native PHP 8+ and StroWallet API integration.
