# Testing

## Scope
Current automated coverage includes Laravel PHPUnit and Python `unittest` tests:
- Language settings and requests cover English defaults, invalid-value fallback, persistence, Save/Reset redirects, destination-locale notices, and Laravel's active request locale.
- Localization behavior covers catalog integrity, representative visible and accessible UI copy, dynamic document language, locale-aware months and pluralization, stable backed values, and current-language fetched tags across Index, Edit, and Tag Library.
- Error behavior covers all recognized Refetch and Quick Add errors, raw persisted/logged values, and verbatim pass-through for unknown external errors.
- `tests/Feature/AutocompleteControllerTest.php`
  - covers database-backed tag and series suggestion endpoints, optional tag background/font color payloads and group-over-tag color precedence, language-agnostic tag results, word-prefix and non-ASCII matching, local popularity ordering, first-word ordering, separate tag/series ordering settings, result limits, and autocomplete asset/data-attribute rendering on Index/Create/Edit
- `tests/Unit/Support/AutocompleteMatcherTest.php`
  - covers autocomplete PHP match ranking and usage-order comparison behavior
- `tests/Feature/ProductControllerTest.php`
  - Index queries and display: filtering/sorting, current-language fetched/custom genre search and tag filters, creator/circle/Japanese-description/English-description filters, and index image selection
  - Form rendering and layouts: default Quick Add, Custom Quick Add, Edit field orders, selected Cherry/Black form theme classes, controller-provided form theme classes, hidden optional Create layout rows with locked required fields preserved, visible Create metadata/creator/split-description rows, and hidden Age Category in Edit
  - Hidden-field rules: hidden Create metadata preservation/ignoring, DLSite hidden scraped description-language preservation, Custom Create hidden description-language nulling, and hidden/read-only Edit field preservation for split descriptions, tags, metadata, and listening fields
  - Tag behavior: editable and readonly current-locale fetched-tag rows, fetched-bucket validation and updates, preservation of other fetched/custom buckets, optional Edit readonly tag colors, separately ordered custom/fetched rows, editable custom tag source behavior, and Tag Library page/component mounting
  - Index return navigation: visible-work anchors, visibility-filter redirects including metadata filters, maker ID-only circle-filter cleanup, visible/hidden-description general search return policy, custom-sort return page calculation, a full visible-update return workflow, and filtered delete page fallback including hidden-description search override page clamping
  - Create navigation and completion: Laravel previous URL create back links, malformed create back-link input, create-mode back-link preservation including `modal=1`, Create Go Back preservation after scraper validation errors, custom create/upload flow, modal create/update/delete completion responses with calculated redirect URLs, and unchanged redirects for standalone requests
  - Product persistence and updates: shared five-attempt DLSite fetches, dismissible fixed Index and modal-completion image-failure warnings, DLSite storage with one fetched tag in both JP/EN buckets, contributor sync, automatic Series from `title_name`, enum-backed product field validation, metadata update flow, map-driven editable update payload behavior, duplicate English description cleanup, and logged destroy cleanup failures
  - Quick Add client behavior: DLSite-only fetching status markup and asset, Custom Quick Add exclusion, green Cherry/Black theme values, submit-event reveal behavior, browser-history reset behavior, and exact three-message recognized-scraper translation versus verbatim unknown errors
