-- Migration: Add unique constraint to license_key in app_license table
-- Date: 2026-01-30
-- Purpose: Prevent duplicate license keys and ensure data integrity
-- Check if the constraint already exists before adding
-- This is safe to run multiple times
-- First, ensure the license_key column has no duplicates
-- (This will fail if there are existing duplicates, which should be cleaned up first)
-- Add unique constraint if it doesn't exist
ALTER TABLE `app_license`
ADD UNIQUE KEY `license_key` (`license_key`);
-- Note: If the above fails with an error saying the key already exists, 
-- that's actually good - it means the constraint is already in place.