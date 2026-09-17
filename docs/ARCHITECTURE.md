# Architecture

This document describes how DLSite List works at runtime and how its major application pieces fit together.

For installation, environment variables, Options settings, recovery commands, and other configuration, see [CONFIGURATION.md](CONFIGURATION.md).  
For test commands and the current test inventory, see [TESTING.md](TESTING.md).

## Main Application Flows

### Index

`GET /` is the main library page.

1. `ProductController@index` renders `resources/views/Index.blade.php`.
2. `app/Livewire/ProductIndex.php` owns Index state:
   - progress/status
   - general search
   - field filters
   - tag filters
   - date ranges
   - primary/secondary sorting
   - pagination
   - URL query-string state
3. `ProductIndex` normalizes request/Livewire state through `ProductIndexFilters`.
4. `ProductIndexResults` builds the filtered/sorted database query and selects only product columns needed by the current Index layout and runtime settings.
5. `ProductIndexRowBuilder` converts hydrated products and contributor rows into typed presentation rows for Blade.
6. Blade renders the saved Index field order as a desktop table or mobile cards.

Index state is kept in the query string so filtered/sorted views can be linked and restored.

`ProductIndexSettings` loads the Index-related `options` values in one batched lookup per render. It prepares:
- Index field layout
- Filter layout
- table width
- content overflow
- Image Viewer state
- Add/Edit modal state
- optional progress-status state
- DLSite link behavior

Tag and contributor data is loaded only when the current visible fields need it.

### Quick Add from DLsite

`GET /create` opens DLSite Quick Add and `POST /store` creates the work.

1. `StoreProductRequest` validates submitted form data.
2. `ProductController` requests the RJ work through `DLSiteWorkFetcher`.
3. `DLSiteWorkFetcher` calls `DLSitePythonRunner`.
4. The PHP side invokes `python/DLSiteScraper.py` with explicit JSON/image/log destinations.
5. PHP owns the fetch retry loop and validates the scraper result.
6. Scraped metadata is converted by `DLSiteWorkData`.
7. Product, tag, contributor, canonical JSON, and image data is stored.
8. The created work returns to the appropriate Index destination.

The fetcher can retry a failed DLsite fetch up to five times. Python does not own a second retry loop.

The Quick Add field layout controls which user-editable override rows are submitted. Hidden DLSite metadata fields such as age, circle, contributors, and descriptions can still be populated from scraped data.

### Custom Quick Add

`GET /create/custom` opens manual creation and `POST /store/custom` stores it.

Custom Quick Add:
- does not call the DLsite scraper
- requires a local cover image
- can store optional sample images
- uses the Custom Quick Add field layout
- stores only submitted visible optional metadata because there is no scraped fallback

Custom works still use the normal product/tag/contributor model and can participate in the library and Refetch selection workflow.

### Edit and Delete

`GET /edit/{product}` renders Edit Details.

`POST /update/{product}`:
- validates through `UpdateProductRequest`
- applies only fields that the saved Edit layout allows to be edited
- preserves hidden/read-only values
- synchronizes tags and contributors through shared support classes
- updates derived sort keys when relevant values change

`POST /destroy/{product}` first attempts to remove the canonical JSON and public image directory, logs cleanup failures, then deletes the product row.

`ReturnTarget` and product-aware return logic preserve normalized Index filters, sorting, and pagination across mutations. Create/Edit return to the affected product and its appropriate page; Delete removes the product anchor and clamps the saved page when deletion makes the previous page invalid.

### Tag Library

`GET /tags` renders the Tag Library shell and mounts `app/Livewire/TagLibraryManager.php`.

`TagLibraryManager` owns:
- current-language/custom tag listing
- zero-use manual tags
- tag search
- empty tag creation/deletion
- tag rename
- Tag Groups
- tag/group ordering
- Index tag visibility
- tag/group background and text colors
- parent/child tag relationships
- session-only Tag Library filters and sorting
- session-only Edit Tags mode
- links back to the Index tag filter

