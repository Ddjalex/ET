-- ============================================================================
-- Telegram Crypto Card Bot - Complete Database Schema
-- ============================================================================
-- Project: Telegram bot for managing virtual crypto cards via StroWallet API
-- Database: PostgreSQL 14+
-- Created: October 30, 2025
-- Last Updated: October 30, 2025
-- 
-- Description:
--   Complete database schema for the Telegram Crypto Card Bot including
--   user management, KYC verification, wallet transactions, card management,
--   deposits, admin panel, and broadcaster functionality.
--
-- Tables: 14 total
--   - users, user_registrations
--   - wallets, wallet_transactions
--   - cards, card_transactions
--   - deposits, deposit_payments
--   - broadcasts, broadcast_logs, giveaway_entries
--   - admin_users, admin_actions, settings
--
-- Usage:
--   psql -h <host> -U <user> -d <database> -f schema.sql
-- ============================================================================

-- Drop existing triggers (for clean re-creation)
DROP TRIGGER IF EXISTS update_users_updated_at ON users;
DROP TRIGGER IF EXISTS update_wallets_updated_at ON wallets;
DROP TRIGGER IF EXISTS update_wallet_transactions_updated_at ON wallet_transactions;
DROP TRIGGER IF EXISTS update_deposits_updated_at ON deposits;
DROP TRIGGER IF EXISTS update_cards_updated_at ON cards;
DROP TRIGGER IF EXISTS update_admin_users_updated_at ON admin_users;
DROP TRIGGER IF EXISTS trigger_deposit_payments_updated_at ON deposit_payments;

-- Drop existing functions (for clean re-creation)
DROP FUNCTION IF EXISTS update_updated_at_column() CASCADE;
DROP FUNCTION IF EXISTS update_deposit_payments_updated_at() CASCADE;

-- ============================================================================
-- CORE USER TABLES
-- ============================================================================

-- Users table: Main user records linked to Telegram accounts
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    kyc_status VARCHAR(20) DEFAULT 'pending' CHECK (kyc_status IN ('pending', 'approved', 'rejected')),
    kyc_submitted_at TIMESTAMP,
    kyc_approved_at TIMESTAMP,
    kyc_rejected_at TIMESTAMP,
    kyc_rejection_reason TEXT,
    strow_customer_id VARCHAR(255),
    id_type VARCHAR(20),
    id_number VARCHAR(50),
    id_image_url TEXT,
    user_photo_url TEXT,
    address_line1 VARCHAR(255),
    address_city VARCHAR(100),
    address_state VARCHAR(100),
    address_zip VARCHAR(20),
    address_country VARCHAR(2) DEFAULT 'ET',
    house_number VARCHAR(20),
    date_of_birth DATE,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'banned')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_telegram_id ON users(telegram_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_kyc_status ON users(kyc_status);

-- User registrations table: Staging table for multi-step registration flow
CREATE TABLE IF NOT EXISTS user_registrations (
    id SERIAL PRIMARY KEY,
    telegram_user_id BIGINT UNIQUE NOT NULL,
    registration_state VARCHAR(50) DEFAULT 'idle',
    is_registered BOOLEAN DEFAULT FALSE,
    
    -- Personal Information (collected step-by-step)
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    date_of_birth VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(255),
    
    -- Address Information
    address_line1 VARCHAR(255),
    address_city VARCHAR(100),
    address_state VARCHAR(100),
    address_zip VARCHAR(20),
    address_country VARCHAR(2) DEFAULT 'ET',
    house_number VARCHAR(20),
    
    -- ID Verification
    id_type VARCHAR(20),
    id_number VARCHAR(50),
    id_front_photo_url TEXT,
    id_back_photo_url TEXT,
    selfie_photo_url TEXT,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    
    -- Additional fields for linking to StroWallet
    kyc_status VARCHAR(20),
    strowallet_customer_id VARCHAR(255),
    temp_data JSONB
);

CREATE INDEX IF NOT EXISTS idx_user_registrations_telegram_id ON user_registrations(telegram_user_id);
CREATE INDEX IF NOT EXISTS idx_user_registrations_state ON user_registrations(registration_state);
CREATE INDEX IF NOT EXISTS idx_user_registrations_is_registered ON user_registrations(is_registered);

-- ============================================================================
-- WALLET & TRANSACTION TABLES
-- ============================================================================

-- Wallets table: User balances in USD and ETB
CREATE TABLE IF NOT EXISTS wallets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    balance_usd DECIMAL(15, 2) DEFAULT 0.00 CHECK (balance_usd >= 0),
    balance_etb DECIMAL(15, 2) DEFAULT 0.00 CHECK (balance_etb >= 0),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'frozen', 'closed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wallets_user_id ON wallets(user_id);

-- Wallet transactions: All money movements
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id SERIAL PRIMARY KEY,
    wallet_id INTEGER NOT NULL REFERENCES wallets(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    transaction_type VARCHAR(30) NOT NULL CHECK (transaction_type IN ('deposit', 'topup', 'card_creation_fee', 'card_topup_fee', 'refund', 'admin_adjustment')),
    amount_usd DECIMAL(15, 2) NOT NULL,
    amount_etb DECIMAL(15, 2),
    balance_before_usd DECIMAL(15, 2),
    balance_after_usd DECIMAL(15, 2),
    reference VARCHAR(255),
    description TEXT,
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'failed', 'reversed')),
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wallet_transactions_wallet_id ON wallet_transactions(wallet_id);
CREATE INDEX IF NOT EXISTS idx_wallet_transactions_user_id ON wallet_transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_wallet_transactions_type ON wallet_transactions(transaction_type);
CREATE INDEX IF NOT EXISTS idx_wallet_transactions_status ON wallet_transactions(status);

