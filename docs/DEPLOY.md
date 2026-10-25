# Deploy and upgrade

Fresh install steps are in the README under "Deploy on Hostinger". This file is the upgrade for a database created from the first `schema.sql`.

Upload the new code only after the SQL finishes. The new code reads `session_version`, `goods_value_cents`, and `gift_adjustments`. The old code still runs on the upgraded schema.

Do not import `schema.sql` into a database that already has tables. Do not merge, tag, or deploy from a review branch until this upgrade has been run on a backup first.

## Live database this upgrade was written for

The live database came from the first-release schema. It has the administrator account and no gifts. The first-release seed inserts five funds. The Hub fund is id 4. The migration marks that id with `code` `hub` and sets `receiving_org` to `unconfirmed`. It does not match the display name.

## Order

1. Back up the database before any change.

```bash
mysqldump -h HOST -u USER -p --single-transaction DATABASE > olive-branch-before-review.sql
```

2. Leave the current code in place until step 4 exits 0.

3. Run the pre-flight query. On this database it returns no rows, because there are no gifts.

```sql
SELECT id, gifted_on, goods_or_services
FROM gifts
WHERE goods_or_services IS NOT NULL;
```

If any row comes back, stop. Do not run the migration yet. Those gifts have a goods note and no good-faith estimate. `sql/migrate-review.sql` stops on its own when that is true, and it does not invent an estimate.

4. Run the migration with the mysql client. It is safe to run again. A second run does not add duplicate columns or tables.

```bash
mysql -h HOST -u USER -p DATABASE < sql/migrate-review.sql
```

Exit 0 means it finished. Exit 1 with `Stop: a gift has a goods note but no good-faith estimate.` means the pre-flight refused the upgrade. Fix those rows, then run the file again. Do not upload the new code after a non-zero exit.

5. Confirm the upgraded schema:

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

6. In `config.php`, leave `privacy.handled_days` unset. A missing key keeps every handled contact message and prayer request. Setting a positive number makes the next visit to `/admin/inbox` delete handled rows older than that many days. That choice is the church's. Do not copy a sample value of 90 into the live config.

7. Upload the new code. Open `/` and `/admin/login`.

Deleting an inbox row now asks for confirmation. The first submit does not delete it.
