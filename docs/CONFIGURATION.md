# Configuration

## Environment Files
- `.env`: local/dev runtime
- `.env.example`: template for `.env`
- `.env.testing`: test runtime
- `.env.testing.example`: template for `.env.testing`
- `docker/.env.docker`: Docker Compose runtime for `app`, `database`, and `pma` containers
- `docker/.env.testing.docker`: Docker Compose runtime for the one-off `tests` service

## Simple Docker Setup
Run from the project root:
- `docker compose --env-file docker/.env.docker up --build`

This path:
- builds the PHP 8.3 app image from `docker/app.dockerfile`
- installs Composer dependencies and the Python scraper venv inside the app image
- builds the Nginx image from `docker/web.dockerfile`
- starts MySQL 8 and phpMyAdmin
- keeps the Docker test services behind the `test` Compose profile
- runs `php artisan migrate` through `docker/docker-app-entrypoint.sh`
- serves `/storage/*` directly from Nginx via `docker/vhost.conf`, so `php artisan storage:link` is not required for Docker

Access points:
- App: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8888`

Docker test services are assigned to the `test` Compose profile, so they do not start during the normal app startup command.

The app image copies `docker/.env.docker` to `.env` during build because some Laravel tooling expects `base_path('.env')` to exist. Runtime values still come from the Compose `env_file` entries, including `docker/.env.testing.docker` for the one-off `tests` service.

## Required Local Setup
1. Create `.env` from `.env.example` if it does not already exist.
2. Install PHP dependencies:
   - `composer install`
3. Configure app key:
   - `php artisan key:generate`
4. Run migrations:
   - `php artisan migrate`
5. Create storage symlink:
   - `php artisan storage:link`
6. Create Python venv and install scraper dependencies:
   - `python -m venv python/venv`
   - activate venv
   - `pip install -r python/requirements.txt`

## Database Settings
Main DB settings are in:
- `.env` for local/manual runtime
- `docker/.env.docker` for Docker Compose runtime
- `docker/.env.testing.docker` for Docker Compose test runtime

Relevant variables:
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

MySQL engine is configured as InnoDB in:
- `config/database.php` (`mysql.engine`)

Tag identity uses `genres.title_key` instead of the display `genres.title` column:
- `genres.title_key` is trimmed and Unicode case-folded by PHP
- `genres.title_key` uses binary collation so kana variants stay distinct
- `genres.title` keeps the user/DLSite display casing and is not the uniqueness column

Docker database services:
- `database` stores normal app data in the `dbdata` Docker volume
- `database_test` stores test data in the `dbdata_test` Docker volume and is used only by the `tests` service

## Queue and Scheduler
Refetch Tags runs through Laravel's database queue and job batches.

Relevant variable:
- `QUEUE_CONNECTION=database`

Required migrations create:
- `jobs`
- `job_batches`
- `tag_refetch_runs`
- `tag_refetch_work_results`

Run the queue worker from the project root while using Refetch Tags:
```bash
php artisan queue:work
```

Cancelling a Refetch Tags run is cooperative. Keep the queue worker running after pressing Cancel so the active fetch can finish and the remaining queued jobs can mark their work results as skipped.

`php artisan schedule:work` is only needed if a scheduled command is added. The project does not currently register a scheduled batch-pruning command.

## App Options
The `options` table stores app settings as scalar string values keyed by `options.key`.

Current settings:
- `index_per_page`: controls how many works the Index list renders per page
- `edit_fetched_tags`: controls whether fetched English tags can be edited from Edit Work

Runtime note:
- `App\Models\Option` normalizes stored strings into the runtime values the app uses

Pagination default:
- `100`

Pagination built-in choices:
- `10`
- `25`
- `50`
- `100`
- `250`
- `500`
- `1000`
- `unlimited`

The Options tab also accepts a custom positive integer. `unlimited` disables Index pagination and renders every matching work.

Fetched tag editing default:
- disabled

When enabled, Edit Work allows changing fetched English tags. Japanese-only fetched tags remain stored but hidden.

## Scraper Runtime Paths
- Python script: `python/DLSiteScraper.py`
- Tags-only Python script: `python/DLSiteTagFetcher.py`
- Python requirements: `python/requirements.txt`
- Scraped JSON output: `storage/app/Works/*.json`
- Scraped image output: `storage/app/public/Works/{RJ}/*`

`python/DLSiteTagFetcher.py` is used only by the Options -> Refetch Tags workflow. It prints JP/EN genre JSON to stdout and does not write scraped JSON or download images.

## Custom Work Upload Paths
- Required custom cover upload output: `storage/app/public/Works/{RJ}/cover.{ext}`
- Optional custom sample upload output: `storage/app/public/Works/{RJ}/sample_1.{ext}`, `sample_2.{ext}`, etc.
- Custom cover and sample uploads are validated as image files up to 20 MB each.
- Stored public paths use the existing `/storage` link format, for example `storage/Works/{RJ}/sample_1.jpg`
- Custom cover uploads set `products.work_image` to the public path with the uploaded image extension, for example `storage/Works/{RJ}/cover.png`
- Local/manual setup still needs `php artisan storage:link` so uploaded custom images are browser-accessible
