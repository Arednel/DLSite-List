# Architecture

## Stack
- Backend: Laravel 12 (PHP 8.3)
- Frontend: Blade templates, Livewire for the Index list, Options work search/settings/refetch progress, and plain CSS/JS
- Database: MySQL 8
- Scraper: Python scripts invoked from Laravel (`python/DLSiteScraper.py`, `python/DLSiteTagFetcher.py`)
- Background work: Laravel database queues and job batches

## Main Application Flow
1. User opens list page (`GET /`).
2. `ProductController@index` renders `resources/views/Index.blade.php`, then `app/Livewire/ProductIndex.php` owns list filters, sorting, pagination, and URL query state.
3. `GET /tags` renders the tag library, shows the work count for each English/custom genre, and links each tag back to the same index filter used on the list page.
4. `GET /options` renders the Options tab by default, and `GET /options?tab=refetch` renders the Refetch Tags tab.
5. User can create/edit/delete entries through forms.
6. Store flow (`POST /store`) validates input, runs scraper, reads scraped JSON, and creates a `products` row.
7. Custom store flow (`POST /store/custom`) validates manual input, skips scraper/network checks, stores the required local cover plus optional sample images, and creates a `products` row.
8. Update flow (`POST /update/{id}`) validates and updates editable fields.
9. Destroy flow (`POST /destroy/{id}`) removes DB row and related local files.
10. Refetch Tags starts a queued batch, stores per-work fetched/skipped results, shows progress, can cancel a running batch, then applies reviewed tag changes.

## Key Components
- Routes: `routes/web.php`
- Controller: `app/Http/Controllers/ProductController.php`
- Options controller: `app/Http/Controllers/OptionsController.php`
- Autocomplete controller: `app/Http/Controllers/AutocompleteController.php`
- Requests:
  - `app/Http/Requests/StartTagRefetchRequest.php`
  - `app/Http/Requests/ApplyTagRefetchRequest.php`
  - `app/Http/Requests/StoreProductRequest.php`
  - `app/Http/Requests/StoreCustomProductRequest.php`
  - `app/Http/Requests/UpdateProductRequest.php`
  - shared normalization/validation in `app/Http/Requests/BaseProductRequest.php`
- Model: `app/Models/Product.php`
- App option model: `app/Models/Option.php`
- Refetch models:
  - `app/Models/TagRefetchRun.php`
  - `app/Models/TagRefetchWorkResult.php`
- Refetch job/support:
  - `app/Jobs/FetchProductTagsJob.php`
  - `app/Support/DLSite/DLSitePythonRunner.php`
  - `app/Support/TagRefetch/DLSiteTagFetcher.php`
  - `app/Support/TagRefetch/TagRefetchService.php`
- Shared genre sync helpers:
  - `app/Support/ProductGenreSync.php`
  - `app/Support/GenreSyncPayload.php`
- Shared visible tag helper:
  - `app/Support/VisibleGenreAttachment.php`
- Autocomplete helpers:
  - `app/Support/Autocomplete/AutocompleteMatcher.php`
  - `app/Support/Autocomplete/TagAutocompleteSearch.php`
  - `app/Support/Autocomplete/SeriesAutocompleteSearch.php`
- Livewire components:
  - `app/Livewire/ProductIndex.php`
  - `app/Livewire/IndexPaginationSettings.php`
  - `app/Livewire/AutocompleteSettings.php`
  - `app/Livewire/FetchedTagEditingSettings.php`
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
- `resources/views/Index.blade.php` hosts `ProductIndex`; the Livewire view keeps the desktop table on larger screens and switches to stacked cards on mobile so search/actions still fit
- `resources/views/Create.blade.php` switches between DLSite create and custom create modes; `resources/views/Create.blade.php` and `resources/views/Edit.blade.php` use `public/css/edit.css` for both desktop and mobile form layouts and render reusable field components from `resources/views/components/fields/*.blade.php`
- `app/View/Components/Fields/*.php` provides the class-based field components used by those Blade views
- `AppServiceProvider` registers the enum-backed field component aliases used by `<x-fields.* />`
- the progress, score, priority, and re-listen field component classes read their select options from the matching enums in `app/Enums/*.php`
- Blade pages load CSS and JS from `public/` with `filemtime(public_path(...))` query strings for cache busting
- `public/scripts/autocomplete-text.js` and `public/css/autocomplete.css` provide opt-in autocomplete for tag CSV fields and single-value series fields through `data-autocomplete-*` attributes
- `resources/views/components/index/advanced-filters.blade.php` renders the index filter/sort modal
- `resources/views/components/index/*.blade.php` contains the reusable filter/select/radio pieces used by the index modal
- `app/Livewire/ProductIndex.php` binds filter/sort properties to the URL query string, then normalizes that state into a `ProductIndexFilters` object
- `app/Enums/*.php` holds enum-backed filter options for progress, priority, tag match, sort fields including `Added to the site Date`, sort backend metadata, and the numeric rating scales
- `app/Models/Product.php` owns the Laravel 12 local scopes used by index filtering/search and keeps derived index keys in sync for RJ sorting and partial date sorting
- `app/Support/ProductIndexResults.php` builds the filtered product query, selects only the columns rendered by the Index, and uses enum-defined SQL-backed pagination for default/RJ/scalar/date sorts
- `ProductIndex` reads `options.index_per_page` once per render, uses Livewire computed properties for derived filter/query/options/sort-icon state, and derives return/progress/tag query arrays from that normalized state
- the advanced filter modal defaults to `All tags` matching and `Desc` sort direction until the user chooses something else

