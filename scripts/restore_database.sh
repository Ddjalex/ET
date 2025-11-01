#!/bin/bash
#
# Restore Database from schema.sql
# WARNING: This will delete all existing data!
#

echo "⚠️  WARNING: This will delete ALL existing data!"
echo "Are you sure you want to restore from schema.sql? (yes/no): "
read -r CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Restore cancelled."
    exit 0
fi

echo ""
echo "🔄 Starting database restore..."
echo ""

# Check if schema.sql exists
if [ ! -f "schema.sql" ]; then
    echo "❌ Error: schema.sql not found!"
    exit 1
fi

# Check if DATABASE_URL exists
if [ -z "$DATABASE_URL" ]; then
    echo "❌ Error: DATABASE_URL not found"
    exit 1
fi

# Restore database
echo "📁 Restoring from schema.sql..."
psql "$DATABASE_URL" < schema.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Database restored successfully!"
    echo ""
    echo "📊 Verifying restored data..."
    
    # Show table counts
    psql "$DATABASE_URL" -c "\
        SELECT tablename, \
               (xpath('/row/count/text()', \
                      query_to_xml('SELECT COUNT(*) FROM \"' || tablename || '\"', false, false, '')))[1]::text::int as row_count \
        FROM pg_tables \
        WHERE schemaname = 'public' \
        ORDER BY tablename;" 2>/dev/null || echo "  ✓ Data restored"
    
else
    echo ""
    echo "❌ Restore failed!"
    exit 1
fi