- `tests/Feature/TagLibraryManagerTest.php`
  - Tag listing and locale behavior: current-language fetched/custom and zero-pivot tag listing, other-language exclusion, and locale-aware group members/counts/links/usage
  - Display state: collapsed default state, saved expanded default state, and search-open behavior
  - Empty-tag lifecycle: creation with title-key and order normalization, duplicate handling, modal-confirmed deletion, and protection when a tag gains a pivot before deletion is confirmed
  - Tag groups and membership: group create/rename/delete behavior, Add group placement inside the Tag Groups section, persisted Index group-ordering switch behavior, pivot-backed multi-group membership, duplicate membership prevention, remove-one-membership behavior, and group/tag ordering actions
  - Index visibility and colors: independent group/tag hidden settings, background and font color save/clear/validation, `#000000` placeholder coverage, independent background/font rendering, group-over-tag precedence from separately ordered values, and Blade color-logic cleanup
  - Editing controls: session-only tag edit mode, shared switch-style Edit Tags, Hide Tag on Index, and Hide Group on Index toggle markup, tag settings modal hydration, and circular delete controls
  - Group assignment UI: dropdown-style group search, searchable group assignment plaques, and staged group plaque save/cancel behavior
  - Status and filtering: compact in-chip hidden-tag indicators for directly hidden tags and tags assigned to any hidden group, All Tags filters for visibility, group status, specific group, and empty/used state, and group-title tie-break ordering in the grouped All Tags list
- `tests/Feature/ReturnTargetProductTest.php`
  - covers product-aware return URLs for unlimited pagination, first-page omission, saved-page redirect fast paths, full-query visibility fast paths, unchanged-visibility fallback cleanup, multi-filter visible-work cleanup, and retaining only current-language fetched-tag filters
- `tests/Feature/ProductIndexLivewireTest.php`
  - Pagination and state: Livewire-owned pagination defaults, fixed/custom/unlimited page sizes, SQL-backed scalar/search/date/Added to the site Date pagination, built-in pagination links with the progress-menu scroll target, page reset behavior, and query-string initialization
  - Settings and hydration: one batched Index option lookup including image-viewer, optional-status, modal, and DLSite-link settings, narrowed result columns including non-hydrated sort-only fields and visible-field hydration, conditional hidden-age hydration without revealing the Age column, and Index table width CSS
  - DLSite, viewer, and modal links: default Maniax image links, enabled local image-viewer triggers with unchanged title links, enabled All Ages Home links, one reused external URL per product row, no trigger while the Image column is hidden, modal host metadata, real standalone Quick Add/Edit `href` values, and one-shot same-Index modal Quick Add positioning after reload
  - Fields and search: language-aware description matching in general search, responsive desktop/mobile Search placement around the progress controls, independent Japanese/English Description columns, configurable field order/visibility including locked Title and hideable Image, optional hidden-by-default notes/listening columns, and tag general-search behavior when the Tags column is enabled or hidden
  - Tags: separate Custom Tags/current-language Fetched Tags rendering inside one Index Tags column, locale-aware search/filter/link behavior, generic runtime bucket controls, prepared tag-link query preservation/replacement, default plain and optional grouped tag-chip ordering, optional tag-background/font colors with group-over-tag precedence, uncolored tag plain-link rendering, hidden-group anti-join skipping when no hidden groups exist, group-title tie-breaks, normalized tag/group order fallback, multi-group tag de-duplication through visible groups, direct tag hiding, and any-hidden-group tag hiding including mixed visible/hidden group memberships
  - Sorting and filter UI: nullable scalar sort ordering, RJ/header sorting including optional listening and contributor columns, advanced primary/secondary sorting, configurable Advanced Filter sort dropdown visibility that does not disable valid URL/header sorting, Livewire-bound Filter modal controls, default and configurable Filter modal order/visibility for fixed and date-range widgets, restored filter defaults, the external Alpine advanced-filter component, and local client-side modal opening/closing without Livewire entanglement or native form reset
- `tests/Unit/Support/ProductIndexRowBuilderTest.php`
  - covers typed product/contributor presentation rows, cast-aware narrow attribute hydration, missing optional fields, rejection of a missing required title, age-appropriate DLSite URLs, preserved Edit return queries, and RFC 3986-encoded Series/Circle/contributor filter URLs
- `tests/Unit/Enums/ProductIndexSortFieldTest.php`
  - covers Index sort field SQL column metadata, default hidden sort values, and Advanced Filter sort dropdown layout normalization
- `tests/Unit/Enums/ProductContributorRoleTest.php`
  - covers contributor role to product field mapping used by configurable Create/Edit layouts
