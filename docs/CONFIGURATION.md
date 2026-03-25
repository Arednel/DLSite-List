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

## Testing Configuration
Test setup:
- `.env.testing` is separate from `docker/.env.docker`
- Put test DB credentials in `.env.testing`
- Set application key

Run tests:
- `php artisan test`

## Scraper Runtime Paths
- Python script: `python/DLSiteScraper.py`
- Python requirements: `python/requirements.txt`
- Scraped JSON output: `storage/app/Works/*.json`
- Scraped image output: `storage/app/public/Works/{RJ}/*`