-- ============================================================================
-- DEPOSIT TABLES
-- ============================================================================

-- Deposits table: User deposit requests with admin approval (legacy)
CREATE TABLE IF NOT EXISTS deposits (
    id SERIAL PRIMARY KEY,
    wallet_id INTEGER NOT NULL REFERENCES wallets(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    usd_amount DECIMAL(15, 2) NOT NULL,
    etb_amount_quote DECIMAL(15, 2) NOT NULL,
    exchange_rate DECIMAL(10, 4) NOT NULL,
    fee_percentage DECIMAL(5, 2) DEFAULT 0.00,
    fee_flat DECIMAL(10, 2) DEFAULT 0.00,
    total_etb_to_pay DECIMAL(15, 2) NOT NULL,
    payment_proof_url TEXT,
    payment_reference VARCHAR(255),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'payment_submitted', 'approved', 'rejected', 'expired')),
    admin_notes TEXT,
    approved_by INTEGER REFERENCES users(id),
    approved_at TIMESTAMP,
    rejected_by INTEGER REFERENCES users(id),
    rejected_at TIMESTAMP,
    rejection_reason TEXT,
    strow_reference VARCHAR(255),
    wallet_transaction_id INTEGER REFERENCES wallet_transactions(id),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_deposits_user_id ON deposits(user_id);
CREATE INDEX IF NOT EXISTS idx_deposits_wallet_id ON deposits(wallet_id);
CREATE INDEX IF NOT EXISTS idx_deposits_status ON deposits(status);
CREATE INDEX IF NOT EXISTS idx_deposits_created_at ON deposits(created_at);

-- Deposit payments table: Manual admin verification for deposit transactions
CREATE TABLE IF NOT EXISTS deposit_payments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    telegram_id BIGINT NOT NULL,
    amount_usd DECIMAL(14,2) NOT NULL,
    amount_etb DECIMAL(14,2) NOT NULL,
    exchange_rate DECIMAL(10,4) NOT NULL DEFAULT 135.00,
    deposit_fee_etb DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_etb DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL CHECK (payment_method IN ('telebirr', 'm-pesa', 'cbe', 'bank_transfer')),
    payment_phone VARCHAR(50),
    payment_account_name VARCHAR(255),
    screenshot_file_id VARCHAR(255),
    screenshot_url TEXT,
    transaction_number VARCHAR(255),
    validation_status VARCHAR(50) DEFAULT 'pending' CHECK (validation_status IN ('pending', 'validating', 'verified', 'rejected', 'manual_approved')),
    validation_response TEXT,
    verification_attempts INTEGER DEFAULT 0,
    verified_at TIMESTAMP,
    verified_by VARCHAR(100),
    rejected_reason TEXT,
    strowallet_deposit_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'screenshot_submitted', 'transaction_submitted', 'verified', 'processing', 'completed', 'rejected', 'cancelled')),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    
    CONSTRAINT fk_deposit_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_deposit_payments_telegram_id ON deposit_payments(telegram_id);
CREATE INDEX IF NOT EXISTS idx_deposit_payments_status ON deposit_payments(status);
CREATE INDEX IF NOT EXISTS idx_deposit_payments_validation_status ON deposit_payments(validation_status);
CREATE INDEX IF NOT EXISTS idx_deposit_payments_transaction_number ON deposit_payments(transaction_number);
CREATE INDEX IF NOT EXISTS idx_deposit_payments_created_at ON deposit_payments(created_at);