Tag Library uses the same stored tag rows as products. Renaming a tag updates the existing tag rather than replacing it, so product attachments, group memberships, colors, and relationships remain attached.

### Options

`GET /options` renders one of five tabs:

- `General`
- `Field Layouts`
- `Authentication`
- `Refetch`
- `Import / Export`

Most application settings are stored in the `options` table and edited by focused Livewire settings components.

Field Layouts configure six independent surfaces:
- Index Table Columns
- Index Filter Fields
- Index Sort Menu
- Edit Form Fields
- Quick Add Form Fields
- Custom Quick Add Form Fields

`ProductField` defines which fields exist on each surface, their defaults, locks, and editability rules. `ProductFieldLayout` normalizes persisted layout rows and prepares render metadata.

### Refetch DLSite Data

Refetch updates scraped DLsite-owned data without immediately overwriting the existing product.

1. The user starts Refetch All Works or Refetch Selected Works from Options.
2. `RefetchService::createRun()` creates a `refetch_runs` row and one `refetch_work_results` row per selected work.
3. Laravel creates a batch containing one `FetchProductWorkJob` per work.
4. Each job fetches staged metadata and, when image checking is enabled, staged cover/sample images into the Refetch storage roots.
5. `RefetchDiffBuilder` compares staged data with the canonical product data and records changes by `RefetchCategory`.
6. `OptionsRefetchProgress` shows running/cancelling progress.
7. `OptionsRefetchReview` lets the user choose whether to overwrite each changed category.
8. Applying changes promotes only accepted data and images.
9. Canonical JSON is promoted only when the accepted changes actually change the work.
10. Obsolete images are cleaned only for works whose image state changed.

Refetch has thirteen ordered review categories defined by `RefetchCategory`.

Cover and sample-image changes are independent. Refetch uses the shared `ProductImagePromotion` boundary so interrupted image replacement can be recovered safely.

Refetch creation, cleanup, and review application use the shared `LibraryMutationLock`, which also serializes Import apply and work-image cleanup.

Cancellation is cooperative: already-running work may finish while queued jobs observe the cancelled run state.

Only the newest Refetch run can be applied. Older completed runs remain available as historical/read-only review data rather than competing application states.

### Library Import / Export

Exports can contain works, images, Tag Library data, and Options. Imports stage and analyze archive data before any library changes.

The export flow is:

1. `LibraryTransferService` creates the export run and selected scope.
2. Queue work serializes portable domain data and inventories image files into `library_transfer_entries`.
3. `TransferArchive` packs deterministic data/image ZIP parts with a `manifest.json` per part.
4. Completed parts are validated and atomically published from private transfer storage.

The import flow is:

1. Uploaded ZIPs are stored under immutable private paths and inspected asynchronously.
2. Manifests, archive-set identity, paths, checksums, media types, and the complete required data-part set are validated before analysis.
3. Analysis maps incoming values to bounded, paginated `library_import_items` without mutating the library.
4. Review decisions are persisted as `ignore`, `overwrite`, or `merge`.
5. Apply rechecks current state under database and library-mutation locks, then updates approved categories in bounded batches.
6. Imported image changes use `ProductImagePromotion` so filesystem replacement and database changes can recover safely from interruption.

Transfer jobs use persisted checkpoints and operation/validation tokens so retries can resume work and stale queued jobs cannot publish or apply obsolete state. Same-direction unfinished runs are superseded, while completed runs remain historical.

Each bounded export planning phase locks and rechecks the run, then commits inventory/part changes, its checkpoint, and the successor job in one database transaction. Cancellation waits for the current batch. Staged JSON paths are deterministic and can be overwritten on retry after a rollback.

Partial work imports include only contributor roles explicitly present in either language document. Omitted roles are preserved; explicitly empty roles can be cleared with Overwrite.

#### Archive boundary

Schema v1 uses one archive-set identity across independent data and image ZIP parts. Every part contains `manifest.json`, which identifies the schema, part kind/count, scopes, and declared entries with sizes and SHA-256 checksums.

Portable entries are domain-oriented:

