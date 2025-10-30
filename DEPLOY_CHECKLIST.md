# Deploy/Move Checklist (Prompt D)

## Export Data from Replit

### Full Database Dump
```bash
# Get database credentials
DB_HOST=$(printenv DB_HOST)
DB_PORT=$(printenv DB_PORT)
DB_NAME=$(printenv DB_NAME)
DB_USER=$(printenv DB_USER)
DB_PASSWORD=$(printenv DB_PASSWORD)

# Export all tables
mysqldump -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASSWORD $DB_NAME > backup.sql
```

### Export Specific Tables Only
```bash
mysqldump -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASSWORD $DB_NAME users wallets deposit_payments > tables_backup.sql
```

## Import into cPanel phpMyAdmin

1. **Login to cPanel** → Open **phpMyAdmin**
2. **Select your database** (e.g., `neodiggi_card`)
3. Click **"Import"** tab
4. **Choose File**: Select your `backup.sql` file
5. **Format**: SQL (auto-detected)
6. Click **"Go"** at the bottom
7. Wait for success message

### Alternative: Via Command Line
```bash
mysql -h localhost -u your_user -p your_database < backup.sql
```

## Environment Secrets for cPanel

### If App Runs on Same Server (Recommended)
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=neodiggi_card
DB_USER=neodiggi_card
DB_PASSWORD=your_password
DB_SSL=false
```

### If App Runs Externally
```
DB_HOST=mysql.yourhost.com
DB_PORT=3306
DB_NAME=neodiggi_card
DB_USER=neodiggi_card
DB_PASSWORD=your_password
DB_SSL=false
```

**Important**: Update your `.env` or environment configuration with these values.

## Troubleshooting

### Error: "Access Denied"

**Symptoms**: `Access denied for user 'username'@'host'`

**Solutions**:
1. Verify `DB_USER` and `DB_PASSWORD` are correct
2. Check user has privileges on the database:
   ```sql
   SHOW GRANTS FOR 'neodiggi_card'@'%';
   ```
3. For remote access, create user with wildcard host:
   ```sql
   CREATE USER 'neodiggi_card'@'%' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON neodiggi_card.* TO 'neodiggi_card'@'%';
   FLUSH PRIVILEGES;
   ```
4. Enable Remote MySQL in cPanel and add your IP

### Error: "Unknown Database" or "Unknown Table"

**Symptoms**: `Unknown database 'dbname'` or `Table 'table_name' doesn't exist`

**Solutions**:
1. Verify database name: `SHOW DATABASES;`
2. Check if you're using the right database:
   ```sql
   USE neodiggi_card;
   SHOW TABLES;
   ```
3. Run table creation script: `php create_tables.php`
4. Import backup if tables are missing: Use phpMyAdmin Import

### Error: "Can't Connect to MySQL Server"

**Symptoms**: `Can't connect to MySQL server on 'host'` or `Connection refused`

**Solutions**:
1. Verify `DB_HOST` is correct:
   - For local: `localhost` or `127.0.0.1`
   - For remote: `mysql.yourhost.com` or IP address
2. Check `DB_PORT` (default: 3306)
3. Ensure MySQL service is running:
   ```bash
   systemctl status mysql
   ```
4. Check firewall allows port 3306:
   ```bash
   telnet mysql.yourhost.com 3306
   ```
5. For cPanel: Enable "Remote MySQL" and add allowed hosts

### Error: "Connection Timeout"

**Symptoms**: Connection hangs or times out after 30+ seconds

**Solutions**:
1. Check network connectivity:
   ```bash
   ping mysql.yourhost.com
   ```
2. Verify port is open:
   ```bash
   nc -zv mysql.yourhost.com 3306
   ```
3. Increase PHP timeout if needed:
   ```php
   ini_set('mysql.connect_timeout', 60);
   ```
4. Check if host firewall is blocking connections
5. For cPanel: Contact hosting provider to verify MySQL remote access is enabled

## Quick Sanity Checks

### 1. Connection Test
```bash
php test_db.php
```
Expected: `✅ Database connection successful!`

### 2. Table Verification
```bash
php create_tables.php
```
Expected: All 3 tables created (users, wallets, deposit_payments)

### 3. Data Check
```bash
php seed_data.php
php check_data.php
```
Expected: JSON output showing 1 user and 1 deposit

### 4. Endpoint Test
```bash
# Test user registration
curl -X POST http://localhost/routes/register_user.php \
  -H "Content-Type: application/json" \
  -d '{"telegram_id":999,"email":"new@test.com","phone":"+251900000000","first_name":"New","last_name":"User"}'

# Test deposit creation
curl -X POST http://localhost/routes/create_deposit.php \
  -H "Content-Type: application/json" \
  -d '{"user_id":1,"telegram_id":999,"amount_usd":50,"amount_etb":6750,"total_etb":6750,"payment_method":"telebirr"}'
```

Expected: Both return `{"ok":true,...}`

## Pre-Deploy Checklist

- [ ] Database backup created (`backup.sql`)
- [ ] All tables exist in target database
- [ ] Database user has correct permissions
- [ ] Remote MySQL enabled (if accessing remotely)
- [ ] Environment secrets configured correctly
- [ ] Connection test passes
- [ ] Sample data insert/read works
- [ ] API endpoints respond correctly
- [ ] Error logs checked for issues

## Post-Deploy Verification

1. Run connection test: `php test_db.php`
2. Check all tables exist: `SHOW TABLES;`
3. Verify data imported: `SELECT COUNT(*) FROM users;`
4. Test API endpoints with curl commands
5. Monitor error logs for any issues
