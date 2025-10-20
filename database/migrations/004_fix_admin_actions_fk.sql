-- Fix foreign key references in admin_actions and settings tables
-- These tables should reference admin_users, not users

-- Fix admin_actions table
ALTER TABLE admin_actions DROP CONSTRAINT IF EXISTS admin_actions_admin_id_fkey;
ALTER TABLE admin_actions ADD CONSTRAINT admin_actions_admin_id_fkey 
    FOREIGN KEY (admin_id) REFERENCES admin_users(id);

-- Fix settings table
ALTER TABLE settings DROP CONSTRAINT IF EXISTS settings_updated_by_fkey;
ALTER TABLE settings ADD CONSTRAINT settings_updated_by_fkey 
    FOREIGN KEY (updated_by) REFERENCES admin_users(id);
