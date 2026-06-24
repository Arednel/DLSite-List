# Testing

## Scope
Current automated coverage is in Laravel PHPUnit tests:
- `tests/Feature/AutocompleteControllerTest.php`
  - covers database-backed tag and series suggestion endpoints, language-agnostic tag results, word-prefix and non-ASCII matching, local popularity ordering, first-word ordering, separate tag/series ordering settings, result limits, and autocomplete asset/data-attribute rendering on Index/Create/Edit
- `tests/Unit/Support/AutocompleteMatcherTest.php`
  - covers autocomplete PHP match ranking and usage-order comparison behavior
- `tests/Feature/ProductControllerTest.php`
  - covers index filtering/sorting, English/custom-visible genre search and tag filters, creator/circle/description filters, create/edit pages including default Quick Add, Custom Quick Add, and Edit field orders, hidden optional Create layout rows with locked required fields preserved, visible Create metadata/creator/description rows, hidden Create metadata preservation/ignoring, hidden Age Category in Edit, optional fetched English tag editing, separate custom/fetched tag edit toggles, Tag Library page/component mounting, index-only return navigation with visible-work anchors, visibility-filter return redirects including metadata filters and maker ID-only circle-filter cleanup, custom-sort return page calculation, a full visible-update return workflow, Laravel previous URL create back links, malformed create back-link input, create-mode back-link preservation, Create Go Back preservation after scraper validation errors, filtered delete page fallback, custom create/upload flow, editable custom tag source behavior, DLSite store with one fetched tag in both JP/EN buckets, DLSite contributor sync, automatic Series from `title_name`, index image selection, enum-backed product field validation, metadata update flow, map-driven editable update payload behavior, hidden/read-only Edit field preservation for metadata and listening fields, and logged destroy cleanup failures
- `tests/Feature/TagLibraryManagerTest.php`
  - covers the Livewire Tag Library manager, including English/custom-visible and zero-pivot tag listing, JP-only attached tag exclusion, collapsed default state, saved expanded default state, search-open behavior, empty tag creation with title-key and order normalization, duplicate handling, modal-confirmed empty tag deletion, protection when a tag gains a pivot before deletion is confirmed, tag group create/rename/delete behavior, Add group placement inside the Tag Groups section, persisted Index group-ordering switch behavior, pivot-backed multi-group tag membership, duplicate membership prevention, remove-one-membership behavior, group/tag ordering actions, independent group/tag Index hidden settings, session-only tag edit mode, shared switch-style Edit Tags, Hide Tag on Index, and Hide Group on Index toggle markup, tag settings modal hydration, dropdown-style group search, searchable group assignment plaques, staged group plaque save/cancel behavior, compact in-chip hidden-tag status indicators for directly hidden tags and tags assigned to any hidden group, circular delete controls, All Tags filters for visibility, group status, specific group, and empty/used state, and group-title tie-break ordering in the grouped All Tags list
- `tests/Feature/ReturnTargetProductTest.php`
  - covers product-aware return URLs for unlimited pagination, first-page omission, saved-page redirect fast paths, full-query visibility fast paths, unchanged-visibility fallback cleanup, and multi-filter visible-work cleanup
- `tests/Feature/ProductIndexLivewireTest.php`
  - covers Livewire-owned Index pagination defaults, fixed/custom/unlimited page sizes, batched Index option setting lookup count, narrowed Index result columns including non-hydrated sort-only fields and visible-field hydration, configurable field order/visibility including locked Title, hideable Image, optional hidden-by-default notes/listening columns, prepared tag-link query preservation/replacement, default plain tag-chip ordering, optional grouped tag-chip ordering, group-title tie-breaks, normalized tag/group order fallback, multi-group tag de-duplication through visible groups, direct tag hiding, any-hidden-group tag hiding including mixed visible/hidden group memberships, Index table width CSS, SQL-backed scalar/search/date/Added to the site Date pagination, nullable scalar sort ordering, built-in pagination links with the progress-menu scroll target, RJ/header sorting including optional listening and contributor columns, advanced primary/secondary sorting, configurable Advanced Filter sort dropdown visibility that does not disable valid URL/header sorting, Livewire-bound Filter modal controls, default Filter modal order/visibility, configurable Filter modal visibility/order for fixed and date-range widgets, restored filter defaults, the external Alpine advanced-filter component, local client-side filter modal opening/closing without Livewire entanglement or native form reset, page reset behavior, and query-string initialization
- `tests/Unit/Enums/ProductIndexSortFieldTest.php`
  - covers Index sort field SQL column metadata, default hidden sort values, and Advanced Filter sort dropdown layout normalization
- `tests/Unit/Enums/ProductContributorRoleTest.php`
  - covers contributor role to product field mapping used by configurable Create/Edit layouts
- `tests/Feature/ProductSortKeysTest.php`
  - covers derived product index keys for numeric RJ sorting, partial start/finish date sorting, and exact series filtering behavior
- `tests/Feature/IndexPaginationSettingsTest.php`
  - covers the Options page-size setting component behavior: default hydration, fixed/custom/unlimited persistence, deferred save behavior, validation, saved-notice clearing, modal-confirmed reset-to-default behavior, reset cancellation, global settings refresh, and supported view option data
