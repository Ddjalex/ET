# Remote MySQL/MariaDB Database Integration

## Required Secrets

Add these secrets in your Replit environment:

| Secret | Description | Example |
|--------|-------------|---------|
| `DB_HOST` | cPanel database host | `mysql.yourhost.com` |
| `DB_PORT` | Database port | `3306` |
| `DB_NAME` | Database name | `yourdatabase` |
| `DB_USER` | Database username | `youruser` |
| `DB_PASSWORD` | Database password | `yourpassword` |
| `DB_SSL` | Use SSL connection | `false` or `true` |

## Connection Test

Run the connection test script to verify database connectivity:

```bash
php test_db.php
```

**Expected output:**
```
✅ Database connection successful!
   Connected to: yourdatabase
   MySQL version: 10.x.x-MariaDB
   Host: mysql.yourhost.com
```

## API Endpoints

### 1. Register User

**Endpoint:** `POST /routes/register_user.php`

**Accepts:** JSON or form-data

**Required fields:**
- `telegram_id` (integer) - Telegram user ID
- `email` (string) - User email address
- `phone` (string) - Phone number
- `first_name` (string) - First name
- `last_name` (string) - Last name

**Example request (JSON):**
```bash
curl -X POST http://your-repl/routes/register_user.php \
  -H "Content-Type: application/json" \
  -d '{
    "telegram_id": 123456789,
    "email": "user@example.com",
    "phone": "+251912345678",
    "first_name": "John",
    "last_name": "Doe"
  }'
```

**Example request (form-data):**
```bash
curl -X POST http://your-repl/routes/register_user.php \
  -F "telegram_id=123456789" \
  -F "email=user@example.com" \
  -F "phone=+251912345678" \
  -F "first_name=John" \
  -F "last_name=Doe"
```

**Success response (new user):**
```json
{
  "ok": true,
  "already_existed": false,
  "user_id": 1,
  "user": {
    "user_id": 1,
    "telegram_id": 123456789,
    "email": "user@example.com",
    "phone": "+251912345678",
    "first_name": "John",
    "last_name": "Doe",
    "status": "active"
  }
}
```

**Success response (existing user):**
```json
{
  "ok": true,
  "already_existed": true,
  "user": {
    "id": 1,
    "telegram_id": 123456789,
    "email": "user@example.com",
    "phone": "+251912345678",
    "first_name": "John",
    "last_name": "Doe",
    "status": "active",
    "created_at": "2025-10-30 12:00:00",
    "updated_at": "2025-10-30 12:00:00"
  }
}
```

**Error response:**
```json
{
  "ok": false,
  "error": "Missing field: email"
}
```

---

### 2. Create Deposit

**Endpoint:** `POST /routes/create_deposit.php`

**Accepts:** JSON or form-data

**Required fields:**
- `user_id` (integer) - User ID from users table
- `telegram_id` (integer) - Telegram user ID
- `amount_usd` (decimal) - Amount in USD
- `amount_etb` (decimal) - Amount in ETB
- `total_etb` (decimal) - Total amount in ETB
- `payment_method` (string) - One of: `telebirr`, `m-pesa`, `cbe`, `bank_transfer`

**Optional fields:**
- `payment_phone` (string) - Payment phone number
- `transaction_number` (string) - Transaction/reference number
- `exchange_rate` (decimal) - Exchange rate (default: 135.00)
- `deposit_fee_etb` (decimal) - Deposit fee in ETB (default: 0)
- `notes` (text) - Additional notes

**Example request (JSON):**
```bash
curl -X POST http://your-repl/routes/create_deposit.php \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "telegram_id": 123456789,
    "amount_usd": 100.00,
    "amount_etb": 13500.00,
    "total_etb": 13500.00,
    "payment_method": "telebirr",
    "payment_phone": "+251912345678",
    "transaction_number": "TXN123456789",
    "exchange_rate": 135.00,
    "deposit_fee_etb": 0,
    "notes": "First deposit"
  }'
```

