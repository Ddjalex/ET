-- Migration 008: Allow NULL telegram_id for StroWallet-only customers
-- Created: October 28, 2025
-- Purpose: Enable importing StroWallet customers who don't have Telegram accounts yet

-- Remove NOT NULL constraint from telegram_id
ALTER TABLE users ALTER COLUMN telegram_id DROP NOT NULL;

-- Make telegram_id unique only when it's not NULL
DROP INDEX IF EXISTS idx_users_telegram_id;
CREATE UNIQUE INDEX idx_users_telegram_id ON users(telegram_id) WHERE telegram_id IS NOT NULL;

-- Add comment explaining this change
COMMENT ON COLUMN users.telegram_id IS 'Telegram user ID (NULL for StroWallet-only customers)';
