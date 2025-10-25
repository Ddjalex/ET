# Telegram Crypto Card Bot - Project Documentation

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

## External Dependencies

- **APIs:**
    - **Telegram Bot API:** For bot interaction and messaging.
    - **StroWallet API:** For virtual crypto card management, user creation, cardholder lookup, card creation, card detail fetching, user profile information, wallet balances, and deposit address generation.
- **Database:**
    - **PostgreSQL:** Used for data storage, managed through Replit/Neon.