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

**Payment Verification System:** Integrated automatic transaction verification for TeleBirr, M-Pesa, and CBE payments. The system accepts receipt URLs for instant automatic verification and approval without manual admin intervention. Features include:
- **Receipt URL Verification:** Parses TeleBirr, CBE, and M-Pesa receipt URLs using DOM-aware parsers
- **Automatic Validation:** Verifies amount, receiver account, and transaction date/time
- **Instant Approval:** Credits StroWallet account automatically when all checks pass
- **Admin Notifications:** Notifies admin after auto-approval with verification details
- **Fallback to Manual Review:** Sends failed verifications to admin panel for manual processing
- **TLS Security:** All HTTP requests enforce strict TLS verification

### Features Implemented
- **Core Bot Features:** `/start`, `/register`, `/quickregister`, `/create_card`, `/cards`, `/userinfo`, `/wallet`, `/deposit_trc20`, `/deposit_etb`, `/invite`, `/support`, and associated reply keyboard buttons.
- **Webhook Features:** Telegram webhook with secret token verification; StroWallet webhook with HMAC verification and real-time KYC sync; Admin alerts for deposits and KYC status changes via Telegram; Automatic database updates for KYC events; Giveaway entry tracking.
- **Admin Panel KYC Management:** Real-time auto-refresh, manual sync, user filtering by KYC status.
- **Broadcaster Module:** Full-featured broadcast system for admin-to-user communication supporting various content types (text, photo, video, poll), delivery channels (Telegram channel, in-app feed), scheduling, inline buttons, giveaway system, message pinning, comprehensive logging, status filtering, and a stats dashboard.
- **Automatic Payment Verification Module (NEW - Oct 29, 2025):** Revolutionary receipt URL verification system for instant deposit approval:
  - **AutoDepositProcessor:** Core automatic verification engine
  - **DOM-Aware Parsers:** TeleBirrParser, CbeParser, MpesaParser with structured HTML parsing
  - **Receipt URL Support:** Accepts receipt URLs from TeleBirr (`transactioninfo.ethiotelecom.et`), CBE (`apps.cbe.com.et`), M-Pesa
  - **Verification Flow:** Amount matching (±5 ETB tolerance), receiver verification, date validation (must be today)
  - **Auto-Approval:** Credits user's StroWallet account via API when verification succeeds
  - **User Experience:** Instant confirmation (~4-6 seconds total processing time)
  - **Security:** TLS verification, amount tolerance, date validation, receiver matching
  - **Fallback:** Failed verifications automatically route to manual admin review
- **Ethiopian Proxy System (NEW - Oct 29, 2025):** Advanced proxy infrastructure for fetching geo-restricted payment receipts:
  - **ProxyService:** Centralized proxy management with support for HTTP, SOCKS4, and SOCKS5 proxies
  - **Automatic Routing:** All receipt URL fetches automatically route through configured Ethiopian proxy
  - **Multi-Proxy Support:** Primary proxy with unlimited fallback proxies for redundancy
  - **Auto-Fallback System:** Automatically falls back to direct connection if all proxies fail
  - **Health Monitoring:** Built-in health check endpoint (`/bot/proxy-health.php`) for monitoring proxy status
  - **Performance Tracking:** Tracks fetch times, proxy usage, and success rates
  - **Flexible Configuration:** Environment-based configuration with support for authenticated proxies
  - **Transparent Integration:** Seamlessly integrated with receipt verification system
  - **Security:** Supports proxy authentication, credential protection, TLS verification
- **Payment Verification Module (Legacy):** Transaction ID verification via external validation API for backward compatibility. Includes screenshot collection, manual review workflows, and hidden fee calculations.
- **Error Handling:** Comprehensive error handling for authentication, invalid endpoints, network errors, Telegram API errors, and payment verification failures, with request ID display and user-friendly messages.

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
    - **Payment Validation API:** External microservice for verifying TeleBirr, M-Pesa, and CBE transaction authenticity.
- **Infrastructure:**
    - **Ethiopian Proxy Server (Optional):** Proxy server located in Ethiopia for fetching geo-restricted payment receipts. Can be self-hosted (Squid, tinyproxy) or commercial service (Bright Data, Smartproxy). Supports HTTP, SOCKS4, and SOCKS5 protocols with authentication.
- **Databases:**
    - **PostgreSQL:** Used for admin panel, tracking, and temporary registration staging (14 tables: `admin_users`, `admin_actions`, `settings`, `deposits`, `deposit_payments`, `wallets`, `wallet_transactions`, `cards`, `card_transactions`, `users`, `user_registrations`, `broadcasts`, `broadcast_logs`, `giveaway_entries`).
    - **StroWallet Database:** Primary storage for all sensitive customer data, KYC documents, cards, and financial data.