- `tests/Feature/ProductSortKeysTest.php`
  - covers derived product index keys for numeric RJ sorting, partial start/finish date sorting, and exact series filtering behavior
- `tests/Feature/IndexPaginationSettingsTest.php`
  - covers the Options page-size setting component behavior: default hydration, fixed/custom/unlimited persistence, deferred save behavior, validation, saved-notice clearing, modal-confirmed reset-to-default behavior, reset cancellation, global settings refresh, and supported view option data
- `tests/Feature/IndexSearchSettingsTest.php`
  - covers the Options hidden-description search setting, including hydration, persistence, modal-confirmed reset-to-default behavior, and global reset refresh
- `tests/Feature/IndexImageViewerTest.php`
  - covers the default-off General setting, persistence, individual/global reset, disabled remote image links, Livewire JSON action success/error returns, unchanged title links, cover-first ordering, retained missing paths, conditional dialog/script rendering, hidden Image-column behavior, and final-boundary migration cleanup
- `tests/Feature/OptionalProductStatusesTest.php`
  - covers every On Hold/Dropped switch combination across DLSite Quick Add, Custom Quick Add, Edit, Index navigation, and Advanced Filter; Edit's current-status exception; unconditional localized labels; and yellow/red row bars
- `tests/Feature/AutocompleteSettingsTest.php`
  - covers the Options autocomplete ordering setting component, including default usage ordering, separate tag and series persistence, modal-confirmed reset-to-default behavior, invalid enum values, and Livewire dirty-state saved notice behavior
- `tests/Feature/TagLibraryDisplaySettingsTest.php`
  - covers the Options Tag Library collapsed/expanded display default, Index group-ordering setting, and tag color surface toggles, including hydration, persistence, modal-confirmed reset-to-default behavior, global reset refresh, and stored option state
- `tests/Feature/ProductMetadataSettingsTest.php`
  - covers metadata-related Options components, including persistence, validation, individual/global reset behavior, independent optional-status switches, tooltips, product form settings, Index table width, separate and save-all field layout persistence, preservation of unsaved drafts in other layout blocks, field layout visibility/editability/order across each surface, generic fetched-bucket controls, current-locale labels, label-free layout storage, and originating-tab reload behavior
- `tests/Feature/ProductGenreMigrationTest.php`
  - covers migration of legacy product genre JSON into `genres` + `genre_product`, language row backfill into `genre_product_languages`, removal of old `genres.type` / `genres.language`, same product/tag attachments with both JP and EN language rows, legacy migration compatibility when `genres.title_key` exists, and index visibility migration backfill from legacy `genres.group_id` into `genre_group_genre`
- `tests/Feature/ProductMetadataMigrationTest.php`
  - covers metadata backfill from stored DLSite JSON, duplicate English description collapse, missing/invalid JSON skip behavior, and the rule that Series is not backfilled
- `tests/Feature/OptionsGeneralTest.php`
  - covers General/Field Layouts/Refetch tab rendering, invalid-tab fallback, shared modal configuration, empty Refetch state, distinct all/selected Refetch cards, and latest-run-only linking
- `tests/Feature/FullRefetchTest.php`
  - covers all/selected batching through result rows, including empty-start validation and custom-created RJ works, the run-wide Refetch Images choice and help, reversible lean replacement schema, deterministic staging paths, full staged metadata fetches, thirteen ordered review tabs, shared review-select styling and tooltip assets, Livewire category validation and apply actions, newest-run-only behavior, incremental apply, checked retryable file promotion, JSON promotion timing, full metadata/contributor/tag overwrite with user-owned fields preserved, detailed tag actions, independent cover/sample promotion, optional tag colors, pre-apply rejection, and partial-apply finish behavior
- `tests/Feature/DLSiteImageFailureSettingsTest.php`
  - confirms the removed image-failure setting and choices are absent from the General tab
