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

### Features Implemented
- **Core Bot Features:** `/start`, `/register`, `/quickregister`, `/create_card`, `/cards`, `/userinfo`, `/wallet`, `/deposit_trc20`, `/invite`, `/support`, and associated reply keyboard buttons.
- **Webhook Features:** Telegram webhook with secret token verification; StroWallet webhook with HMAC verification and real-time KYC sync; Admin alerts for deposits and KYC status changes via Telegram; Automatic database updates for KYC events; Giveaway entry tracking.
- **Admin Panel KYC Management:** Real-time auto-refresh, manual sync, user filtering by KYC status.
- **Broadcaster Module:** Full-featured broadcast system for admin-to-user communication supporting various content types (text, photo, video, poll), delivery channels (Telegram channel, in-app feed), scheduling, inline buttons, giveaway system, message pinning, comprehensive logging, status filtering, and a stats dashboard.
- **Error Handling:** Comprehensive error handling for authentication, invalid endpoints, network errors, and Telegram API errors, with request ID display and user-friendly messages.

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
- **Databases:**
    - **PostgreSQL:** Used for admin panel, tracking, and temporary registration staging (13 tables: `admin_users`, `admin_actions`, `settings`, `deposits`, `wallets`, `wallet_transactions`, `cards`, `card_transactions`, `users`, `user_registrations`, `broadcasts`, `broadcast_logs`, `giveaway_entries`).
    - **StroWallet Database:** Primary storage for all sensitive customer data, KYC documents, cards, and financial data.