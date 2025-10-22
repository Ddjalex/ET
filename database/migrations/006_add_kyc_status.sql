-- Add KYC status tracking to user_registrations table
-- Migration 006: Add kyc_status and strowallet_customer_id columns

ALTER TABLE user_registrations 
ADD COLUMN IF NOT EXISTS kyc_status VARCHAR(20) DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS strowallet_customer_id VARCHAR(100);

-- Create index for faster KYC status lookups
CREATE INDEX IF NOT EXISTS idx_user_registrations_kyc_status ON user_registrations(kyc_status);
CREATE INDEX IF NOT EXISTS idx_user_registrations_strowallet_id ON user_registrations(strowallet_customer_id);

COMMENT ON COLUMN user_registrations.kyc_status IS 'KYC verification status: pending, approved, rejected';
COMMENT ON COLUMN user_registrations.strowallet_customer_id IS 'Customer ID from StroWallet API';
