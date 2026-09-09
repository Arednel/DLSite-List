# Configuration

This document explains how to run and configure DLSite List.

For runtime architecture and data flow, see [ARCHITECTURE.md](ARCHITECTURE.md).  
For test setup, commands, and the current tests, see [TESTING.md](TESTING.md).

## Run the Application

### Docker

Docker is the recommended self-hosted setup.

From the project root run:

```bash
docker compose --env-file docker/.env.docker up --build -d
```

This starts:
- Laravel/PHP-FPM from `docker/app.dockerfile`
- Laravel queue worker
- Nginx from `docker/web.dockerfile`
- MySQL 8 database

The app container runs migrations during startup.

After that DLSite List is available at:

```text
http://localhost:8080
```

Docker serves `/storage/*` directly through Nginx, so `php artisan storage:link` is not required inside the Docker setup.

The test database/services are behind the Compose `test` profile and do not start with the normal application command.

phpMyAdmin is disabled/commented out by default. If enabled in `compose.yaml`, its configured access point is:

```text
http://localhost:8888
```

### Run Artisan Commands in Docker

To run Artisan command inside the running Docker app container run:

```bash
docker compose --env-file docker/.env.docker exec app php artisan <command>
```

Examples:

```bash
docker compose --env-file docker/.env.docker exec app php artisan admin:reset-password
docker compose --env-file docker/.env.docker exec app php artisan admin:reset
docker compose --env-file docker/.env.docker exec app php artisan works:cleanup-images
```

### Local / Manual Setup

Requirements:
- PHP 8.3
- Composer
- MySQL 8
- Python 3.14.6 (currently tested version) and pip

From the project root:

1. Copy `.env.example` to `.env`.
2. Configure the database and other environment values.
3. Install PHP dependencies:

```bash
composer install
```

4. Generate the application key:

```bash
php artisan key:generate
```

5. Run migrations:

```bash
php artisan migrate
```

6. Create the public storage link:

```bash
php artisan storage:link
```

7. Create the scraper python virtual environment:

```bash
python -m venv python/venv
```

8. Activate the virtual environment:

Windows:

```bat
python\venv\Scripts\activate
```

Linux/macOS:

```bash
source python/venv/bin/activate
```

9. Install scraper dependencies:

```bash
pip install -r python/requirements.txt
```

10. Keep a Laravel queue worker running when using Refetch:

```bash
php artisan queue:work
```

## Recovery and Maintenance

### Reset Administrator Password

If administrator exists:

```bash
php artisan admin:reset-password
```

This command:
- prompts for a new password and confirmation without echoing the password
- refuses to choose an account when there are zero or multiple user rows

Docker equivalent:

```bash
docker compose --env-file docker/.env.docker exec app php artisan admin:reset-password
```

### Reset Administrator Account

To delete all administrator user rows and return authentication to setup:

```bash
php artisan admin:reset
```

The command requires destructive confirmation and does not disable the Authentication option.

If authentication remains enabled, the next application request opens administrator setup.

Docker equivalent:

```bash
docker compose --env-file docker/.env.docker exec app php artisan admin:reset
```

### Trusted Environment Password Recovery

Use this only when:
- administrator authentication is enabled
- console recovery is unavailable
- application is on a trusted local environment/network

1. Set in the active `.env` or `docker/.env.docker`:

```dotenv
ADMIN_PASSWORD_RESET=true
```

2. Restart the PHP/web process. For Docker, recreate the app container:

```bash
docker compose --env-file docker/.env.docker up -d --force-recreate app
```

3. Open the application and complete the forced password reset.
4. Set `ADMIN_PASSWORD_RESET` back to `false`.
5. Restart/recreate the PHP/web process again.

After one recovery is consumed, normal pages remain blocked until the running process sees the flag disabled after restart.

### Clean Obsolete Work Images

Run:

```bash
php artisan works:cleanup-images
```

Docker:

```bash
docker compose --env-file docker/.env.docker exec app php artisan works:cleanup-images
```

