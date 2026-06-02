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
- `tag_autocomplete_order`: controls how tag autocomplete suggestions are ordered
- `series_autocomplete_order`: controls how series autocomplete suggestions are ordered
- `auto_series_from_title_name`: controls whether DLSite create fills an empty Series from `japanese.title_name`
- `index_field_layout`: controls Index table field visibility/order
- `edit_field_layout`: controls Edit Work field visibility/order/editability
- `filter_field_layout`: controls Filter modal field visibility/order
- `quick_add_field_layout`: controls DLSite Create field visibility/order
- `custom_quick_add_field_layout`: controls Custom Create field visibility/order
- `index_table_width`: controls the Index list/table width and top cover image width

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

Tag editing defaults:
- Custom Tags editable: enabled when Tags is visible/editable in the Edit Form layout
- Fetched EN Tags editable: disabled unless the Edit Form layout enables it

When Fetched EN Tags editing is enabled, Edit Work allows changing the fetched English tag bucket. Japanese-only fetched tags remain stored but hidden.

Automatic Series from DLSite `title_name` default:
- enabled

When enabled, DLSite create fills Series from `japanese.title_name` only if the Series field is empty. Manually entered Series values win. Custom create does not use this option.

Index field layout default order:
- `image`
- `title` locked visible
- `score`
- `series`
- `age_category`
- `progress`
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description` hidden by default
- `tags`

Edit form field layout default order:
- `progress`
- `score`
- `series`
- `title` locked visible
- `tags`
- `notes`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `age_category` hidden by default
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description` hidden by default

Filter modal field layout default order:
- `title`
- `series`
- `notes`
- `age_category`
- `progress`
- `score`
- `priority`
- `num_re_listen_times`
- `re_listen_value`
- `tags`
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description` hidden by default

Quick Add field layout default order:
- `rj_code` locked visible
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
- `age_category` hidden by default
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description` hidden by default

Custom Quick Add field layout default order:
- `rj_code` locked visible
- `progress`
- `score`
- `series`
- `title` locked visible
- `tags`
- `notes`
- `age_category` locked visible
- `image` locked visible
- `sample_images`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description` hidden by default

The Index table, Edit form, Filter modal, Quick Add form, and Custom Quick Add form each store their own layout JSON in `options.value`. Rows can be reordered by dragging the row handle or by using the Up/Down buttons, and changes are persisted when Save is submitted. Field settings are keyed by field id while editing, so reordering rows does not change checkbox state. Unknown or duplicate field ids are ignored and missing known fields fall back to the surface default order. Index `title` is always visible but can still be reordered. Edit Form `title` is also locked visible and represents the Japanese/English title inputs after the fixed RJ Code + Title display row. Quick Add keeps `rj_code` locked visible. Custom Quick Add keeps `rj_code`, `title`, `age_category`, and `image` locked visible. In the Edit Form layout, the `tags` row stores separate toggles for Custom Tags and Fetched EN Tags, grouped in the same edit-controls column on desktop.

Create form layout note:
- hidden Quick Add fields are not persisted from submitted form data
- DLSite Create still keeps scraped DLSite metadata for hidden age, circle, creator, and description rows
- visible DLSite Create metadata rows act as manual overrides when the user enters a value
- visible Custom Quick Add metadata rows are saved directly because custom works have no scraped fallback

Index table width default:
- `default`

Index table width choices:
- `default`: current 1024px list width
- `wide`: 1400px list width
- `full`: 100% of the available page width
- custom CSS length or percentage, for example `1600px`, `90%`, `80vw`, `72rem`, or `64em`

This width is applied to the Index list/table panel and the top cover image. The top cover image keeps a capped desktop height, and product row thumbnails keep their fixed list size.

Options reset behavior:
- each visible Options setting has a modal-confirmed `Reset to default` action
- `Reset All Options` opens the same Options confirmation modal and resets the visible Options tab settings together
- reset buttons are right-aligned in full-width Options action rows
- reset confirmation modals are teleported to the document body so they stay centered in the viewport instead of inside the Options panel
- reset confirmation modals close from Cancel, Escape, or clicking outside the modal card
- the global reset confirmation button is disabled for 3 seconds and shows a countdown before it can be clicked
- reset defaults are pagination `100`, table width `default`, all five default field layouts, automatic Series enabled, and autocomplete `usage`
- global reset does not change products, tags, refetch runs, legacy hidden fallback keys, or unrelated future option rows

Autocomplete ordering default:
- `usage`

Autocomplete ordering choices:
- `usage`: orders matching suggestions by attached work count and then title
- `first_word`: shows values that start with the typed query before later-word matches, then orders each group by attached work count and title

## Scraper Runtime Paths
- Python script: `python/DLSiteScraper.py`
- Tags-only Python script: `python/DLSiteTagFetcher.py`
- Python requirements: `python/requirements.txt`
- Scraped JSON output: `storage/app/Works/*.json`
- Scraped image output: `storage/app/public/Works/{RJ}/*`

Scraped JSON files are also used by the product metadata backfill migration. The migration reads `storage/app/Works/{RJ}.json` when it exists and skips missing or invalid JSON without blocking the migration.

`python/DLSiteTagFetcher.py` is used only by the Options -> Refetch Tags workflow. It prints JP/EN genre JSON to stdout and does not write scraped JSON or download images.

## Custom Work Upload Paths
- Required custom cover upload output: `storage/app/public/Works/{RJ}/cover.{ext}`
- Optional custom sample upload output: `storage/app/public/Works/{RJ}/sample_1.{ext}`, `sample_2.{ext}`, etc.
- Custom cover and sample uploads are validated as image files up to 20 MB each.
- Stored public paths use the existing `/storage` link format, for example `storage/Works/{RJ}/sample_1.jpg`
- Custom cover uploads set `products.work_image` to the public path with the uploaded image extension, for example `storage/Works/{RJ}/cover.png`
- Local/manual setup still needs `php artisan storage:link` so uploaded custom images are browser-accessible
