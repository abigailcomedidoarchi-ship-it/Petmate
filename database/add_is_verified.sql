USE petmate;
ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0;
