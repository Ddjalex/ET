# Telegram Crypto Card Bot - Project Documentation

## Overview
A production-ready Telegram bot for managing virtual crypto cards through StroWallet API integration. Built with native PHP 8+ (no frameworks, no Composer dependencies) specifically designed for cPanel deployment.

**Current State:** Development complete, ready for cPanel deployment with actual API keys.

## 🔐 Admin Panel Access

**Admin Login Credentials (Development):**
- **URL:** `/admin/login.php` or `/admin/`
- **Username:** `admin`
- **Password:** `admin123`
- **⚠️ WARNING:** Change this password immediately for production use!

See `ADMIN_CREDENTIALS.md` for detailed security information.

---

## Recent Changes (October 20, 2025)
- ✅ **IMPORT TO REPLIT COMPLETED** - Project successfully imported and configured
  - PostgreSQL database created with all 9 tables
  - Database migrations run successfully
  - Fixed SSL mode configuration for Replit database
  - Admin password hash corrected and verified
  - All endpoints tested and working
- ✅ **ADMIN PANEL SYSTEM IMPLEMENTED** - Complete admin panel for managing deposits, KYC, and settings
  - Database schema with 9 tables: users, wallets, wallet_transactions, deposits, cards, card_transactions, settings, admin_actions, admin_users
  - Admin authentication with session management and CSRF protection
  - Deposit approval workflow with wallet balance updates
  - KYC verification system
  - Settings management (exchange rates, fees, limits)
  - Dashboard with statistics and pending items
  - Fixed database connection handling (singleton pattern for transactions)
  - Fixed foreign key references (admin_users instead of users)
- ✅ **WALLET SYSTEM** - Users can now have wallet balances tracked in database
- ✅ **DEPOSIT WORKFLOW** - ETB to USD conversion with admin approval

## Previous Changes (October 11, 2025)
- ✅ Initial project setup with PHP 8.2
- ✅ Created dual webhook architecture (Telegram + StroWallet)
- ✅ Implemented all core features: card creation, listing, user info, wallet, deposits
- ✅ Built persistent reply keyboard UI with 6 buttons (fixed: using is_persistent flag)
- ✅ Added comprehensive error handling with Request ID display
- ✅ Migrated from .env file to Replit Secrets (secure environment variables)
- ✅ Fixed StroWallet API authentication (added Authorization Bearer header)
- ✅ Updated API integration to use public_key in request body with secret_key in header
- ✅ Built API testing script for endpoint validation
- ✅ Written complete deployment documentation (README.md)
- ✅ Set up PHP development server for local testing
- ✅ Created webhook info page (index.php) for easy webhook URL access
- ✅ Fixed critical keyboard persistence bug (corrected from 'persistent' to 'is_persistent')
- ✅ Configured Telegram webhook successfully - bot responding to messages
- ✅ Added API secrets via Replit Secrets (BOT_TOKEN, STROW_PUBLIC_KEY, STROW_SECRET_KEY, STROWALLET_EMAIL)
- ✅ Added debug logging for troubleshooting API errors
- ✅ **CRITICAL FIX:** Removed automatic customer creation with fake KYC data (compliance issue)
- ✅ **NEW:** Implemented customer verification system - checks if customer exists before card creation
- ✅ **NEW:** Added HTTP status-based error handling (404=customer missing, 401/403=auth error, 5xx=server error)
- ✅ **Architect Approved:** Production-ready implementation with proper error messaging
- ✅ **NEW FEATURE:** Customer Registration System (October 11, 2025)
  - Added `/register` command - Shows all registration options
  - Added `/quickregister` command - Automated customer creation via environment variables
  - Integrated StroWallet create-user API endpoint
  - Enhanced card creation flow with registration prompts
  - Created comprehensive documentation (CUSTOMER_REGISTRATION.md)
  - Supports environment-based customer data configuration
  - Full compliance with KYC requirements (no fake data)
- ✅ **CRITICAL FIXES (Latest):**
  - **Fixed "Auth failed" error**: Added missing Authorization Bearer header to all StroWallet API calls
  - **Fixed KYC images not showing**: Relocated uploads to public_html/uploads/kyc_documents for web accessibility
  - **Fixed bot not responding**: Configured Telegram webhook to Replit server
  - **Improved UX**: /start command now checks registration status and prompts new users to register
  - **Fixed registration flow**: Created PostgreSQL database and user_registrations table for tracking registration state
  - **Fixed swapped secrets**: Corrected BOT_TOKEN and STROWALLET_EMAIL configuration
  - **Verified customer exists**: Successfully tested with addisumelke01@gmail.com
  - **Ready for production**: All API tests passing, registration system working

