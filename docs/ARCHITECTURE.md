# Architecture

## Stack
- Backend: Laravel 12 (PHP 8.3)
- Frontend: Blade templates + plain CSS/JS
- Database: MySQL
- Scraper: Python script (`python/DLSiteScraper.py`) invoked from Laravel

## Main Application Flow
1. User opens list page (`GET /`).
2. `ProductController@index` applies filters/search and renders `resources/views/Index.blade.php`.
3. `GET /tags` renders the tag library and links each English/custom genre back to the same index filter used on the list page.
4. User can create/edit/delete entries through forms.
5. Store flow (`POST /store`) validates input, runs scraper, reads scraped JSON, and creates a `products` row.
6. Update flow (`POST /update/{id}`) validates and updates editable fields.
7. Destroy flow (`POST /destroy/{id}`) removes DB row and related local files.

## Key Components
- Routes: `routes/web.php`
- Controller: `app/Http/Controllers/ProductController.php`
- Requests:
  - `app/Http/Requests/StoreProductRequest.php`
  - `app/Http/Requests/UpdateProductRequest.php`
  - shared normalization/validation in `app/Http/Requests/BaseProductRequest.php`
- Model: `app/Models/Product.php`
- Views: `resources/views/*.blade.php`
- UI field components: `resources/views/components/fields/*.blade.php`
- Scripts/CSS: `public/scripts/*`, `public/css/*`

## Data Model
`products` table stores:
- DLSite identifiers and titles
- progress/listening metadata (`progress`, dates, re-listen fields, priority)
- local image paths

`genres` table stores the visible genre title text and metadata:
- `title`
- `type` (`auto_generated_japanese`, `auto_generated_english`, `custom`)
- `language` (`jp` or `en` for now)
- optional `group_id`

`genre_groups` stores optional genre group definitions.

`genre_product` is the many-to-many pivot table between `products` and `genres`.

If the same title text already exists, the existing `genres` row is reused. Auto-generated
genres still take priority over `custom` when a title collision happens.

Migration note:
- legacy `products.genre`, `products.genre_english`, and `products.genre_custom` JSON columns are migrated into
  `genres` + `genre_product` by `2026_03_16_160000_convert_product_genre_titles_to_ids.php`
- after conversion, those legacy JSON columns are dropped

Runtime note:
- `ProductController@index` shows English + custom genres through Eloquent many-to-many relationships
- create/store resolves scraped/custom titles into `genres` rows and syncs the pivot
- edit shows fetched Japanese/English genres as read-only and keeps them attached automatically
- update reads user-added genres from the form and reuses an existing fetched genre row when the title already exists

## Scraper Integration
- `ProductController::Scrape()` runs the Python script in `python/venv`.
- Python fetches Japanese/English DLSite metadata, stores JSON in `storage/app/Works`, and downloads images to `storage/app/public/Works/{RJ}`.

## Validation / Normalization Notes
- RJ input can be raw RJ code or URL containing RJ code.
- Custom tags are comma-separated and parsed with CSV rules:
  - commas inside a tag are supported via quotes
  - example: `"Junior / Senior (at work, school, etc)", Office Lady`

## Testing Overview
- Feature tests: `tests/Feature/ProductControllerTest.php`
- Uses `RefreshDatabase` to isolate DB state during test execution.
