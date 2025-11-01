# Manual Payment Verification System - Complete Guide

## Overview
The Telegram Crypto Card Bot now includes a comprehensive **Manual Payment Verification System** that allows admins to review and approve/reject all user deposit submissions before funds are credited.

## System Architecture

### Two Deposit Systems
The project contains two deposit tracking systems:

1. **Legacy `deposits` table** - Old system (still visible in admin panel)
2. **New `deposit_payments` table** - Current manual verification system (used by the bot)

### Current Implementation
All deposits initiated from the Telegram bot now use the **`deposit_payments` table** with manual admin review.

---

## How the Manual Payment Verification Works

### User Flow (Telegram Bot)

1. **User Initiates Deposit**
   - User clicks "💵 Deposit ETB" button in the bot
   - Bot prompts for USD amount

2. **Amount Calculation**
   - User enters desired USD amount (minimum $5)
   - System calculates:
     - ETB equivalent using exchange rate
     - Adds deposit fee (default 500 ETB)
     - Shows total amount to pay

3. **Payment Method Selection**
   - User selects payment method:
     - 📱 TeleBirr
     - 💵 CBE Birr
     - 💳 M-Pesa
     - 🏦 Bank Transfer

4. **Payment Instructions**
   - Bot displays payment account details
   - Shows exact amount to transfer
   - Requests screenshot + transaction ID

5. **Screenshot Upload**
   - User uploads payment confirmation screenshot
   - Screenshot is saved to database

6. **Transaction ID Submission**
   - User enters transaction ID/reference number
   - Payment is marked as "pending" for admin review
   - User receives confirmation message
   - Admin receives notification (if ADMIN_CHAT_ID is configured)

### Admin Flow (Admin Panel)

1. **Access Payment Verification**
   - Navigate to **Admin Panel → Payment Verification** (`/admin/payments.php`)
   - View all pending payment submissions

2. **Review Payment Details**
   Each payment card shows:
   - User information (name, email, phone, Telegram ID)
   - USD amount and ETB amount
   - Total amount paid (including fee)
   - Payment method
   - Exchange rate used
   - Deposit fee
   - Transaction ID
   - Payment screenshot (clickable to view full size)
   - Submission timestamp

3. **Approve or Reject**
   - **Approve:** Marks payment as approved, credits to user wallet
   - **Reject:** Requires rejection reason, notifies user

4. **Filter Payments**
   - ⏳ Pending (requires action)
   - ✅ Approved (historical)
   - ❌ Rejected (historical)
   - 📋 All (complete history)

---

## Database Structure

### `deposit_payments` Table
```sql
- id (auto-increment)
- user_id
- telegram_id
- amount_usd (USD amount)
- amount_etb (ETB equivalent)
- exchange_rate
- deposit_fee_etb
- total_etb (amount + fee)
- payment_method (telebirr, cbe, m-pesa, bank_transfer)
- screenshot_file_id (Telegram file ID)
- screenshot_url (full URL to screenshot)
- transaction_number (user-provided reference)
- status (pending, approved, rejected)
- notes
- created_at, updated_at, completed_at
```

---

## Configuration

### Required Environment Variables
- `TELEGRAM_BOT_TOKEN` ✅ (configured)
- `STROWALLET_API_KEY` ✅ (configured)
- `DATABASE_URL` ✅ (configured)

### Optional Environment Variables
- `ADMIN_CHAT_ID` - Telegram chat ID for admin notifications
- `STROWALLET_EMAIL` - StroWallet account email
- `TELEGRAM_SECRET_TOKEN` - Webhook secret token

### Settings (Admin Panel → Settings)
- **Exchange Rate** - USD to ETB conversion rate
- **Deposit Fee** - Flat fee added to deposits (default 500 ETB)
- **Payment Accounts** - Bank/mobile money account details for each method

---

## Testing the System

### Option 1: Test with Real Telegram Bot
1. Set up Telegram webhook pointing to your bot
2. Start a conversation with your bot
3. Use `/deposit_etb` command
4. Follow the deposit flow
5. Check admin panel for payment

