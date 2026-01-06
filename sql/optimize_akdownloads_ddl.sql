-- Optimization DDL for akdownloads_ak23studio4
-- Run during a maintenance window; DDL in MySQL causes implicit commits.

-- 1) Ensure database default charset/collation
ALTER DATABASE `akdownloads_ak23studio4`
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- 2) Convert legacy MyISAM tables to InnoDB
ALTER TABLE `activity_log` ENGINE=InnoDB;
ALTER TABLE `admin_users` ENGINE=InnoDB;
ALTER TABLE `contact_messages` ENGINE=InnoDB;
ALTER TABLE `downloads_log` ENGINE=InnoDB;
ALTER TABLE `orders` ENGINE=InnoDB;
ALTER TABLE `payments` ENGINE=InnoDB;

-- 3) Normalize charsets/collations on outliers
ALTER TABLE `ipn_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 4) Add missing primary keys / auto-increment where appropriate
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `activity_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `payment_methods`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

-- 5) Practical secondary indexes for common access patterns
-- activity_log lookups and listing
CREATE INDEX `idx_activity_created` ON `activity_log` (`created_at`);
CREATE INDEX `idx_activity_subject` ON `activity_log` (`subject_type`, `subject_id`);
CREATE INDEX `idx_activity_causer` ON `activity_log` (`causer_type`, `causer_id`);

-- contact_messages moderation/search
CREATE INDEX `idx_contact_email` ON `contact_messages` (`email`);
CREATE INDEX `idx_contact_user` ON `contact_messages` (`user_id`);
-- Optional: fulltext supports fast keyword search (InnoDB supports FULLTEXT on utf8mb4)
CREATE FULLTEXT INDEX `ft_contact_subject_message` ON `contact_messages` (`subject`, `message`);

-- downloads_log analytics
CREATE INDEX `idx_dl_downloaded_at` ON `downloads_log` (`downloaded_at`);

-- orders dashboards
CREATE INDEX `idx_orders_user_status_created` ON `orders` (`user_id`, `status`, `created_at`);
CREATE INDEX `idx_orders_callback_used` ON `orders` (`callback_used_at`);

-- payments reporting
CREATE INDEX `idx_payments_paid_at` ON `payments` (`paid_at`);

-- courses catalog search
CREATE FULLTEXT INDEX `ft_courses_search` ON `courses` (`name`, `description`);

-- notifications listing
CREATE INDEX `idx_notifications_created` ON `notifications` (`created_at`);

-- otps validation paths
CREATE INDEX `idx_otps_phone_code_used` ON `otps` (`phone`, `code`, `used_at`);

-- ipn_logs query helpers
CREATE INDEX `idx_ipn_order_created` ON `ipn_logs` (`order_tracking_id`, `created_at`);

-- mail_outbox queue processing
CREATE INDEX `idx_outbox_sent_created` ON `mail_outbox` (`sent_at`, `created_at`);

-- 6) Add safe foreign keys where types and engines match
-- payments.order_id -> orders.id (both INT)
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order`
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- medias.courses_id -> courses.id (both BIGINT UNSIGNED)
ALTER TABLE `medias`
  ADD CONSTRAINT `fk_medias_course`
  FOREIGN KEY (`courses_id`) REFERENCES `courses`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- media_share_tokens.media_id -> medias.id (both BIGINT UNSIGNED)
ALTER TABLE `media_share_tokens`
  ADD CONSTRAINT `fk_media_share_tokens_media`
  FOREIGN KEY (`media_id`) REFERENCES `medias`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;
