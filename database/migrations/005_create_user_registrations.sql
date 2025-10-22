-- Create user_registrations table for tracking multi-step Telegram registration flow
-- Created: October 22, 2025
-- Description: Staging table for registration conversation state

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
    completed_at TIMESTAMP
);

CREATE INDEX idx_user_registrations_telegram_id ON user_registrations(telegram_user_id);
CREATE INDEX idx_user_registrations_state ON user_registrations(registration_state);
CREATE INDEX idx_user_registrations_is_registered ON user_registrations(is_registered);