- `tests/Feature/OptionsRefetchProgressTest.php`
  - covers the Livewire refetch progress panel polling while a run is running/cancelling, its status and single fetched/failed/total summary, showing the cancel action only while running, and redirecting once review results are ready
- `tests/Feature/OptionsRefetchReviewTest.php`
  - covers initial and changed Livewire tabs, choice preservation across navigation and Apply Tab, validation, eligible-only Set Overwrite for All presets, preserved per-change overrides, read-only runs, built-in confirmations, accessible tab markup, and the no-inline-PHP/no-Alpine review and prepared-value views
- `tests/Feature/OptionsWorkSearchTest.php`
  - covers the Livewire selected-work search, numeric RJ-desc visible order, and selected product preservation when filtered results change
- `tests/Feature/AuthenticationTest.php`
  - covers default-off access without recovery-state queries, setup and single-account creation, exact-case usernames, protected routes/mutations/Livewire updates, public help, generic login failures, five-attempt per-IP throttling and expiry, intended redirects, logout, both authentication themes, and the 180-day remember cookie
- `tests/Feature/AuthenticationSettingsTest.php`
  - covers the separate non-resettable Authentication tab, guest-hidden authenticated controls, enable/setup and enable/login redirects, theme persistence, global-reset exclusion, confirmed password replacement, remember-token rotation, and logout after change
- `tests/Feature/AdminRecoveryTest.php`
  - covers the environment flag being ignored while authentication is off, forced one-time recovery while enabled, atomic rollback when recovery-marker persistence fails, consumed-state blocking, flag removal/restart state clearing, and unsupported multiple-user recovery
- `tests/Feature/AdminCommandTest.php`
  - covers masked console password reset, zero/multiple-user refusal, full user-table reset confirmation, and reset cancellation
- `tests/Performance/PerformanceSmokeTest.php`
  - defaults to 500 works, 500 tags, 10000 tag pivot rows, and contributor rows for every Index contributor role, then reports average response times for default/full-column paginated and unlimited Index paths without configured colors, the same four Index paths with unique tag background/font colors, filtered/search/tag Index paths, Options tabs, common/recalculated/filter-cleanup update redirects, and delete page clamp redirects
  - performance smoke timings emit PHPUnit warning issues above 500ms and stronger warning text above 1000ms; use `--do-not-fail-on-phpunit-warning` when you want the command to exit successfully while still showing those warnings
- `tests/Unit/Support/ProductIndexFiltersTest.php`
  - covers query normalization, metadata text and date range filter round trips, defaults, configurable sort option maps, explicit input keys, visibility filter group coverage, and query export helpers
- `tests/Unit/Support/ProductFieldLayoutTest.php`
  - covers surface field availability/defaults, current-locale fetched labels, invalid/duplicate row normalization, generic fetched keys, separate Edit Custom/Fetched rows, locked required rows, editability, and prepared layout metadata
- `tests/Unit/Support/DLSite/DLSiteWorkDataTest.php`
  - covers shared DLSite metadata extraction for descriptions, creator roles, maker/circle values, duplicate English fallback behavior, fallback product ids, and missing product id errors
- `tests/Unit/Models/OptionMetadataSettingsTest.php`
  - covers global UI language default/normalization/persistence/reset, optional-status defaults/persistence/reset/batched hydration, field layout option persistence/fallbacks for Index/Edit/Filter/Create layouts, Index sort dropdown layout option persistence/fallbacks, automatic Series option normalization, age-appropriate DLSite link defaults/persistence/individual and global reset/batched hydration, product form theme normalization/Black default reset, work-form modal defaults/persistence/invalid-action fallback/reset, Index table width normalization, and batched ProductIndex settings normalization/fallbacks
- `tests/Unit/Models/ProductDLSiteUrlTest.php`
  - covers the no-age-access disabled Maniax path plus enabled Home mapping for exact All Ages and Maniax fallback for R15, R18, null, and malformed legacy ages
