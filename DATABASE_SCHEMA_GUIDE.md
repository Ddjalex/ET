# Database Schema Management Guide

## Overview

The `schema.sql` file is the master database schema for the Telegram Crypto Card Bot. It contains the complete, up-to-date structure of all 14 database tables and is designed to preserve your data through updates.

## Database Structure

### Total Tables: 14

#### 1. **User Management**
- `users` - Main user records linked to Telegram accounts
- `user_registrations` - Staging table for multi-step registration flow

#### 2. **Wallet System**
- `wallets` - User balances in USD and ETB
- `wallet_transactions` - All money movements and transaction history

#### 3. **Card Management**
- `cards` - StroWallet virtual cards
- `card_transactions` - All card activity (purchases, topups, fees)

#### 4. **Deposit System**
- `deposits` - Legacy deposit requests (retained for compatibility)
- `deposit_payments` - **Active deposit system with manual admin verification**

#### 5. **Broadcasting**
- `broadcasts` - Admin-to-user communication
- `broadcast_logs` - Detailed send history and error tracking
- `giveaway_entries` - Giveaway participation tracking

#### 6. **Administration**
- `admin_users` - Admin panel user accounts
- `admin_actions` - Complete audit log of admin activities
- `settings` - System configuration (exchange rates, fees, accounts)

## Usage Instructions

### Initial Setup (New Database)

```bash
# Connect to your PostgreSQL database and run the schema
psql -h <hostname> -U <username> -d <database_name> -f schema.sql
```

For Replit environment:
```bash
psql -h helium -U postgres -d heliumdb -f schema.sql
```

### Updating Existing Database

**⚠️ IMPORTANT: The schema.sql file preserves existing data**

All `CREATE TABLE` statements use `IF NOT EXISTS`, so running this file on an existing database will:
- ✅ Create missing tables
- ✅ Preserve existing data
- ✅ Update functions and triggers
- ✅ Insert missing default settings (using `ON CONFLICT DO NOTHING`)

```bash
# Safe to run on existing database - will not drop or lose data
psql -h helium -U postgres -d heliumdb -f schema.sql
```

### Verify Schema Version

After running the schema, check the version:

```sql
SELECT value->>'version' as version, 
       value->>'last_updated' as last_updated,
       value->>'description' as description
FROM settings 
WHERE key = 'schema_version';
```

## Schema Features

### 1. Automatic Timestamps
All tables have triggers that automatically update the `updated_at` timestamp on any UPDATE operation.

### 2. Data Integrity
- Foreign key constraints ensure referential integrity
- Check constraints validate data values
- Unique constraints prevent duplicates
- Cascading deletes maintain consistency

### 3. Performance Indexes
Indexes on frequently queried columns:
- Telegram IDs
- User emails
- Status fields
- Transaction types
- Created/updated timestamps

### 4. Default Settings
The schema includes essential default settings:
- Exchange rates (USD to ETB)
- Fee structures (card creation, deposits)
- System limits and configurations
- Default admin account (username: `admin`, password: `admin123`)

**⚠️ SECURITY: Change the default admin password immediately in production!**

## Manual Deposit Verification Flow

The current schema implements **manual admin review** for all deposits:

1. User submits deposit → Stored in `deposit_payments` table with `status='pending'`
2. Screenshot and transaction ID saved
3. Admin reviews in admin panel
4. Admin approves → Credits user's StroWallet account
5. Transaction recorded in `wallet_transactions`

## Backup Recommendations

### Before Major Updates
```bash
# Backup entire database
pg_dump -h helium -U postgres -d heliumdb > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup schema only
pg_dump -h helium -U postgres -d heliumdb --schema-only > schema_backup.sql

# Backup data only
pg_dump -h helium -U postgres -d heliumdb --data-only > data_backup.sql
```

### Restore from Backup
```bash
# Full restore
psql -h helium -U postgres -d heliumdb < backup_20251030_123456.sql
```

## Schema Updates Workflow

When you provide updates:

1. **I'll update `schema.sql`** with new tables/columns/constraints
2. **Version number incremented** in the `schema_version` setting
3. **You run the updated schema** - existing data preserved
4. **Verify with version check** - confirm update applied

Example update process:
```sql
-- Check current version
SELECT value FROM settings WHERE key = 'schema_version';

-- Run updated schema
\i schema.sql

-- Verify new version
SELECT value FROM settings WHERE key = 'schema_version';
```

## Migration from Old System

If you have data in a different structure:

```sql
-- Example: Migrate old deposit data to new table
INSERT INTO deposit_payments (
    user_id, telegram_id, amount_usd, amount_etb,
    exchange_rate, total_etb, payment_method, status
)
SELECT 
    user_id, telegram_id, usd_amount, etb_amount_quote,
    exchange_rate, total_etb_to_pay, 'telebirr', status
FROM old_deposits_table
ON CONFLICT DO NOTHING;
```

## Troubleshooting

### "Relation already exists" errors
These are safe to ignore - the schema uses `IF NOT EXISTS` clauses.

### Foreign key constraint violations
Ensure parent records exist before inserting child records:
```sql
-- Example: Ensure user exists before creating wallet
INSERT INTO users (...) VALUES (...) RETURNING id;
INSERT INTO wallets (user_id, ...) VALUES (<user_id>, ...);
```

### Index creation failures
Drop and recreate if needed:
```sql
DROP INDEX IF EXISTS idx_name;
CREATE INDEX idx_name ON table_name(column_name);
```

## Contact & Support

For schema-related issues or questions:
1. Check this guide first
2. Review the inline comments in `schema.sql`
3. Verify data with SQL queries
4. Create a backup before making changes

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.0.0 | 2025-10-30 | Initial comprehensive schema, manual deposit verification |

---

**Last Updated**: October 30, 2025
