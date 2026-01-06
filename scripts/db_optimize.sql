-- Database optimization: recommended indexes for common queries
-- Run this once in your DB (e.g., via phpMyAdmin or mysql cli)

-- courses
ALTER TABLE courses
  ADD INDEX idx_courses_slug (slug),
  ADD INDEX idx_courses_category (category_id),
  ADD INDEX idx_courses_created (created_at),
  ADD INDEX idx_courses_price (price);

-- Categories
ALTER TABLE categories
  ADD INDEX idx_categories_parent (parent_id),
  ADD INDEX idx_categories_slug (slug);

-- Users
ALTER TABLE users
  ADD INDEX idx_users_email (email),
  ADD INDEX idx_users_phone (phone);

-- Orders
ALTER TABLE orders
  ADD INDEX idx_orders_user (user_id),
  ADD INDEX idx_orders_status (status),
  ADD INDEX idx_orders_created (created_at);

-- Payments
ALTER TABLE payments
  ADD INDEX idx_payments_order (order_id),
  ADD INDEX idx_payments_status (status),
  ADD INDEX idx_payments_created (created_at);

-- Downloads log
ALTER TABLE downloads_log
  ADD INDEX idx_downloads_order (order_id),
  ADD INDEX idx_downloads_time (downloaded_at);
