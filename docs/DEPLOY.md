# Deploy and upgrade

Fresh install steps are in the README under "Deploy on Hostinger". This file is the upgrade for a database created from the first `schema.sql`.

Upload the new code only after the SQL finishes. The new code reads `session_version`, `goods_value_cents`, and `gift_adjustments`. The old code still opens the public pages and the office on the upgraded schema. Do not record gifts in that window. A goods note saved by the old code hits the new check and returns an error, and no gift row is written.

Do not import `schema.sql` into a database that already has tables. Do not merge, tag, or deploy from a review branch until this upgrade has been run on a backup first.

## Live database this upgrade was written for

The live database came from the first-release schema. It has the administrator account and no gifts. The first-release seed inserts five funds. The Hub fund is id 4. The migration marks that id with `code` `hub` and sets `receiving_org` to `unconfirmed`. It does not match the display name.

## Order

1. Check the database engine before anything else. This file uses MariaDB syntax (`ADD COLUMN IF NOT EXISTS`, `ADD UNIQUE KEY IF NOT EXISTS`, and `ADD CONSTRAINT IF NOT EXISTS`). MySQL rejects those statements.

```sql
SELECT VERSION();
```

Continue only when the result is MariaDB 10.5 or newer. If it says MySQL, stop and do not run `sql/migrate-review.sql`.

2. Back up the database before any change.

```bash
mysqldump -h HOST -u USER -p --single-transaction DATABASE > olive-branch-before-review.sql
```

3. Leave the current code in place until step 5 exits 0. After that SQL succeeds, and until step 8, staff must not record gifts.

4. Run the pre-flight query. On a database with no gifts it returns no rows.

```sql
SELECT id, gifted_on, goods_or_services
FROM gifts
WHERE goods_or_services IS NOT NULL;
```

If any row comes back, stop. Do not run the migration yet. Those gifts have a goods note and no good-faith estimate. `sql/migrate-review.sql` stops on its own when that is true, and it does not invent an estimate. Do not clear `goods_or_services`. That would erase the fact that goods were provided.

Fix each flagged row with the church's own estimate, in cents, and not more than that gift's `amount_cents`. Add the column only when `SHOW COLUMNS FROM gifts LIKE 'goods_value_cents'` returns nothing:

```sql
ALTER TABLE gifts ADD COLUMN goods_value_cents BIGINT UNSIGNED NULL;

UPDATE gifts
SET goods_value_cents = 1500
WHERE id = 6
  AND goods_or_services IS NOT NULL
  AND goods_value_cents IS NULL;
```

Use the real gift id and the church's estimate. `1500` is an example of $15.00, not a value to copy. Repeat the `UPDATE` for every flagged id. Run the pre-flight query again. It must return no rows before you continue.

5. Run the migration with the mysql client. It is safe to run again. A second run does not add duplicate columns or tables.

```bash
mysql -h HOST -u USER -p DATABASE < sql/migrate-review.sql
```

Exit 0 means it finished. Exit 1 with `Stop: a gift has a goods note but no good-faith estimate.` means the pre-flight refused the upgrade. Fix the flagged rows as in step 4, then run the file again. In phpMyAdmin that same stop is an error on the import screen, not an exit code. Do not upload the new code after a failed run.

6. Confirm the upgraded schema:

```sql
SELECT id, name, code, receiving_org FROM funds ORDER BY id;

SELECT CONSTRAINT_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME IN ('chk_receiving_org', 'chk_goods_value');

SHOW COLUMNS FROM users LIKE 'session_version';
SHOW TABLES LIKE 'gift_adjustments';
SHOW TABLES LIKE 'gift_audit';
SHOW TABLES LIKE 'fund_audit';
```

Fund id 4 should show `code` `hub` and `receiving_org` `unconfirmed`. Both check constraints should be listed.

7. In `config.php`, leave `privacy.handled_days` unset. A missing key keeps every handled contact message and prayer request. Setting a positive number makes the next visit to `/admin/inbox` delete handled rows older than that many days. That choice is the church's. Do not copy a sample value of 90 into the live config.

8. Upload the new code. Open `/` and `/admin/login`. Staff may record gifts again only after this upload.

Deleting an inbox row now asks for confirmation. The first submit does not delete it.
