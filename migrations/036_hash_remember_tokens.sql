-- Migration: Invalidate all existing plaintext remember tokens
-- After this migration, all users with "remember me" will need to re-login once.
-- New tokens will be stored as SHA-256 hashes for security.
UPDATE users SET remember_token = NULL WHERE remember_token IS NOT NULL;
