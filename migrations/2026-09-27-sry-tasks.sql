-- Additive and repeatable. Run after the existing php-core schema and auth migrations.
-- No existing tenant data is changed. All sry business tables use family ownership.
CREATE TABLE IF NOT EXISTS sry_family (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 franchise_code VARCHAR(64) NOT NULL DEFAULT 'sry',
 owner_user_id INT UNSIGNED NOT NULL UNIQUE,
 timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (owner_user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_member (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, family_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NULL UNIQUE, name VARCHAR(100) NOT NULL,
 role ENUM('admin','user') NOT NULL, daily_target INT UNSIGNED NOT NULL DEFAULT 100,
 wifi_allowed TINYINT NOT NULL DEFAULT 1, data_allowed TINYINT NOT NULL DEFAULT 1,
 active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY member_family(id,family_id), FOREIGN KEY(family_id) REFERENCES sry_family(id),
 FOREIGN KEY(user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_session (
 token_hash CHAR(64) PRIMARY KEY, member_id INT UNSIGNED NOT NULL,
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(member_id) REFERENCES sry_member(id), KEY session_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_invitation (
 token_hash CHAR(64) PRIMARY KEY, family_id INT UNSIGNED NOT NULL,
 child_id INT UNSIGNED NULL, created_by INT UNSIGNED NOT NULL,
 expires_at DATETIME NOT NULL, consumed_at DATETIME NULL,
 FOREIGN KEY(family_id) REFERENCES sry_family(id), FOREIGN KEY(child_id,family_id) REFERENCES sry_member(id,family_id),
 FOREIGN KEY(created_by) REFERENCES sry_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tasks (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, franchise_code VARCHAR(64) NOT NULL DEFAULT 'sry',
 family_id INT UNSIGNED NOT NULL, created_by INT UNSIGNED NOT NULL,
 title VARCHAR(160) NOT NULL, description TEXT NOT NULL, points INT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NULL, enumeration_id INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY task_family(id,family_id), FOREIGN KEY(family_id) REFERENCES sry_family(id),
 FOREIGN KEY(created_by,family_id) REFERENCES sry_member(id,family_id),
 FOREIGN KEY(category_id) REFERENCES category(id), FOREIGN KEY(enumeration_id) REFERENCES enumeration(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS task_assignment (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, family_id INT UNSIGNED NOT NULL,
 task_id INT UNSIGNED NOT NULL, member_id INT UNSIGNED NOT NULL, due_date DATE NOT NULL,
 points INT UNSIGNED NOT NULL, status ENUM('assigned','submitted','approved','returned') NOT NULL DEFAULT 'assigned',
 revision INT UNSIGNED NOT NULL DEFAULT 1, current_submission_id INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY assignment_family(id,family_id), KEY member_day(member_id,due_date), KEY family_date(family_id,due_date,id),
 FOREIGN KEY(task_id,family_id) REFERENCES tasks(id,family_id),
 FOREIGN KEY(member_id,family_id) REFERENCES sry_member(id,family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_media (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, family_id INT UNSIGNED NOT NULL, member_id INT UNSIGNED NOT NULL,
 object_key VARCHAR(255) NOT NULL UNIQUE, mime VARCHAR(64) NOT NULL, byte_size INT UNSIGNED NOT NULL,
 state ENUM('pending','ready') NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY media_family(id,family_id), FOREIGN KEY(member_id,family_id) REFERENCES sry_member(id,family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS task_media (
 task_id INT UNSIGNED NOT NULL, media_id INT UNSIGNED NOT NULL, family_id INT UNSIGNED NOT NULL,
 PRIMARY KEY(task_id,media_id), FOREIGN KEY(task_id,family_id) REFERENCES tasks(id,family_id),
 FOREIGN KEY(media_id,family_id) REFERENCES sry_media(id,family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS task_submission (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, assignment_id INT UNSIGNED NOT NULL,
 family_id INT UNSIGNED NOT NULL, member_id INT UNSIGNED NOT NULL, media_id INT UNSIGNED NOT NULL,
 note VARCHAR(2000) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(assignment_id,family_id) REFERENCES task_assignment(id,family_id),
 FOREIGN KEY(member_id,family_id) REFERENCES sry_member(id,family_id),
 FOREIGN KEY(media_id,family_id) REFERENCES sry_media(id,family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS task_review (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id INT UNSIGNED NOT NULL UNIQUE,
 reviewer_id INT UNSIGNED NOT NULL, decision ENUM('approved','returned') NOT NULL,
 note VARCHAR(2000) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(submission_id) REFERENCES task_submission(id), FOREIGN KEY(reviewer_id) REFERENCES sry_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS task_points (
 assignment_id INT UNSIGNED PRIMARY KEY, member_id INT UNSIGNED NOT NULL,
 points INT UNSIGNED NOT NULL, earned_on DATE NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(assignment_id) REFERENCES task_assignment(id), FOREIGN KEY(member_id) REFERENCES sry_member(id),
 KEY points_day(member_id,earned_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_notification (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL,
 event VARCHAR(48) NOT NULL, entity_id INT UNSIGNED NOT NULL DEFAULT 0,
 read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(member_id) REFERENCES sry_member(id), KEY member_read(member_id,read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_outbox (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL,
 topic VARCHAR(32) NOT NULL, entity_id INT UNSIGNED NOT NULL,
 event VARCHAR(48) NOT NULL, delivered_at DATETIME NULL, realtime_at DATETIME NULL, push_at DATETIME NULL, attempts INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY pending_delivery(delivered_at,attempts,id),
 FOREIGN KEY(member_id) REFERENCES sry_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_push_device (
 token VARCHAR(255) PRIMARY KEY, member_id INT UNSIGNED NOT NULL, language VARCHAR(2) NOT NULL DEFAULT 'cs',
 FOREIGN KEY(member_id) REFERENCES sry_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_chat (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, family_id INT UNSIGNED NOT NULL,
 sender_id INT UNSIGNED NOT NULL, recipient_id INT UNSIGNED NOT NULL,
 body VARCHAR(2000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(sender_id,family_id) REFERENCES sry_member(id,family_id),
 FOREIGN KEY(recipient_id,family_id) REFERENCES sry_member(id,family_id), KEY thread_lookup(family_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sry_password_reset (
 token_hash CHAR(64) PRIMARY KEY, user_id INT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL,
 FOREIGN KEY(user_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO role(franchise_code,name,label) VALUES('sry','user','SRY account') ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO category(franchise_code,syscode,name) VALUES
 ('sry','home','Domácí práce'),('sry','outdoor','Venkovní práce'),('sry','school','Škola'),('sry','clubs','Kroužky')
 ON DUPLICATE KEY UPDATE syscode=VALUES(syscode);