The command scans existing RJ work folders and removes obsolete cover/sample images that are no longer referenced by their work.

It deliberately skips:
- orphan RJ folders without a matching work
- unknown filenames
- nested files
- non-image files

Normal create/update/refetch flows already perform their own image cleanup where applicable. This command is for maintenance/recovery.

### Refetch Cleanup

`Options -> Refetch` includes cleanup for stored Refetch runs and staged Refetch files.

Cleanup:
- disabled while Refetch status is `running` or `cancelling`
- deletes Refetch run/result history
- clears staged Refetch content
- preserves works
- preserves canonical `storage/app/Works`
- preserves canonical `storage/app/public/Works`

## Environment Files

Runtime files:

| File | Purpose |
| --- | --- |
| `.env` | Local/manual application runtime |
| `.env.example` | Local/manual environment template |
| `.env.testing` | Local test runtime |
| `.env.testing.example` | Local test environment template |
| `docker/.env.docker` | Docker application runtime |
| `docker/.env.testing.docker` | Docker test runtime |

Testing-specific configuration is documented in [TESTING.md](TESTING.md).

### Application Environment

Important values include:

```dotenv
APP_NAME=
APP_ENV=
APP_KEY=
APP_DEBUG=
APP_URL=
ADMIN_PASSWORD_RESET=false
```

Generate `APP_KEY` for a local/manual setup with:

```bash
php artisan key:generate
```

`ADMIN_PASSWORD_RESET` is a recovery switch, not a normal authentication setting. Keep it `false` unless intentionally performing trusted-environment recovery.

### Database

Relevant variables:

```dotenv
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Docker uses `database` as the application database host.

### Queue

Refetch uses Laravel's database queue and job batches.

Normal setting:

```dotenv
QUEUE_CONNECTION=database
```

For a local/manual installation, keep this worker running while Refetch is in use:

```bash
php artisan queue:work
```

Cancellation is cooperative. After pressing Cancel, keep the worker running so active work can finish and queued jobs can record their cancelled state.

### Storage, Cache, and Session

The supplied environment templates use:

```dotenv
FILESYSTEM_DISK=local
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

These are the project's supplied defaults. Work images and Refetch staging explicitly use the named `local` and `public` disks from `config/filesystems.php`; changing `FILESYSTEM_DISK` alone does not move those files to another storage backend.

Refetch lifecycle protection uses Laravel atomic cache locks. If `CACHE_DRIVER` is changed, use a configured cache store that supports Laravel atomic locks.

### Logs

Laravel and the DLSite scraper write weekly logs under `storage/logs`:

```text
laravel-YYYY-MM-DD.log
DLSiteScraper-YYYY-MM-DD.log
```

The filename date is the UTC Monday starting that log week.

Retention is configured with:

```dotenv
LOG_RETENTION_DAYS=90
```

Rules:
- default: `90`
- value must be a positive integer
- missing/invalid/zero/negative values fall back to `90`
- cleanup happens lazily during log writes
- current week is not deleted
- retention is based on completed whole log weeks, so a 90-day setting effectively retains an archive for 90–96 days

## Options

Application UI settings are stored in the `options` table and configured from `/options`.

Tabs:
- `General`
- `Field Layouts`
- `Authentication`
- `Refetch`

`Reset All Options` is available on both General and Field Layouts. Using it resets all settings from both tabs to their defaults. Authentication settings are not affected.

### General

#### UI Language

Default: `English`

Choices:
- `English` (`en`)
- `日本語` (`ja`)

The setting is application-wide, not per browser/user.

The UI language also selects the fetched-tag display bucket:
- `en` UI -> `en` fetched tags
- `ja` UI -> `jp` fetched tags

#### Index Pagination

Default: `100`

Built-in choices:

```text
10
25
50
100
250
500
1000
unlimited
```

A custom positive integer is also accepted.

`unlimited` renders every matching work without pagination.

#### Index Search

`Search hidden descriptions` is disabled by default.

