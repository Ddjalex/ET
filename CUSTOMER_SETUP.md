# Customer Setup Guide

## Overview
The bot now uses a **compliant, production-ready** approach that requires you to manually create a customer in your StroWallet dashboard before using the bot.

## Why This Approach?

**Previous Issue:** The bot was attempting to automatically create customers with fake/placeholder KYC data (phone numbers, addresses, ID images), which is:
- ❌ Non-compliant with KYC regulations
- ❌ Could create fraudulent records
- ❌ Would be rejected by StroWallet API

**New Solution:** The bot verifies that a customer exists in your StroWallet account before creating cards. This ensures:
- ✅ All KYC data is real and verified
- ✅ Compliant with financial regulations
- ✅ Cards appear in your actual StroWallet dashboard
- ✅ Production-ready implementation

## Setup Steps

### 1. Create Customer in StroWallet Dashboard

1. **Log into StroWallet:** https://strowallet.com/login
2. **Navigate to Card Holders:**
   - Go to "Card Management" or "Card Holders" section
   - Click "Create New Customer" or "Add Card Holder"

3. **Fill in Customer Information:**
   - **Email:** Use the SAME email you configured in `STROWALLET_EMAIL` secret
   - **First Name:** Your actual first name
   - **Last Name:** Your actual last name
   - **Phone Number:** Your real phone number
   - **Date of Birth:** Your actual date of birth
   - **Address:** Your complete address
   - **ID Type:** Choose your ID type (Passport, Driver's License, etc.)
   - **ID Number:** Your actual ID number
   - **Upload Documents:** Upload real ID images and selfie photo

4. **Complete KYC Verification:**
   - Wait for StroWallet to verify your documents
   - Ensure KYC status shows "Verified"

### 2. Configure Bot Secret

Make sure your `STROWALLET_EMAIL` secret matches the customer email you created:

```bash
# In Replit Secrets (already configured)
STROWALLET_EMAIL=your.email@example.com
```

### 3. Test Card Creation

Once your customer is verified in StroWallet:

1. Open your Telegram bot
2. Click "➕ Create Card" button
3. Bot will verify customer exists and create the card

## Error Messages Explained

### ❌ Customer Not Found
```
❌ Customer Not Found

No customer with email your.email@example.com exists in StroWallet.

📝 Setup Required:
1. Log into StroWallet dashboard
2. Go to Card Holders → Create New
3. Create customer with email: your.email@example.com
4. Complete KYC verification
5. Try creating a card again
```

**Solution:** Follow the setup steps above to create a customer.

### ❌ Authentication Error
```
❌ Authentication Error

StroWallet API authentication failed.

This is a configuration issue. Please contact the administrator.
```

**Possible Causes:**
- Wrong API keys in secrets
- IP address not whitelisted (contact StroWallet support)
- API keys expired or revoked

**Solution:** 
1. Verify `STROW_PUBLIC_KEY` and `STROW_SECRET_KEY` are correct
2. Contact StroWallet support to whitelist your server IP
3. Check API key status in StroWallet dashboard

### ❌ Service Error
```
❌ Service Error

Unable to connect to StroWallet API.

Please try again in a few moments.
```

**Possible Causes:**
- Network connectivity issues
- StroWallet API temporarily down
- Rate limiting

**Solution:** Wait a few moments and try again.

## How It Works

### Customer Verification Flow

```
User clicks "Create Card"
        ↓
Check STROWALLET_EMAIL configured
        ↓
Call /getcardholder API to verify customer exists
        ↓
┌───────────────────────────┐
│   Customer Found (200)    │ → Proceed to create card
└───────────────────────────┘
        │
┌───────────────────────────┐
│  Customer Missing (404)   │ → Show setup instructions
└───────────────────────────┘
        │
┌───────────────────────────┐
│   Auth Error (401/403)    │ → Show configuration error
└───────────────────────────┘
        │
┌───────────────────────────┐
│   Other Errors (5xx)      │ → Show retry message
└───────────────────────────┘
```

### Error Logging

All errors are logged with HTTP status codes for troubleshooting:

```
[11-Oct-2025 11:51:00 UTC] Customer check failed - HTTP 404: {"error":"Customer not found"}
[11-Oct-2025 11:51:05 UTC] Customer check failed - HTTP 403: {"error":"IP not whitelisted"}
```

## Important Notes

1. **One Customer Per Account:** The bot uses ONE configured customer email (STROWALLET_EMAIL) for all cards
2. **Real Data Required:** All KYC data must be real and verified
3. **Manual Setup:** Customer must be created manually in StroWallet dashboard
4. **No Automatic Registration:** Bot will NOT create customers automatically
5. **Production Ready:** This approach is compliant and production-ready

## Troubleshooting

### Card Not Appearing in Dashboard

**Check:**
1. Customer is verified in StroWallet
2. STROWALLET_EMAIL matches customer email exactly
3. API keys are correct
4. No error messages in bot responses

### Bot Shows "Customer Not Found" But Customer Exists

**Check:**
1. Email in STROWALLET_EMAIL secret matches exactly (case-sensitive)
2. Customer status is "Verified" in StroWallet
3. Check bot error logs: `tail -50 /tmp/telegram_bot_errors.log`

### Authentication Errors

**Check:**
1. API keys are correct in Replit Secrets
2. Server IP is whitelisted in StroWallet (contact support)
3. API keys are not expired

## Next Steps

1. **Create Customer:** Follow setup steps above
2. **Test Bot:** Try creating a card
3. **Monitor Logs:** Check `/tmp/telegram_bot_errors.log` for any issues
4. **Deploy:** Once working, publish your Replit app for production use

## Support

- **StroWallet Support:** Contact to whitelist IP addresses
- **Bot Logs:** Check `/tmp/telegram_bot_errors.log` for detailed error information
- **API Documentation:** https://strowallet.readme.io