- `tests/Unit/Support/DLSite/DLSitePythonRunnerTest.php`
  - covers the Laravel Process command arrays for explicit work id, JSON/log destinations, optional image destination, project venv executable, disabled timeout, and normalized log-retention subprocess environment
- `tests/Unit/Support/DLSite/DLSiteWorkFetcherTest.php`
  - covers PHP-owned five-attempt retries, latest-manifest partial results, immediate success, strict manifest/JSON failures, and rejection of stale JSON fallback
- `tests/Unit/Support/RefetchDiffBuilderTest.php`
  - covers every metadata/creator/tag category, content-hashed Cover changes, and independently unavailable Sample Images after any sample failure
- `tests/Unit/Logging/WeeklyRotatingFileHandlerTest.php`
  - covers UTC weekly filenames and boundaries, same-week appends, Monday file switching, first-write cleanup after same-week expiry, complete-week retention, selective cleanup, concurrent archive removal, invalid retention fallback, and non-blocking cleanup failures
- `tests/Unit/Logging/LoggingConfigurationTest.php`
  - covers the Laravel stack/custom Monolog channel configuration, locked writes, and a real configured channel write
- `tests/Unit/Support/GenreSyncPayloadTest.php`
  - covers shared `genre_product.source` sync payload creation, deduplication, fetched-over-custom precedence, and fetched language map creation
- `tests/Unit/Support/ProductGenreSyncTest.php`
  - covers syncing one product/tag attachment with multiple fetched language rows, replacing only the selected English or Japanese bucket, preserving other fetched languages and unsubmitted custom tags, and fetched-over-custom precedence across languages
- `tests/Unit/Support/ProductContributorSyncTest.php`
  - covers case-folded contributor identity, circle maker id persistence, role-specific contributor replacement, and same-contributor/different-role pivot isolation
- `tests/Unit/Models/GenreTest.php`
  - covers title-key identity, including case-insensitive tag reuse, preserved display casing, and distinct Hiragana/Katakana variants
- `tests/Unit/Support/VisibleGenreAttachmentTest.php`
  - covers current-locale defaults, explicit language overrides, always-visible custom attachments, fetched-source gating, and one-row visibility for shared JP+EN attachments
- `tests/Unit/Support/ReturnTargetTest.php`
  - covers index-only return query/fragment normalization, malformed input fallback, ignored legacy return routes, and URL generation
- `tests/Unit/View/Components/Fields/EnumSelectFieldTest.php`
  - covers enum-backed field component defaults and option maps
- `tests/Unit/Models/RefetchStateTest.php`
  - covers generic refetch run/result state and category helpers
- `tests/Unit/Support/DLSite/DLSiteScraperContractTest.php`
  - checks the explicit destination arguments, structured image manifest, and absence of a Python retry loop

- `python/tests/test_weekly_logging.py`
  - covers Python's matching UTC week calculation, weekly append/switch behavior, first-write cleanup after same-week expiry, complete-week retention, selective cleanup, concurrent archive removal, invalid retention fallback, the production handler interface, and non-blocking cleanup failures

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

Full Refetch tests use Laravel's `Bus::fake()`, `Process::fake()`, and fake storage disks so no DLSite network calls or canonical storage writes occur during tests.
Python process tests use Laravel's `Process::fake()` and `Process::preventStrayProcesses()` so scraper commands can be asserted without running Python.