When disabled:
- general Index search includes Japanese Description only when that Index column is visible
- general Index search includes English Description only when that Index column is visible

When enabled:
- general Index search can match both description languages even while their Index columns are hidden

The explicit Japanese/English description filters are independent of this setting.

#### Image Viewer

Default: disabled.

When enabled:
- clicking an Index thumbnail opens the saved cover and sample images in "Image Viewer"
- the viewer operates only while the Image Index field is visible

Hiding the Image field does not reset the saved Image Viewer option.

#### Optional Statuses

Defaults:
- On Hold: disabled
- Dropped: disabled

Enabling a status exposes it in:
- DLSite Quick Add
- Custom Quick Add
- Edit Details
- Index progress navigation
- Advanced Filter

Disabling a status does not rewrite status of works that already use it.

#### Autocomplete

Controls how Tag and Series autocomplete suggestions are ordered.

Tag autocomplete order:

* `usage` - default; most-used matching tags are shown first
* `first_word` - prioritizes matches at the start of the tag, then at the start of later words; usage breaks ties

Series autocomplete order:

* `usage` - default; most-used matching series are shown first
* `first_word` - prioritizes matches at the start of the series name, then at the start of later words; usage breaks ties

#### Automatic Series

`Automatic Series from DLsite metadata` is enabled by default.

When enabled, DLSite Quick Add fills Series only when the user did not enter one:
1. `japanese.title_name`
2. fallback `english.title_name`

It does not apply to Custom Quick Add or Refetch.

#### DLSite Links

Age-appropriate DLSite links are disabled by default.

Disabled:
- all Index image/title DLSite links use Maniax URL

Enabled:
- exact `ALL_AGES` -> DLSite Home URL
- `R15`, `R18`, missing, or malformed values -> Maniax URL

#### Add/Edit Form Theme

Default: `black`.

Choices:
- `cherry`
- `black`

This theme applies to:
- DLSite Quick Add
- Custom Quick Add
- Edit Details

#### Add/Edit Modal

Default:
- disabled
- completion action: `redirect`

Completion choices:
- `redirect` - navigate the host page to calculated Index return URL
- `refresh` - close the modal and reload the page that opened it
- `close` - close without navigation; the host page may remain stale

The modal applies to ordinary primary-click Quick Add/Edit navigation. Real standalone links remain available for modified/middle-click navigation.

#### Index Table Width

Default: `default` choice

Choices:
- `default` - 1024px
- `wide` - 1400px
- `full` - 100%
- custom width - supports `px`, `rem`, `em`, `%`, or `vw`

#### Index Content Overflow

Three independent targets:
- Notes below Title
- Notes column
- Tags

Defaults for each are:
- disabled
- `80px`

Enabled accept positive values using `px`, `rem`, `em`, `%`, `vw`, `vh`, `vmin`, `vmax`, `svh`, `lvh`, or `dvh`

Disabled targets are normalized back to the default `80px`.

#### Tag Library

Defaults:
- "All Tags" list collapsed
- Index tag group ordering disabled

When Index tag group ordering is enabled:
1. grouped tags follow saved group order
2. tags within groups follow saved per-group order
3. ungrouped tags follow alphabetically

Tag/group color defaults:
- Index - enabled;
- Tag Library - enabled;
- Autocomplete suggestions - disabled;
- Edit readonly tags - disabled;
- Refetch review tags - disabled;

### Field Layouts

Six layouts are configurable:

1. Index Table Columns
2. Index Filter Fields
3. Index Sort Menu
4. Edit Form Fields
5. Quick Add Form Fields
6. Custom Quick Add Form Fields

Each layout can be saved independently. `Save all field layouts` saves all six.

Rows can be reordered and shown/hidden where allowed. Edit rows can also expose an `Editable` setting where supported.

Required fields remain visible.

Index Title is locked visible but reorderable. Its independent `Notes below Title` switch is enabled by default.

Index Tags has separate visibility switches for:
- Custom Tags
- current-language Fetched Tags

