# Configuration

## Environment Files
- `.env`: local/dev runtime
- `.env.example`: template for `.env`
- `.env.testing`: test runtime
- `.env.testing.example`: template for `.env.testing`
- `docker/.env.docker`: Docker Compose runtime for `app`, `database`, and `pma` containers

## Simple Docker Setup
Run from the project root:
- `docker compose --env-file docker/.env.docker up --build`

This path:
- builds the PHP 8.3 app image from `docker/app.dockerfile`
- installs Composer dependencies and the Python scraper venv inside the app image
- builds the Nginx image from `docker/web.dockerfile`
- starts MySQL 8 and phpMyAdmin
- runs `php artisan migrate` through `docker/docker-app-entrypoint.sh`
- serves `/storage/*` directly from Nginx via `docker/vhost.conf`, so `php artisan storage:link` is not required for Docker

Access points:
- App: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8888`

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

Relevant variables:
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

MySQL engine is configured as InnoDB in:
- `config/database.php` (`mysql.engine`)

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

`php artisan schedule:work` is only needed if a scheduled command is added. The project does not currently register a scheduled batch-pruning command.

## Livewire
The Options selected-work search and Refetch Tags progress panel use Livewire.

Relevant Composer package:
- `livewire/livewire`

No published Livewire config is required. The Options page includes Livewire's Blade asset directives so `wire:model.live.debounce.250ms` can update the search results while typing. The refetch progress page uses `wire:poll.1s` only while a run is still running.

## Testing Configuration
Test setup:
- `.env.testing` is separate from `docker/.env.docker`
- Put test DB credentials in `.env.testing`
- Set application key

Run tests:
- `php artisan test`

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
