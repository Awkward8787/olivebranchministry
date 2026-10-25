-- Olive Branch Ministries Inc.
-- Fresh database. This file does not insert people, gifts, messages, or attendance.
-- Service times are the church's published schedule. Funds are the requested list.
-- Money is integer cents (amount_cents). Gift and attendance dates are inclusive
-- calendar dates interpreted in America/New_York by the application.
-- Create the first admin from the server, not from this file:
--   ADMIN_EMAIL=... ADMIN_PASSWORD=... php bin/seed-admin.php

SET NAMES utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL,
    name VARCHAR(120) NOT NULL,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    disabled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    succeeded TINYINT(1) NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_email (email, attempted_at),
    KEY idx_login_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    bucket CHAR(64) NOT NULL,
    window_start DATETIME NOT NULL,
    hits INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_times (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    note VARCHAR(180) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    weekly TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT chk_weekday CHECK (weekday BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    summary TEXT NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    location VARCHAR(180) NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_events_start (published, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    handled_at DATETIME NULL,
    handled_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_contact_handled (handled_at, created_at),
    CONSTRAINT fk_contact_user FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prayer_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    request TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    handled_at DATETIME NULL,
    handled_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_prayer_handled (handled_at, created_at),
    CONSTRAINT fk_prayer_user FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE people (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    kind ENUM('member', 'visitor') NOT NULL,
    follow_up_status ENUM('new', 'contacted') NOT NULL DEFAULT 'new',
    follow_up_on DATE NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_people_follow (kind, follow_up_status, follow_up_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE funds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    receiving_org VARCHAR(20) NOT NULL DEFAULT 'church',
    code VARCHAR(40) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_funds_name (name),
    UNIQUE KEY uq_funds_code (code),
    CONSTRAINT chk_receiving_org CHECK (receiving_org IN ('church', 'unconfirmed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gifts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id INT UNSIGNED NULL,
    fund_id INT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    gifted_on DATE NOT NULL,
    method ENUM('cash', 'check', 'online', 'other') NOT NULL,
    reference VARCHAR(80) NULL,
    note VARCHAR(500) NULL,
    goods_or_services VARCHAR(500) NULL,
    goods_value_cents BIGINT UNSIGNED NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_gifts_date (gifted_on),
    KEY idx_gifts_person_date (person_id, gifted_on),
    KEY idx_gifts_fund_date (fund_id, gifted_on),
    CONSTRAINT fk_gifts_person FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
    CONSTRAINT fk_gifts_fund FOREIGN KEY (fund_id) REFERENCES funds (id) ON DELETE RESTRICT,
    CONSTRAINT fk_gifts_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_goods_value CHECK (
        (goods_or_services IS NULL AND goods_value_cents IS NULL)
        OR (goods_or_services IS NOT NULL AND goods_value_cents IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gift_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gift_id INT UNSIGNED NOT NULL,
    kind ENUM('adjustment', 'void') NOT NULL,
    amount_cents BIGINT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_adj_gift (gift_id),
    CONSTRAINT fk_adj_gift FOREIGN KEY (gift_id) REFERENCES gifts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_adj_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gift_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gift_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    detail VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_gift (gift_id, id),
    CONSTRAINT fk_audit_gift FOREIGN KEY (gift_id) REFERENCES gifts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_audit_user FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fund_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fund_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    detail VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fund_audit (fund_id, id),
    CONSTRAINT fk_fund_audit_fund FOREIGN KEY (fund_id) REFERENCES funds (id) ON DELETE RESTRICT,
    CONSTRAINT fk_fund_audit_user FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_time_id INT UNSIGNED NULL,
    service_label VARCHAR(120) NOT NULL,
    attended_on DATE NOT NULL,
    headcount INT UNSIGNED NOT NULL,
    notes VARCHAR(500) NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attendance_date (attended_on),
    CONSTRAINT fk_attendance_service FOREIGN KEY (service_time_id) REFERENCES service_times (id) ON DELETE SET NULL,
    CONSTRAINT fk_attendance_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    name VARCHAR(80) NOT NULL,
    value VARCHAR(255) NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekday: 0 Sunday through 6 Saturday. weekly = 0 for the fourth Sunday only.
INSERT INTO service_times (name, weekday, start_time, end_time, note, sort_order, active, weekly) VALUES
    ('Sunday School', 0, '09:30:00', NULL, NULL, 10, 1, 1),
    ('Worship', 0, '11:00:00', NULL, NULL, 20, 1, 1),
    ('Bible Study & Youth', 4, '18:30:00', NULL, NULL, 30, 1, 1),
    ('Evening Service', 0, '18:00:00', NULL, 'Fourth Sunday', 40, 1, 0),
    ('Prayer & Fasting', 6, '10:00:00', '12:00:00', NULL, 50, 1, 1);

INSERT INTO funds (name, sort_order, active, receiving_org, code) VALUES
    ('General', 10, 1, 'church', NULL),
    ('Tithes', 20, 1, 'church', NULL),
    ('Offering', 30, 1, 'church', NULL),
    ('The Hub', 40, 1, 'unconfirmed', 'hub'),
    ('Building', 50, 1, 'church', NULL);
