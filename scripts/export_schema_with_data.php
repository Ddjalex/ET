#!/usr/bin/env php
<?php
/**
 * Export Complete Database Schema with Customer Data
 * 
 * This script exports:
 * - All table structures (CREATE TABLE)
 * - All customer data (INSERT statements)
 * - Sequences and indexes
 * 
 * Output: schema.sql (complete backup with data)
 */

require_once __DIR__ . '/../db_postgres.php';

echo "🔄 Starting database export with customer data...\n\n";

try {
    $db = getDbConnection();
    
    $sql = "-- =====================================================\n";
    $sql .= "-- Telegram Bot Database Schema with Customer Data\n";
    $sql .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- =====================================================\n\n";
    
    // Get all tables
    $tables = $db->query("
        SELECT tablename 
        FROM pg_tables 
        WHERE schemaname = 'public' 
        ORDER BY tablename
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Found " . count($tables) . " tables to export\n\n";
    
    foreach ($tables as $table) {
        echo "Exporting: $table\n";
        
        // 1. Export table structure
        $sql .= "-- =====================================================\n";
        $sql .= "-- Table: $table\n";
        $sql .= "-- =====================================================\n\n";
        
        // Drop table if exists
        $sql .= "DROP TABLE IF EXISTS \"$table\" CASCADE;\n\n";
        
        // Get CREATE TABLE statement
        $createStmt = $db->query("
            SELECT 
                'CREATE TABLE \"' || tablename || '\" (' || 
                string_agg(
                    '\"' || column_name || '\" ' || 
                    CASE 
                        WHEN data_type = 'character varying' THEN 'VARCHAR(' || character_maximum_length || ')'
                        WHEN data_type = 'integer' THEN 
                            CASE 
                                WHEN column_default LIKE 'nextval%' THEN 'SERIAL'
                                ELSE 'INTEGER'
                            END
                        WHEN data_type = 'bigint' THEN 
                            CASE 
                                WHEN column_default LIKE 'nextval%' THEN 'BIGSERIAL'
                                ELSE 'BIGINT'
                            END
                        WHEN data_type = 'boolean' THEN 'BOOLEAN'
                        WHEN data_type = 'text' THEN 'TEXT'
                        WHEN data_type = 'timestamp without time zone' THEN 'TIMESTAMP'
                        WHEN data_type = 'timestamp with time zone' THEN 'TIMESTAMPTZ'
                        WHEN data_type = 'numeric' THEN 'NUMERIC(' || numeric_precision || ',' || numeric_scale || ')'
                        ELSE UPPER(data_type)
                    END ||
                    CASE WHEN is_nullable = 'NO' THEN ' NOT NULL' ELSE '' END ||
                    CASE 
                        WHEN column_default IS NOT NULL AND column_default NOT LIKE 'nextval%' 
                        THEN ' DEFAULT ' || column_default 
                        ELSE '' 
                    END,
                    ', '
                    ORDER BY ordinal_position
                ) || ');'
            FROM information_schema.columns
            WHERE table_name = '$table' AND table_schema = 'public'
            GROUP BY tablename
        ")->fetchColumn();
        
        $sql .= $createStmt . "\n\n";
        
        // Add primary key
        $pkQuery = $db->query("
            SELECT 
                'ALTER TABLE \"' || tc.table_name || '\" ADD CONSTRAINT \"' || tc.constraint_name || 
                '\" PRIMARY KEY (\"' || kcu.column_name || '\");'
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.constraint_type = 'PRIMARY KEY' 
                AND tc.table_name = '$table'
                AND tc.table_schema = 'public'
        ");
        
        while ($pk = $pkQuery->fetchColumn()) {
            $sql .= $pk . "\n";
        }
        
        // Add unique constraints
        $uniqueQuery = $db->query("
            SELECT 
                'ALTER TABLE \"' || tc.table_name || '\" ADD CONSTRAINT \"' || tc.constraint_name || 
                '\" UNIQUE (\"' || kcu.column_name || '\");'
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.constraint_type = 'UNIQUE' 
                AND tc.table_name = '$table'
                AND tc.table_schema = 'public'
        ");
        
        while ($unique = $uniqueQuery->fetchColumn()) {
            $sql .= $unique . "\n";
        }
        
        $sql .= "\n";
        
        // 2. Export data
        $rowCount = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
        
        if ($rowCount > 0) {
            echo "  └─ Exporting $rowCount rows of data\n";
            
            $sql .= "-- Data for table: $table ($rowCount rows)\n";
            
            $data = $db->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $row) {
                $columns = array_keys($row);
                $values = array_map(function($value) use ($db) {
                    if ($value === null) {
                        return 'NULL';
                    } elseif (is_bool($value)) {
                        return $value ? 'TRUE' : 'FALSE';
                    } elseif (is_numeric($value)) {
                        return $value;
                    } else {
                        return $db->quote($value);
                    }
                }, array_values($row));
                
                $sql .= sprintf(
                    "INSERT INTO \"%s\" (\"%s\") VALUES (%s);\n",
                    $table,
                    implode('", "', $columns),
                    implode(', ', $values)
                );
            }
            
            $sql .= "\n";
        } else {
            echo "  └─ No data to export\n";
        }
        
        $sql .= "\n";
    }
    
    // Add foreign keys at the end
    $sql .= "-- =====================================================\n";
    $sql .= "-- Foreign Keys\n";
    $sql .= "-- =====================================================\n\n";
    
    $fkQuery = $db->query("
        SELECT 
            'ALTER TABLE \"' || tc.table_name || '\" ADD CONSTRAINT \"' || tc.constraint_name || 
            '\" FOREIGN KEY (\"' || kcu.column_name || '\") REFERENCES \"' || 
            ccu.table_name || '\" (\"' || ccu.column_name || '\");'
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu 
            ON tc.constraint_name = kcu.constraint_name
        JOIN information_schema.constraint_column_usage ccu 
            ON ccu.constraint_name = tc.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = 'public'
    ");
    
    while ($fk = $fkQuery->fetchColumn()) {
        $sql .= $fk . "\n";
    }
    
    // Write to file
    $outputFile = __DIR__ . '/../schema.sql';
    file_put_contents($outputFile, $sql);
    
    echo "\n✅ Export completed successfully!\n";
    echo "📁 File saved: schema.sql\n";
    echo "📊 Total tables: " . count($tables) . "\n";
    
    // Show summary
    echo "\n📈 Data Summary:\n";
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
        if ($count > 0) {
            echo "  • $table: $count rows\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ Export failed: " . $e->getMessage() . "\n";
    exit(1);
}
