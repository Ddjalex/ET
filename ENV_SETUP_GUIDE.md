# Environment Variables Setup Guide

## ✅ .env File Successfully Created!

Your environment variables are now stored in `secrets/.env` and automatically loaded by all components of your application.

## 📍 File Location
```
secrets/.env  (secure, outside public_html)
```

## 🔐 Current Configuration

The following environment variables are configured:

### Telegram Bot
- `BOT_TOKEN` - Your Telegram bot token from @BotFather

### StroWallet API
- `STROW_BASE` - StroWallet API base URL
- `STROW_ADMIN_KEY` - Admin API key for card operations
- `STROW_PERSONAL_KEY` - Personal API key for wallet/profile access

### Database
- `DATABASE_URL` - PostgreSQL connection string (auto-configured)

### Admin & Support
- `ADMIN_CHAT_ID` - Your Telegram ID for admin alerts
- `SUPPORT_URL` - Support channel/group link
- `REFERRAL_TEXT` - Referral invitation message

### Security (Optional)
- `TELEGRAM_SECRET_TOKEN` - Webhook verification token
- `STROW_WEBHOOK_SECRET` - StroWallet HMAC secret

## 🔄 How It Works

All PHP files automatically load the `.env` file using:
```php
require_once __DIR__ . '/../../secrets/load_env.php';
```

This happens in:
- ✅ `public_html/bot/webhook.php` (Telegram bot)
- ✅ `public_html/bot/strowallet-webhook.php` (StroWallet events)
- ✅ `public_html/admin/config/database.php` (Admin panel)

## 📝 How to Update Variables

1. Open `secrets/.env` in the editor
2. Modify the values you need
3. Save the file
4. Changes take effect immediately (no restart needed for PHP)

## 🧪 Test Your Configuration

Visit: **`/test_env.php`** to see all loaded environment variables (sensitive values are masked)

## 🔒 Security Features

✅ File is outside `public_html/` directory (not web-accessible)
✅ Permissions set to `600` (owner read/write only)
✅ Listed in `.gitignore` (never committed to git)
✅ Template available in `secrets/.env.example`

## 📋 Admin Panel Credentials

**Login URL:** `/admin/login.php`
- **Username:** `admin`
- **Password:** `admin123`

⚠️ **IMPORTANT:** Change the admin password after first login!

## 🚀 Next Steps

1. **Update your API keys** in `secrets/.env`:
   - Get your Telegram bot token from @BotFather
   - Get StroWallet API keys from your dashboard
   - Add your Telegram chat ID for admin alerts

2. **Configure your bot webhook**:
   ```bash
   curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://your-repl-url.repl.co/bot/webhook.php"}'
   ```

3. **Test your bot** by sending `/start` to it on Telegram

4. **Access admin panel** at `/admin` to manage deposits and KYC

## 📚 Documentation

- Main README: `README.md`
- Project docs: `replit.md`
- API test script: `scripts/test_endpoints.sh`

## 💡 Tips

- Use Replit Secrets for production deployment
- Keep `.env` file for local development/testing
- Never share or commit your `.env` file
- Regularly rotate your API keys for security

---

**Status:** ✅ All systems operational
**Last Updated:** October 20, 2025
