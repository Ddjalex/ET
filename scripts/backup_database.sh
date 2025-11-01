#!/bin/bash
#
# Backup Complete Database with Customer Data
# Exports both schema and data to schema.sql
#

echo "🔄 Starting database backup..."
echo ""

# Check if DATABASE_URL exists
if [ -z "$DATABASE_URL" ]; then
    echo "❌ Error: DATABASE_URL not found"
    exit 1
fi

# Create backup
echo "📊 Exporting database structure and data..."
pg_dump $DATABASE_URL --clean --if-exists --inserts > schema.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Backup completed successfully!"
    echo "📁 File saved: schema.sql"
    echo "📏 File size: $(du -h schema.sql | cut -f1)"
    echo ""
    echo "📈 Data Summary:"
    
    # Count tables
    TABLE_COUNT=$(grep -c "CREATE TABLE" schema.sql)
    echo "  • Tables: $TABLE_COUNT"
    
    # Count INSERT statements (data rows)
    INSERT_COUNT=$(grep -c "^INSERT INTO" schema.sql)
    echo "  • Data rows: $INSERT_COUNT"
    echo ""
    echo "✅ All customer data preserved in schema.sql"
else
    echo ""
    echo "❌ Backup failed!"
    exit 1
fi