## Data Model
`products` table stores:
- DLSite identifiers and titles
- progress/listening metadata (`progress`, dates, re-listen fields, priority)
- local image paths

`genres` table stores one row per visible genre title:
- `title` as the display text
- `title_key` as the unique identity key
- optional `group_id`

`title_key` is built from the trimmed title with Unicode case folding, then stored with a binary collation. This keeps tag matching case-insensitive while still treating Hiragana/Katakana variants as separate tags.

`genre_groups` stores optional genre group definitions.

`genre_product` is the many-to-many pivot table between `products` and `genres`.
It also stores a `source` value:
- `fetched` for scraper-provided genre attachments
- `custom` for tags the user typed into the editable Custom Tags field

`genre_product_languages` stores the fetched language buckets for each product/tag attachment:
- `genre_product_id`
- `language`

Current language values are `jp` and `en`, but the column is a string so future language codes can be added without changing the global genre row. Custom pivot rows do not have language rows. A fetched tag can have both `jp` and `en` rows for the same product, so titles like `ASMR` and `VTuber` remain a single `genres` row while still recording that DLSite returned the tag in both languages.

Current English/custom UI surfaces show:
- custom pivot rows
- fetched pivot rows with an `en` language row

JP-only fetched tags stay attached and stored, but are hidden from the current Index/Edit/Tag Library UI until a Japanese tag UI exists.

`tag_refetch_runs` stores each Options -> Refetch Tags batch:
- batch id and status
- selected product ids
- total/processed/fetched/skipped counts
- started/completed/cancelled/applied timestamps

`tag_refetch_work_results` stores each product result for a run:
- fetched JP/EN tags
- new JP/EN tags
- stale JP/EN tags
- custom tags that DLSite now returns as JP/EN fetched tags
- skipped error text
- chosen new-tag handling for JP/EN
- chosen stale-tag handling for JP/EN
- chosen custom-to-fetched handling

Queue tables:
- `jobs` stores pending database queue jobs
- `job_batches` stores Laravel batch metadata

`options` stores app-level settings as scalar string values:
- `index_per_page` controls Index pagination, defaults to `100`, accepts fixed choices (`10`, `25`, `50`, `100`, `250`, `500`, `1000`) or any positive integer, and can be set to `unlimited`
- `edit_fetched_tags` controls whether fetched English tags can be edited from Edit Work, and defaults to disabled
- `tag_autocomplete_order` controls tag suggestion ordering, defaults to `usage`, and can be set to `first_word`
- `series_autocomplete_order` controls series suggestion ordering, defaults to `usage`, and can be set to `first_word`
- `App\Models\Option` normalizes stored scalar strings into the runtime values the app uses

Current repo-level Index sort-key indexes:
- `products.id` remains the primary key
- `products.rj_number`, `start_date_sort`, and `end_date_sort` are derived nullable integer sort keys with indexes used by Index SQL sorting

Migration note:
- legacy `products.genre`, `products.genre_english`, and `products.genre_custom` JSON columns are migrated into
  `genres` + `genre_product` by `2026_03_16_160000_convert_product_genre_titles_to_ids.php`
- after conversion, those legacy JSON columns are dropped
- legacy global `genres.type` / `genres.language` metadata is migrated into product-specific
  `genre_product_languages` rows by `2026_05_24_000000_create_genre_product_languages_table.php`
