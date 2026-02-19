-- Supabase performance indexes for this project.
-- Run once in Supabase SQL Editor.

CREATE INDEX IF NOT EXISTS idx_customers_email_lower
ON customers (LOWER(email));

CREATE INDEX IF NOT EXISTS idx_admins_email_lower
ON admins (LOWER(email));

CREATE INDEX IF NOT EXISTS idx_books_author_id
ON books (author_id);

CREATE INDEX IF NOT EXISTS idx_books_category_id
ON books (category_id);
