# PHP Payment Verification Bundle (Telebirr + CBE)

This is a **pure PHP** port of your Node.js payment verification flow. It mirrors:
- Validate transaction number via external microservice (`VALIDATION_API_BASE_URL`).
- Process deposits (credit balance + record transaction).
- Create withdrawal requests (pending approval).

## Folder Structure
- `public/index.php` — front controller (set your domain to this folder on cPanel).
- `routes/payments.php` — minimal REST endpoints.
- `services/PaymentService.php` — core payment logic.
- `config/env.php` & `config/db.php` — environment + PDO.
- `sql/schema.sql` — minimal MySQL schema.
- `.env.example` — copy to `.env` and fill values.
- `.htaccess` — routes everything to `public/index.php`.

## Endpoints
- `GET  /api/payments/validate?provider=telebirr&txn=XXXX`
- `GET  /api/payments/validate?provider=cbe&txn=XXXX`
- `POST /api/payments/deposit` (JSON)
  ```json
  { "userId":1, "amount":100, "method":"telebirr", "transactionNumber":"TXN123" }
  ```
- `POST /api/payments/withdraw` (JSON)
  ```json
  { "userId":1, "amount":50, "method":"cbe", "account":"0911...", "name":"John Doe" }
  ```

## How Verification Works
1. Client (bot/web) sends `transactionNumber` and `method` (telebirr|cbe).
2. PHP calls your **validation microservice**:
   - `POST {VALIDATION_API_BASE_URL}/api/telebirr/:transactionNumber`
   - `POST {VALIDATION_API_BASE_URL}/api/cbe/:transactionNumber`
   - Falls back to GET on error.
3. If `ok: true` in JSON response, we **credit the user** and insert a `transactions` row.
4. Withdrawals are recorded as `pending` for admin to fulfill.

## Deploy on cPanel (Quick)
1. Upload all files to your domain root.
2. Ensure `.htaccess` is present.
3. Create DB and import `sql/schema.sql`.
4. Copy `.env.example` → `.env` and edit DB + `VALIDATION_API_BASE_URL`.
5. Test:
   - `https://yourdomain.com/api/payments/validate?provider=telebirr&txn=TEST`

## Security
- cURL uses TLS verification **enabled** by default. Only disable for internal HTTP services.
- Rate-limit the endpoints or protect with auth for admin-only routes.

## Replit AI Agent Instruction (short)
"""
Project goal: Use PHP `PaymentService.php` to validate Telebirr/CBE transactions via external microservice.
Tasks:
- Load env via `config/env.php`, connect PDO via `config/db.php`.
- Implement deposit/withdraw endpoints in `routes/payments.php` (already included).
- Integrate with Telegram bot PHP code: call `/api/payments/deposit` after collecting txn number.
- Ensure `VALIDATION_API_BASE_URL` is set correctly.
"""
