# Payment Verification System - Complete Guide

## Overview

Your Telegram bot now has **TWO payment verification methods**:

### Method 1: Transaction ID Validation (Old Method)
- User provides transaction ID
- Bot validates via external API microservice
- Requires external validation service running

### Method 2: Receipt URL Verification (NEW - Advanced)
- User sends receipt URL directly (e.g., `https://transactioninfo.ethiotelecom.et/receipt/ABC123`)
- Bot automatically fetches and parses the receipt page
- Extracts transaction ID, amount, date, and other details
- Verifies date/time (can require receipt to be from today)
- **No external API needed** - parses receipt directly

---

## How the Receipt URL Verification Works

### Step-by-Step Process

1. **User shares a payment receipt URL**
   - Telebirr: `https://transactioninfo.ethiotelecom.et/receipt/XYZ123`
   - CBE: `https://apps.cbe.com.et:100/?id=FT25302BLC7739256208`

2. **Bot fetches the receipt page**
   - Uses secure HTTPS with TLS verification
   - Follows redirects (max 5)
   - Only allows whitelisted domains for security

3. **System parses the HTML/JSON**
   - TelebirrParser: Extracts data from Telebirr receipts
   - CbeParser: Extracts data from CBE receipts
   - Uses regex patterns to find transaction details

4. **Extracts key information:**
   - Transaction ID
   - Amount (in ETB)
   - Currency
   - Date/Time
   - Sender/Receiver details (if available)

5. **Verifies date/time**
   - Can require receipt to be from today
   - Or verify within a custom date range
   - Timezone-aware (Africa/Addis_Ababa by default)

6. **Validates amount**
   - Compares receipt amount vs expected deposit amount
   - Allows 1 ETB tolerance for rounding
   - Rejects if mismatch detected

7. **Updates database**
   - Stores verification result
   - Prevents duplicate transactions
   - Records receipt URL for audit trail

---

## Using the New System in Your Bot

### Option A: Standalone Receipt Verification
```php
// Just verify a receipt URL without processing deposit
$result = $paymentService->verifyByReceiptUrl(
    $receiptUrl, 
    mustBeToday: true  // Require receipt to be from today
);

if ($result['success']) {
    $transactionId = $result['transaction_id'];
    $amount = $result['amount'];
    $currency = $result['currency'];
    // Use the verified data...
}
```

### Option B: Full Deposit Processing (Recommended)
```php
// Create payment first
$payment = $paymentService->createDepositPayment(
    userId: $userId,
    telegramId: $telegramId,
    amountUSD: 10.00,
    paymentMethod: 'telebirr',
    exchangeRate: 135.00,
    depositFeeETB: 500.00
);

$paymentId = $payment['payment_id'];
$totalETB = $payment['total_etb']; // User must pay this amount

// When user sends receipt URL
$result = $paymentService->processDepositByReceiptUrl(
    paymentId: $paymentId,
    receiptUrl: $userProvidedUrl,
    mustBeToday: true
);

if ($result['success']) {
    // Receipt verified! Now credit the user's StroWallet
    $depositResult = $paymentService->processVerifiedDeposit($paymentId);
    
    if ($depositResult['success']) {
        // Deposit completed successfully
        $amountUSD = $depositResult['amount_usd'];
        // Notify user...
    }
}
```

---

## Supported Payment Providers

### Telebirr
- **Domain:** `transactioninfo.ethiotelecom.et`
- **Receipt URL Format:** `https://transactioninfo.ethiotelecom.et/receipt/[TRANSACTION_ID]`
- **Extracts:** Transaction ID, Amount, Date, Sender/Receiver info

### CBE (Commercial Bank of Ethiopia)
- **Domains:** 
  - `apps.cbe.com.et`
  - `www.combanketh.et`
- **Receipt URL Format:** `https://apps.cbe.com.et:100/?id=[TRANSACTION_ID]`
- **Extracts:** Transaction ID, Amount, Date, Account numbers

### M-Pesa
- Currently uses API validation method only
- Can be extended to support receipt URL if M-Pesa provides public receipt URLs

---

## Date Verification Options

### Require Receipt from Today
```php
$result = $paymentService->verifyByReceiptUrl($url, mustBeToday: true);
```
- Verifies receipt timestamp is today in local timezone (Africa/Addis_Ababa)
- Rejects receipts from yesterday or tomorrow

### Custom Date Range
```php
// You can extend the method to support custom date ranges
// See PaymentServiceEnhanced::evaluateDateMatch() for implementation
```

---

## Security Features

### Domain Whitelist
Only these domains are allowed (configurable via `ALLOWED_DOMAINS` env var):
- `transactioninfo.ethiotelecom.et`
- `apps.cbe.com.et`
- `www.combanketh.et`

Any other domain will be rejected immediately.

### TLS Verification
- All HTTPS requests enforce TLS certificate verification
- Prevents man-in-the-middle attacks
- `CURLOPT_SSL_VERIFYPEER` and `CURLOPT_SSL_VERIFYHOST` always enabled

### Duplicate Prevention (ENHANCED)
**Application-Level Protection:**
- Checks if transaction ID already exists in any non-rejected/non-cancelled payment
- Prevents same receipt from being used multiple times
- Returns clear error message if duplicate detected

**Database-Level Protection:**
- Unique partial index enforces transaction_number uniqueness
- Index: `idx_deposit_payments_unique_txn`
- Only applies to verified/completed transactions (excludes rejected/cancelled)
- Provides additional layer of protection even if application logic fails

This two-layer approach ensures maximum security against receipt reuse fraud.