-- ============================================================================
-- CARD TABLES
-- ============================================================================

-- Cards table: StroWallet virtual cards
CREATE TABLE IF NOT EXISTS cards (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    strow_card_id VARCHAR(255) UNIQUE NOT NULL,
    card_brand VARCHAR(20),
    last4 VARCHAR(4),
    name_on_card VARCHAR(100),
    card_type VARCHAR(20),
    balance_usd DECIMAL(15, 2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'frozen', 'closed', 'expired')),
    frozen_at TIMESTAMP,
    frozen_by INTEGER REFERENCES users(id),
    frozen_reason TEXT,
    unfrozen_at TIMESTAMP,
    creation_fee_usd DECIMAL(10, 2),
    creation_wallet_transaction_id INTEGER REFERENCES wallet_transactions(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cards_user_id ON cards(user_id);
CREATE INDEX IF NOT EXISTS idx_cards_strow_card_id ON cards(strow_card_id);
CREATE INDEX IF NOT EXISTS idx_cards_status ON cards(status);

-- Card transactions table: All card activity
CREATE TABLE IF NOT EXISTS card_transactions (
    id SERIAL PRIMARY KEY,
    card_id INTEGER NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    transaction_type VARCHAR(30) NOT NULL CHECK (transaction_type IN ('topup', 'topup_fee', 'purchase', 'refund', 'fee', 'reversal')),
    amount_usd DECIMAL(15, 2) NOT NULL,
    merchant_name VARCHAR(255),
    merchant_category VARCHAR(100),
    description TEXT,
    strow_transaction_id VARCHAR(255),
    wallet_transaction_id INTEGER REFERENCES wallet_transactions(id),
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'declined', 'reversed')),
    metadata JSONB,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_card_transactions_card_id ON card_transactions(card_id);
CREATE INDEX IF NOT EXISTS idx_card_transactions_user_id ON card_transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_card_transactions_type ON card_transactions(transaction_type);
CREATE INDEX IF NOT EXISTS idx_card_transactions_date ON card_transactions(transaction_date);

-- ============================================================================
-- BROADCASTER TABLES
-- ============================================================================

-- Broadcasts table: Admin-to-user communication
CREATE TABLE IF NOT EXISTS broadcasts (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content_type VARCHAR(20) NOT NULL CHECK (content_type IN ('text', 'photo', 'video', 'poll')),
    content_text TEXT,
    media_url TEXT,
    media_caption TEXT,
    
    -- Poll-specific fields
    poll_question TEXT,
    poll_options JSONB,
    poll_type VARCHAR(20) CHECK (poll_type IN ('regular', 'quiz')),
    poll_correct_option INTEGER,
    
    -- Inline buttons
    buttons JSONB,
    
    -- Delivery settings
    send_to_telegram BOOLEAN DEFAULT false,
    send_to_inapp BOOLEAN DEFAULT true,
    telegram_channel_id VARCHAR(100),
    
    -- Scheduling
    scheduled_for TIMESTAMP,
    send_now BOOLEAN DEFAULT false,
    
    -- Pin message
    pin_message BOOLEAN DEFAULT false,
    
    -- Giveaway tracking
    is_giveaway BOOLEAN DEFAULT false,
    giveaway_winners_count INTEGER DEFAULT 0,
    giveaway_ends_at TIMESTAMP,
    
    -- Status tracking
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft', 'scheduled', 'sent', 'failed')),
    telegram_message_id BIGINT,
    sent_at TIMESTAMP,
    error_message TEXT,
    
    -- Metadata
    created_by INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_broadcasts_status ON broadcasts(status);
CREATE INDEX IF NOT EXISTS idx_broadcasts_scheduled ON broadcasts(scheduled_for) WHERE status = 'scheduled';

-- Broadcast logs table: Detailed send history and errors
CREATE TABLE IF NOT EXISTS broadcast_logs (
    id SERIAL PRIMARY KEY,
    broadcast_id INTEGER REFERENCES broadcasts(id) ON DELETE CASCADE,
    event_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    telegram_message_id BIGINT,
    response_data JSONB,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_broadcast_logs_broadcast ON broadcast_logs(broadcast_id);

-- Giveaway entries table: Tracks button clicks for giveaway tracking
CREATE TABLE IF NOT EXISTS giveaway_entries (
    id SERIAL PRIMARY KEY,
    broadcast_id INTEGER REFERENCES broadcasts(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    telegram_user_id BIGINT NOT NULL,
    button_data VARCHAR(255),
    is_winner BOOLEAN DEFAULT false,
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(broadcast_id, telegram_user_id)
);

CREATE INDEX IF NOT EXISTS idx_giveaway_entries_broadcast ON giveaway_entries(broadcast_id);
CREATE INDEX IF NOT EXISTS idx_giveaway_entries_user ON giveaway_entries(user_id);

-- ============================================================================
-- ADMIN TABLES
-- ============================================================================

-- Admin users table: Separate from regular users for security
CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(200),
    role VARCHAR(20) DEFAULT 'admin' CHECK (role IN ('super_admin', 'admin', 'viewer')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'deactivated')),
    last_login_at TIMESTAMP,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username);
CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email);