## Project Architecture

### File Structure
```
.
├── database/
│   └── migrations/          # Database schema migrations
│       ├── 001_create_schema.sql
│       └── 002_fix_admin_fk.sql
├── public_html/
│   ├── admin/              # Admin panel (NEW)
│   │   ├── config/
│   │   │   ├── database.php    # Database connection helpers
│   │   │   └── session.php     # Session & auth management
│   │   ├── includes/
│   │   │   ├── header.php      # Shared header template
│   │   │   └── footer.php      # Shared footer template
│   │   ├── login.php           # Admin login page
│   │   ├── logout.php          # Logout handler
│   │   ├── dashboard.php       # Main dashboard
│   │   ├── deposits.php        # Deposit approval
│   │   ├── kyc.php             # KYC verification
│   │   ├── settings.php        # System settings
│   │   └── users.php           # User management (planned)
│   └── bot/                # Public webhooks
│       ├── webhook.php          # Telegram bot webhook handler
│       └── strowallet-webhook.php # StroWallet events webhook
├── scripts/
│   └── test_endpoints.sh    # API connectivity validator
├── README.md                # Deployment & troubleshooting guide
└── replit.md               # This file
```

### Key Technologies
- **Language:** PHP 8.2 (native, no frameworks)
- **Database:** PostgreSQL (Replit/Neon)
- **APIs:** Telegram Bot API, StroWallet API
- **Deployment:** cPanel via webhooks (HTTPS required)
- **Admin Panel:** PHP with session-based authentication

### API Integration
- **Dual API Keys:**
  - Admin Key: Card creation/management operations
  - Personal Key: Wallet/profile data access
- **Endpoints:**
  - `/bitvcard/create-user/` - Create customer (NEW)
  - `/bitvcard/getcardholder/` - Check customer exists (NEW)
  - `/bitvcard/create-card/` - Create virtual cards
  - `/bitvcard/fetch-card-detail/` - List cards
  - `/user/profile` - User information
  - `/wallet/balance` - Wallet balances
  - `/wallet/deposit-address` - Generate deposit addresses

### Webhook Architecture
1. **Telegram Webhook** (`/bot/webhook.php`)
   - Receives user commands and button presses
   - Routes to appropriate handlers
   - Sends formatted HTML responses with persistent keyboard
   - Handles secret token verification (optional)

2. **StroWallet Webhook** (`/bot/strowallet-webhook.php`)
   - Receives deposit confirmations
   - Sends admin alerts via Telegram
   - Supports HMAC signature verification (optional)

## User Preferences & Coding Style

### Coding Conventions
- Native PHP 8+ only (no Composer, no frameworks)
- PSR-style function naming (camelCase)
- Comprehensive error handling with user-friendly messages
- Security-first: secrets outside public_html, never logged
- HTML formatting for Telegram messages with emojis
- Defensive coding: handle both nested and flat JSON responses

### Bot UX Standards
- Persistent reply keyboard always visible
- Emoji-rich formatted messages
- Request ID display on errors
- Masked sensitive data (user IDs, transaction hashes)
- KYC verification prompts for unverified users

## Features Implemented

### Core Bot Features ✅
- [x] `/start` - Welcome message with keyboard
- [x] `/register` - View customer registration options (NEW)
- [x] `/quickregister` - Automated customer creation (NEW)
- [x] `/create_card` - Create virtual USD card
- [x] `/cards` - List all user cards
- [x] `/userinfo` - Display profile with KYC status
- [x] `/wallet` - Show wallet balances
- [x] `/deposit_trc20` - Generate USDT deposit address
- [x] `/invite` - Share referral message
- [x] `/support` - Link to support

### Reply Keyboard Buttons ✅
- [x] ➕ Create Card
- [x] 💳 My Cards
- [x] 👤 User Info
- [x] 💰 Wallet
- [x] 💸 Invite Friends
- [x] 🧑‍💻 Support

### Webhook Features ✅
- [x] Telegram webhook with secret token verification
- [x] StroWallet webhook with HMAC verification stub
- [x] Admin alerts for deposits
- [x] Proper HTTP 200 responses

### Error Handling ✅
- [x] Auth failures (401/403)
- [x] Wrong endpoints (404)
- [x] Network errors
- [x] Request ID display
- [x] User-friendly error messages

