USE yeb_portal;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    device_token VARCHAR(100) NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NULL,
    auth VARCHAR(255) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_push_user (user_id),
    INDEX idx_push_device_token (device_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(150) NULL DEFAULT NULL AFTER message;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS url VARCHAR(255) NULL DEFAULT NULL AFTER title;
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS device_token VARCHAR(100) NULL AFTER user_id;