-- Admin actions table: Audit log for all admin activities
CREATE TABLE IF NOT EXISTS admin_actions (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL REFERENCES admin_users(id),
    action_type VARCHAR(50) NOT NULL,
    target_table VARCHAR(50),
    target_id INTEGER,
    action_description TEXT,
    payload JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_admin_actions_admin_id ON admin_actions(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_actions_type ON admin_actions(action_type);
CREATE INDEX IF NOT EXISTS idx_admin_actions_created_at ON admin_actions(created_at);

-- Settings table: System configuration
CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(100) PRIMARY KEY,
    value JSONB NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER REFERENCES admin_users(id)
);

-- ============================================================================
-- FUNCTIONS & TRIGGERS
-- ============================================================================

-- Function: Update updated_at timestamp automatically
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Function: Update deposit_payments updated_at timestamp
CREATE OR REPLACE FUNCTION update_deposit_payments_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply triggers to tables
CREATE TRIGGER update_users_updated_at 
    BEFORE UPDATE ON users 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_wallets_updated_at 
    BEFORE UPDATE ON wallets 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_wallet_transactions_updated_at 
    BEFORE UPDATE ON wallet_transactions 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_deposits_updated_at 
    BEFORE UPDATE ON deposits 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_cards_updated_at 
    BEFORE UPDATE ON cards 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_admin_users_updated_at 
    BEFORE UPDATE ON admin_users 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER trigger_deposit_payments_updated_at
    BEFORE UPDATE ON deposit_payments
    FOR EACH ROW
    EXECUTE FUNCTION update_deposit_payments_updated_at();

-- ============================================================================
-- DEFAULT DATA
-- ============================================================================

-- Insert default settings
INSERT INTO settings (key, value, description) VALUES 
('exchange_rate_usd_to_etb', '{"rate": 130.50, "last_updated": "2025-10-30"}', 'USD to ETB exchange rate'),
('card_creation_fee', '{"percentage": 1.99, "flat": 1.99, "currency": "USD"}', 'Card creation fee structure'),
('card_topup_fee', '{"percentage": 1.99, "flat": 1.99, "currency": "USD"}', 'Card top-up fee structure'),
('deposit_fee', '{"percentage": 0.00, "flat": 500.00, "currency": "ETB"}', 'Deposit fee structure (500 ETB flat fee)'),
('card_limits', '{"min_topup": 5, "max_topup": 10000, "daily_limit": 1000}', 'Card transaction limits'),
('kyc_requirements', '{"require_id_image": true, "require_photo": true, "require_address": true}', 'KYC verification requirements'),
('system_status', '{"maintenance_mode": false, "allow_deposits": true, "allow_card_creation": true}', 'System operational status'),
('deposit_accounts', '{"accounts": [{"method": "TeleBirr", "account_name": "Bot Wallet", "account_number": "+251900000000"}]}', 'Payment account details for deposits')
ON CONFLICT (key) DO NOTHING;

-- Create default admin user (password: admin123 - CHANGE THIS IMMEDIATELY IN PRODUCTION)
-- Password hash for 'admin123' using PHP password_hash with bcrypt
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@cardbot.local', '$2y$10$44B3Z2K3NJ9jU7pm7jp0ee3QX89F3yPD1r2wwPASeGAQPfmNFXRwG', 'System Administrator', 'super_admin')
ON CONFLICT (username) DO NOTHING;

-- ============================================================================
-- SCHEMA VERSION TRACKING
-- ============================================================================

-- Add schema version to settings
INSERT INTO settings (key, value, description) VALUES 
('schema_version', '{"version": "1.0.0", "last_updated": "2025-10-30", "description": "Complete schema with manual deposit verification"}', 'Database schema version')
ON CONFLICT (key) DO UPDATE SET 
    value = EXCLUDED.value,
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
