# Architecture

## Stack
- Backend: Laravel 12 (PHP 8.3)
- Frontend: Blade templates, Livewire for Options work search/refetch progress, and plain CSS/JS
- Database: MySQL 8
- Scraper: Python scripts invoked from Laravel (`python/DLSiteScraper.py`, `python/DLSiteTagFetcher.py`)
- Background work: Laravel database queues and job batches

## Main Application Flow
1. User opens list page (`GET /`).
2. `ProductController@index` applies filters/search and renders `resources/views/Index.blade.php`.
3. `GET /tags` renders the tag library, shows the work count for each English/custom genre, and links each tag back to the same index filter used on the list page.
4. `GET /options` renders Options workflows, including Refetch Tags.
5. User can create/edit/delete entries through forms.
6. Store flow (`POST /store`) validates input, runs scraper, reads scraped JSON, and creates a `products` row.
7. Custom store flow (`POST /store/custom`) validates manual input, skips scraper/network checks, stores the required local cover plus optional sample images, and creates a `products` row.
8. Update flow (`POST /update/{id}`) validates and updates editable fields.
9. Destroy flow (`POST /destroy/{id}`) removes DB row and related local files.
10. Refetch Tags starts a queued batch, stores per-work fetched/skipped results, shows progress, then applies reviewed tag changes.

## Key Components
- Routes: `routes/web.php`
- Controller: `app/Http/Controllers/ProductController.php`
- Options controller: `app/Http/Controllers/OptionsController.php`
- Requests:
  - `app/Http/Requests/StartTagRefetchRequest.php`
  - `app/Http/Requests/ApplyTagRefetchRequest.php`
  - `app/Http/Requests/StoreProductRequest.php`
  - `app/Http/Requests/StoreCustomProductRequest.php`
  - `app/Http/Requests/UpdateProductRequest.php`
  - shared normalization/validation in `app/Http/Requests/BaseProductRequest.php`
- Model: `app/Models/Product.php`
- Refetch models:
  - `app/Models/TagRefetchRun.php`
  - `app/Models/TagRefetchWorkResult.php`
- Refetch job/support:
  - `app/Jobs/FetchProductTagsJob.php`
  - `app/Support/TagRefetch/DLSiteTagFetcher.php`
  - `app/Support/TagRefetch/TagRefetchService.php`
- Livewire components:
  - `app/Livewire/OptionsWorkSearch.php`
  - `app/Livewire/OptionsRefetchProgress.php`
- Views: `resources/views/*.blade.php`
- Livewire views: `resources/views/livewire/*.blade.php`
- UI field components: `resources/views/components/fields/*.blade.php`
- UI field component classes: `app/View/Components/Fields/*.php`
- Scripts/CSS: `public/scripts/*`, `public/css/*`

Shared UI note:
- `resources/views/components/list-menu-float.blade.php` is reused by index/tag library
- desktop keeps the floating hover menu
- mobile uses a toggle button that opens the same menu as a left-side drawer
- `resources/views/Index.blade.php` keeps the desktop table on larger screens and switches to stacked cards on mobile so search/actions still fit
- `resources/views/Create.blade.php` switches between DLSite create and custom create modes; `resources/views/Create.blade.php` and `resources/views/Edit.blade.php` use `public/css/edit.css` for both desktop and mobile form layouts and render reusable field components from `resources/views/components/fields/*.blade.php`
- `app/View/Components/Fields/*.php` provides the class-based field components used by those Blade views
- `AppServiceProvider` registers the enum-backed field component aliases used by `<x-fields.* />`
- the progress, score, priority, and re-listen field component classes read their select options from the matching enums in `app/Enums/*.php`
- Blade pages load CSS and JS from `public/` with `filemtime(public_path(...))` query strings for cache busting
- `resources/views/components/index/advanced-filters.blade.php` renders the index filter/sort modal
- `resources/views/components/index/*.blade.php` contains the reusable filter/select/radio pieces used by the index modal
- `app/Http/Requests/ProductIndexRequest.php` normalizes the query string into a `ProductIndexFilters` object
- `app/Enums/*.php` holds enum-backed filter options for progress, priority, tag match, sort fields, and the numeric rating scales
- `app/Models/Product.php` owns the Laravel 12 local scopes used by index filtering/search
- `app/Support/ProductIndexResults.php` loads filtered products, applies PHP-side multi-column sorting, and runs the lightweight visible-genre query used by the index page
- the advanced filter modal defaults to `All tags` matching and `Desc` sort direction until the user chooses something else

## Data Model
`products` table stores:
- DLSite identifiers and titles
- progress/listening metadata (`progress`, dates, re-listen fields, priority)
- local image paths

`genres` table stores the visible genre title text and metadata:
- `title`
- `type` (`auto_generated_japanese`, `auto_generated_english`, `custom`)
- `language` (`jp` or `en`)
- optional `group_id`

`genre_groups` stores optional genre group definitions.

`genre_product` is the many-to-many pivot table between `products` and `genres`.
It also stores a `source` value:
- `fetched` for scraper-provided genre attachments
- `custom` for tags the user typed into the editable Custom Tags field

