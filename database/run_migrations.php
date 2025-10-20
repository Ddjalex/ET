<?php
/**
 * Database Migration Runner
 * Executes all SQL migrations in order
 */

// Load environment variables
require_once __DIR__ . '/../secrets/load_env.php';

// Get database connection details from environment
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("ERROR: DATABASE_URL not found in environment\n");
}

// Parse DATABASE_URL
preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+?)(\?.*)?$/', $dbUrl, $matches);
$user = $matches[1] ?? 'postgres';
$pass = $matches[2] ?? '';
$host = $matches[3] ?? 'localhost';
$dbname = $matches[4] ?? 'postgres';

// Build connection string for pg_connect
// Note: Replit's internal PostgreSQL uses sslmode=prefer for security while maintaining compatibility
$connString = sprintf(
    "host=%s port=5432 dbname=%s user=%s password=%s sslmode=prefer",
    $host,
    $dbname,
    $user,
    $pass
);

echo "Connecting to database...\n";
$conn = @pg_connect($connString);

if (!$conn) {
    die("ERROR: Failed to connect to database\n");
}

echo "Connected successfully!\n\n";

// Migration files in order
$migrations = [
    '001_create_schema.sql',
    '002_fix_admin_fk.sql',
    '003_fix_settings_fkey.sql'
];

foreach ($migrations as $migration) {
    $filePath = __DIR__ . '/migrations/' . $migration;
    
    if (!file_exists($filePath)) {
        echo "WARNING: Migration file not found: $migration\n";
        continue;
    }
    
    echo "Running migration: $migration\n";
    $sql = file_get_contents($filePath);
    
    $result = @pg_query($conn, $sql);
    
    if (!$result) {
        echo "ERROR in $migration: " . pg_last_error($conn) . "\n";
        pg_close($conn);
        exit(1);
    }
    
    echo "✓ $migration completed successfully\n\n";
}

// Verify tables were created
echo "Verifying database schema...\n";
$result = pg_query($conn, "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'public'");
$row = pg_fetch_assoc($result);
echo "✓ Total tables created: " . $row['table_count'] . "\n\n";

// List all tables
$result = pg_query($conn, "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
echo "Tables in database:\n";
while ($row = pg_fetch_assoc($result)) {
    echo "  - " . $row['tablename'] . "\n";
}

pg_close($conn);
echo "\n✅ All migrations completed successfully!\n";
