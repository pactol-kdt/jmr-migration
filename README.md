# JMR Migration

Internal app for importing Microsoft DataSet XML files into MySQL, with year-aware import/merge and a global records viewer (optional year filter).

## Stack

- Plain PHP API (`public/index.php`, PDO, PHP sessions)
- React + Vite + Tailwind
- MySQL (`manhour_importer`) or SQLite
- Background PHP worker for XML import

## Auth

Self-registration is disabled. On a fresh database, create the tables and admin user:

```bash
php bin/install.php
```

| Email | Password |
|---|---|
| `adm@ex.com` | `12345678` |

That installer account is a developer, so it can upload. Change the password after first login. An existing database is left as it is.

## First-time setup

1. Ensure MySQL is running and the database exists (or run `php bin/create-db.php`), and point `.env` at it.
2. Install and, for a new database, create tables:

```bash
npm install
php bin/install.php
```

3. Run the API and the React dev server (two terminals):

```bash
php -S 127.0.0.1:8000 -t public public/index.php
npm run dev
```

Open `http://localhost:5173`.

Imports stay `pending` until a worker runs. Saving an upload starts `php bin/worker.php` in the background. You can also run it yourself:

```bash
php bin/worker.php
```

Production assets:

```bash
npm run build
php -S 127.0.0.1:8000 -t public public/index.php
```

Then open `http://127.0.0.1:8000`.

### Scheduler (optional)

Orphan pending uploads under `storage/.../imports/pending` are cleaned daily when the scheduler runs:

Manual cleanup:

```bash
php bin/cleanup-pending.php --hours=24
```

## Import flow

1. **Import** → upload one or more XML files (up to 500 total; uploaded in batches of 20, 50 MB each, `.xml` / `.txt`)
2. App validates extension, then scans dated row names and detects year(s) per file (safe XML load: no network entities, size guard). Unknown/new columns are reported on the confirm screen and ignored during import.
3. Confirm screen — choose which year(s) to import (all detected years selected by default)
4. **Mixed-year files** queue **one import batch per selected year** (file is copied per batch). No years are silently dropped.
5. After multi-batch confirm, a **summary page** lists every queued batch with links
6. Each job merges into that year, then **deletes the stored XML** (success or failure)

Analyze is throttled (`60` requests / minute) to allow multi-batch uploads.

## Merge rules

Upload names must look like `2017_1042.xml` (`year_employeeId`). The number is stored on `employees` and linked from each import batch and record.

Unique key: `year + employee_id + work_date + location + project + item + type + category + group + person_in_charge`

- New key → insert  
- Same key, different content → update  
- Same key, same content → skip  
- Rows not in the file are **not** deleted  
- Invalid calendar dates (e.g. Feb 30) are rejected and stored as row errors on the batch  

## Records viewer

- Global **All records** page (`/records`) is the default home; year remains optional filter
- Partial (`LIKE`) filters for location / project / type / category / group / person in charge
- Sortable columns and filtered hour totals
- CSV export (same filters; includes year column)

## Commands

| Command | Purpose |
|---|---|
| `php -S 127.0.0.1:8000 -t public public/index.php` | Run the PHP API |
| `npm run dev` | Run the React dev server |
| `php bin/worker.php` | Process queued XML imports |
| `php bin/cleanup-pending.php [--hours=24]` | Delete orphaned `storage/app/imports/pending/*` files |
| `php bin/install.php` | Create tables and the admin user on a fresh database |
| `php bin/sync-kdt.php` | Copy employee profiles from `DB_KDT_DATABASE` |

The React app lives in `resources/js/Pages/` and loads its data from the same paths under `/api`.
