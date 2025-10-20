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
The admin panel features an **ultra-premium, modern design** with extensive enhancements:
- **Advanced Animation System**: Slide-up, fade-in, pulse, shimmer, and particle effects
- **Premium Visual Effects**: Multi-layer gradients, glass-morphism, glowing shadows, floating backgrounds
- **Enhanced Stat Cards**: 70px gradient icons, 2.5rem bold values, 10px hover lift with scale, radial glow effects
- **Modern Typography**: Inter (300-900), Poppins (600-900), JetBrains Mono for code
- **Premium Interactions**: Ripple effects, hover lift (4px), animated borders, smooth cubic-bezier transitions
- **Professional Color System**: Enhanced gradients (blue/purple primary), vibrant accents (gold, green, red, cyan, purple, pink)
- **Glass Design**: Backdrop blur effects, transparent borders, layered shadows
- **Responsive Components**: Mobile-optimized layouts, adaptive grids, touch-friendly interactions
- Last updated: October 2025 - Premium CSS Enhancement

### System Design Choices
The system implements a dual webhook architecture for Telegram and StroWallet. It includes a robust admin panel with authentication, session management, and CSRF protection. A secure password change functionality is in place with strong password requirements and audit trails. Database constraints are properly configured for normalized schema. User authentication and session management are central to the admin panel's security.

### Features Implemented
- **Core Bot Features:** `/start`, `/register`, `/quickregister`, `/create_card`, `/cards`, `/userinfo`, `/wallet`, `/deposit_trc20`, `/invite`, `/support`.
- **Reply Keyboard Buttons:** Create Card, My Cards, User Info, Wallet, Invite Friends, Support.
- **Webhook Features:** Telegram webhook with secret token verification, StroWallet webhook with HMAC verification stub, admin alerts for deposits.
- **Error Handling:** Comprehensive handling for authentication failures, wrong endpoints, network errors, with request ID display and user-friendly messages.

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
- **Database:**
    - **PostgreSQL:** Used for data storage, managed through Replit/Neon.