If the same title text already exists, the existing `genres` row is reused. Auto-generated
genres still take priority over `custom` when a title collision happens.

`tag_refetch_runs` stores each Options -> Refetch Tags batch:
- batch id and status
- selected product ids
- total/processed/fetched/skipped counts
- started/completed/applied timestamps

`tag_refetch_work_results` stores each product result for a run:
- fetched JP/EN tags
- new JP/EN tags
- stale JP/EN tags
- skipped error text
- chosen stale-tag handling for JP/EN

Queue tables:
- `jobs` stores pending database queue jobs
- `job_batches` stores Laravel batch metadata

Migration note:
- legacy `products.genre`, `products.genre_english`, and `products.genre_custom` JSON columns are migrated into
  `genres` + `genre_product` by `2026_03_16_160000_convert_product_genre_titles_to_ids.php`
- after conversion, those legacy JSON columns are dropped

Runtime note:
- `ProductController@index` shows English + custom genres through one lightweight grouped query from `genre_product` + `genres`
- index cover images are rendered directly from `products.work_image`
- index filter state is normalized into `app/Support/ProductIndexFilters.php`, which is then reused by the controller, query layer, and Blade modal
- `app/Support/ProductIndexFilters.php` also builds query arrays used by progress tabs, preserved search state, and tag links on the index page
- switching progress tabs keeps the rest of the index request state, but intentionally drops the current `genre` filter
- clicking a series link opens the index with only the `series` filter applied
- `app/Support/ReturnTarget.php` normalizes the structured return state (`return_route`, `return_query`, `return_fragment`) used by create/edit/update/destroy flows
- update rebuilds the return URL through `ReturnTarget` and updates the `progress` query only when returning to the index after a status change
- create/store resolves scraped/custom titles into `genres` rows and syncs the pivot
- edit loads only the fetched English/custom genre rows it renders, while keeping fetched non-custom genres attached automatically
- update reads user-added genres from the form, stores them as `genre_product.source = custom`, and can reuse an existing fetched genre row while keeping it editable for that product
- custom create stores user-uploaded covers/samples in `storage/app/public/Works/{RJ}`, saves the uploaded cover public path in `products.work_image`, and attaches custom tags through the same genre resolver used by update
- Options -> Refetch Tags dispatches one queued `FetchProductTagsJob` per selected product and stores results before any product tags are changed
- the refetch progress panel is rendered by Livewire and polls every second only while the run is still running
- the Options page links to the latest refetch run when at least one run exists
- the selected-work search on the Options page is rendered by Livewire so filtering can run through the same product query rules as the server
- custom-only works are skipped during refetch because they do not have DLSite metadata to fetch from
- applying a refetch run attaches new fetched tags with `genre_product.source = fetched`
- stale fetched tags move to `genre_product.source = custom` by default, but the review form can remove them instead
- existing custom tags are preserved, and unused global auto-generated `genres` rows are detached from products but not deleted
- only the newest review run shows apply controls; older review runs are read-only to avoid applying stale review decisions after a newer fetch
- each review result row shows compact indicators for new JP, new EN, stale JP, and stale EN changes when those buckets are present
- refetch Blade views use small state/summary helpers on `TagRefetchRun` and `TagRefetchWorkResult` instead of checking raw status constants or counting buckets in controllers

## Scraper Integration
- `ProductController::Scrape()` runs the Python script in `python/venv`.
- Python fetches Japanese/English DLSite metadata, stores JSON in `storage/app/Works`, and downloads images to `storage/app/public/Works/{RJ}`.
- Custom create does not run the scraper and does not create or read scraped JSON.
- `DLSiteTagFetcher` runs `python/DLSiteTagFetcher.py` for the Refetch Tags queue job.
- `python/DLSiteTagFetcher.py` fetches tags only, returns `japanese.genre` and `english.genre` JSON through stdout, and does not write files.
- Known DLSite fetch errors are stored as skipped work results and are shown on the review page.

## Validation / Normalization Notes
- RJ input can be raw RJ code or URL containing RJ code.
- `BaseProductRequest` normalizes the create/edit `add[...]` fields in `prepareForValidation()`, then runs date-part and date-order checks through the form request `after()` hook.
- `StoreCustomProductRequest` keeps RJ-format and uniqueness validation, requires Japanese title, age category, and cover image, and validates cover/sample uploads as images up to 20 MB each.
- `StartTagRefetchRequest` validates all/selected refetch scope and resolves the product ids before the controller creates a run.
- `ApplyTagRefetchRequest` validates stale-tag actions and blocks applying any run except the newest review run.
- Custom tags are comma-separated and parsed with CSV rules:
  - commas inside a tag are supported via quotes
  - example: `"Junior / Senior (at work, school, etc)", Office Lady`

## Testing Overview
- Feature tests:
  - `tests/Feature/ProductControllerTest.php`
  - `tests/Feature/ProductGenreMigrationTest.php`
  - `tests/Feature/OptionsControllerTest.php`
- Unit tests:
  - `tests/Unit/Support/ProductIndexFiltersTest.php`
  - `tests/Unit/ExampleTest.php`
- Feature tests use `RefreshDatabase` to isolate DB state during test execution.