### Amount Validation
- Compares parsed amount vs expected amount
- Allows 1 ETB tolerance for floating-point rounding
- Rejects if amounts don't match

---

## File Structure

```
public_html/bot/
├── PaymentService.php              # Original service (API validation)
├── PaymentServiceEnhanced.php      # NEW: Supports both methods
├── test_receipt_verification.php   # Test/example script
└── ReceiptVerification/
    ├── ReceiptVerifier.php         # Main verification logic
    └── Parsers/
        ├── TelebirrParser.php      # Parses Telebirr receipts
        └── CbeParser.php           # Parses CBE receipts
```

---

## Database Changes

Added `receipt_url` column to `deposit_payments` table:
```sql
ALTER TABLE deposit_payments ADD COLUMN receipt_url TEXT;
```

This stores the original receipt URL for audit and reference.

---

## Integration with Your Bot

### Update deposit_handler_v2.php

You can update your deposit handler to support receipt URLs:

```php
// Load the enhanced service
require_once __DIR__ . '/PaymentServiceEnhanced.php';
$paymentService = new PaymentServiceEnhanced($pdo, $validationApiBase, $botToken);

// In your message handler
if (str_contains($text, 'ethiotelecom.et') || str_contains($text, 'cbe.com.et')) {
    // User sent a receipt URL
    $pendingPayment = $paymentService->getPendingPayment($telegramId);
    
    if (!$pendingPayment) {
        sendMessage($chatId, "No pending payment found. Start a deposit first.");
        exit;
    }
    
    $result = $paymentService->processDepositByReceiptUrl(
        $pendingPayment['id'],
        $text,  // The receipt URL
        mustBeToday: true
    );
    
    if ($result['success']) {
        // Process the verified deposit
        $depositResult = $paymentService->processVerifiedDeposit($pendingPayment['id']);
        
        if ($depositResult['success']) {
            sendMessage($chatId, "✅ Payment verified! Your deposit of $" . $depositResult['amount_usd'] . " has been processed.");
        }
    } else {
        sendMessage($chatId, "❌ Verification failed: " . $result['message']);
    }
}
```

---

## Advantages Over API Validation

### Receipt URL Method:
✅ **No external API dependency** - Works standalone  
✅ **Direct verification** - Parses receipt from source  
✅ **Date/time validation** - Can require today's receipts  
✅ **More data** - Extracts full transaction details  
✅ **Audit trail** - Stores original receipt URL  
✅ **Faster** - No external API roundtrip  

### API Validation Method:
✅ **Good for transaction IDs** - When URL not available  
✅ **Standardized response** - Consistent API format  

---

## Testing

Run the test script to see examples:
```bash
php public_html/bot/test_receipt_verification.php
```

This demonstrates:
- How to verify Telebirr receipts
- How to verify CBE receipts
- Expected success/failure responses
- How to integrate in your bot

---

## Environment Variables

Optional configurations:

```bash
# Allowed domains (comma-separated)
ALLOWED_DOMAINS="transactioninfo.ethiotelecom.et,apps.cbe.com.et,www.combanketh.et"

# Application timezone for date verification
APP_TZ="Africa/Addis_Ababa"
```

---

## Common Use Cases

### Use Case 1: User Sends Receipt URL
```
User: https://transactioninfo.ethiotelecom.et/receipt/ABC123
Bot: Verifying receipt...
Bot: ✅ Verified! Transaction ID: ABC123, Amount: 2000 ETB
     Processing your deposit of $10 USD...
Bot: ✅ Deposit complete! Your balance has been updated.
```

### Use Case 2: Amount Mismatch
```
User: https://apps.cbe.com.et:100/?id=FT123
Bot: Verifying receipt...
Bot: ❌ Amount mismatch: Expected 1850 ETB, but receipt shows 1500 ETB
     Please send the correct receipt or contact support.
```

### Use Case 3: Date Verification Failed
```
User: https://transactioninfo.ethiotelecom.et/receipt/OLD123
Bot: Verifying receipt...
Bot: ❌ Receipt is not from today. Please send a fresh receipt from today's payment.
```

---

## Troubleshooting

### "Domain not allowed"
- The receipt URL domain is not whitelisted
- Add to `ALLOWED_DOMAINS` environment variable

### "Could not parse receipt HTML"
- Receipt page format may have changed
- Check TelebirrParser or CbeParser regex patterns
- May need to update parsing logic

### "Fetch failed"
- Network error or receipt URL is invalid
- Check if receipt URL is accessible
- Verify TLS certificates are valid

### "Duplicate transaction"
- Transaction ID already exists in database
- User trying to use same receipt twice
- Check `deposit_payments` table for existing record

---

## Next Steps

1. **Update your deposit flow** to accept receipt URLs
2. **Test with real receipts** from Telebirr and CBE
3. **Adjust parsers if needed** based on actual receipt formats
4. **Monitor verification success rate** to improve parsing
5. **Add user-friendly messages** for different verification outcomes

---

## Support

For questions or issues with the payment verification system:
1. Check this documentation first
2. Review the test script for examples
3. Check logs in `deposit_payments` table
4. Verify environment variables are set correctly

---

**Last Updated:** October 29, 2025  
**Version:** 1.0.1 (Receipt URL Verification with Enhanced Duplicate Prevention)

## Version History

### v1.0.1 (2025-10-29)
- **SECURITY FIX:** Enhanced duplicate prevention to check all non-rejected/cancelled transactions
- Added database unique partial index for transaction_number
- Fixed potential receipt reuse vulnerability

### v1.0.0 (2025-10-29)
- Initial release with receipt URL verification
- Support for Telebirr and CBE receipts
- Date/time matching capabilities
