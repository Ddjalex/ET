-- Fix foreign key references in deposits table
-- approved_by and rejected_by should reference admin_users, not users

-- Drop existing foreign keys if they exist
ALTER TABLE deposits DROP CONSTRAINT IF EXISTS deposits_approved_by_fkey;
ALTER TABLE deposits DROP CONSTRAINT IF EXISTS deposits_rejected_by_fkey;

-- Add correct foreign keys to admin_users table
ALTER TABLE deposits ADD CONSTRAINT deposits_approved_by_fkey 
    FOREIGN KEY (approved_by) REFERENCES admin_users(id);

ALTER TABLE deposits ADD CONSTRAINT deposits_rejected_by_fkey 
    FOREIGN KEY (rejected_by) REFERENCES admin_users(id);
