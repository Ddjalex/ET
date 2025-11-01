# Database Configuration

## ✅ Active Database: PostgreSQL (Replit)

This project uses **Replit's built-in PostgreSQL database** for all data storage.

### Connection Details
- **Connection File:** `db.php` (PostgreSQL connection)
- **Environment Variable:** `DATABASE_URL` (automatically provided by Replit)
- **Database Type:** PostgreSQL 16.x
- **Tables:** 14 tables (see schema.sql)

### Why PostgreSQL?
- ✅ Built-in Replit support with automatic provisioning
- ✅ Supports data rollback and snapshots
- ✅ No remote connection restrictions
- ✅ Persistent across sessions (same Replit account)
- ✅ Included in Replit subscription at no extra cost

### Customer Data Backup
All customer data is backed up to `schema.sql`:
```bash
# Backup current database
bash scripts/backup_database.sh

# Restore from backup
bash scripts/restore_database.sh
```

See `replit.md` for complete data persistence documentation.

---

## 🔧 MySQL Integration (Reference Only)

The MySQL/MariaDB integration code is preserved for reference:
- **Connection File:** `db_mysql.php` (not used, reference only)
- **Target Database:** cPanel hosted MySQL
- **Status:** Not in use due to remote access restrictions

### Why Not MySQL?
- ❌ Remote MySQL connections blocked by hosting provider
- ❌ Cannot connect from Replit to cPanel MySQL
- ❌ Would require deploying code to cPanel (defeating Replit's purpose)

### MySQL Files (Reference)
- `db_mysql.php` - MySQL connection module
- `attached_assets/payment-verification/` - Complete MySQL integration code
- These files are kept for future cPanel deployment if needed

---

## 📌 Environment Secrets

### Active Secrets (Required)
```
DATABASE_URL         # Auto-provided by Replit PostgreSQL
STROWALLET_API_KEY   # StroWallet API access
STROWALLET_WEBHOOK_SECRET  # StroWallet webhook verification
```

### Unused Secrets (Can be Removed)
```
DB_HOST       # Not used (MySQL related)
DB_PORT       # Not used (MySQL related)
DB_NAME       # Not used (MySQL related)
DB_USER       # Not used (MySQL related)
DB_PASSWORD   # Not used (MySQL related)
DB_SSL        # Not used (MySQL related)
```

You can safely remove the MySQL-related secrets from your Replit Secrets panel.

---

## 🚀 Database Commands

### View Database
```bash
psql $DATABASE_URL
```

### Backup Database
```bash
bash scripts/backup_database.sh
```

### Restore Database
```bash
bash scripts/restore_database.sh
```

### Check Tables
```bash
psql $DATABASE_URL -c "\dt"
```

### View Customer Data
```bash
psql $DATABASE_URL -c "SELECT telegram_id, first_name, last_name, kyc_status FROM users;"
```