Livewire component tests use `Livewire::test()` to update component state without a browser.
Index pagination tests set `options.index_per_page` through `App\Models\Option` so fixed, custom, and unlimited list sizes can be verified without touching application config.
Autocomplete settings tests set `options.tag_autocomplete_order` and `options.series_autocomplete_order` through `App\Models\Option` so tag and series suggestion ranking can be verified independently. Autocomplete controller tests cover optional tag background/font color payloads through `options.tag_color_surfaces` and assert suggestions do not render a separate color marker.
Product metadata settings tests set the field layouts, automatic Series, and Index table width options through `App\Models\Option` so UI behavior can be verified without changing environment config. Field layout tests update Livewire component state and movement actions directly, then assert persisted layout order and checkbox/editability state remains attached to field ids after row movement.
Work-form modal tests store both modal options through `App\Models\Option`, render all supported host pages, and assert option normalization, standalone link URLs, modal metadata, Livewire save/reset events, modal completion responses, and the same-Index pending-redirect asset contract without requiring a browser. The shared completion response assertion also covers its dedicated stylesheet, semantic fallback card, and `_top` Continue link.
Age-appropriate DLSite link tests store `options.dlsite_age_appropriate_links_enabled` through `App\Models\Option`; query-log assertions verify hidden `age_category` remains unselected while disabled and is hydrated only when enabled.
Image-viewer tests store `options.index_image_viewer_enabled` through `App\Models\Option`; Livewire action assertions verify ordered browser URLs without requiring files to exist, conditional response assertions cover dialog/script inclusion, and migration tests own unsafe value cleanup.
Optional-status tests store the two switches in `options.optional_product_statuses` and verify that they affect only rendered form, filter, and Index controls.

## Manual Authentication Checks

- With no user rows, confirm the application remains public by default. Enable `Options -> Authentication` and confirm the next page is administrator setup.
- Create the administrator, log out, and confirm Index, Options, Tag Library, create/edit actions, autocomplete, Refetch, and Livewire interactions redirect to login.
- Create a mixed-case username, confirm a differently cased login receives the generic credentials error and counts as a failed attempt, then confirm the exact username casing succeeds.
- Fail login five times from one IP, confirm the next attempt is blocked, then confirm access returns after five minutes. Check another IP remains independent.
- Sign in once without Remember me and once with it; confirm the remembered login survives a normal browser restart and is documented as 180 days.
- Check login, setup, forgot-password help, and environment recovery in both Cherry and Black themes and both UI languages.
- Change the password from the Authentication tab, accept the browser confirmation, and confirm the browser is logged out and the new password works.
- Run `php artisan admin:reset-password`, then `php artisan admin:reset`; confirm the first changes credentials and the second clears all user rows and returns enabled authentication to setup.
- In a trusted local environment only, enable `ADMIN_PASSWORD_RESET=true`, restart, complete one reset, and confirm all pages remain blocked by the removal message. Remove the variable, restart/recreate the app process, and confirm normal login resumes.

## Manual UI Language and Locale-Aware Tag Checks
- Save and reset `English` / `日本語` in Options. Confirm the full reload, destination-locale notice, global scope, and originating tab for Reset All.
- Check representative Index, Create/Edit, Options, Tag Library, Refetch, and completion surfaces in both languages. Confirm dynamic `<html lang>`, localized months, accessible copy, desktop menu border/hover, and the mobile drawer.
- Confirm recognized Refetch and Quick Add errors localize while persisted/logged and unknown external errors remain unchanged.
- With EN-only, JP-only, shared, empty, and custom tags, verify Index display/search/filter/links, Edit, Tag Library counts/filters, one-row shared tags, and language-independent autocomplete.

## Manual DLSite Link Checks
- With the option disabled and the Age column hidden, confirm All Ages, R15, and R18 image/title links all open Maniax.
- Enable General -> DLSite Links, keep the Age column hidden, and confirm All Ages image/title links open DLSite Home while R15 and R18 links open Maniax.
- Confirm the Image, Japanese title, and optional English title links for the same work share one destination and still open in a new tab.

## Manual Image Viewer Checks
- With General -> Image Viewer disabled, confirm clicking an Index thumbnail opens DLSite and title links keep their configured DLSite destinations.
- Enable the viewer and confirm clicking a thumbnail opens the saved cover first, shows only the image counter, and leaves both title links unchanged.
- Confirm Previous and Next wrap between the cover and numerically ordered samples, keyboard navigation and close controls work, opening another work resets the viewer to its cover, and `View in full` opens the current loaded image in a new tab.
- Confirm a retained path for a missing download shows `No image` without removing its counter position, hides `View in full`, and leaves later valid images reachable.
- Hide the Image Index column and confirm no viewer trigger renders.
- With optional authentication enabled, confirm a guest Livewire image action redirects to login. Remember that a known `/storage` URL is still a public static asset.