- `tests/Feature/AutocompleteSettingsTest.php`
  - covers the Options autocomplete ordering setting component, including default usage ordering, separate tag and series persistence, modal-confirmed reset-to-default behavior, invalid enum values, and Livewire dirty-state saved notice behavior
- `tests/Feature/TagLibraryDisplaySettingsTest.php`
  - covers the Options Tag Library collapsed/expanded display default and Index group-ordering settings, including hydration, persistence, modal-confirmed reset-to-default behavior, global reset refresh, and stored option state
- `tests/Feature/ProductMetadataSettingsTest.php`
  - covers metadata-related Options components, including automatic Series persistence/reset/refresh behavior, Index table width fixed/custom/unlimited validation and reset behavior, Product field layout visibility/editability/order persistence across Index/Edit/Filter/Quick Add/Custom Quick Add surfaces, field-row movement with field-keyed state preservation, invalid layout movement handling, reset-to-default confirmation behavior, Reset All Options behavior, General and Field Layouts tab component mounting, and light rendered smoke coverage for core user-visible controls
- `tests/Feature/ProductGenreMigrationTest.php`
  - covers migration of legacy product genre JSON into `genres` + `genre_product`, language row backfill into `genre_product_languages`, removal of old `genres.type` / `genres.language`, same product/tag attachments with both JP and EN language rows, legacy migration compatibility when `genres.title_key` exists, and index visibility migration backfill from legacy `genres.group_id` into `genre_group_genre`
- `tests/Feature/ProductMetadataMigrationTest.php`
  - covers metadata backfill from stored DLSite JSON, duplicate English description collapse, missing/invalid JSON skip behavior, and the rule that Series is not backfilled
- `tests/Feature/OptionsControllerTest.php`
  - covers the General/Field Layouts/Refetch page tabs, invalid tab fallback, latest-refetch link, Refetch Tags request validation, queue batch creation, selected/all work scopes including numeric RJ-desc queued order, progress JSON including cancellation metadata, cancel route behavior, tags-only job results, cancelled-before-fetch skips, during-fetch cancellation, relationship-backed tag diff ordering, case-insensitive/kana-sensitive tag identity, skipped errors/custom-only works, review rendering/change indicators, newest-run-only apply controls, partial cancelled run apply, new-tag add/ignore behavior, stale-language move/remove behavior, JP-only to JP+EN and EN-only transitions, and custom-to-fetched promote/keep choices
- `tests/Feature/OptionsRefetchProgressTest.php`
  - covers the Livewire refetch progress panel polling while a run is running/cancelling, showing the cancel action only while running, and redirecting once review results are ready
- `tests/Feature/OptionsWorkSearchTest.php`
  - covers the Livewire selected-work search, numeric RJ-desc visible order, and selected product preservation when filtered results change
- `tests/Feature/PerformanceSmokeTest.php`
  - defaults to 500 works, 500 tags, 10000 tag pivot rows, and contributor rows for every Index contributor role, then reports average response times for default/full-column paginated Index paths, filtered/search/tag Index paths, default/full-column unlimited Index paths, Options tabs, common/recalculated/filter-cleanup update redirects, and delete page clamp redirects
  - performance smoke timings emit PHPUnit warning issues above 500ms and stronger warning text above 1000ms; use `--do-not-fail-on-phpunit-warning` when you want the command to exit successfully while still showing those warnings
- `tests/Unit/Support/ProductIndexFiltersTest.php`
  - covers query normalization, metadata text and date range filter round trips, defaults, configurable sort option maps, explicit input keys, visibility filter group coverage, and query export helpers
- `tests/Unit/Support/ProductFieldLayoutTest.php`
  - covers enum-owned surface field order/availability metadata, surface-specific field layout normalization, default visibility/order including Filter modal defaults, locked Index/Edit/Quick Add/Custom Quick Add required rows, hidden-by-default optional Quick Add metadata/creator/description rows, Edit Age Category hidden by default, invalid field ids, duplicate field ids, editable flag behavior, and prepared Index/Edit/Filter/Create field metadata used by Blade components
- `tests/Unit/Support/DLSite/DLSiteWorkDataTest.php`
  - covers shared DLSite metadata extraction for descriptions, creator roles, maker/circle values, duplicate English fallback behavior, fallback product ids, and missing product id errors
- `tests/Unit/Models/OptionMetadataSettingsTest.php`
  - covers field layout option persistence/fallbacks for Index/Edit/Filter/Create layouts, Index sort dropdown layout option persistence/fallbacks, automatic Series option normalization, Index table width normalization, and batched ProductIndex settings normalization/fallbacks
- `tests/Unit/Support/DLSite/DLSitePythonRunnerTest.php`
  - covers the Laravel Process command arrays used for scraper and tag-fetcher Python calls, including the project venv executable and disabled timeout
- `tests/Unit/Support/GenreSyncPayloadTest.php`
  - covers shared `genre_product.source` sync payload creation, deduplication, fetched-over-custom precedence, and fetched language map creation
