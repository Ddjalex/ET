# PHP Payment Verification Bundle (Telebirr + CBE) — URL Receipt Parser

This is a **pure PHP** payment verification mini-API that can **verify Telebirr/CBE receipts by URL**, parse the page (HTML or JSON), extract transaction details, and (optionally) record a **deposit** for a user.

## What’s new in this version (v1.1.0)
- `ReceiptVerifier` that fetches a **receipt URL** and parses details via robust regex + per-source parsers.
- New endpoints:
  - `POST /api/payments/verify-receipt` — verify by URL, no DB writes.
  - `POST /api/payments/deposit-by-receipt` — verify by URL **and** record a completed deposit.
- Domain whitelist via `ALLOWED_DOMAINS` env var.
- Clean PDO DB layer and `.env` loader included.

## Endpoints
- `GET  /api/health`
- `POST /api/payments/verify-receipt`
  ```json
  { "url": "https://transactioninfo.ethiotelecom.et/receipt/CJT5R0Z9OF" }
  ```
- `POST /api/payments/deposit-by-receipt`
  ```json
  { "userId": 1, "url": "https://apps.cbe.com.et:100/?id=FT25302BLC7739256208" }
  ```
- `POST /api/payments/withdraw`
  ```json
  { "userId": 1, "amount": 500, "method": "telebirr", "account": "2519xxxxxx", "name": "Almesagad" }
  ```

## Setup (cPanel/Replit)
1. Upload this folder; point your domain/subdomain **document root** to `/public`.
2. Copy `.env.example` → `.env`, fill **DB_*** and **ALLOWED_DOMAINS**.
3. Import `sql/schema.sql` in phpMyAdmin (ensures `users`, `payments`, `withdrawals`).
4. Ensure `.htaccess` at project root routes everything to `/public/index.php`.

## Security
- Only whitelisted domains are fetched.
- TLS verification is **enabled** (cURL).
- Response HTML is **truncated** to 50KB in API output.
- Duplicate `external_txn_id` is rejected.

## Replit AI Agent — short description to paste
```
Convert/extend my PHP project to add a receipt URL verification feature for Telebirr and CBE:
- Use /services/ReceiptVerifier.php with per-source parsers in /services/Parsers (TelebirrParser.php, CbeParser.php).
- Add endpoints in /routes/payments.php:
  - POST /api/payments/verify-receipt { url }
  - POST /api/payments/deposit-by-receipt { userId, url } (verifies then inserts into payments and credits users.balance)
- Load env via config/env.php; DB via config/db.php (PDO, utf8mb4).
- Whitelist domains from ALLOWED_DOMAINS in .env.
- Keep all existing routes working.
```


## Date/Time Matching (optional)
You can require the receipt's parsed date/time to **match** a rule by passing `match` in the request body.

- **Same day (local)** — require today in `APP_TZ` (default Africa/Addis_Ababa):
```json
POST /api/payments/verify-receipt
{ "url": "...", "match": { "mustBeToday": true } }
```
- **Between window (UTC)** — require receipt time to be between two ISO timestamps:
```json
POST /api/payments/deposit-by-receipt
{
  "userId": 1,
  "url": "...",
  "match": {
    "dateBetween": {
      "from": "2025-10-28T00:00:00Z",
      "to":   "2025-10-29T23:59:59Z"
    }
  }
}
```
The response includes:
```json
"date_match": {
  "ok": true,
  "reason": "No date matching rule applied",
  "parsed_date_iso": "2025-10-29T09:34:56Z",
  "parsed_date_local": "2025-10-29T12:34:56+03:00",
  "today_local": "2025-10-29",
  "receipt_local_date": "2025-10-29",
  "window_from": "...",
  "window_to": "..."
}
```
Configure timezone with `APP_TZ` in `.env`.
