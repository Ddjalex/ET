# Database Setup & Usage Guide

## Connection Module Location

**File**: `db.php` (project root)

This module provides a reusable PDO connection that:
- Reads credentials from environment secrets
- Uses UTF8MB4 charset
- Configures PDO for security (prepared statements, no emulation)
- Returns clear error messages

## Running the Connection Test

**File**: `test_db.php`

```bash
php test_db.php
```

**Expected Success Output**:
```
✅ Database connection successful!
   Connected to: neodiggi_card
   MySQL version: X.X.X-MariaDB
   Host: vesper.hostns.io
```

**Expected Failure Output**:
```
❌ Database connection failed!
   Error: [specific error message]
```

## Creating Tables (Prompt A)

**File**: `create_tables.php`

Creates 3 tables if they don't exist:
- `users` (telegram_id, email, phone, first_name, last_name, status)
- `wallets` (user_id, balance_usd, balance_etb, status)
- `deposit_payments` (full payment tracking)

```bash
php create_tables.php
```

**Safe to Re-run**: Uses `CREATE TABLE IF NOT EXISTS`, so running multiple times won't cause errors.

**Expected Output**:
```
=== Creating Tables ===

✅ Table 'users' created/verified
✅ Table 'wallets' created/verified
✅ Table 'deposit_payments' created/verified

=== Verifying Tables ===

Tables in database:
  - users
  - wallets
  - deposit_payments

✅ All tables created successfully!
```

## Seeding Sample Data (Prompt B)

**File**: `seed_data.php`

Inserts:
- 1 sample user (telegram_id: 123456789)
- 1 sample deposit payment linked to that user

```bash
php seed_data.php
```

**Expected Output**:
```
=== Seeding Sample Data ===

✅ User inserted (ID: 1)
✅ Deposit payment inserted (ID: 1)

✅ Sample data seeded successfully!
```

## Checking Data (Prompt B)

**File**: `check_data.php`

Queries and displays:
- User by telegram_id (123456789)
- Last 5 deposit_payments

```bash
php check_data.php
```

**Expected Output** (JSON):
```json
{
  "user": {
    "id": 1,
    "telegram_id": 123456789,
    "email": "test@example.com",
    "phone": "+251912345678",
    "first_name": "Test",
    "last_name": "User",
    "status": "active",
    "created_at": "2025-10-30 12:00:00",
    "updated_at": "2025-10-30 12:00:00"
  },
  "last_5_deposits": [
    {
      "id": 1,
      "user_id": 1,
      "telegram_id": 123456789,
      "amount_usd": 100.00,
      "amount_etb": 13500.00,
      "exchange_rate": 135.00,
      "deposit_fee_etb": 0.00,
      "total_etb": 13500.00,
      "payment_method": "telebirr",
      "validation_status": "pending",
      "status": "pending"
    }
  ],
  "total_deposits": 1
}
```

## API Endpoints (Prompt C)

### 1. Register User

**File**: `routes/register_user.php`

**Endpoint**: `POST /routes/register_user.php`

**Required Fields**:
- telegram_id
- email
- phone
- first_name
- last_name

**Example**:
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

### 2. Create Deposit

**File**: `routes/create_deposit.php`

**Endpoint**: `POST /routes/create_deposit.php`

**Required Fields**:
- user_id
- telegram_id
- amount_usd
- amount_etb
- total_etb
- payment_method (telebirr, m-pesa, cbe, bank_transfer)

**Optional Fields**:
- payment_phone
- transaction_number
- exchange_rate (default: 135.00)
- deposit_fee_etb (default: 0)
- notes

**Example**:
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
    "transaction_number": "TXN123456"
  }'
```

## File Structure

```
project/
├── db.php                      # Connection module
├── test_db.php                 # Connection test
├── debug_connection.php        # Detailed diagnostics
├── create_tables.php           # Table creation (Prompt A)
├── seed_data.php               # Data seeding (Prompt B)
├── check_data.php              # Data verification (Prompt B)
├── models/
│   ├── UserModel.php          # User operations
│   └── DepositPaymentsModel.php
├── routes/
│   ├── register_user.php      # User registration endpoint (Prompt C)
│   └── create_deposit.php     # Deposit creation endpoint (Prompt C)
├── README_DB.md               # Detailed API documentation
├── README_SETUP.md            # This file
└── DEPLOY_CHECKLIST.md        # Production deployment guide (Prompt D)
```

## Troubleshooting

If `test_db.php` fails, run the diagnostic:
```bash
php debug_connection.php
```

This provides detailed error information and specific solutions.

See `DEPLOY_CHECKLIST.md` for comprehensive troubleshooting guide.
