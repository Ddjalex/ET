-- Fix foreign key in settings table to reference admin_users instead of users
-- Created: October 20, 2025
-- Issue: Settings updates failed because updated_by referenced wrong table

-- Drop old foreign key constraint
ALTER TABLE settings DROP CONSTRAINT IF EXISTS settings_updated_by_fkey;

-- Add correct foreign key to admin_users table
ALTER TABLE settings ADD CONSTRAINT settings_updated_by_fkey 
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL;