### Option 2: Create Test Payment Manually
```sql
INSERT INTO deposit_payments (
    user_id, telegram_id, amount_usd, amount_etb, 
    exchange_rate, deposit_fee_etb, total_etb, 
    payment_method, transaction_number, status
) VALUES (
    1, 123456789, 10.00, 1250.00, 
    125.00, 500.00, 1750.00, 
    'telebirr', 'TEST123456', 'pending'
);
```

Then check `/admin/payments.php` to see the test payment.

---

## Security Features

1. **CSRF Protection** - All admin actions require valid CSRF tokens
2. **Transaction Tracking** - Complete audit trail in `admin_actions` table
3. **Screenshot Storage** - Secure storage via Telegram file system
4. **Status Validation** - Prevents double-approval or modification of processed payments
5. **Database Transactions** - Atomic operations prevent data inconsistency

---

## Admin Panel Features

### Payment Verification Page (`/admin/payments.php`)
- ✅ Real-time payment status counters
- ✅ Filterable payment list (pending/approved/rejected/all)
- ✅ Detailed payment cards with all information
- ✅ Screenshot preview with click-to-enlarge
- ✅ One-click approve button
- ✅ Reject with reason modal
- ✅ Beautiful glass-morphism design
- ✅ Responsive mobile layout
- ✅ Auto-refresh counters

### Integration with Existing System
- Works alongside existing deposit system
- Uses same authentication/session management
- Consistent UI/UX with rest of admin panel
- Logged to admin actions table

---

## File Locations

### Bot Files
- `/public_html/bot/webhook.php` - Main webhook handler
- `/public_html/bot/deposit_handler_v2.php` - Deposit flow logic

### Admin Files
- `/public_html/admin/payments.php` - Payment verification page
- `/public_html/admin/includes/header.php` - Navigation (updated)

### Database
- PostgreSQL database via Replit/Neon
- All tables created via `schema.sql`

---

## Workflow Summary

```
USER                        BOT                         ADMIN
  │                          │                            │
  ├─ /deposit_etb ────────→  │                            │
  │                          │                            │
  │  ← Enter amount ─────────┤                            │
  ├─ 10 USD ───────────────→  │                            │
  │                          │                            │
  │  ← Select method ────────┤                            │
  ├─ TeleBirr ──────────────→  │                            │
  │                          │                            │
  │  ← Payment details ──────┤                            │
  │  ← Send screenshot ──────┤                            │
  ├─ [Screenshot] ──────────→  │                            │
  │                          ├─ Save to DB                 │
  │                          │                            │
  │  ← Enter Txn ID ─────────┤                            │
  ├─ TXN123456 ─────────────→  │                            │
  │                          ├─ Create payment (pending)   │
  │                          ├─ Notify admin ───────────→  │
  │  ← Under review ─────────┤                            │
  │                          │                            │
  │                          │              Review ←──────┤
  │                          │              Screenshot    │
  │                          │              Verify Txn    │
  │                          │                            │
  │                          │   ← Approve/Reject ────────┤
  │                          ├─ Update status             │
  │  ← Notification ─────────┤  (via webhook if setup)    │
  │                          │                            │
```

---

## Next Steps

1. **Configure Webhook** - Point Telegram webhook to your bot URL
2. **Set Admin Chat ID** - Add your Telegram chat ID for notifications
3. **Configure Payment Accounts** - Update payment method details in Settings
4. **Test Deposit Flow** - Make a test deposit through the bot
5. **Verify Admin Panel** - Check payments appear in `/admin/payments.php`

---

## Troubleshooting

### Payments not appearing in admin panel
- Check `deposit_payments` table has records
- Verify bot is using `deposit_handler_v2.php`
- Check database connection in bot code

### Screenshot not loading
- Verify `TELEGRAM_BOT_TOKEN` is set correctly
- Check screenshot URL is properly saved
- Telegram file URLs require valid bot token

### Approval not working
- Check CSRF token is valid
- Verify admin session is active
- Check database connection
- Review PHP error logs

---

## Support

For issues or questions:
- Review bot error logs: `/tmp/telegram_bot_errors.log`
- Check workflow logs in Replit
- Verify database records via SQL tool
- Contact support via configured support URL