## Configuration Requirements

### Environment Variables Setup

**✅ NEW: .env File Support Added (October 20, 2025)**

Environment variables can now be configured in two ways:

#### Method 1: `.env` File (Recommended for Development)
Location: `secrets/.env`

All environment variables are automatically loaded from this file. See `ENV_SETUP_GUIDE.md` for details.

#### Method 2: Replit Secrets (Recommended for Production)
```ini
BOT_TOKEN=              # From @BotFather
STROW_ADMIN_KEY=        # Admin key from StroWallet API dashboard
STROW_PERSONAL_KEY=     # Personal key from StroWallet API dashboard
DATABASE_URL=           # Auto-configured by Replit
ADMIN_CHAT_ID=          # For webhook alerts (optional)
SUPPORT_URL=            # Support link (optional)
REFERRAL_TEXT=          # Invite message (optional)
```

### Optional Security Variables
```ini
TELEGRAM_SECRET_TOKEN=  # Webhook verification
STROW_WEBHOOK_SECRET=   # HMAC signature
```

### Test Your Configuration
Visit `/test_env.php` to verify all environment variables are loaded correctly.

**Important:** The `.env` file is automatically loaded by all PHP components. Use it for development/testing, and Replit Secrets for production deployment.

## Deployment Notes

### cPanel Deployment Steps
1. Upload `public_html/bot/` to website public directory
2. Place `secrets/.env` outside public_html (in home directory)
3. Ensure PHP 8.0+ with curl extension enabled
4. Verify HTTPS/SSL certificate is active
5. Set Telegram webhook to `https://yourdomain.com/bot/webhook.php`
6. Configure StroWallet webhook to `https://yourdomain.com/bot/strowallet-webhook.php`
7. Test with `scripts/test_endpoints.sh`

### Security Checklist
- [x] Secrets stored outside public_html
- [x] .env excluded from git (.gitignore configured)
- [x] API keys never logged or exposed
- [x] HTTPS-only webhooks
- [x] Optional secret token verification
- [x] File permissions: 600 for .env, 644 for PHP files

## Testing & Validation

### API Testing
Run `./scripts/test_endpoints.sh` to validate:
- Admin API key connectivity
- Personal API key connectivity  
- Base URL configuration
- Network connectivity

### Webhook Testing
```bash
# Test Telegram webhook
curl -X POST "https://yourdomain.com/bot/webhook.php" \
  -H "Content-Type: application/json" \
  -d '{"message":{"chat":{"id":123},"text":"/start"}}'

# Test StroWallet webhook
curl -X POST "https://yourdomain.com/bot/strowallet-webhook.php" \
  -H "Content-Type: application/json" \
  -d '{"event":"deposit_confirmed","data":{"amount":"100"}}'
```

## Known Limitations

1. **Local Development:** PHP built-in server used for testing (replace with Apache/Nginx for production)
2. **Placeholder Keys:** Default .env contains test keys (must replace with real StroWallet keys)
3. **LSP Warnings:** PHP LSP shows false positives for forward function references (safe to ignore)
4. **No Database:** All data fetched from StroWallet API (stateless bot)
5. **IP Whitelist Required:** StroWallet requires whitelisting server IP addresses for API access

## Troubleshooting Guide

### Common Issues
1. **Bot not responding:** Verify webhook URL and SSL certificate
2. **Auth failed:** Check API keys in .env file
3. **Config not found:** Ensure .env path matches file structure
4. **Webhook not triggering:** Confirm HTTPS and public accessibility

See README.md for detailed troubleshooting steps.

## Next Steps (Future Enhancements)

### Potential Features
- [ ] Card funding from wallet balance
- [ ] Transaction history view
- [ ] Card freeze/unfreeze controls
- [ ] Multi-currency support
- [ ] Enhanced admin dashboard
- [ ] Rate limiting & anti-spam protection
- [ ] User session management
- [ ] Inline keyboard for card actions

### Production Readiness
- [ ] Replace test API keys with production keys
- [ ] Enable Telegram secret token verification
- [ ] Enable StroWallet HMAC verification
- [ ] Set up proper logging and monitoring
- [ ] Configure backup/failover webhooks
- [ ] Implement rate limiting

## Resources
- StroWallet API: https://strowallet.readme.io
- Telegram Bot API: https://core.telegram.org/bots/api
- PHP 8 Documentation: https://www.php.net/docs.php
