ALTER TABLE users
    ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL AFTER email_verified_at;
