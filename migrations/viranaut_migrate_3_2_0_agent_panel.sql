-- ViraNaut 3.2.0 — agent-panel Pro schema

ALTER TABLE agent_panel_tokens
    ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS onboarded TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS notify_telegram TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS lang VARCHAR(8) DEFAULT 'fa';

CREATE TABLE IF NOT EXISTS agent_panel_tokens_multi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL,
    api_token VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(64) NOT NULL DEFAULT 'default',
    rate_limit INT NOT NULL DEFAULT 60,
    last_used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_multi_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_api_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NULL,
    id_user VARCHAR(32) NOT NULL,
    action VARCHAR(64) NOT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_api_log_user (id_user),
    INDEX idx_agent_api_log_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_login_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_login_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL,
    url VARCHAR(512) NOT NULL,
    secret VARCHAR(64) NULL,
    events JSON NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_webhooks_user (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL,
    type VARCHAR(32) NOT NULL,
    payload JSON NULL,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_notif_user (id_user, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_action_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL,
    username VARCHAR(128) NULL,
    action VARCHAR(64) NOT NULL,
    detail TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agent_action_user (id_user),
    INDEX idx_agent_action_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_2fa_pending (
    id_user VARCHAR(32) NOT NULL PRIMARY KEY,
    code VARCHAR(8) NOT NULL,
    expires_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE site_admin_requests
    ADD COLUMN IF NOT EXISTS source VARCHAR(32) DEFAULT 'site';

INSERT INTO shopSetting (Namevalue, value) VALUES ('viranaut_version', '3.2.0-ViraNaut')
ON DUPLICATE KEY UPDATE value = '3.2.0-ViraNaut';