- each work is a deterministic `works/{RJ}/work.json` document using the `dlsite-async+dlsite-list` format
- Tag Library and Options are stored as bounded JSON fragments
- images are separate binary entries referenced by archive path, checksum, size, and media type

The portable format excludes database IDs, authentication data, internal storage paths, and deployment-specific settings. Import reads explicitly declared entries rather than extracting archives wholesale, and rejects unsupported/mixed layouts, unsafe paths, undeclared or conflicting entries, and integrity mismatches before review.

### Authentication

Administrator authentication is optional and disabled by default.

When enabled:
- an empty `users` table redirects application requests to administrator setup
- exactly one administrator account is supported
- guests are redirected to login
- normal controllers, mutations, autocomplete endpoints, and Livewire update/upload requests share the same authenticated Laravel web-session boundary
- login/setup/help/recovery routes remain available as required
- five failed login attempts from the same client IP within five minutes trigger throttling
- a successful login clears that IP's failed-attempt state
- `Remember me` keeps the administrator signed in for up to 180 days

Administrator password changes and resets rotate the remember token.

`RequireOptionalAuthentication` runs in the web middleware stack after session startup.

Files served directly from `public/` and `/storage` are not protected by Laravel session middleware.

## Application Structure

### Stack

- Backend: Laravel 12 on PHP 8.3
- Frontend: Blade, Livewire 4, plain CSS, and plain JavaScript
- Database: MySQL 8
- Background work: Laravel database queues and job batches
- Scraper: `python/DLSiteScraper.py`
- Persistent application files: Laravel storage plus publicly served work images

Laravel remains the application boundary. Python is a scraper process invoked by Laravel, not a second web service.

### Routes and Controllers

Routes are defined in `routes/web.php`.