**Example request (form-data):**
```bash
curl -X POST http://your-repl/routes/create_deposit.php \
  -F "user_id=1" \
  -F "telegram_id=123456789" \
  -F "amount_usd=100.00" \
  -F "amount_etb=13500.00" \
  -F "total_etb=13500.00" \
  -F "payment_method=telebirr" \
  -F "payment_phone=+251912345678" \
  -F "transaction_number=TXN123456789"
```

**Success response:**
```json
{
  "ok": true,
  "deposit_payment_id": 1,
  "deposit": {
    "deposit_payment_id": 1,
    "user_id": 1,
    "telegram_id": 123456789,
    "amount_usd": 100.00,
    "amount_etb": 13500.00,
    "exchange_rate": 135.00,
    "deposit_fee_etb": 0,
    "total_etb": 13500.00,
    "payment_method": "telebirr",
    "validation_status": "pending",
    "status": "pending"
  }
}
```

**Error response:**
```json
{
  "ok": false,
  "error": "Missing field: payment_method"
}
```

---

## Database Schema

### users table

Expected columns:
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### deposit_payments table

Expected columns:
```sql
CREATE TABLE deposit_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    telegram_id BIGINT NOT NULL,
    amount_usd DECIMAL(10,2) NOT NULL,
    amount_etb DECIMAL(10,2) NOT NULL,
    exchange_rate DECIMAL(10,2) DEFAULT 135.00,
    deposit_fee_etb DECIMAL(10,2) DEFAULT 0,
    total_etb DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_phone VARCHAR(100),
    transaction_number VARCHAR(100),
    validation_status VARCHAR(50) DEFAULT 'pending',
    status VARCHAR(50) DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Troubleshooting

### Access Denied Error

**Error:** `Database access denied. Check DB_USER and DB_PASSWORD.`

**Solutions:**
1. Verify `DB_USER` and `DB_PASSWORD` secrets are correct
2. Check user has permissions on the database in cPanel
3. Ensure user is allowed to connect from remote hosts (check remote MySQL in cPanel)

---

### Cannot Connect Error

**Error:** `Cannot connect to database. Check DB_HOST and DB_PORT.`

**Solutions:**
1. Verify `DB_HOST` is correct (usually `localhost` or `mysql.yourhost.com`)
2. Check `DB_PORT` is correct (default: `3306`)
3. Ensure remote MySQL is enabled in cPanel
4. Check firewall rules allow connections from Replit

---

### Unknown Column Error

**Error:** `Missing column in users table: kyc_status. Execute: ALTER TABLE users ADD COLUMN kyc_status VARCHAR(255);`

**Solutions:**
1. The error message provides the exact SQL command to fix it
2. Run the suggested `ALTER TABLE` command in cPanel phpMyAdmin:
   ```sql
   ALTER TABLE users ADD COLUMN kyc_status VARCHAR(255);
   ```
3. Or create the table with all required columns (see Database Schema above)

---

### Table Doesn't Exist Error

**Error:** `Table 'users' does not exist in the database.`

**Solutions:**
1. Create the table using the schema provided above
2. Run the CREATE TABLE commands in cPanel phpMyAdmin
3. Verify `DB_NAME` secret points to the correct database

---

### Invalid Payment Method Error

**Error:** `Invalid payment_method. Must be one of: telebirr, m-pesa, cbe, bank_transfer`

**Solutions:**
1. Use one of the valid payment methods listed
2. Check for typos (case-sensitive, use lowercase)
3. Valid values: `telebirr`, `m-pesa`, `cbe`, `bank_transfer`

---

## File Structure

```
project/
├── db.php                          # Database connection module
├── test_db.php                     # Connection test script
├── models/
│   ├── UserModel.php              # User model
│   └── DepositPaymentsModel.php   # Deposit payments model
├── routes/
│   ├── register_user.php          # User registration endpoint
│   └── create_deposit.php         # Create deposit endpoint
└── README_DB.md                    # This file
```

## Security Notes

- All queries use prepared statements to prevent SQL injection
- Error messages are sanitized (no stack traces sent to client)
- Database credentials are loaded from environment secrets
- Connection uses UTF8MB4 charset for full Unicode support
- InnoDB engine ensures ACID compliance and transaction support
