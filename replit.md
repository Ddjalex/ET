# Telegram Crypto Card Bot - Project Documentation

## 🎯 Current Status (Last Updated: October 28, 2025)

**System Status:** ✅ Fully Operational
- Bot Username: **@ETH_Card_BOT**
- Bot Name: ET_Card
- Telegram Webhook: Connected and Active
- StroWallet Integration: Working
- Database: PostgreSQL (Replit/Neon) - Migrated and Ready
- Admin Panel: Available at `/admin/` (default: admin/admin123)
- **StroWallet User Sync:** ✅ Active (imports existing StroWallet customers)

### ✅ Working Features:
- Customer registration via Telegram (`/register` command)
- Full KYC data submission to StroWallet (ID images, selfie, personal info)
- Customer data stored and verified in StroWallet dashboard
- Webhook endpoints configured and active
- **NEW:** Sync existing StroWallet customers to admin panel (`scripts/sync_strowallet_users.php`)

## Overview
This project is a production-ready Telegram bot designed to manage virtual crypto cards through integration with the StroWallet API. Built with native PHP 8+ for cPanel deployment, it offers functionalities such as virtual card creation, card listing, user information display, wallet management, and deposit handling via Telegram. A comprehensive admin panel supports deposit management, KYC verification, and system settings, ensuring secure and efficient operations. The project aims to provide a robust solution for crypto card management.

## User Preferences
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

## System Architecture

### File Structure
The project is organized into `database/` for migrations, `public_html/` containing `admin/` panel and `bot/` webhooks, and `scripts/` for utilities. `public_html/admin/` includes configuration, templates, login, dashboard, and management pages for deposits, KYC, and settings. `public_html/bot/` handles Telegram and StroWallet webhooks.

### Key Technologies
- **Language:** PHP 8.2 (native, no frameworks)
- **Database:** PostgreSQL (Replit/Neon)
- **Deployment:** cPanel via webhooks (HTTPS required)
- **Admin Panel:** PHP with session-based authentication, featuring a professional crypto-themed UI with a comprehensive CSS design system, modern typography, glass-morphism effects, and responsive components.