Edit uses separate rows for:
- Custom Tags
- current-language Fetched Tags

Fetched Tags are readonly by default. If made editable, editing changes only the current UI language's fetched bucket.

#### Index Table Columns Default Order

- `image`
- `title` - locked visible; Notes below Title enabled
- `score`
- `series`
- `age_category`
- `progress`
- `circle` - hidden
- `scenario` - hidden
- `illustration` - hidden
- `voice_actor` - hidden
- `author` - hidden
- `description_japanese` - hidden
- `description_english` - hidden
- `tags` - Custom and current-language Fetched Tags visible
- `notes` - hidden
- `start_date` - hidden
- `end_date` - hidden
- `num_re_listen_times` - hidden
- `re_listen_value` - hidden
- `priority` - hidden
- `created_at` - hidden
- `updated_at` - hidden

#### Edit Form Default Order

- `progress`
- `score`
- `series`
- `title` - locked visible
- `fetched_tags`
- `tags`
- `notes`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `age_category` - hidden
- `circle` - hidden
- `scenario` - hidden
- `illustration` - hidden
- `voice_actor` - hidden
- `author` - hidden
- `description_japanese` - hidden
- `description_english` - hidden

#### Index Filter Default Order

- `title`
- `score`
- `series`
- `age_category`
- `progress`
- `notes`
- `priority`
- `num_re_listen_times`
- `re_listen_value`
- `tags`
- `start_date` - hidden
- `end_date` - hidden
- `created_at` - hidden
- `updated_at` - hidden
- `circle` - hidden
- `scenario` - hidden
- `illustration` - hidden
- `voice_actor` - hidden
- `author` - hidden
- `description_japanese` - hidden
- `description_english` - hidden

#### Quick Add Default Order

- `rj_code` - locked visible
- `progress`
- `score`
- `series`
- `title`
- `tags`
- `notes`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `age_category` - hidden
- `circle` - hidden
- `scenario` - hidden
- `illustration` - hidden
- `voice_actor` - hidden
- `author` - hidden
- `description_japanese` - hidden
- `description_english` - hidden

Hidden DLSite Quick Add metadata fields are not accepted as user overrides, but their scraped age/circle/contributor/description values are still preserved from DLsite.

#### Custom Quick Add Default Order

- `rj_code` - locked visible
- `progress`
- `score`
- `series`
- `title` - locked visible
- `tags`
- `notes`
- `age_category` - locked visible
- `image` - locked visible
- `sample_images`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `circle` - hidden
- `scenario` - hidden
- `illustration` - hidden
- `voice_actor` - hidden
- `author` - hidden
- `description_japanese` - hidden
- `description_english` - hidden

Custom Quick Add has no scraper fallback. Hidden optional description rows store `null`.

#### Index Sort Menu Default Order

- `rj`
- `score`
- `series`
- `age_category`
- `progress`
- `priority`
- `num_re_listen_times`
- `re_listen_value`
- `start_date`
- `end_date`
- `created_at`
- `updated_at` - hidden
- `circle` - hidden
- `scenario` - hidden
- `illustration` - hidden
- `voice_actor` - hidden
- `author` - hidden

Hiding a value from the Index Sort Menu only removes it from that dropdown. It does not invalidate otherwise supported URL/table-header sorting.

### Authentication

Administrator authentication is disabled by default.

`Authentication` configures:

* administrator login enabled/disabled
* authentication-page theme
* authenticated password change

Authentication theme choices:

* `Cherry` - default
* `Black`

Authentication settings are not affected by `Reset All Options`.

When authentication is enabled, application supports a single administrator account.

Username matching is case-sensitive. Passwords must be 8–256 characters long.

### Refetch

`Refetch` contains:
- Refetch All Works
- Refetch Selected Works
- run-wide `Refetch Images` choice
- progress/cancel state
- latest-run link
- review/apply/reject controls
- Refetch cleanup

Refetch requires the Laravel queue worker and the application's database, storage, and cache configuration. No separate Refetch-specific environment variables are required.
