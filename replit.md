# Telegram Crypto Card Bot

## Overview
This project is a production-ready Telegram bot designed to manage virtual crypto cards through integration with the StroWallet API. It offers functionalities such as virtual card creation, card listing, user information display, wallet management, and deposit handling via Telegram. A comprehensive admin panel supports deposit management, KYC verification, and system settings, ensuring secure and efficient operations. The project aims to provide a robust solution for crypto card management.

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

### Key Technologies
- **Language:** PHP 8.2 (native, no frameworks)
- **Database:** PostgreSQL (Replit/Neon)
- **Deployment:** cPanel via webhooks (HTTPS required)

### UI/UX Decisions
The admin panel features a premium glass-morphism design with enhanced visibility, including backdrop blur effects, beautiful gradient systems (blue/purple primary), enhanced text contrast, animated backgrounds, premium stat cards, and modern typography (Inter, Poppins). All components are responsive and mobile-optimized.

### System Design Choices
The system utilizes a dual webhook architecture for Telegram and StroWallet. It includes a robust admin panel with session-based authentication, CSRF protection, secure password change functionality, and audit trails. Database constraints are configured for a normalized schema. Key features include a comprehensive KYC approval workflow, an enhanced deposit system with exchange rate calculations, and real-time KYC status synchronization with the StroWallet API. All protected commands are gated behind KYC approval.

**Payment Verification System:** Manual admin review for all deposit transactions. All payment submissions (screenshot + transaction ID) are routed to the admin panel for verification and approval. Features include:
- **Transaction Submission:** Users submit payment screenshot and transaction ID via Telegram
- **Admin Review:** All deposits require manual verification through the admin panel
- **Secure Processing:** Admin verifies payment details before approving deposits
- **User Notifications:** Users receive confirmation once admin approves their deposit
- **StroWallet Integration:** Approved deposits are credited to user's StroWallet account
- **Audit Trail:** Complete tracking of all deposit submissions and approvals

### Features Implemented
- **Core Bot Features:** `/start`, `/register`, `/quickregister`, `/create_card`, `/cards`, `/userinfo`, `/wallet` (enhanced with transaction history), `/deposit_trc20`, `/deposit_etb`, `/invite`, `/support`, and associated reply keyboard buttons.
- **Webhook Features:** Telegram webhook with secret token verification; StroWallet webhook with HMAC verification and real-time KYC sync; Admin alerts for deposits and KYC status changes via Telegram; Automatic database updates for KYC events; Giveaway entry tracking.
- **Admin Panel KYC Management:** Real-time auto-refresh, manual sync, user filtering by KYC status.
- **Broadcaster Module:** Full-featured broadcast system for admin-to-user communication supporting various content types (text, photo, video, poll), delivery channels (Telegram channel, in-app feed), scheduling, inline buttons, giveaway system, message pinning, comprehensive logging, status filtering, and a stats dashboard.
- **Manual Deposit Verification:** All deposit transactions are manually reviewed by admin through the admin panel. Users submit payment screenshots and transaction IDs via Telegram, which are stored in the database for admin verification. Approved deposits are credited to user's StroWallet account.
- **Enhanced Wallet Interface:** Beautiful wallet display with box-drawing characters, separate USD/ETB balance sections, transaction count, and quick action buttons (Add ETB, Add USDT, Transaction History, Refresh Balance). Transaction history shows last 10 transactions with emoji indicators, status, amount, and date.
- **Error Handling:** Comprehensive error handling for authentication, invalid endpoints, network errors, Telegram API errors, and payment verification failures, with request ID display and user-friendly messages.

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
- **Databases:**
    - **PostgreSQL:** Used for admin panel, tracking, and temporary registration staging (14 tables: `admin_users`, `admin_actions`, `settings`, `deposits`, `deposit_payments`, `wallets`, `wallet_transactions`, `cards`, `card_transactions`, `users`, `user_registrations`, `broadcasts`, `broadcast_logs`, `giveaway_entries`).
    - **StroWallet Database:** Primary storage for all sensitive customer data, KYC documents, cards, and financial data.

## Data Persistence & Backup

### ✅ Customer Data Protection
All customer data is preserved in `schema.sql` which contains:
- Complete database structure (all 14 tables)
- All customer records (users, deposits, cards, etc.)
- All configuration and settings
- Complete transaction history

### 📦 Backup System
**File:** `schema.sql` (auto-generated, contains complete database backup)

**Backup Script:** `scripts/backup_database.sh`
```bash
bash scripts/backup_database.sh
```
This exports all database tables and customer data to `schema.sql` file.

**Restore Script:** `scripts/restore_database.sh`
```bash
bash scripts/restore_database.sh
```
This restores the complete database from `schema.sql` file (⚠️ WARNING: Deletes existing data).

### 🔄 Data Persistence Across Account Logins
The `schema.sql` file ensures your customer data persists across different Replit account logins:

1. **Before logging out or switching accounts:**
   ```bash
   bash scripts/backup_database.sh
   ```
   This saves all current data to `schema.sql`

2. **After logging in with a different account:**
   ```bash
   bash scripts/restore_database.sh
   ```
   This restores all customer data from `schema.sql`

3. **Regular backups (recommended):**
   - Run backup script weekly or after important transactions
   - Download `schema.sql` file to local machine for extra safety
   - Keep multiple timestamped backups for disaster recovery

### 🛡️ Important Notes
- `schema.sql` is version controlled in Git (always committed)
- Contains sensitive customer data - never share publicly
- Replit PostgreSQL database is persistent within the same account
- Use backup/restore scripts when migrating between accounts or Repls
- All data is preserved: users, KYC status, deposits, cards, transactions