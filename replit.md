# Telegram Crypto Card Bot - Project Documentation

## Overview
This project is a production-ready Telegram bot designed to manage virtual crypto cards by integrating with the StroWallet API. The bot is built using native PHP 8+ (without frameworks or Composer dependencies) and is optimized for cPanel deployment. Its primary purpose is to provide users with functionalities like creating virtual cards, listing existing cards, viewing user information, managing wallets, and handling deposits through a Telegram interface.

The project features a comprehensive admin panel for managing deposits, KYC verification, and system settings, ensuring secure and efficient operation.

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
The project is organized into `database/` for migrations, `public_html/` containing the `admin/` panel and `bot/` webhooks, and a `scripts/` directory for utilities. The `public_html/admin/` includes configuration, templates, login, dashboard, and management pages for deposits, KYC, and settings. The `public_html/bot/` handles Telegram and StroWallet webhooks.

### Key Technologies
- **Language:** PHP 8.2 (native, no frameworks)
- **Database:** PostgreSQL (Replit/Neon)
- **Deployment:** cPanel via webhooks (HTTPS required)
- **Admin Panel:** PHP with session-based authentication, featuring a professional crypto-themed UI with a comprehensive CSS design system, modern typography (Inter, Poppins), glass-morphism effects, and responsive components.

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
- Last updated: October 2025 - Glass-morphism with enhanced visibility and contrast

### System Design Choices
The system implements a dual webhook architecture for Telegram and StroWallet. It includes a robust admin panel with authentication, session management, and CSRF protection. A secure password change functionality is in place with strong password requirements and audit trails. Database constraints are properly configured for normalized schema. User authentication and session management are central to the admin panel's security.

### Recent Changes

**October 22, 2025 - Registration API Call Bug Fix (CRITICAL)**
- **Bug Fix 1 - Phone Number Missing**: Fixed StroWallet API call failing with "Missing required field: phone_number"
- **Root Cause**: The `createStroWalletCustomerFromDB()` function used outdated column names from before database schema fix
- **Issues Fixed**:
  - API validation: `phone_number` → `phone` ✓
  - API validation: `customer_email` → `email` ✓
  - API validation: `city` → `address_city` ✓
  - API validation: `state` → `address_state` ✓
  - API validation: `zip_code` → `address_zip` ✓
  - API payload: All fields updated to use correct database column names ✓
  - Photo URLs: Added backward compatibility for both old and new field names ✓
- **Bug Fix 2 - ID Type Selection UX**: Changed from text names to numbered selection (1, 2, 3)
- **UX Improvements**:
  - Users now select ID type by number instead of typing "GOVERNMENT_ID", "PASSPORT", etc.
  - Bot confirms selected ID type with formatted name (e.g., "Government Id")
  - Country-specific options maintained (ET: National ID/Government ID/Passport, NG: BVN/NIN/Passport)
  - Invalid selections show clear error message requesting 1, 2, or 3
- **Impact**: Registration flow now completes successfully with all required fields sent to StroWallet API
- **Architect Review**: ✅ Passed - All field mappings correct, no regressions found

**October 22, 2025 - Registration Data Saving Fix (CRITICAL)**
- **Bug Fix**: Fixed registration flow not saving user data to database
- **Root Cause**: The `updateUserField()` function and all registration handlers were using wrong database column names
- **Issues Fixed**:
  - Phone: `phone_number` → `phone` ✓
  - Email: `customer_email` → `email` ✓
  - City: `city` → `address_city` ✓
  - State: `state` → `address_state` ✓
  - ZIP: `zip_code` → `address_zip` ✓
  - ID Image: `id_image_url` → `id_front_photo_url` ✓
  - Selfie: Missing save call → Added `selfie_photo_url` ✓
- **Changes Made**:
  1. Updated `updateUserField()` allowedFields array with correct schema column names
  2. Fixed all 7 registration case handlers to use correct column names
  3. Added missing database save for selfie photo upload
  4. Updated `showRegistrationReview()` with fallbacks for backward compatibility
- **Impact**: Registration now saves ALL user data correctly and displays it properly in the review message
- **Testing**: User registration data cleared to enable fresh testing with fixes

**October 22, 2025 - Security Enhancements**
- **Selfie Upload Security**: Removed URL upload option for selfies, now only accepts direct camera photos
- **User Messaging**: Updated all prompts to say "selfie" instead of "selfie/photo" for clarity
- **Security Messaging**: Added clear explanations that only direct photos are accepted for security purposes
- **Bug Fix**: Restored .env file to fix bot token access issue (bot was unable to respond after selfie upload)

**October 20, 2025 - Initial Setup**
- **Environment Setup**: Created `.env` file with admin credentials (admin/admin123) and API keys stored in Replit Secrets
- **Database Setup**: PostgreSQL database provisioned and all migrations applied successfully (10 tables created)
- **KYC Real-Time Updates**: Enhanced admin panel with auto-refresh (30s) for real-time KYC status monitoring
- **Webhook Integration**: Added StroWallet webhook handlers for KYC events (kyc_updated, kyc_approved, kyc_rejected)
- **Admin Notifications**: Telegram alerts sent to admin when KYC status changes from StroWallet
- **Auto-Sync Feature**: KYC page automatically syncs with StroWallet API and updates database in real-time
- **Foreign Key Fixes**: Corrected `admin_actions` and `settings` tables to reference `admin_users` instead of `users` table
- **Admin Login**: Fixed default admin password to match login page (username: `admin`, password: `admin123`)
- **UI/Alert Fixes**: Corrected alert message CSS classes from `error`/`success` to `alert-error`/`alert-success` for proper styling
- **Migration System**: Added migration 004 to document foreign key constraint fixes

### Features Implemented
- **Core Bot Features:** `/start`, `/register`, `/quickregister`, `/create_card`, `/cards`, `/userinfo`, `/wallet`, `/deposit_trc20`, `/invite`, `/support`.
- **Reply Keyboard Buttons:** Create Card, My Cards, User Info, Wallet, Invite Friends, Support.
- **Webhook Features:** 
  - Telegram webhook with secret token verification
  - StroWallet webhook with HMAC verification and real-time KYC sync
  - Admin alerts for deposits and KYC status changes via Telegram
  - Automatic database updates when StroWallet sends KYC events
- **Admin Panel KYC Management:**
  - Real-time auto-refresh (30 seconds) for KYC status monitoring
  - Manual sync button to fetch latest status from StroWallet API
  - Countdown timer showing next auto-refresh
  - Filter users by KYC status (pending, approved, rejected)
- **Error Handling:** Comprehensive handling for authentication failures, wrong endpoints, network errors, with request ID display and user-friendly messages.

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
- **Database:**
    - **PostgreSQL:** Used for data storage, managed through Replit/Neon.