-- Broadcaster Module Tables
-- Enables admin to create and send broadcasts to Telegram channel and in-app feed

-- Broadcasts table: stores broadcast content and configuration
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

-- Broadcast logs: detailed send history and errors
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

-- Giveaway entries: tracks button clicks for giveaway tracking
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

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_broadcasts_status ON broadcasts(status);
CREATE INDEX IF NOT EXISTS idx_broadcasts_scheduled ON broadcasts(scheduled_for) WHERE status = 'scheduled';
CREATE INDEX IF NOT EXISTS idx_broadcast_logs_broadcast ON broadcast_logs(broadcast_id);
CREATE INDEX IF NOT EXISTS idx_giveaway_entries_broadcast ON giveaway_entries(broadcast_id);
CREATE INDEX IF NOT EXISTS idx_giveaway_entries_user ON giveaway_entries(user_id);
