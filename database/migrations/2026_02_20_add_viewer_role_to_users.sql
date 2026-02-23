-- Migration: Add 'viewer' and 'user' roles to users table ENUM
-- Date: 2026-02-20
-- Description: Expands the role ENUM to include 'user' and 'viewer' roles
--              for read-only audit access

ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'support', 'user', 'viewer') DEFAULT 'admin';
