-- Admin Panel System - Database Schema Migration
-- Created: October 20, 2025
-- Description: Complete schema for wallet, card, and admin management

-- Users table (registration with email, phone, name)
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

CREATE INDEX idx_users_telegram_id ON users(telegram_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_kyc_status ON users(kyc_status);

-- Wallets table (user balances in USD and ETB)
CREATE TABLE IF NOT EXISTS wallets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    balance_usd DECIMAL(15, 2) DEFAULT 0.00 CHECK (balance_usd >= 0),
    balance_etb DECIMAL(15, 2) DEFAULT 0.00 CHECK (balance_etb >= 0),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'frozen', 'closed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_wallets_user_id ON wallets(user_id);

-- Wallet transactions (all money movements)
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

CREATE INDEX idx_wallet_transactions_wallet_id ON wallet_transactions(wallet_id);
CREATE INDEX idx_wallet_transactions_user_id ON wallet_transactions(user_id);
CREATE INDEX idx_wallet_transactions_type ON wallet_transactions(transaction_type);
CREATE INDEX idx_wallet_transactions_status ON wallet_transactions(status);

-- Deposits table (user deposit requests with admin approval)
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

CREATE INDEX idx_deposits_user_id ON deposits(user_id);
CREATE INDEX idx_deposits_wallet_id ON deposits(wallet_id);
CREATE INDEX idx_deposits_status ON deposits(status);
CREATE INDEX idx_deposits_created_at ON deposits(created_at);

-- Cards table (StroWallet virtual cards)
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

CREATE INDEX idx_cards_user_id ON cards(user_id);
CREATE INDEX idx_cards_strow_card_id ON cards(strow_card_id);
CREATE INDEX idx_cards_status ON cards(status);

-- Card transactions (all card activity)
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

CREATE INDEX idx_card_transactions_card_id ON card_transactions(card_id);
CREATE INDEX idx_card_transactions_user_id ON card_transactions(user_id);
CREATE INDEX idx_card_transactions_type ON card_transactions(transaction_type);
CREATE INDEX idx_card_transactions_date ON card_transactions(transaction_date);

-- Settings table (system configuration)
CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(100) PRIMARY KEY,
    value JSONB NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER REFERENCES users(id)
);

-- Admin actions audit log
CREATE TABLE IF NOT EXISTS admin_actions (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL REFERENCES users(id),
    action_type VARCHAR(50) NOT NULL,
    target_table VARCHAR(50),
    target_id INTEGER,
    action_description TEXT,
    payload JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admin_actions_admin_id ON admin_actions(admin_id);
CREATE INDEX idx_admin_actions_type ON admin_actions(action_type);
CREATE INDEX idx_admin_actions_created_at ON admin_actions(created_at);

-- Admin users table (separate from regular users)
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

CREATE INDEX idx_admin_users_username ON admin_users(username);
CREATE INDEX idx_admin_users_email ON admin_users(email);

-- Insert default settings
INSERT INTO settings (key, value, description) VALUES 
('exchange_rate_usd_to_etb', '{"rate": 130.50, "last_updated": "2025-10-20"}', 'USD to ETB exchange rate'),
('card_creation_fee', '{"percentage": 1.99, "flat": 1.99, "currency": "USD"}', 'Card creation fee structure'),
('card_topup_fee', '{"percentage": 1.99, "flat": 1.99, "currency": "USD"}', 'Card top-up fee structure'),
('deposit_fee', '{"percentage": 0.00, "flat": 0.00, "currency": "ETB"}', 'Deposit fee structure'),
('card_limits', '{"min_topup": 5, "max_topup": 10000, "daily_limit": 1000}', 'Card transaction limits'),
('kyc_requirements', '{"require_id_image": true, "require_photo": true, "require_address": true}', 'KYC verification requirements'),
('system_status', '{"maintenance_mode": false, "allow_deposits": true, "allow_card_creation": true}', 'System operational status')
ON CONFLICT (key) DO NOTHING;

-- Create default admin user (password: admin123 - CHANGE THIS IMMEDIATELY)
-- Password hash for 'admin123' using PHP password_hash
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@cardbot.local', '$2y$10$44B3Z2K3NJ9jU7pm7jp0ee3QX89F3yPD1r2wwPASeGAQPfmNFXRwG', 'System Administrator', 'super_admin')
ON CONFLICT (username) DO NOTHING;

-- Trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_wallets_updated_at BEFORE UPDATE ON wallets FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_wallet_transactions_updated_at BEFORE UPDATE ON wallet_transactions FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_deposits_updated_at BEFORE UPDATE ON deposits FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_cards_updated_at BEFORE UPDATE ON cards FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_admin_users_updated_at BEFORE UPDATE ON admin_users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