## Manual Optional Status Checks

- With both switches disabled, confirm Quick Add, Custom Quick Add, Index status buttons, and Advanced Filter show only Listening, Completed, and Plan to Listen.
- Enable each switch separately and then together. Confirm the Index buttons stay between Completed and Plan to Listen and each form/filter exposes only enabled optional values.
- Disable both after saving one On Hold work and one Dropped work. Confirm All ASMR still shows their localized labels with yellow/red bars; Add, Advanced Filter, and the Index status tabs hide both choices; and Edit offers only the work's own current optional status.

## Manual Quick Add Fetch Status Checks
- In standalone and modal DLSite Quick Add, confirm top Submit, bottom Submit, and Enter reveal the localized green fetching-status text beneath the RJ field.
- Confirm an empty required RJ field does not reveal the message and both Submit buttons remain enabled while the message is visible.
- Confirm Laravel validation and scraper errors reload Quick Add with the green message hidden and the existing red error visible.
- Confirm Custom Quick Add never renders the fetching message and browser Back restores DLSite Quick Add with the message hidden.
- Confirm the message remains readable in both Cherry and Black form themes.

## Manual Work Form Modal Checks
- With the option disabled, confirm Quick Add and Index Edit continue to navigate as standalone pages.
- With it enabled, confirm ordinary left-click opens the native dialog on Index, Options, Tag Library, and Refetch for Quick Add, and on Index for Edit Work.
- Confirm middle-click, right-click, and Ctrl/Cmd/Shift/Alt-click keep native link behavior and do not open the modal.
- Confirm the header Close button, Escape, backdrop click, and the form's Go Back/Close control dismiss the dialog, clear the iframe, and return focus without treating the action as a successful mutation.
- Confirm DLSite/Custom Create switching, validation errors, autocomplete, responsive sizing, Edit's Delete confirmation, and Create/Edit styling work inside the iframe without inheriting or covering host-page UI.
- Confirm successful create, update, and delete apply each completion choice: Laravel redirect, host-page refresh, or close-only with potentially stale host data.
- From the plain Index with Follow redirect selected, complete modal Quick Add and confirm the refreshed URL keeps `#RJ...` and the viewport moves to the newly rendered work row.
- Confirm modal Quick Add from filtered/paginated Index URLs and non-Index hosts still follows the calculated destination, and modal Edit/Delete plus Refresh/Close behavior remains unchanged.
- If parent messaging is intentionally blocked during inspection, confirm the completion fallback shows a centered responsive Cherry/Options card and its Continue button navigates the full browser page.

### Docker test setup
Docker tests use:
- `docker/.env.testing.docker` for Laravel's testing environment variables
- `database_test` as the MySQL host inside the Docker network
- `dbdata_test` as the separate Docker test database volume

The Docker test service is one-off and does not run during the normal app startup command unless it is requested directly.

## Running Tests
- Run Unit + Feature suites:
  - `php artisan test`
- Run performance smoke suite:
  - `php artisan test --testsuite=Performance`
- Run weekly logging and Python runner tests:
  - `php artisan test tests/Unit/Logging tests/Unit/Support/DLSite/DLSitePythonRunnerTest.php`
- Run project-owned Python tests from an activated Python environment:
  - `python -m unittest discover -s python/tests -v`
- Run Unit + Feature suites inside Docker:
  - `docker compose --env-file docker/.env.docker --profile test run --rm --build tests`
- Run the performance smoke suite inside Docker:
  - `docker compose --env-file docker/.env.docker --profile test run --rm --build tests php artisan test --testsuite=Performance`
- Run a focused subset with PHPUnit's filter option:
  - `php artisan test --filter=<TestClassOrMethod>`
