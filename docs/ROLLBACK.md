# Rolling back a deploy

`scripts/deploy.sh` takes a database backup before it changes anything, and
when new code comes in it writes where it started from to
`storage/app/last-deploy-from`:

```
commit=<the commit the server was on before this deploy>
backup=<the backup it took, e.g. mybooks-2026-10-30-091500.zip>
at=<when, UTC>
```

It prints the same at the end of the deploy. Running `deploy.sh` again after
a failed step, or with nothing new, leaves this file alone, so it still
points at where the last real deploy started. Every deploy is also added to
`storage/app/deploy-history`.

The backup is taken while the site is still up, just before the new code
comes in. Anything entered between the backup and maintenance mode (a
minute or two) is not in it.

There are two ways back, depending on what the deploy changed.

- **Code only** (no new migrations, or migrations that only added tables or
  columns): go back to the previous commit and, if needed, undo those
  migrations. Nothing entered since the deploy is lost, except whatever was
  written into the tables or columns the deploy added.
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

If the way back is "data changed", check the backup now, while this code
(which has the command) is still in place:

```bash
$PHP artisan mybooks:backup:verify --file="$backup"
```

## 2. Undo the migrations, if the way back is "code only"

**Skip this step if `git diff --stat "$commit" HEAD -- database/migrations`
listed nothing.** A deploy with no new migrations adds no batch, and
`migrate:rollback` would then undo the deploy *before* it.

Otherwise do this **before** going back to the old code: only the new code
has the `down()` methods of its own migrations. One deploy runs its
migrations as one batch, so rolling back the last batch undoes that
deploy's migrations:

```bash
$PHP artisan migrate:rollback --pretend   # asks to confirm, then shows what it would do
$PHP artisan migrate:rollback --force
```

Check that `--pretend` names only the files the `git diff` listed. If it
names any other migration, stop and restore the backup instead.

A migration's `down()` is safe to run when it only removes what `up()`
added: a new table, a new nullable column, an index (whatever was written
there since the deploy goes with it). It is **not** safe, and you should
restore the backup instead (step 4), when `up()`:

- copied, moved or recalculated data (a `DB::table(...)->update(...)`, a
  loop over rows, a backfill);
- dropped or renamed a column or table, or narrowed a column's type (the
  data that was there is already gone);
- has an empty `down()`, or one that throws.

If in doubt, restore the backup.

## 3. Go back to the previous code

```bash
git status --porcelain --untracked-files=no   # must print nothing
git fetch origin
git checkout main
git reset --hard "$commit"
$PHP "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
```

If `git status` lists files, someone changed them on the server. Copy them
somewhere first: `git reset --hard` throws those changes away.

`main` stays checked out, so the next `bash scripts/deploy.sh` brings it
forward again, to whatever is on GitHub's `main`. Unless the bad change has
been reverted there first, that deploys it again.

The front-end files are not in git. If the deploy came with a new build
zip, unzip the one for `$commit` over the project folder: it is
`build-${commit:0:7}.zip`, from `bash scripts/package-assets.sh` on that
commit.

## 4. Restore the pre-deploy backup, if the way back is "data changed"

The backup is `$backup` in the first backup disk (`storage/app/backups/mybooks-backups/`
by default). It holds `database.sql`, encrypted with AES-256 using
`BACKUP_ARCHIVE_PASSWORD` when that is set. `unzip` cannot open AES zips,
so take it out with PHP (or 7-Zip, `7z x`):

```bash
mkdir -p ~/restore && cd ~/restore
read -rs -p "BACKUP_ARCHIVE_PASSWORD (empty if none): " P && export P && echo
$PHP -r '$z = new ZipArchive; $z->open($argv[1]) === true or exit(2);
  if (getenv("P") !== "") { $z->setPassword(getenv("P")); }
  exit($z->extractTo(".", "database.sql") ? 0 : 1);' \
  "/path/to/mybooks/storage/app/backups/mybooks-backups/$backup"
unset P
```

MySQL / MariaDB (what the server runs): empty the live database, then load
the dump. The dump drops and recreates each table it holds, but tables the
deploy *created* are not in it, and left behind they would make the next
`migrate` fail with "table already exists". Emptying the database first
means it ends up exactly as it was when the backup was taken.

```bash
cd /path/to/mybooks
$PHP artisan db:wipe --force
mysql --defaults-extra-file=<file with the DB user and password> <database> < ~/restore/database.sql
rm ~/restore/database.sql
```

SQLite: `database.sql` is a copy of the database file. Replace the file
named by `DB_DATABASE` with it.

Queued jobs (emails and the like) live in the `jobs` table, so they go back
too: ones waiting when the backup was taken may run again, and ones queued
since are gone. Look at `SELECT id, queue, created_at FROM jobs` before
the next step and delete any that must not run twice.

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
