# Configuration

## Environment Files
- `.env`: local/dev runtime
- `.env.example`: template for `.env`
- `.env.testing`: test runtime
- `.env.testing.example`: template for `.env.testing`

## Required Local Setup
1. Install PHP dependencies:
   - `composer install`
2. Configure app key:
   - `php artisan key:generate`
3. Run migrations:
   - `php artisan migrate`
4. Create storage symlink:
   - `php artisan storage:link`
5. Create Python venv and install scraper dependencies:
   - `python -m venv python/venv`
   - activate venv
   - `pip install -r python/requirements.txt`

## Database Settings
Main DB settings are in `.env`:
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
- Put test DB credentials in `.env.testing`
- Set application key

Run tests:
- `php artisan test`

## Scraper Runtime Paths
- Python script: `python/DLSiteScraper.py`
- Python requirements: `python/requirements.txt`
- Scraped JSON output: `storage/app/Works/*.json`
- Scraped image output: `storage/app/public/Works/{RJ}/*`
