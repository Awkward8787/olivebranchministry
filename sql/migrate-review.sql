-- Upgrade a database created from the first release of schema.sql.
-- Run once. Importing schema.sql again into a live database will fail.
-- Gifts stay in place. This file does not delete gift rows.

ALTER TABLE users
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN disabled_at DATETIME NULL;

ALTER TABLE funds
    ADD COLUMN receiving_org VARCHAR(20) NOT NULL DEFAULT 'church';

UPDATE funds SET receiving_org = 'unconfirmed' WHERE name = 'The Hub';

ALTER TABLE gifts
    ADD COLUMN goods_value_cents BIGINT UNSIGNED NULL;

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
