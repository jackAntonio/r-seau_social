-- Migration 001 : Rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at),
    INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration 002 : 2FA TOTP
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) DEFAULT 0;

-- Migration 003 : Système de rôles
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role ENUM('user','moderateur','admin') DEFAULT 'user';

-- Migration 004 : Logs d'audit
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration 005 : Expiration des stories (colonne expires_at)
ALTER TABLE stories
    ADD COLUMN IF NOT EXISTS expires_at DATETIME DEFAULT NULL;

UPDATE stories SET expires_at = DATE_ADD(created_at, INTERVAL 24 HOUR) WHERE expires_at IS NULL;

-- Migration 006 : Index pagination posts
ALTER TABLE posts ADD INDEX IF NOT EXISTS idx_created_desc (created_at DESC);
