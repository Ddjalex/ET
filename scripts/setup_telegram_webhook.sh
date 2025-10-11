#!/bin/bash

# Telegram Webhook Setup Script
# This script configures your Telegram bot webhook to point to your Replit server

# Get environment variables
BOT_TOKEN="${BOT_TOKEN}"
REPLIT_DOMAIN="${REPLIT_DEV_DOMAIN}"

# Construct webhook URL
WEBHOOK_URL="https://${REPLIT_DOMAIN}/bot/webhook.php"

echo "================================"
echo "Telegram Webhook Setup"
echo "================================"
echo ""
echo "Bot Token: ${BOT_TOKEN:0:10}..."
echo "Webhook URL: $WEBHOOK_URL"
echo ""

if [ -z "$BOT_TOKEN" ]; then
    echo "❌ Error: BOT_TOKEN not found in environment variables"
    exit 1
fi

if [ -z "$REPLIT_DOMAIN" ]; then
    echo "❌ Error: REPLIT_DEV_DOMAIN not found"
    exit 1
fi

# Set webhook
echo "Setting webhook..."
RESPONSE=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d "{\"url\":\"${WEBHOOK_URL}\"}")

echo ""
echo "Response from Telegram:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""

# Get webhook info to verify
echo "Verifying webhook configuration..."
WEBHOOK_INFO=$(curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo")

echo ""
echo "Current Webhook Status:"
echo "$WEBHOOK_INFO" | jq '.' 2>/dev/null || echo "$WEBHOOK_INFO"
echo ""
echo "================================"
echo "✅ Setup complete!"
echo "================================"
echo ""
echo "Test your bot by sending /start to it on Telegram"
