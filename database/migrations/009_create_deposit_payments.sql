-- Create table for tracking deposit payments with screenshot and transaction verification
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

-- Create indexes for faster queries
CREATE INDEX idx_deposit_payments_telegram_id ON deposit_payments(telegram_id);
CREATE INDEX idx_deposit_payments_status ON deposit_payments(status);
CREATE INDEX idx_deposit_payments_validation_status ON deposit_payments(validation_status);
CREATE INDEX idx_deposit_payments_transaction_number ON deposit_payments(transaction_number);
CREATE INDEX idx_deposit_payments_created_at ON deposit_payments(created_at);

-- Create trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_deposit_payments_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_deposit_payments_updated_at
    BEFORE UPDATE ON deposit_payments
    FOR EACH ROW
    EXECUTE FUNCTION update_deposit_payments_updated_at();
