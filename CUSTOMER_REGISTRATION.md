# Customer Registration Guide

## Overview
This guide explains how to set up customer registration for your Telegram Crypto Card Bot using the StroWallet API.

## What Changed
Based on the StroWallet API documentation, we've added automatic customer creation functionality to your bot:

### New Features ✅
- `/register` - View registration options and instructions
- `/quickregister` - Automated customer creation using environment variables
- Enhanced card creation flow with registration prompts
- Full StroWallet create-user API integration

## How Customer Registration Works

### Method 1: Quick Registration (Automated)
Uses pre-configured environment variables for customer data.

**Required Environment Variables:**
```bash
# Already configured
STROWALLET_EMAIL=your@email.com
STROW_PUBLIC_KEY=your_public_key
STROW_SECRET_KEY=your_secret_key

# New variables needed for customer registration
CUSTOMER_FIRST_NAME=John
CUSTOMER_LAST_NAME=Doe
CUSTOMER_PHONE=2348012345678          # International format without +
CUSTOMER_DOB=01/15/1990                # MM/DD/YYYY format
CUSTOMER_HOUSE_NUMBER=12B
CUSTOMER_ADDRESS=123 Main Street
CUSTOMER_CITY=Lagos
CUSTOMER_STATE=Lagos
CUSTOMER_ZIP=100001
CUSTOMER_COUNTRY=NG                    # Two-letter country code
CUSTOMER_ID_TYPE=PASSPORT              # BVN, NIN, or PASSPORT
CUSTOMER_ID_NUMBER=A12345678
CUSTOMER_ID_IMAGE=https://example.com/id-photo.jpg    # URL to ID document
CUSTOMER_PHOTO=https://example.com/selfie.jpg         # URL to user photo
```

**Steps:**
1. Set up all environment variables in Replit Secrets
2. User sends `/quickregister` command to bot
3. Bot creates customer in StroWallet automatically
4. User can now create cards

### Method 2: Manual Registration (Recommended)
Manually create customer in StroWallet dashboard with proper KYC.

