CREATE DATABASE IF NOT EXISTS `asics_mining`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `asics_mining`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `account_id` VARCHAR(191) NOT NULL,
  `uid` VARCHAR(191) NOT NULL,
  `balance` DECIMAL(16,8) NOT NULL DEFAULT 0,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `avatar` VARCHAR(255) NULL,
  `bio` VARCHAR(500) NULL,
  `referral_code` VARCHAR(32) NULL,
  `referred_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_account_id_unique` (`account_id`),
  UNIQUE KEY `users_uid_unique` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `price_usd` DECIMAL(16,8) NOT NULL,
  `total_return_usd` DECIMAL(16,8) NOT NULL,
  `daily_profit_usd` DECIMAL(16,8) NOT NULL,
  `hashrate` DECIMAL(16,8) NOT NULL,
  `icon` VARCHAR(32) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_addresses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `coin` VARCHAR(16) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_user_coin` (`user_id`, `coin`),
  CONSTRAINT `wallet_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `coin` VARCHAR(16) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `amount_usd` DECIMAL(16,8) NOT NULL,
  `amount_coin` DECIMAL(16,8) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `txid` VARCHAR(191) NULL,
  `admin_note` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `withdrawals_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referral_rewards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT UNSIGNED NOT NULL,
  `referred_id` INT UNSIGNED NOT NULL,
  `amount_usd` DECIMAL(16,8) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `referral_referrer_fk` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_referred_fk` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `invoice_code` VARCHAR(191) NOT NULL,
  `network` VARCHAR(32) NOT NULL,
  `address` VARCHAR(191) NOT NULL,
  `amount_usd` DECIMAL(16,8) NOT NULL,
  `amount_crypto` DECIMAL(16,8) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `txid` VARCHAR(191) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_invoice_code_unique` (`invoice_code`),
  CONSTRAINT `payments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `payment_id` INT UNSIGNED NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `purchased_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `earnings_paid` DECIMAL(16,8) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `purchases_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchases_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `purchases_payment_fk` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(191) NOT NULL,
  `amount` DECIMAL(16,8) NOT NULL,
  `status` VARCHAR(32) NOT NULL,
  `reference` VARCHAR(191) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `transactions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `message` TEXT NOT NULL,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `users`
  (`id`, `name`, `email`, `password_hash`, `account_id`, `uid`, `balance`, `is_admin`, `referral_code`)
VALUES
  (1, 'Demo User', 'demo@asics.test', '$2y$12$1S4.glEeydnZZ26Xe.9Ep.UkFQfWYKKBQMukA8VLGTUoKjyIwkOBq', '0505050', '8358-4562-4521', 400, 1, 'ASIC-DEMO')
ON DUPLICATE KEY UPDATE
  `is_admin` = 1,
  `referral_code` = COALESCE(`referral_code`, 'ASIC-DEMO');

INSERT INTO `products`
  (`id`, `name`, `price_usd`, `total_return_usd`, `daily_profit_usd`, `hashrate`, `icon`, `active`)
VALUES
  (1, 'Antminer DR7 127 Th', 10.00, 19.00, 2.71, 127, '▧', 1),
  (2, 'Antminer KA3 (166 Th)', 30.00, 61.00, 3.39, 166, '▦', 1),
  (3, 'Bitmain Antminer S23', 50.00, 111.00, 3.70, 195, '▥', 1),
  (4, 'Bitmain Antminer S21 Pro', 100.00, 350.00, 11.67, 234, '▤', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `price_usd` = VALUES(`price_usd`),
  `total_return_usd` = VALUES(`total_return_usd`),
  `daily_profit_usd` = VALUES(`daily_profit_usd`),
  `hashrate` = VALUES(`hashrate`),
  `icon` = VALUES(`icon`),
  `active` = VALUES(`active`);

INSERT IGNORE INTO `transactions`
  (`id`, `user_id`, `type`, `amount`, `status`, `reference`)
VALUES
  (1, 1, 'Welcome bonus', 400, 'completed', 'WELCOME');

INSERT IGNORE INTO `notifications`
  (`id`, `user_id`, `title`, `message`)
VALUES
  (1, 1, 'Welcome to ASICs Mining', 'Your account is ready to start mining.');
