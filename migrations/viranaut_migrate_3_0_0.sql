-- ViraNaut 3.0.0 — Pro feature schema stubs
CREATE TABLE IF NOT EXISTS site_admin_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL,
    message TEXT NULL,
    photo_file_id VARCHAR(255) NULL,
    status VARCHAR(32) DEFAULT 'pending',
    admin_reply TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_panel_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user VARCHAR(32) NOT NULL UNIQUE,
    api_token VARCHAR(64) NOT NULL,
    theme VARCHAR(32) DEFAULT 'default',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO shopSetting (Namevalue, value) VALUES ('viranaut_version', '3.0.0-ViraNaut')
ON DUPLICATE KEY UPDATE value = '3.0.0-ViraNaut';