### UI/UX Decisions
The admin panel features **premium glass-morphism design with enhanced visibility**:
- **Glass Morphism Effects**: Backdrop blur effects with clear borders and high contrast
- **Gradient System**: Beautiful gradients (blue/purple primary) with text shadows for readability
- **Enhanced Text Contrast**: Bright text colors (#f8fafc) with text shadows on glass backgrounds
- **Animated Backgrounds**: Particle effects and floating card backgrounds at lower opacity
- **Premium Stat Cards**: Gradient top borders, large icons, bold values with shadows
- **Modern Typography**: Inter (400-900), Poppins (600-900) for headings with high contrast
- **High Visibility Alerts**: Glass effect alerts with bright, clear colors
- **Responsive Components**: Mobile-optimized layouts, adaptive grids

### System Design Choices
The system utilizes a dual webhook architecture for Telegram and StroWallet. It includes a robust admin panel with authentication, session management, and CSRF protection. Secure password change functionality with strong requirements and audit trails is implemented. Database constraints are properly configured for a normalized schema. User authentication and session management are central to the admin panel's security. Key features include a comprehensive KYC approval workflow, an enhanced deposit system with exchange rate calculations, and real-time KYC status synchronization with the StroWallet API. All protected commands are gated behind KYC approval.

### Features Implemented
- **Core Bot Features:** `/start`, `/register`, `/quickregister`, `/create_card`, `/cards`, `/userinfo`, `/wallet`, `/deposit_trc20`, `/invite`, `/support`.
- **Reply Keyboard Buttons:** Create Card, My Cards, User Info, Wallet, Invite Friends, Support.
- **Webhook Features:** Telegram webhook with secret token verification; StroWallet webhook with HMAC verification and real-time KYC sync; Admin alerts for deposits and KYC status changes via Telegram; Automatic database updates for KYC events.
- **Admin Panel KYC Management:** Real-time auto-refresh, manual sync, user filtering by KYC status.
- **Error Handling:** Comprehensive error handling for authentication, invalid endpoints, and network errors, with request ID display and user-friendly messages.

## 🔧 Required Environment Variables (Replit Secrets)

**Critical Secrets - Must Be Set:**
- `TELEGRAM_BOT_TOKEN` - Your bot token from @BotFather (optional, for bot functionality)
- `STROWALLET_API_KEY` - Your StroWallet Public Key (used as `public_key` in API calls) ✅ Configured
- `STROWALLET_WEBHOOK_SECRET` - Your StroWallet Secret Key (used for webhook authentication) ✅ Configured
- `DATABASE_URL` - Auto-configured by Replit PostgreSQL

**Optional Secrets:**
- `TELEGRAM_SECRET_TOKEN` - Webhook verification (recommended for security)
- `STROWALLET_EMAIL` - Customer email for quick registration
- `ADMIN_CHAT_ID` - Telegram chat ID for admin notifications
- `SUPPORT_URL` - Support link shown to users

**Important:** The bot code looks for these exact variable names. Do not use old names like `STROW_PUBLIC_KEY` or `STROW_SECRET_KEY`.

## 🗄️ Data Architecture

### What's Stored Where:

#### Local PostgreSQL Database (10 Tables):
**Purpose:** Admin panel, tracking, and temporary registration staging

1. **admin_users** - Admin panel login credentials
2. **admin_actions** - Audit trail for admin actions
3. **settings** - System configuration
4. **deposits** - Deposit requests (before StroWallet confirmation)
5. **wallets** - Wallet tracking
6. **wallet_transactions** - Transaction logs
7. **cards** - Card reference data (links to StroWallet)
8. **card_transactions** - Transaction logs
9. **users** - Basic user tracking (telegram_user_id → strowallet_customer_id mapping)
10. **user_registrations** - **TEMPORARY staging** during registration flow
    - Stores: telegram_user_id, registration_state, kyc_status
    - During registration: Temporarily holds all data (name, address, ID photos)
    - After completion: Only keeps reference data and KYC status sync

#### StroWallet API Database (Their System):
**Purpose:** Primary customer data, KYC documents, cards, and wallets

**All Sensitive Customer Data:**
- ✅ Full customer credentials (name, email, phone, date of birth)
- ✅ Complete address information
- ✅ ID documents (national ID/passport images)
- ✅ Customer selfie photos
- ✅ KYC verification status and results
- ✅ Virtual card details and credentials
- ✅ Wallet balances and transactions
- ✅ All financial data

**Data Flow:**
1. Customer registers via Telegram → Bot collects data step-by-step
2. Data temporarily staged in `user_registrations` table
3. On CONFIRM → Full data sent to StroWallet `/bitvcard/create-user/` API
4. StroWallet stores everything permanently and performs KYC
5. Local DB only keeps: telegram_user_id, strowallet_customer_id, kyc_status
6. StroWallet sends webhook updates when KYC status changes

## 📋 Customer Registration Workflow

### `/register` Command Flow:

**Step-by-Step Data Collection:**
1. Email address
2. First name
3. Last name
4. Date of birth (MM/DD/YYYY format)
5. Phone number (international format without +, e.g., 2348012345678)
6. House/apartment number
7. Street address (line1)
8. City
9. State/Province
10. ZIP/Postal code
11. Country (2-letter code: ET, NG, US, etc.)
12. ID type (National ID, Passport, BVN, NIN, Driver License)
13. ID number
14. ID document photo (front or both sides)
15. Selfie photo (customer self-portrait)

**Completion:**
- User types `CONFIRM`
- Bot validates all fields
- Sends complete data package to StroWallet API
- StroWallet creates customer and starts KYC verification
- Customer appears in StroWallet dashboard with all documents
- Bot shows "KYC Under Review" message
- User must wait for StroWallet KYC approval before using card features

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
- **Database:**
    - **PostgreSQL:** Used for admin panel and reference data, managed through Replit/Neon.
    - **StroWallet Database:** Primary storage for all customer credentials, KYC documents, cards, and financial data.

## 🌐 Webhook Configuration

### Current Webhook URLs:
- **Telegram Bot Webhook:** `https://bda85921-287b-4e71-a7ad-fcdfcae1ae1f-00-cnhm1o810wc1.picard.replit.dev/bot/webhook.php`
  - Status: ✅ Active and configured
  - Handles: All Telegram messages and commands
  
- **StroWallet Events Webhook:** `https://bda85921-287b-4e71-a7ad-fcdfcae1ae1f-00-cnhm1o810wc1.picard.replit.dev/bot/strowallet-webhook.php`
  - Status: Ready (configure in StroWallet dashboard)
  - Handles: KYC status updates, deposit confirmations, card events

### Setting Up StroWallet Webhook:
1. Log into https://strowallet.com
2. Navigate to: Settings → Webhooks (or Developer Settings)
3. Add webhook URL: `/bot/strowallet-webhook.php`
4. Save changes

### Workflow Configuration:
- **Workflow Name:** PHP Bot Server
- **Command:** `cd public_html && php -S 0.0.0.0:5000`
- **Port:** 5000 (required by Replit)
- **Status:** Running

## 🔐 Security Notes

1. **Never commit secrets** - All API keys must be in Replit Secrets, never in code
2. **Photo URLs** - ID and selfie photos uploaded to Telegram are converted to HTTPS URLs and sent to StroWallet
3. **Database access** - Local database only accessible via Replit environment
4. **Admin panel** - Change default password (admin/admin123) immediately after first login
5. **Webhook verification** - Telegram secret token and StroWallet HMAC signatures verify webhook authenticity

## 📝 Quick Reference

### Important File Locations:
- **Main bot webhook:** `public_html/bot/webhook.php` (2290 lines)
- **StroWallet webhook:** `public_html/bot/strowallet-webhook.php`
- **Admin panel:** `public_html/admin/` directory
- **Database migrations:** `database/migrations/` (6 migration files)
- **Environment loader:** `secrets/load_env.php`

### Database Schema:
- Migration 001: Core schema (users, cards, wallets, deposits, settings)
- Migration 002-004: Foreign key fixes
- Migration 005: User registrations table
- Migration 006: KYC status column (added Oct 25, 2025)

### Key Functions in webhook.php:
- `handleRegistrationFlow()` - Multi-step registration conversation
- `createStroWalletCustomerFromDB()` - Sends customer data to StroWallet API
- `callStroWalletAPI()` - Wrapper for all StroWallet API calls
- `getUserRegistrationState()` - Tracks registration progress
- `handleCreateCard()` - Card creation flow
- `handleCards()` - List all cards
- `handleUserInfo()` - Display user profile

### Testing Checklist:
- [ ] `/start` - Welcome message displays
- [ ] `/register` - Registration flow completes
- [ ] Customer appears in StroWallet dashboard
- [ ] ID images and selfie visible in StroWallet
- [ ] KYC status shows "pending" or "approved"
- [ ] Admin panel accessible at `/admin/`
- [ ] Database has proper schema (10 tables)

---

**System saved and documented. All configuration preserved for future updates. Ready for development!** 🚀