- the `genre_product_languages` down migration restores old global metadata best-effort because one fetched title can now belong to multiple languages for the same product
- `2026_05_26_000000_add_title_key_to_genres_table.php` moves tag uniqueness from `genres.title` to `genres.title_key`; rollback can fail if kana-distinct tags were added because the old `genres.title` unique index used MySQL's broader text collation

Runtime note:
- `ProductIndex` shows English + custom genres through one lightweight grouped query from `genre_product` + `genres` for the current page
- index cover images are rendered directly from `products.work_image`
- `products.start_date` and `products.end_date` JSON remain the editable/display source of truth; their `*_sort` columns store `YYYYMMDD` integers with missing month/day as `00`
- `ProductIndex` keeps its filter/sort state in the URL through Livewire's `queryString()` config, then normalizes that state into `app/Support/ProductIndexFilters.php`
- `app/Support/ProductIndexFilters.php` provides the normalized filter query used by progress tabs, preserved search state, tag links, explicit Livewire query-string keys, and the visibility-affecting filter groups used by return redirects
- Index genre links, tag filters, genre-backed search, edit tag loading, and Tag Library use `VisibleGenreAttachment` for the same visible-tag rule as rendered tags: custom source or fetched `en` language row
- opening and closing the advanced filter modal is local Alpine state registered in `public/scripts/index-advanced-filters.js`, not Livewire state, so showing the modal does not rerun the Index query or reset draft filter values
- changing advanced sort draft fields is client-side/deferred through that Alpine component until Apply, so the modal does not send requests while choosing primary/secondary sort columns
- desktop table headers and the advanced sort modal both update the same Livewire-backed server-side sort state
- Index pagination uses Livewire/Laravel paginator links with the project pagination view and Livewire's scroll target data to return to `#progress-menu`, keeping progress tabs, search, and Filter visible after page changes
- switching progress tabs keeps the rest of the index request state, but intentionally drops the current `genre` filter
- clicking a series link opens the index with only the exact `series` filter applied
- `/autocomplete/tags` returns all stored genre titles, including JP/EN/custom tags
- `/autocomplete/series` returns distinct non-empty series values
- autocomplete matching uses word-prefix behavior for Latin-style text and substring matching for non-ASCII input so Japanese tag text can be found naturally
- autocomplete search is split into small tag and series search helpers that share the same matcher/ranking logic
- autocomplete ordering is configurable per source from Options: `usage` orders all matches by attached work count and then title; `first_word` puts values starting with the typed query before later-word matches, then orders each group by attached work count and title
- `app/Support/ReturnTarget.php` normalizes index-only return state (`return_query`, `return_fragment`) used by create/edit/update/destroy flows and builds index URLs with Laravel URI helpers
- successful create/update redirects prioritize showing the created/edited work on the Index: `ReturnTarget` first keeps the saved page when the work is already visible there, then avoids per-filter cleanup when the full query still matches, otherwise drops filters that would hide the work, preserves matching filters and sort state, and uses `ProductIndexResults` to calculate the correct page before appending the work anchor
- update detects whether visibility-affecting product fields or custom tags changed before redirecting, so unchanged edits can trust the current index query unless the saved return state no longer contains the work
- destroy keeps the saved index query but clamps stale page numbers to the last valid page after deletion; storage cleanup uses Laravel storage deletes and logs cleanup failures without blocking product deletion
- create-page Go Back ignores malformed `return_url` input, uses Laravel previous URL behavior with the Index as fallback, preserves that back URL while switching between DLSite Create and Custom Create, and restores the flashed return target after validation or scraper errors
- create/store resolves scraped/custom titles into `genres` rows and syncs the pivot
- edit loads only the fetched English/custom genre rows it renders, while keeping fetched non-custom genres attached automatically
- update reads user-added genres from the form, stores them as `genre_product.source = custom`, and can reuse an existing fetched genre row while keeping it editable for that product
- when `options.edit_fetched_tags` is enabled, edit also renders fetched English tags as an editable CSV field; update replaces only the `en` fetched language bucket, preserves hidden `jp` fetched rows, and still keeps fetched-over-custom precedence
- custom create stores user-uploaded covers/samples in `storage/app/public/Works/{RJ}`, saves the uploaded cover public path in `products.work_image`, and attaches custom tags through the same genre resolver used by update
- product create/update and refetch apply use `app/Support/ProductGenreSync.php` to sync `genre_product.source` and `genre_product_languages` together
- `app/Support/GenreSyncPayload.php` keeps fetched-over-custom source precedence and builds the fetched language map used by `ProductGenreSync`
- `app/Models/Genre.php` resolves tag titles by `title_key`, preserving the existing display title when the new input only differs by case
- Options -> Refetch Tags dispatches one queued `FetchProductTagsJob` per selected product and stores results before any product tags are changed
- running refetch runs can be cancelled from the progress page; cancellation changes the run from `running` to `cancelling`, cancels that run's Laravel batch, lets any already-started Python fetch finish, and moves the run to `review` after pending results become fetched or skipped
- cancelled-before-fetch work results are stored as skipped results, while fetched results completed before or during cancellation remain reviewable and can be applied
- the refetch progress panel is rendered by Livewire and polls every second while the run is active (`running` or `cancelling`)
- the Options page has separate `Options` and `Refetch` tabs; validation errors from refetch forms reopen the Refetch tab
- the Refetch tab links to the latest refetch run when at least one run exists
- the Options tab includes an Index Pagination setting powered by Livewire and persisted in `options.index_per_page`; changing the mode can reveal the custom-value input immediately, but the setting is only persisted when Save is submitted
- the Options tab includes a Livewire fetched-tag editing toggle persisted in `options.edit_fetched_tags`
- the Options tab includes Livewire autocomplete ordering settings persisted in `options.tag_autocomplete_order` and `options.series_autocomplete_order`
- the selected-work search on the Refetch tab is rendered by Livewire and uses Laravel query helpers for the ID/title match
- the Refetch tab work list and queued all/selected refetch ids use numeric RJ descending order, matching the Index default order
- custom-only works are skipped during refetch because they do not have DLSite metadata to fetch from
- applying a refetch run attaches new fetched tags with `genre_product.source = fetched` and one language row per fetched bucket, unless the review form ignores new JP/EN tags globally or for that work
- stale fetched JP/EN actions remove only that language row when another fetched language remains; the tag moves to `genre_product.source = custom` only when no fetched language rows remain and the selected stale action is move-to-custom
- custom tags that DLSite now returns as fetched are promoted to fetched by default; the review form has global and per-work controls to keep those tags custom instead
- refetch diff/apply reads current fetched/custom tags through the Product genre relationships and compares titles by the same `Genre::titleKey()` identity rule used for storage
- existing custom tags are preserved, and unused global fetched `genres` rows are detached from products but not deleted
- only the newest review run shows apply controls; older review runs are read-only to avoid applying stale review decisions after a newer fetch
- each review result row shows compact indicators for new JP, new EN, stale JP, stale EN, and custom-to-fetched changes when those buckets are present
- refetch Blade views use small state/summary helpers on `TagRefetchRun` and `TagRefetchWorkResult` instead of checking raw status constants or counting buckets in controllers

