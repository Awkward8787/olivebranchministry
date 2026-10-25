-- Upgrade a database created from the first release of schema.sql.
-- Order: back up the database, run this file, then upload the new code.
-- Do not import schema.sql into a database that already has tables.
-- This file does not delete or rewrite gift rows. It is safe to run again.
--
-- Pre-flight. Stop when a gift has a goods note and no good-faith estimate.
-- On a first-release database, list those rows with:
--   SELECT id, gifted_on, goods_or_services
--   FROM gifts
--   WHERE goods_or_services IS NOT NULL;
-- After goods_value_cents exists, the same rows are:
--   SELECT id, gifted_on, goods_or_services
--   FROM gifts
--   WHERE goods_or_services IS NOT NULL AND goods_value_cents IS NULL;
-- A database with no gifts returns no rows and this file continues.
-- The first-release seed inserts the Hub fund as id 4. This file sets
-- code = 'hub' on that id and then sets receiving_org from the code.
-- It does not match the fund's display name.

SET @obm_missing_estimate := 0;
SET @obm_has_goods_value := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'gifts'
      AND COLUMN_NAME = 'goods_value_cents'
);
SET @obm_count_sql := IF(
    @obm_has_goods_value = 0,
    'SELECT COUNT(*) INTO @obm_missing_estimate FROM gifts WHERE goods_or_services IS NOT NULL',
    'SELECT COUNT(*) INTO @obm_missing_estimate FROM gifts WHERE goods_or_services IS NOT NULL AND goods_value_cents IS NULL'
);
PREPARE obm_count FROM @obm_count_sql;
EXECUTE obm_count;
DEALLOCATE PREPARE obm_count;

SET @obm_stop_sql := IF(
    IFNULL(@obm_missing_estimate, 1) = 0,
    'DO 0',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Stop: a gift has a goods note but no good-faith estimate.'''
);
PREPARE obm_stop FROM @obm_stop_sql;
EXECUTE obm_stop;
DEALLOCATE PREPARE obm_stop;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS disabled_at DATETIME NULL;

ALTER TABLE funds
    ADD COLUMN IF NOT EXISTS receiving_org VARCHAR(20) NOT NULL DEFAULT 'church';

ALTER TABLE funds
    ADD COLUMN IF NOT EXISTS code VARCHAR(40) NULL;

ALTER TABLE funds
    ADD UNIQUE KEY IF NOT EXISTS uq_funds_code (code);

UPDATE funds SET code = 'hub' WHERE id = 4 AND (code IS NULL OR code = '');

UPDATE funds
    SET receiving_org = 'unconfirmed'
    WHERE code = 'hub';

ALTER TABLE gifts
    ADD COLUMN IF NOT EXISTS goods_value_cents BIGINT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS gift_adjustments (
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

CREATE TABLE IF NOT EXISTS gift_audit (
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

CREATE TABLE IF NOT EXISTS fund_audit (
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

ALTER TABLE funds
    ADD CONSTRAINT IF NOT EXISTS chk_receiving_org CHECK (receiving_org IN ('church', 'unconfirmed'));

ALTER TABLE gifts
    ADD CONSTRAINT IF NOT EXISTS chk_goods_value CHECK (
        (goods_or_services IS NULL AND goods_value_cents IS NULL)
        OR (goods_or_services IS NOT NULL AND goods_value_cents IS NOT NULL)
    );
