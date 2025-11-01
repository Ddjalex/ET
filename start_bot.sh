#!/bin/bash
# Startup script for PHP bot server with environment variables

# Export all Replit secrets as environment variables
export TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN}"
export STROWALLET_API_KEY="${STROWALLET_API_KEY}"
export STROWALLET_PUBLIC_KEY="${STROWALLET_API_KEY}"
export DATABASE_URL="${DATABASE_URL}"
export PGHOST="${PGHOST}"
export PGPORT="${PGPORT}"
export PGUSER="${PGUSER}"
export PGPASSWORD="${PGPASSWORD}"
export PGDATABASE="${PGDATABASE}"

# Start PHP built-in server
cd public_html && php -S 0.0.0.0:5000