Main controllers:
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/OptionsController.php`
- `app/Http/Controllers/AutocompleteController.php`
- `app/Http/Controllers/AuthenticationController.php`
- `app/Http/Controllers/RefetchController.php`
- `app/Http/Controllers/LibraryTransferController.php`

Main form requests:
- `app/Http/Requests/BaseProductRequest.php`
- `app/Http/Requests/StoreProductRequest.php`
- `app/Http/Requests/StoreCustomProductRequest.php`
- `app/Http/Requests/UpdateProductRequest.php`
- `app/Http/Requests/StartRefetchRequest.php`
- `app/Http/Requests/LibraryTransferUploadRequest.php`

### Livewire Components

Core application components include:

- `ProductIndex`
- `TagLibraryManager`
- `OptionsWorkSearch`
- `OptionsRefetchActions`
- `OptionsRefetchProgress`
- `OptionsRefetchReview`
- `OptionsTransfers`
- `OptionsTransferRun`

Settings components include:

- `UiLanguageSettings`
- `IndexPaginationSettings`
- `IndexSearchSettings`
- `IndexImageViewerSettings`
- `IndexTableWidthSettings`
- `IndexContentOverflowSettings`
- `AutocompleteSettings`
- `AutoSeriesSettings`
- `DlsiteLinkSettings`
- `OptionalProductStatusesSettings`
- `ProductFormThemeSettings`
- `ProductFormModalSettings`
- `TagLibraryDisplaySettings`
- `ProductFieldLayoutSettings`
- `AuthenticationSettings`
- `OptionsResetDefaults`

Shared reset-confirmation behavior is in `app/Livewire/Concerns/ConfirmsOptionReset.php`.

### Support Classes

Important support boundaries include:

Index:
- `ProductIndexFilters`
- `ProductIndexResults`
- `ProductIndexRowBuilder`
- `ProductIndexSettings`
- `ProductIndexSort`
- `ReturnTarget`

Fields/UI data:
- `ProductFieldLayout`
- `VisibleGenreAttachment`
- `TagColor`

Tags/contributors:
- `GenreSyncPayload`
- `ProductGenreSync`
- `ProductContributorSync`
- `GenreHierarchy`
- `GraphCycleValidator`

DLsite:
- `DLSitePythonRunner`
- `DLSiteWorkFetcher`
- `DLSiteWorkData`

Refetch:
- `RefetchService`
- `RefetchDiffBuilder`
- `RefetchCleanupService`

Library transfers:
- `LibraryTransferService`
- `TransferArchive`
- `TransferJobDispatcher`
- `TransferStorage`
- `ImportReview`
- `WorkArchiveData`
- `LibraryData`
- `PortableOptions`
- `LibraryTransferCleanupService`

Shared mutation/filesystem safety:
- `LibraryMutationLock`
- `ProductImagePromotion`
- `ProductImageCleanupService`

Autocomplete:
- `AutocompleteMatcher`
- `TagAutocompleteSearch`
- `SeriesAutocompleteSearch`

## Data Model

### Products

`products` stores the library work and user-owned tracking data, including:
- RJ/product identifier
- titles
- series
- age category
- Japanese and English descriptions
- progress
- score
- listening dates
- re-listen fields
- priority
- notes
- local cover path
- sample-image paths
- legacy/fallback maker metadata where required

The RJ code is the product identifier.

`sample_images` is stored as JSON and cast to a PHP array by `Product`.

Partial start/finish dates remain the editable source of truth. Derived integer sort columns are maintained for SQL sorting:
- `start_date_sort`
- `end_date_sort`

`rj_number` is maintained for numeric RJ sorting.

### Tags

Tags are normalized instead of stored as per-product text arrays.

`genres` stores the shared tag:
- display `title`
- normalized unique `title_key`
- Index visibility
- optional background color
- optional text color

`title_key` is created from the trimmed title using Unicode case folding and stored with binary collation. Case-only variants resolve to the same identity while Hiragana/Katakana variants remain distinct.

`genre_product` attaches tags to products and stores the source:
- `fetched`
- `custom`

`genre_product_languages` records which fetched language bucket supplied an attachment:
- `jp`
- `en`

A fetched tag can belong to both language buckets for one product. Custom attachments have no fetched-language row.

`UiLanguage` maps:
- UI `en` -> fetched tag language `en`
- UI `ja` -> fetched tag language `jp`

`VisibleGenreAttachment` defines the normal visible set as:
- all custom tags
- fetched tags for the selected UI language

A shared JP/EN fetched attachment is rendered once.

### Tag Groups

`genre_groups` stores group definitions and group-level Index visibility/color/order.

`genre_group_genre` stores many-to-many group membership and per-group tag order.

A tag can belong to multiple groups.

Index tags are alphabetical by default. When group ordering is enabled, grouped tags follow saved group/tag order and ungrouped tags follow alphabetically.

A tag is excluded from Index tag chips when:
- the tag itself is hidden, or
- any group containing the tag is hidden

### Tag Relationships

`genre_relations` stores directed parent/child tag relationships.

`GenreHierarchy`:
- resolves ancestors iteratively
- prevents self-relations
- prevents direct and indirect cycles through `GraphCycleValidator`, which Import reuses for incoming relationships
- synchronizes parent/child edits

When a child tag is attached to a work, missing parents/ancestors are added as `custom` attachments. Existing fetched ancestors keep their fetched source/language data.

Removing a relationship does not remove parent tags already attached to products.

### Contributors

`contributors` stores normalized creator/circle identities:
- `name`
- normalized `name_key`
- optional `maker_id`

`contributor_product` stores product-specific roles:
- `circle`
- `scenario`
- `voice_actor`
- `illustration`
- `author`

`ProductContributorSync` updates one role without detaching the same contributor from another role.

### Refetch

`refetch_runs` stores:
- Laravel batch id
- run status
- run-wide image-check choice
- total/processed/fetched/failed counts
- resolved category tabs
- lifecycle timestamps

`refetch_work_results` stores:
- product/run membership
- fetch status/errors
- image warnings
- detected category changes
- overwrite/ignore decisions
- whether an accepted decision actually changed the work

Laravel queue infrastructure uses:
- `jobs`
- `job_batches`

### Library transfers

`library_transfer_runs` stores transfer direction, lifecycle/checkpoint state, archive-set identity, settings, progress, warnings, and errors.

`library_transfer_parts` stores expected/uploaded ZIP parts and their validation state.

`library_transfer_entries` stores the bounded archive inventory used for packing, validation, and image access without rescanning manifests.

`library_import_items` stores review identity, baseline/incoming values, bounded previews, decisions, and apply results. Review state is persisted and paginated instead of being held as one Livewire payload.

Transfer mapping deliberately separates portable domain values from transport metadata. `LibraryData` maps library entities, `WorkArchiveData` owns the per-work document format, and `PortableOptions` limits import/export to explicitly portable settings.

## Storage and External Process Boundary

Canonical work metadata:

```text
storage/app/Works/{RJ}.json
```

Canonical public work images:

```text
storage/app/public/Works/{RJ}/...
```

Staged Refetch metadata:

```text
storage/app/Refetch/{run}/Works/{RJ}.json
```

Staged Refetch images:

```text
storage/app/public/Refetch/{run}/Works/{RJ}/...
```

Refetch and Import share `ProductImagePromotion` for canonical image replacement. It keeps private backups plus a durable recovery journal under:

```text
storage/app/ImagePromotions/active.json
storage/app/ImagePromotions/{token}/...
```

Recovery distinguishes committed from uncommitted replacements so an interrupted mutation can restore old files or finish post-commit cleanup before another library mutation proceeds.

The scraper is invoked with explicit output paths. Laravel validates returned manifest/JSON state instead of searching for arbitrary fallback output.

`ProductImageCleanupService` removes obsolete canonical cover/sample images while preserving files still referenced by the product. The manual cleanup command uses the same storage rules.

`RefetchCleanupService` deletes Refetch run records and staged content while preserving canonical products and `Works` files.

Private transfer files:

```text
storage/app/Transfers/{run}/...
```

`TransferStorage` owns transfer-local paths, immutable import staging, temporary archive builds, atomic publication, and safe removal of unreferenced temporary files. Transfer data remains private rather than being exposed through `/storage`.

Temporary-file sweeps hold the per-run upload lock throughout reference lookup and removal, after the worker's transfer-run lock. If an upload holds the lock, optional cleanup is skipped so files awaiting database registration remain safe; later sweeps or history cleanup remove abandoned files. Uploads wait briefly when a sweep already owns the lock so a large sequential selection does not fail at the boundary between validation and cleanup.

## UI and Localization

Application-owned UI text is stored in:
- `lang/en.json`
- `lang/ja.json`

`SetUiLocale` applies the application-wide saved locale for web requests. Missing/invalid locale values fall back to English.

Localized display labels are generated for enums, field layouts, and settings, while backed enum values, routes, query values, stored data, and user content remain stable.

`resources/views/components/list-menu-float.blade.php` is shared by Index, Options, Tag Library, Refetch, and transfer pages.

The shared menu also hosts the native `<dialog>` used for Add/Edit modals. Quick Add and Edit retain real `href` values; JavaScript intercepts only eligible ordinary primary clicks.

The Index uses:
- desktop table layout on larger screens
- stacked product cards on mobile

Create/Edit use class-based field components under:
- `app/View/Components/Fields`
- `resources/views/components/fields`

Public CSS/JS assets use `filemtime()` query strings for cache busting.

Autocomplete is provided by:
- `public/scripts/autocomplete-text.js`
- `public/css/autocomplete.css`
- `/autocomplete/tags`
- `/autocomplete/series`

DLSite Quick Add has a browser-side fetching status. Custom Quick Add intentionally does not load or render that status behavior.

## Logging

Laravel and the Python scraper use matching weekly UTC log rotation.

- Laravel handler: `app/Logging/WeeklyRotatingFileHandler.php`
- Python handler: `python/weekly_logging.py`

Configuration of retention belongs in [CONFIGURATION.md](CONFIGURATION.md).

## Maintenance Commands

Project-specific Artisan commands are implemented under `app/Console/Commands`:

- `admin:reset-password`
- `admin:reset`
- `works:cleanup-images`

How and when to run them is documented in [CONFIGURATION.md](CONFIGURATION.md).
