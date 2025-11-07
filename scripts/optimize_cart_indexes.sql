-- Optional performance indexes for cart-related operations
-- Safe to run multiple times: uses IF NOT EXISTS where supported (MariaDB 10.5+); otherwise check manually

-- Index to speed up lookups per user
CREATE INDEX IF NOT EXISTS idx_cart_user ON cart (user_id);

-- Composite index for upsert (exists check by user+product+size+color)
CREATE INDEX IF NOT EXISTS idx_cart_user_product_size_color ON cart (user_id, product_id, size, color);

-- Optional: if your PK is not id, adjust column names accordingly
-- Example fallback statements (uncomment and edit when IF NOT EXISTS is unsupported):
-- -- Check existing indexes via: SHOW INDEX FROM cart;
-- -- Then create manually:
-- -- CREATE INDEX idx_cart_user ON cart (user_id);
-- -- CREATE INDEX idx_cart_user_product_size_color ON cart (user_id, product_id, size, color);
