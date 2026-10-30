# Rolling back a deploy

`scripts/deploy.sh` takes a database backup before it changes anything, and
writes where it started from to `storage/app/last-deploy-from`:

```
commit=<the commit the server was on before this deploy>
backup=<the backup it took, e.g. mybooks-2026-10-30-091500.zip>
at=<when, UTC>
```

It prints the same at the end of the deploy. Keep that line until you are
sure the deploy is good.

There are two ways back, depending on what the deploy changed.

- **Code only** (no new migrations, or migrations that only added tables or
  columns): go back to the previous commit and, if needed, undo those
  migrations. Nothing entered since the deploy is lost.
- **Data changed** (a migration moved, rewrote or deleted data, or you are
  not sure): go back to the previous commit *and* restore the pre-deploy
  backup. Everything entered since the deploy is lost, so note what people
  did in the meantime and tell them.

To see what a deploy's migrations were:

```bash
source storage/app/last-deploy-from    # sets $commit and $backup
git diff --stat "$commit" HEAD -- database/migrations
php artisan migrate:status | tail -n 20
```

## 1. Stop the site

```bash
PHP=/opt/alt/php84/usr/bin/php        # as in deploy.sh
$PHP artisan down --retry=30
source storage/app/last-deploy-from
```

## 2. Undo the migrations, if the way back is "code only"

Do this **before** going back to the old code: only the new code has the
`down()` methods of its own migrations.

One deploy runs its migrations as one batch, so rolling back the last batch
undoes exactly that deploy's migrations:

```bash
$PHP artisan migrate:rollback --pretend   # read what it would do
$PHP artisan migrate:rollback --force
```

A migration's `down()` is safe to run when it only removes what `up()`
added and nothing anyone needs has been written there since: a new table, a
new nullable column, an index. It is **not** safe, and you should restore the
backup instead (step 4), when `up()`:

- copied, moved or recalculated data (a `DB::table(...)->update(...)`, a
  loop over rows, a backfill);
- dropped or renamed a column or table, or narrowed a column's type (the
  data that was there is already gone);
- has an empty `down()`, or one that throws.

If in doubt, restore the backup.

## 3. Go back to the previous code

```bash
git fetch origin
git checkout main
git reset --hard "$commit"
$PHP "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
```

`git reset --hard` is safe here: the server holds no work of its own
(`deploy.sh` refuses to deploy over local changes). It keeps `main` checked
out, so the next `bash scripts/deploy.sh` brings it forward again as usual.

The front-end files are not in git. If the deploy came with a new build
zip, unzip the one for `$commit` (`build-<commit>.zip`, from
`bash scripts/package-assets.sh` on that commit) over the project folder.

## 4. Restore the pre-deploy backup, if the way back is "data changed"

The backup is `$backup` in the first backup disk (`storage/app/backups/mybooks-backups/`
by default). It holds `database.sql`, encrypted with `BACKUP_ARCHIVE_PASSWORD`
when that is set.

```bash
$PHP artisan mybooks:backup:verify --file="$backup"   # check it opens and holds the database
mkdir -p ~/restore && cd ~/restore
unzip -P "<BACKUP_ARCHIVE_PASSWORD>" "<path to backups>/mybooks-backups/$backup" database.sql
```

MySQL / MariaDB (what the server runs): restore into the live database. The
dump drops and recreates each table, so the database ends up exactly as it
was when the backup was taken.

```bash
mysql --defaults-extra-file=<file with the DB user and password> <database> < database.sql
rm database.sql
```

SQLite: `database.sql` is a copy of the database file. Replace the file
named by `DB_DATABASE` with it.

## 5. Bring the site back

```bash
cd /path/to/mybooks
$PHP artisan optimize:clear && $PHP artisan optimize
$PHP artisan queue:restart
$PHP artisan up
```

Check the dashboard and one invoice, then tell everyone what was undone.

## Practise it

Do this once on staging before you need it on production: deploy, roll back
with each method, and time it. A runbook nobody has tried is a guess.
