#!/bin/bash
# Startup script for PHP bot server with environment variables

# Export all Replit secrets as environment variables
export TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN}"
export BOT_TOKEN="${BOT_TOKEN:-$TELEGRAM_BOT_TOKEN}"
export STROWALLET_API_KEY="${STROWALLET_API_KEY}"
export STROWALLET_PUBLIC_KEY="${STROWALLET_API_KEY}"
export STROWALLET_WEBHOOK_SECRET="${STROWALLET_WEBHOOK_SECRET}"
export STROWALLET_EMAIL="${STROWALLET_EMAIL}"
export ADMIN_CHAT_ID="${ADMIN_CHAT_ID}"
export TELEGRAM_SECRET_TOKEN="${TELEGRAM_SECRET_TOKEN}"
export SUPPORT_URL="${SUPPORT_URL}"
export DATABASE_URL="${DATABASE_URL}"
export PGHOST="${PGHOST}"
export PGPORT="${PGPORT}"
export PGUSER="${PGUSER}"
export PGPASSWORD="${PGPASSWORD}"
export PGDATABASE="${PGDATABASE}"

# Print debug info (without showing actual secrets)
echo "🚀 Starting PHP Bot Server..."
echo "📋 Environment check:"
echo "  - BOT_TOKEN: ${BOT_TOKEN:+SET (${#BOT_TOKEN} chars)}"
echo "  - STROWALLET_API_KEY: ${STROWALLET_API_KEY:+SET}"
echo "  - DATABASE_URL: ${DATABASE_URL:+SET}"

# Start PHP built-in server
cd public_html && php -S 0.0.0.0:5000
