#!/usr/bin/env php
<?php
/**
 * Restore Database from schema.sql
 * 
 * This script restores the complete database including:
 * - All tables
 * - All customer data
 * - All constraints
 * 
 * WARNING: This will DROP all existing tables!
 */

require_once __DIR__ . '/../db_postgres.php';

echo "⚠️  WARNING: This will delete ALL existing data!\n";
echo "Are you sure you want to restore from schema.sql? (yes/no): ";

$handle = fopen("php://stdin", "r");
$confirm = trim(fgets($handle));
fclose($handle);

if (strtolower($confirm) !== 'yes') {
    echo "❌ Restore cancelled.\n";
    exit(0);
}

echo "\n🔄 Starting database restore...\n\n";

try {
    $db = getDbConnection();
    
    // Read schema.sql
    $schemaFile = __DIR__ . '/../schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found! Run export_schema_with_data.php first.");
    }
    
    $sql = file_get_contents($schemaFile);
    
    echo "📁 Reading schema.sql...\n";
    echo "📊 File size: " . round(filesize($schemaFile) / 1024, 2) . " KB\n\n";
    
    // Execute SQL
    echo "🔄 Executing SQL statements...\n";
    $db->exec($sql);
    
    echo "\n✅ Database restored successfully!\n\n";
    
    // Show summary
    echo "📈 Restored Tables:\n";
    $tables = $db->query("
        SELECT tablename, 
               (SELECT COUNT(*) FROM pg_class WHERE relname = tablename) as row_count
        FROM pg_tables 
        WHERE schemaname = 'public' 
        ORDER BY tablename
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM \"{$table['tablename']}\"")->fetchColumn();
        echo "  • {$table['tablename']}: $count rows\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Restore failed: " . $e->getMessage() . "\n";
    exit(1);
}
