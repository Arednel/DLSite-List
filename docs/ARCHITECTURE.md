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

The Index reads Japanese text from `products` and selects the preferred translation with locale-scoped subqueries when the layout needs it. General search can match any stored title. The Title filter and work selectors search Japanese and the preferred language. Description search follows the [Index Search settings](CONFIGURATION.md#index-search).

`ProductIndexSettings` loads the Index-related `options` values in one batched lookup per render. It prepares:
- Index field layout
- Filter layout
- content overflow
- Image Viewer state
- Add/Edit modal state
- optional progress-status state
- rating scale and visual style
- DLsite link behavior

Tag and contributor data is loaded only when the current visible fields need it.

### Quick Add from DLsite

`GET /create` opens DLSite Quick Add and `POST /store` creates the work.

1. `StoreProductRequest` validates submitted form data.
2. `ProductController` builds a `DLSiteProductImportInput` snapshot from the validated values and Quick Add layout.
3. `DLSiteProductImporter` requests the product through `DLSiteWorkFetcher`.
4. `DLSiteWorkFetcher` calls the Python scraper through `DLSitePythonRunner` with the requested languages and JSON, image, and log destinations.
5. PHP owns the fetch retry loop and validates the scraper result.
6. Scraped metadata is converted by `DLSiteWorkData`; explicit visible Quick Add values override the fetched values where supported.
7. `DLSiteProductImporter` creates the Product, persists only successfully downloaded cover/sample image paths, and synchronizes tags/contributors.
8. The created work returns to the appropriate Index destination.

A failed cover download is stored as no cover, and failed sample downloads are omitted from the saved sample list. The fetcher can retry a failed DLsite fetch up to five times. Python does not own a second retry loop.

The Quick Add field layout controls which user-editable override rows are submitted. Hidden DLsite metadata such as age, product format, DLsite rating, circle, publisher, brand, contributors, and descriptions can still be populated from scraped data.

The main DLsite `Product Format` is stored first; only SND (Voice), MS2 (Music), and MV2 (Animation) are accepted as additional formats from Work.options. When an additional format mirrors the fetched main format, the additional value is omitted: SOU suppresses SND, MUS suppresses MS2, and MOV suppresses MV2. An additional format is kept when its matching main format is absent. This DLsite-fetch rule does not apply to manual Edit input or Library export/import, which preserve the stored values.

#### DLsite Work Data Mapping

`DlsiteLanguage` defines the supported metadata languages and maps each to its `dlsite-async` locale. PHP always requests Japanese and tells the Python scraper which additional locales to fetch. The scraper writes one document per language under `locales` and uses Japanese for images. PHP also reads the historical `japanese` and `english` sections in saved scraper responses.

`DLSiteWorkData` maps the scraper response to Product fields. Shared metadata comes from the Japanese document. Title, Description, and fetched Tags come from each fetched locale. A fetched title or description that matches its Japanese counterpart is treated as a missing translation.

- Japanese `maker_name` is used as Circle, Publisher, or Brand when `maker_id` starts with `RG`, `BG`, or `VG` respectively
- each locale's Description uses the first nonempty value from `intro_masked`, `intro_s`, then `intro` when `description` is absent
- Japanese `creater` is used as Author when `author` is absent or `null`; an explicitly empty Author list remains empty
- a trailing `+09:00` is removed from Japanese `announce_date` without otherwise changing the supplied date or time
- `rate_count_detail` is normalized by `DLSiteRating`; its weighted vote distribution is used once to persist independent two-decimal 5-point and 10-point ratings plus the vote count

Explicit Circle, Publisher, Brand, and Description fields take precedence over their aliases, even when empty. An unfetched locale is absent from the result, so Refetch can preserve its stored values. A fetched locale with empty values can produce a removal for review.

For translated works, the fetcher looks for the original in the Japanese `language_editions` entry with `display_order` 1, then in `translation_info.original_workno`. When it finds a different work, it fetches that work in Japanese for its `RG` maker metadata.

The original response is stored beside the translated JSON as `<translation-id> - <original-id>.json`. `DLSiteWorkData` uses the original Maker ID, Circle, and Circle contributor while retaining the remaining metadata from the translated work. If the original work cannot be resolved, the translated work's own metadata is used.

### Custom Quick Add

`GET /create/custom` opens manual creation and `POST /store/custom` stores it.

Custom Quick Add:
- does not call the DLsite scraper
- can store optional cover image and sample images
- uses the Custom Quick Add field layout
- stores only submitted visible optional metadata because there is no scraped fallback

Custom Quick Add and Edit keep newly selected image files in the browser until Save. Previews use browser object URLs, while removal/reordering state is represented by the form and submitted through the normal multipart request. `ProductImageSubmission` converts that submitted state into the final cover/sample image set and delegates filesystem-changing saves to `ProductImagePromotion`. Existing-image removal/order can be restored through Laravel old input after validation; newly selected local files must be selected again after a full redirect.

Quick Add and Edit save changes using the metadata language shown when the form opened. If validation fails, the form reopens using the current language set in Options. Any title or description you typed into the translation fields reappears if those fields are visible, even if the language has changed.

Custom works still use the normal product/tag/contributor model and can participate in the library and Refetch selection workflow.

### Bulk Import

`GET /create/bulk` opens Bulk Import and `POST /store/bulk` starts a background run.

1. `StartBulkImportRequest` extracts, normalizes, de-duplicates, and validates up to 500 product codes plus the shared form values.
2. `BulkImportService` snapshots the input, including the effective DLsite metadata fetch-language list, creates the run/items, and dispatches sequential `ImportBulkWorkJob` jobs on the database queue.
3. Each item skips an existing product or uses the shared `DLSiteProductImporter`; expected DLsite work failures are recorded per item while unexpected failures stop the run.
4. `FinishBulkImportRunJob` reconciles counts and completes the run.
5. `OptionsBulkImports` paginates history and cleanup, while `BulkImportRunCard` updates only queued/running runs.

Product creation, genre/contributor synchronization, and successful Bulk item completion/count updates commit in one database transaction. A worker interruption before commit rolls them back so a redelivered job can retry. A normal completion exception fails the run through queue failure handling rather than automatically retrying.

If the item or run is no longer eligible for successful completion, product creation rolls back instead of committing without a matching `Imported` result.

Bulk Import history cleanup deletes all persisted run/item history and is unavailable while a run is active.

### Edit and Delete

`GET /edit/{product}` renders Edit Details.

`POST /update/{product}`:
- validates through `UpdateProductRequest`
- applies only fields that the saved Edit layout allows to be edited
- preserves hidden/read-only values
- synchronizes tags and contributors through shared support classes
- updates derived sort keys when relevant values change

`POST /destroy/{product}` first attempts to remove the canonical JSON, any `<translation-id> - <original-id>.json` original-work JSON files associated with that work, and the public image directory. Cleanup failures are logged and do not prevent deletion of the product row.

`ReturnTarget` and product-aware return logic preserve normalized Index filters, sorting, and pagination across mutations. Create/Edit return to the affected product and its appropriate page; Delete removes the product anchor and clamps the saved page when deletion makes the previous page invalid.

### Tag Library

`GET /tags` renders the Tag Library shell and mounts `app/Livewire/TagLibraryManager.php`.

`TagLibraryManager` owns:
- listing fetched tags for the selected language and all Custom Tags
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

`GET /options` renders one of six tabs:

- `General`
- `Display`
- `Authentication`
- `Refetch`
- `Import / Export`
- `Bulk Imports`

Most application settings are stored in the `options` table and edited by focused Livewire settings components.

UI wording is a presentation-only setting. `ContentFocus` defines the selected UI wording profile (`general`, `listening`, `reading`, `games`, `video`, `music`, or `artwork`), and the request-scoped `ContentTerminology` service resolves focus-sensitive labels through Laravel translations.

Focus profiles only define UI wording that differs from `General`; any missing profile-specific term automatically falls back to the `General` wording. Score wording uses numeric keys in the same term map and shares the same lookup.

The setting does not change stored or internal values. Status values such as `Listening` and `Plan to Listen`, score values, repeat-field keys, URL query parameters, and transfer/database formats remain unchanged.

Enums and field-label presenters delegate only focus-sensitive labels to `ContentTerminology`; all other interface text continues to use normal Laravel translations directly.

The Display tab's Field Layouts section configures seven independent layouts:
- Index Table Columns
- Index Filter Fields
- Index Sort Menu
- Edit Form Fields
- Quick Add Form Fields
- Bulk Import Form Fields
- Custom Quick Add Form Fields

`ProductField` defines which fields exist on each surface, their defaults, locks, and editability rules. `ProductFieldLayout` normalizes persisted layout rows and prepares render metadata.

#### Generated Options Stylesheet

Index table width, side margins, Index column-width overrides, and enabled overflow heights are compiled into `storage/app/public/generated/options.css` and served through `/styles/options.css`.

The stylesheet is regenerated after CSS-affecting Options are saved, reset, or imported. Static CSS remains authoritative for defaults, so the generated file contains only saved overrides and is loaded after the normal Index styles.

#### User Stylesheet

Application-wide Custom CSS is stored in `storage/app/public/generated/user.css` and served through `/styles/user.css` as `text/css`. `CustomCssSettings` reads and replaces the file directly. `Option::resetVisibleSettingsToDefault()` clears `user.css`.

Custom CSS uses last-write-wins semantics. Writes and import recovery share a short stylesheet-specific lock. Import publication keeps a durable backup/journal and restores it only when an interrupted uncommitted import is still the latest stylesheet write.

Every standalone user-visible page loads `user.css` after its normal stylesheets. Nested components do not load it again. The stylesheet route bypasses optional authentication and locale middleware so Custom CSS also applies to authentication pages.

### Refetch DLsite Data

Refetch updates scraped DLsite-owned data without immediately overwriting the existing product.

1. The user starts Refetch All Works or Refetch Selected Works from Options.
2. `RefetchService::startRun()` creates a `refetch_runs` row and one `refetch_work_results` row per selected work.
3. Laravel creates a batch containing one `FetchProductWorkJob` per work. Each job carries the fetch languages selected when the run started. Run creation and queue dispatch commit together under the library mutation lock.
4. Each job fetches staged metadata and, when image checking is enabled, staged cover/sample images into the Refetch storage roots.
5. `RefetchDiffBuilder` compares staged data with the canonical product data and records changes by `RefetchCategory`.
6. `OptionsRefetchProgress` shows running/cancelling progress.
7. `OptionsRefetchReview` lets the user choose whether to overwrite each changed category.
8. Applying changes promotes only accepted data and images.
9. Canonical JSON is promoted only when the accepted changes actually change the work.
10. For translated works, the matching staged original-work JSON is copied beside the canonical JSON when available.
11. Obsolete images are cleaned only for works whose image state changed.

Refetch groups Title and Description changes by work and keeps a separate decision for each changed language. Fetched Tag decisions also apply per language. Detailed review can promote a Custom Tag to Fetched. Ignoring every language leaves the tags unchanged.

The work JSON is required for promotion. If the associated original-work JSON is missing or cannot be copied, applied metadata is retained and finalization continues for the other works.

Refetch has nineteen ordered review categories defined by `RefetchCategory`.

Cover and sample-image changes are independent. Refetch uses the shared `ProductImagePromotion` boundary so interrupted image replacement can be recovered safely.

Refetch creation, cleanup, and review application use the shared `LibraryMutationLock`, which also serializes Import apply and work-image cleanup.

Cancellation is cooperative: already-running work may finish while queued jobs observe the cancelled run state.

Refetch batches continue after individual job failures. Permanently failed jobs reconcile still-pending results without overwriting terminal states. Each Refetch work has a 600-second queue timeout, with a 590-second scraper-process timeout so hung fetches normally fail back into PHP first.

Only the newest Refetch run can be applied. Older completed runs remain available as historical/read-only review data rather than competing application states.

### Library Import / Export

Exports can contain works, images, Tag Library data, and Options. Imports stage and analyze archive data before any library changes.

The export flow is:

1. `LibraryTransferService` creates the export run and selected scope.
2. Queue work serializes portable domain data and inventories image files into `library_transfer_entries`.
3. `TransferArchive` packs deterministic data/image ZIP parts with a `manifest.json` per part.
4. Completed parts are validated and atomically published from private transfer storage.

Export planning loads Products, their translations, contributors, and tag attachments in batches. Each work document includes shared fetched tags in every supported language to which they belong and lists custom tags once.

The import flow is:

1. Uploaded ZIPs are stored under immutable private paths and inspected asynchronously.
2. Manifests, archive-set identity, paths, checksums, media types, and the complete required data-part set are validated before analysis.
3. Analysis maps incoming values to bounded, paginated `library_import_items` without mutating the library.
4. Review decisions are persisted as `ignore`, `overwrite`, or `merge`.
5. Apply rechecks current state under database and library-mutation locks, then updates approved categories in bounded batches.
6. Imported image changes use `ProductImagePromotion` so filesystem replacement and database changes can recover safely from interruption.

Transfer jobs use persisted checkpoints and operation/validation tokens so retries can resume work and stale queued jobs cannot publish or apply obsolete state. Same-direction unfinished runs are superseded, while completed runs remain historical.

Each bounded export planning phase locks and rechecks the run, then commits inventory/part changes, its checkpoint, and the successor job in one database transaction. Cancellation waits for the current batch. Staged JSON paths are deterministic and can be overwritten on retry after a rollback.

Partial work imports read shared DLsite fields from the Japanese document. Omitted fields preserve existing values. An explicit null for optional text or an empty fetched Tag list can clear that value on Overwrite. Import validates incoming fields before applying the same aliases used for scraped work data.

For existing works, Import Review keeps all languages for a work on the same page. Titles, Descriptions, and fetched Tags can each be reviewed separately for each language. Merging fetched tags rereads the selected language's tags under the apply lock and preserves memberships in other languages. For a new work, one Add or Ignore decision covers the whole work.

Incomplete image categories remain importable. Import applies the valid image files present in the archive and records a warning; an incomplete category with no available files is treated as unavailable data rather than an explicit empty category, so it does not clear existing images. New works follow the same rule and import any available images.

#### Archive Boundary

Exports use schema v4. Imports accept schemas v1 through v4. All parts must share an archive-set identity and schema version. Each part has a `manifest.json` declaring its kind, part count, scopes, entry sizes, and SHA-256 checksums.

In v4, `work.json` contains `locales` for DLsite metadata and `dlsite_list` for library fields. Each work must supply its Japanese title in `locales.ja.work_name`. Other supported locales are optional on import. Explicit translations that match Japanese are preserved. Schemas v1 through v3 used `japanese` and `english` sections and could copy Japanese into a missing English field. Import treats those matching legacy values as missing translations.

Portable entries are domain-oriented:

- `works/{product_code}/work.json` holds one work. The manifest identifies its format as `dlsite-async-locales+dlsite-list` in v4 or `dlsite-async+dlsite-list` in earlier versions. Versions 2 and later use `product_code` for RJ, BJ, and VJ works. Version 1 uses `rj_code` and supports RJ works only.
- Tag Library and Options are stored as bounded JSON fragments
- since v3, the `user_css` scope can include a root-level `user.css` file. Import stages it until Overwrite is applied. A durable journal restores uncommitted replacements after interruption.
- images are separate binary entries referenced by archive path, checksum, size, and media type

The portable format excludes database IDs, authentication data, internal storage paths, and deployment-specific settings. Import reads explicitly declared entries rather than extracting archives wholesale, and rejects unsupported/mixed layouts, unsafe paths, undeclared or conflicting entries, and integrity mismatches before review.

For v1 archives, legacy `rj_code` fields in the three portable Add/Import Options layouts and the `rj` Index sort field are normalized to `product_code` before Import Review. 
Schema v1/v2 archives have no `user_css` scope, so importing them never changes the local `user.css` file. 
Site ID is imported from `dlsite_list.site_id` when present, otherwise from the Japanese JSON `site_id`, without age-based inference.

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
- files under `storage/app/public` are served through Laravel; unauthenticated `/storage/*` requests return HTTP 401 while authentication is enabled

Administrator password changes and resets rotate the remember token.

`RequireOptionalAuthentication` runs in the web middleware stack after session startup. The `/storage/*` route replaces it with the narrower `RequireOptionalStorageAuthentication` middleware and skips UI-locale resolution, while retaining the session/cookie middleware required for normal and remember-me authentication.

Static application assets under `public/` remain directly web-accessible and are not part of the authenticated library-media boundary.

## Application Structure

### Stack

- Backend: Laravel 12 on PHP 8.3
- Frontend: Blade, Livewire 4, plain CSS, and plain JavaScript
- Database: MySQL 8
- Background work: Laravel database queues and job batches
- Scraper: `python/DLSiteScraper.py`
- Persistent application files: Laravel storage, with `storage/app/public` files served through Laravel

Laravel remains the application boundary. Python is a scraper process invoked by Laravel, not a second web service.

### Docker Startup

`compose.yaml` is the published runtime definition. Release automation publishes it as the `ghcr.io/arednel/dlsite-list` OCI Compose application, and it references the pre-built application and web images in GHCR. `compose.dev.yaml` is the standalone local source-build definition used to build those images from the checked-out repository.

Before MySQL starts, `secrets-init` runs `docker/init-secrets.php` to save `APP_KEY`, `DB_PASSWORD`, and a complete `laravel.env` in the `secrets` volume.

The app and queue mount this volume read-only. Their `.env` symlink points to `laravel.env`, so Laravel loads configuration normally. MySQL and optional phpMyAdmin mount only `db_password` through `volume.subpath` and read it through their `_FILE` environment variables.

The test services use the same flow with a separate `secrets_test` volume. For generation, overrides, and upgrades, see [Docker Secrets](CONFIGURATION.md#docker-secrets).

### Routes and Controllers

Routes are defined in `routes/web.php`.

Main controllers:
- `app/Http/Controllers/PublicStorageController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/OptionsController.php`
- `app/Http/Controllers/AutocompleteController.php`
- `app/Http/Controllers/AuthenticationController.php`
- `app/Http/Controllers/RefetchController.php`
- `app/Http/Controllers/LibraryTransferController.php`
- `app/Http/Controllers/BulkImportController.php`

Main form requests:
- `app/Http/Requests/BaseProductRequest.php`
- `app/Http/Requests/StoreProductRequest.php`
- `app/Http/Requests/StoreCustomProductRequest.php`
- `app/Http/Requests/UpdateProductRequest.php`
- `app/Http/Requests/StartRefetchRequest.php`
- `app/Http/Requests/LibraryTransferUploadRequest.php`
- `app/Http/Requests/StartBulkImportRequest.php`

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
- `OptionsBulkImports`
- `BulkImportRunCard`

Settings components include:

- `UiLanguageSettings`
- `ContentTerminologySettings`
- `CustomCssSettings`
- `IndexPaginationSettings`
- `IndexSearchSettings`
- `IndexImageViewerSettings`
- `IndexTableWidthSettings`
- `IndexContentOverflowSettings`
- `AutocompleteSettings`
- `AutoSeriesSettings`
- `DlsiteLinkSettings`
- `OptionalProductStatusesSettings`
- `RatingSettings`
- `ThemeSettings`
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
- `DLSiteRating`
- `DLSiteProductImportInput`
- `DLSiteProductImporter`

Bulk import:
- `BulkImportService`
- `BulkImportCleanupService`
- `ImportBulkWorkJob`
- `FinishBulkImportRunJob`

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
- product identifier
- required Japanese title (`work_name`)
- series
- age category
- site ID
- circle
- publisher
- brand
- product format
- scheduled release date
- optional Japanese description (`description`)
- progress
- score
- DLsite 5-point rating, 10-point rating, and vote count
- listening dates
- re-listen fields
- priority
- notes
- local cover path
- sample-image paths
- maker ID metadata associated with the work/circle

The product code (RJ, BJ or VJ) is the product identifier.

`product_localizations` holds optional translated titles and descriptions, with one row per Product and locale. A row is removed when both fields are blank. Translation changes update the parent Product timestamp, and deleting a Product also deletes its translations.

`sample_images` and `product_format` are stored as JSON and cast to PHP arrays by `Product`. DLsite ratings are stored as exact two-decimal values in `dlsite_rating_5` and `dlsite_rating_10`, with `dlsite_rating_count` stored separately so Index filtering, sorting, and display do not recalculate the weighted average.

Partial start/finish dates remain the editable source of truth. Derived integer sort columns are maintained for SQL sorting:
- `start_date_sort`
- `end_date_sort`

`code_number` stores the numeric part of RJ/BJ/VJ product codes for sorting (non-unique), using `id` as a tie-breaker.

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

`genre_product_languages` records the metadata locales that supplied a fetched attachment. A fetched tag can belong to several languages on the same Product. Custom attachments have no language rows. Updating one language preserves the other memberships.

`VisibleGenreAttachment` defines the normal visible set as:
- all custom tags
- fetched tags for the selected language

A fetched tag shared across languages appears once.

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

Refetch and Import share field identifiers for review decisions. Japanese text uses `work_name` and `description`. Translated text uses `localization:<locale>:title` and `localization:<locale>:description`. Refetch and existing-work import reviews show field headings in the current UI language. New-work import previews keep the headings saved during analysis.

Laravel queue infrastructure uses:
- `jobs`
- `job_batches`

### Bulk Import

`bulk_import_runs` stores the shared-input snapshot, lifecycle state, aggregate counts, timestamps, and run-level errors.

`bulk_import_items` stores ordered product codes with per-item state, warning/error text, and timestamps.

### Library Transfers

`library_transfer_runs` stores transfer direction, lifecycle/checkpoint state, archive-set identity, settings, progress, warnings, and errors.

`library_transfer_parts` stores expected/uploaded ZIP parts and their validation state.

`library_transfer_entries` stores the bounded archive inventory used for packing, validation, and image access without rescanning manifests.

`library_import_items` stores review identity, baseline/incoming values, complete human-readable previews, decisions, and apply results. Review state is persisted and paginated instead of being held as one Livewire payload. Imported image previews persist application-relative transfer image routes rather than environment-specific hosts, so the review UI resolves them against the current server when rendered.

Transfer mapping deliberately separates portable domain values from transport metadata. `LibraryData` maps library entities, `WorkArchiveData` owns the per-work document format, and `PortableOptions` limits import/export to explicitly portable settings.

## Storage and External Process Boundary

Canonical work metadata:

```text
storage/app/Works/{product_code}.json
storage/app/Works/{translation_code} - {original_code}.json
```

Canonical work images:

```text
storage/app/public/Works/{product_code}/...
```

These files are addressed by the existing `/storage/...` application URLs but are served by `PublicStorageController` from the `public` filesystem disk. The controller serves files through Laravel's `public` disk (`storage/app/public`) and returns `404` for missing, malformed, or path-traversal requests.

Staged Refetch metadata:

```text
storage/app/Refetch/{run}/Works/{product_code}.json
storage/app/Refetch/{run}/Works/{translation_code} - {original_code}.json
```

Staged Refetch images:

```text
storage/app/public/Refetch/{run}/Works/{product_code}/...
```

Refetch, Import, and final Custom Quick Add/Edit image saves share `ProductImagePromotion` for canonical image replacement. It keeps private backups plus a durable recovery journal under:

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

### Languages

- **UI support** controls the application interface.
- **DLsite metadata support** controls locale-specific **Title, Description, and fetched Tags** fetched from DLsite.

| Language | Locale | UI | DLsite metadata | Errors file | Validation file |
| --- | --- | --- | --- | --- | --- |
| Japanese | `ja` | Machine translated and LLM-reviewed | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang); project-specific translations machine translated and LLM-reviewed |
| English | `en` | Manually translated | Supported | Manually translated | Manually translated |
| Simplified Chinese | `zh_CN` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang); project-specific labels use DLsite terminology and the UI catalog |
| Traditional Chinese | `zh_TW` | Mix of manual and machine translation with LLM review | Supported | Mix of manual and machine translation with LLM review | Mix of user-translated and automatically translated messages with LLM review |
| Korean | `ko` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| German | `de` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| French | `fr` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Spanish | `es` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Italian | `it` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Portuguese | `pt_BR` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Swedish | `sv` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Indonesian | `id` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Thai | `th` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Vietnamese | `vi` | Machine translated and LLM-reviewed using DLsite terminology | Supported | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Belarusian | `be` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Dutch | `nl` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Filipino | `fil` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Polish | `pl` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Romanian | `ro` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Russian | `ru` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Turkish | `tr` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |
| Ukrainian | `uk` | Machine translated and LLM-reviewed | Not supported (English metadata fallback) | Machine translated and LLM-reviewed | Framework messages based on [Laravel-Lang](https://github.com/Laravel-Lang) where available; project-specific labels use the UI catalog |

DLsite metadata is fetched directly from DLsite and is not translated by DLSite List.

When localized metadata is unavailable for the requested locale, DLsite may return Japanese metadata as a fallback.

Application-owned UI text is stored in `lang/{locale}.json`.

Application-owned errors use stable semantic codes (for example, `transfer.archive.invalid_zip`) defined in `lang/{locale}/errors.php` and translated with Laravel's translator. Parameterized errors pass replacement values to the translator.

Laravel’s built-in validation messages use `lang/{locale}/validation.php`.

Blade uses `str_replace('_', '-', app()->getLocale())` because HTML language tags use hyphens (`zh-TW`, `pt-BR`), while some application locales use underscores (`zh_TW`, `pt_BR`). This only formats the HTML `lang` attribute; it does not change which translations Laravel loads.

UI locales with a matching `DlsiteLanguage` select that metadata language directly when `Follow UI language automatically` is enabled. UI-only locales have no `DlsiteLanguage` mapping, so the existing English metadata fallback is used instead. `SetUiLocale` applies the saved UI language to web requests. See [UI Language](CONFIGURATION.md#ui-language) for the display and fetch settings.

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

DLSite Quick Add has a browser-side fetching status. Custom Quick Add and Bulk Import do not use it; Bulk Import reports queue progress from Options instead.

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