- `tests/Unit/Support/ProductGenreSyncTest.php`
  - covers syncing one product/tag attachment with multiple fetched language rows, preserving fetched language rows when custom tags are updated, replacing editable English fetched rows, and fetched-over-custom precedence
- `tests/Unit/Support/ProductContributorSyncTest.php`
  - covers case-folded contributor identity, circle maker id persistence, role-specific contributor replacement, and same-contributor/different-role pivot isolation
- `tests/Unit/Models/GenreTest.php`
  - covers title-key identity, including case-insensitive tag reuse, preserved display casing, and distinct Hiragana/Katakana variants
- `tests/Unit/Support/VisibleGenreAttachmentTest.php`
  - covers the shared English/custom-visible genre attachment query helper for custom, fetched EN, JP-only hidden, and same-title JP+EN cases
- `tests/Unit/Support/TagRefetch/DLSiteTagFetcherTest.php`
  - covers Process-faked tag fetch output parsing, failed-process error messages, and invalid JSON handling
- `tests/Unit/Support/ReturnTargetTest.php`
  - covers index-only return query/fragment normalization, malformed input fallback, ignored legacy return routes, and URL generation
- `tests/Unit/View/Components/Fields/EnumSelectFieldTest.php`
  - covers enum-backed field component defaults and option maps
- `tests/Unit/Models/TagRefetchStateTest.php`
  - covers refetch run/result state helper methods used by Blade and controller code, including active/cancelling/cancelled run state, run summaries, and result change-bucket helpers

There are no project-owned Python tests.

## Test Environment Setup
### Local test setup
1. Create a dedicated testing env file:
   - copy `.env.testing.example` to `.env.testing`
2. Keep test settings separate from `docker/.env.docker`:
   - PHPUnit uses `.env.testing`, not the Docker Compose env file
3. Configure test DB credentials in `.env.testing`:
   - `DB_CONNECTION`
   - `DB_HOST`
   - `DB_PORT`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
4. Set application key in:
   - `APP_KEY`

Feature tests use `RefreshDatabase`, so the configured test database is migrated/reset for each test run.

Upload tests use Laravel's `UploadedFile::fake()` and `Storage::fake('public')` helpers, so custom cover/sample image tests do not write to the real public storage disk.

Refetch Tags tests use Laravel's `Bus::fake()` for batch dispatch assertions and fake `DLSiteTagFetcher` classes so no DLSite network calls run during tests.
Python process tests use Laravel's `Process::fake()` and `Process::preventStrayProcesses()` so scraper/tag-fetcher commands can be asserted without running Python.

Livewire component tests use `Livewire::test()` to update component state without a browser.
Index pagination tests set `options.index_per_page` through `App\Models\Option` so fixed, custom, and unlimited list sizes can be verified without touching application config.
Autocomplete settings tests set `options.tag_autocomplete_order` and `options.series_autocomplete_order` through `App\Models\Option` so tag and series suggestion ranking can be verified independently.
Product metadata settings tests set the field layouts, automatic Series, and Index table width options through `App\Models\Option` so UI behavior can be verified without changing environment config. Field layout tests update Livewire component state and movement actions directly, then assert persisted layout order and checkbox/editability state remains attached to field ids after row movement.

### Docker test setup
Docker tests use:
- `docker/.env.testing.docker` for Laravel's testing environment variables
- `database_test` as the MySQL host inside the Docker network
- `dbdata_test` as the separate Docker test database volume

The Docker test service is one-off and does not run during the normal app startup command unless it is requested directly.

## Running Tests
- Run all tests:
  - `php artisan test`
- Run all tests inside Docker:
  - `docker compose --env-file docker/.env.docker --profile test run --rm --build tests`
- Run a filtered subset:
  - `php artisan test --filter=ProductControllerTest`
  - `php artisan test --filter=ProductIndexLivewireTest`
  - `php artisan test --filter=ProductMetadataMigrationTest`
  - `php artisan test --filter=ProductMetadataSettingsTest`
  - `php artisan test --filter=IndexPaginationSettingsTest`
  - `php artisan test --filter=AutocompleteSettingsTest`
  - `php artisan test --filter=OptionMetadataSettingsTest`
  - `php artisan test --filter=OptionsControllerTest`
  - `php artisan test --filter=OptionsRefetchProgressTest`
  - `php artisan test --filter=OptionsWorkSearchTest`
  - `php artisan test --filter=ProductFieldLayoutTest`
  - `php artisan test --filter=ProductContributorSyncTest`
  - `php artisan test --filter=DLSiteWorkDataTest`
  - `php artisan test --filter=DLSite`
  - `php artisan test --filter=GenreTest`
  - `php artisan test --filter=GenreSyncPayloadTest`
  - `php artisan test --filter=ProductGenreSyncTest`
  - `php artisan test --filter=VisibleGenreAttachmentTest`
  - `php artisan test --filter=PerformanceSmokeTest`
  - `php artisan test tests\Feature\PerformanceSmokeTest.php --do-not-fail-on-phpunit-warning`
- Run a filtered subset inside Docker:
  - `docker compose --env-file docker/.env.docker --profile test run --rm --build tests php artisan test --filter=ProductControllerTest`