## Scraper Integration
- `app/Support/DLSite/DLSitePythonRunner.php` runs Python scripts through Laravel's Process facade with the project `python/venv`.
- Product create runs `python/DLSiteScraper.py` through `DLSitePythonRunner`.
- Python fetches Japanese/English DLSite metadata, stores JSON in `storage/app/Works`, and downloads images to `storage/app/public/Works/{RJ}`.
- Custom create does not run the scraper and does not create or read scraped JSON.
- `DLSiteTagFetcher` runs `python/DLSiteTagFetcher.py` through `DLSitePythonRunner` for the Refetch Tags queue job.
- `python/DLSiteTagFetcher.py` fetches tags only, returns `japanese.genre` and `english.genre` JSON through stdout, and does not write files.
- Known DLSite fetch errors are stored as skipped work results and are shown on the review page.

## Validation / Normalization Notes
- RJ input can be raw RJ code or URL containing RJ code.
- `BaseProductRequest` normalizes the create/edit `add[...]` fields in `prepareForValidation()`, then runs date-part and date-order checks through the form request `after()` hook.
- `BaseProductRequest` validates progress, score, priority, and re-listen value against the matching product enums so form input cannot drift from the UI option sets.
- `StoreCustomProductRequest` keeps RJ-format and uniqueness validation, requires Japanese title, age category, and cover image, and validates cover/sample uploads as images up to 20 MB each.
- `StartTagRefetchRequest` validates all/selected refetch scope and resolves the product ids before the controller creates a run.
- `ApplyTagRefetchRequest` validates new-tag, stale-tag, and custom-to-fetched actions, then blocks applying any run except the newest review run.
- Custom tags are comma-separated and parsed with CSV rules:
  - commas inside a tag are supported via quotes
  - example: `"Junior / Senior (at work, school, etc)", Office Lady`
- autocomplete inserts selected tag suggestions with the same CSV quote rules and appends `, ` so the next tag can be typed immediately