**Steps:**
1. Log into [StroWallet Dashboard](https://strowallet.com/dashboard)
2. Navigate to **Card Holders** → **Create New**
3. Fill in all customer information
4. Upload real KYC documents
5. Complete verification process
6. Use the registered email in your bot configuration

### Method 3: View Options
Send `/register` to see all available registration methods.

## StroWallet API Integration

### Customer Creation Endpoint
```
POST https://strowallet.com/api/bitvcard/create-user/
```

### Request Format (from StroWallet PDF docs)
```json
{
  "public_key": "YOUR_PUBLIC_KEY",
  "houseNumber": "12B",
  "firstName": "John",
  "lastName": "Doe",
  "idNumber": "AB123456",
  "customerEmail": "john@example.com",
  "phoneNumber": "2348012345678",
  "dateOfBirth": "01/15/1990",
  "idImage": "https://example.com/id.jpg",
  "userPhoto": "https://example.com/photo.jpg",
  "line1": "123 Main Street",
  "state": "Lagos",
  "zipCode": "100001",
  "city": "Ikeja",
  "country": "NG",
  "idType": "PASSPORT"
}
```

### Field Requirements
| Field | Format | Example | Required |
|-------|--------|---------|----------|
| firstName | String | John | ✅ |
| lastName | String | Doe | ✅ |
| customerEmail | Email | user@example.com | ✅ |
| phoneNumber | International (no +) | 2348012345678 | ✅ |
| dateOfBirth | MM/DD/YYYY | 01/15/1990 | ✅ |
| idNumber | String | A12345678 | ✅ |
| idType | BVN/NIN/PASSPORT | PASSPORT | ✅ |
| idImage | URL | https://... | ✅ |
| userPhoto | URL | https://... | ✅ |
| houseNumber | String | 12B | ✅ |
| line1 | String | 123 Main St | ✅ |
| city | String | Lagos | ✅ |
| state | String | Lagos | ✅ |
| zipCode | String | 100001 | ✅ |
| country | 2-letter code | NG | ✅ |
| public_key | API Key | pub_xxx | ✅ |

## Updated Card Creation Flow

### Before (Old Flow)
1. User clicks "Create Card"
2. Bot checks if customer exists
3. If not found: Show error and manual setup instructions
4. User must manually create customer in dashboard

### After (New Flow)
1. User clicks "Create Card"
2. Bot checks if customer exists
3. If not found: Show 3 registration options:
   - `/quickregister` - Automated setup
   - Manual dashboard setup
   - `/register` - View all options
4. User completes registration
5. User can create cards

## Bot Commands Summary

| Command | Description |
|---------|-------------|
| `/start` | Start the bot and show menu |
| `/register` | View customer registration options |
| `/quickregister` | Quick automated customer creation |
| `/create_card` or ➕ Create Card | Create a virtual card |
| `/cards` or 💳 My Cards | View all cards |
| `/userinfo` or 👤 User Info | View profile |
| `/wallet` or 💰 Wallet | Check balance |
| `/deposit_trc20` | Get USDT deposit address |
| `/invite` or 💸 Invite Friends | Share referral |
| `/support` or 🧑‍💻 Support | Contact support |

## Security & Compliance

### ⚠️ Important Security Notes
1. **Never use fake or placeholder KYC data** - This violates compliance regulations
2. **Real documents required** - ID images and photos must be genuine
3. **Secure storage** - KYC document URLs must be hosted securely (HTTPS)
4. **Data privacy** - Follow GDPR/data protection regulations
5. **Environment variables** - Never commit sensitive data to code

### Best Practices
- Use real KYC documents only
- Store document URLs in secure cloud storage (AWS S3, Cloudinary, etc.)
- Enable proper access controls on document URLs
- Regularly audit customer data
- Implement proper error handling
- Log all registration attempts for audit

## Troubleshooting

### Issue: "Missing KYC documents" error
**Solution:** Set `CUSTOMER_ID_IMAGE` and `CUSTOMER_PHOTO` environment variables with valid HTTPS URLs to real documents.

### Issue: Customer creation fails with 400 error
**Solution:** Verify all required fields are set correctly:
- Phone number is in international format without '+'
- Date of birth is in MM/DD/YYYY format
- Country code is 2 letters (e.g., NG, US, UK)
- ID type is one of: BVN, NIN, PASSPORT
- All URLs are accessible and valid HTTPS

### Issue: Authentication error during registration
**Solution:** Verify `STROW_PUBLIC_KEY` and `STROW_SECRET_KEY` are correctly set in Replit Secrets.

### Issue: Customer already exists
**Solution:** This is normal. The bot will skip creation and allow card creation directly.

## Testing the Integration

### 1. Test Customer Check
```bash
# Send to bot
/create_card
```
Expected: Shows registration options if customer doesn't exist

### 2. Test Registration Options
```bash
# Send to bot
/register
```
Expected: Displays all 3 registration methods

### 3. Test Quick Registration
```bash
# First, set up all CUSTOMER_* environment variables
# Then send to bot
/quickregister
```
Expected: Creates customer in StroWallet and confirms success

### 4. Test Card Creation After Registration
```bash
# Send to bot
/create_card
```
Expected: Successfully creates a virtual card

## Environment Variables Checklist

### Basic Configuration (Already Set)
- [x] BOT_TOKEN
- [x] STROW_PUBLIC_KEY
- [x] STROW_SECRET_KEY
- [x] STROWALLET_EMAIL

### Customer Registration (New - Optional)
- [ ] CUSTOMER_FIRST_NAME
- [ ] CUSTOMER_LAST_NAME
- [ ] CUSTOMER_PHONE
- [ ] CUSTOMER_DOB
- [ ] CUSTOMER_HOUSE_NUMBER
- [ ] CUSTOMER_ADDRESS
- [ ] CUSTOMER_CITY
- [ ] CUSTOMER_STATE
- [ ] CUSTOMER_ZIP
- [ ] CUSTOMER_COUNTRY
- [ ] CUSTOMER_ID_TYPE
- [ ] CUSTOMER_ID_NUMBER
- [ ] CUSTOMER_ID_IMAGE (URL)
- [ ] CUSTOMER_PHOTO (URL)

## Next Steps

1. **For Quick Registration:**
   - Set up all `CUSTOMER_*` environment variables in Replit Secrets
   - Upload real KYC documents to secure cloud storage
   - Get HTTPS URLs for ID image and photo
   - Test with `/quickregister`

2. **For Manual Registration:**
   - Log into StroWallet dashboard
   - Create customer with complete KYC
   - Use same email as `STROWALLET_EMAIL`

3. **Test the Flow:**
   - Try creating a card
   - Verify registration prompts appear
   - Complete registration
   - Create card successfully

## Support Resources
- **StroWallet API Docs:** [https://strowallet.readme.io](https://strowallet.readme.io)
- **StroWallet Dashboard:** [https://strowallet.com/dashboard](https://strowallet.com/dashboard)
- **Bot README:** See `README.md` for general setup
