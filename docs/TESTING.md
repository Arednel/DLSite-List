# Testing

## Automated Coverage

Current automated coverage includes Laravel PHPUnit and Python `unittest` tests:

- Language settings and requests cover English defaults, invalid-value fallback, persistence, Save/Reset redirects, destination-locale notices, and Laravel's active request locale.
- Localization behavior covers catalog integrity, representative visible and accessible UI copy, dynamic document language, locale-aware months and pluralization, stable backed values, and current-language fetched tags across Index, Edit, and Tag Library.
- Error behavior covers current recognized Refetch and Quick Add errors, unchanged raw error values, and verbatim pass-through for unknown errors.
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
  - Create navigation and completion: Laravel previous URL create back links, malformed create back-link input, create-mode back-link preservation including `modal=1`, Create Go Back preservation after scraper validation errors, custom create/upload flow, modal create/update/delete completion responses with calculated redirect URLs, deletion-specific `"RJ..." removed` status, and unchanged redirects for standalone requests
  - Product persistence and updates: shared five-attempt DLSite fetches, dismissible fixed Index and modal-completion image-failure warnings, DLSite storage with one fetched tag in both JP/EN buckets, contributor sync, automatic Series from `title_name`, enum-backed product field validation, metadata update flow, map-driven editable update payload behavior, semantic partial-date comparison with no-op Updated Date preservation, real date-change timestamp updates, duplicate English description cleanup, and logged destroy cleanup failures
  - Quick Add request behavior: exact three-message recognized-scraper translation versus verbatim unknown errors, concise missing-RJ validation, preserved return navigation after scraper failure, and reloaded scraper-error markup with the fetching status hidden and red error visible
- `tests/Feature/WorkFormModalTest.php`
  - covers the server-rendered completion fallback's `_top` link and presence or absence of completion-script markup based on warning state
- `tests/Feature/QuickAddFetchStatusTest.php`
  - covers the DLSite fetching-status markup across representative standalone/modal, Cherry/Black, and English/Japanese combinations; exactly two enabled submit controls; Custom Quick Add exclusion in standalone/modal modes; and Laravel-validation reload markup
- `tests/Feature/TagLibraryManagerTest.php`
  - Tag listing and locale behavior: current-language fetched/custom and zero-pivot tag listing, other-language exclusion, and locale-aware group members/counts/links/usage
  - Display state: collapsed default state, saved expanded default state, and search-open behavior
  - Empty-tag lifecycle: creation with normalized title keys, duplicate handling, modal-confirmed deletion, and protection when a tag gains a pivot before deletion is confirmed
  - Tag groups and membership: group create/rename/delete behavior, Add group placement inside the Tag Groups section, persisted Index group-ordering switch behavior, pivot-backed multi-group membership, duplicate membership prevention, remove-one-membership behavior, and group/tag ordering actions
  - Index visibility and colors: independent group/tag hidden settings, background and font color save/clear/validation, `#000000` placeholder coverage, independent background/font rendering, group-over-tag precedence from separately ordered values, and Blade color-logic cleanup
  - Editing controls: session-only tag edit mode, shared switch-style Edit Tags, Hide Tag on Index, and Hide Group on Index toggle markup, tag settings modal hydration, case-preserving rename validation, pivot/relationship preservation, cancel behavior, and circular delete controls
  - Group assignment UI: dropdown-style group search, searchable group assignment plaques, and staged group plaque save/cancel behavior
  - Parent/child relationships: searchable parent and child plaques, shared help-circle styling, current-tag relationship examples, both relation-edit directions, locked editing tag identity, existing-work backfill with Custom parents, non-destructive relation removal, and cycle rejection
  - Status, filtering, and ordering: compact in-chip hidden-tag indicators; fixed-order staged modal controls; Apply/Clear and invalid-value normalization; All Tags visibility, group, usage, parent/child-role, and own-color filters; duplicate secondary normalization; alphabetical/visible-work-count sorting in both directions with deterministic and configurable ties; Index group-ordering help; search preservation; and unchanged saved order inside Tag Group cards
- `tests/Feature/ReturnTargetProductTest.php`
  - covers product-aware return URLs for unlimited pagination, first-page omission, saved-page redirect fast paths, full-query visibility fast paths, unchanged-visibility fallback cleanup, multi-filter visible-work cleanup, and retaining only current-language fetched-tag filters
- `tests/Feature/ProductIndexLivewireTest.php`
  - Pagination and state: Livewire-owned pagination defaults, fixed/custom/unlimited page sizes, SQL-backed scalar/search/date/Added to the site Date pagination, built-in pagination links with the progress-menu scroll target, page reset behavior, and query-string initialization
  - Settings and hydration: one batched Index option lookup including content-overflow, image-viewer, optional-status, modal, and DLSite-link settings, narrowed result columns including non-hydrated sort-only fields and visible-field hydration, conditional hidden-age hydration without revealing the Age column, and Index table width CSS
  - DLSite, viewer, and modal links: default Maniax image links, enabled local image-viewer triggers with unchanged title links, enabled All Ages Home links, one reused external URL per product row, no trigger while the Image column is hidden, modal host metadata, real standalone Quick Add/Edit `href` values, and one-shot same-Index modal Quick Add positioning after reload
  - Fields and search: language-aware description matching in general search, responsive desktop/mobile Search placement around the progress controls, independent Japanese/English Description columns, configurable field order/visibility including locked Title and hideable Image, hidden-by-default sortable Added/Updated Date columns with `YYYY-MM-DD HH:mm` output, default-on inline Notes that can be hidden independently from the optional Notes column, independent inline Notes/Notes column/Tags overflow markup, and tag general-search behavior when the Tags column is enabled or hidden
  - Tags: separate Custom Tags/current-language Fetched Tags rendering inside one Index Tags column, locale-aware search/filter/link behavior, generic runtime bucket controls, prepared tag-link query preservation/replacement, default plain and optional grouped tag-chip ordering, optional tag-background/font colors with group-over-tag precedence, uncolored tag plain-link rendering, hidden-group anti-join skipping when no hidden groups exist, group-title tie-breaks, group-order normalization, multi-group tag de-duplication through visible groups, direct tag hiding, and any-hidden-group tag hiding including mixed visible/hidden group memberships
  - Sorting and filter UI: nullable scalar sort ordering, RJ/header sorting including optional listening and contributor columns, advanced primary/secondary sorting, configurable Advanced Filter sort dropdown visibility that does not disable valid URL/header sorting, Livewire-bound Filter modal controls with the Tag Library-aligned close icon, default and configurable Filter modal order/visibility for fixed and date-range widgets, restored filter defaults, the external Alpine advanced-filter component, and local client-side modal opening/closing without Livewire entanglement or native form reset
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
- `tests/Feature/IndexContentOverflowSettingsTest.php`
  - covers the three default-off Overflow controls, representative valid/invalid persistence, input normalization, restoration of default heights when disabled despite saved custom heights and valid/invalid hidden drafts, nested-update notice/error clearing, hydration, modal-confirmed individual reset, and refresh through the global reset event
- `tests/Feature/IndexImageViewerTest.php`
  - covers the default-off General setting, persistence, individual/global reset, disabled remote image links, Livewire JSON action success/error returns, unchanged title links, cover-first ordering, modification-time cache busting, retained missing positions with later valid images, accessible placeholder/navigation/counter markup, the safe new-tab contract, conditional dialog/script rendering, hidden Image-column behavior, and final-boundary migration cleanup
- `tests/Feature/OptionalProductStatusesTest.php`
  - covers every On Hold/Dropped switch combination across DLSite Quick Add, Custom Quick Add, Edit, Index navigation, and Advanced Filter; Edit's current-status exception; unconditional localized labels; and yellow/red row bars
- `tests/Feature/AutocompleteSettingsTest.php`
  - covers the Options autocomplete ordering setting component, including default usage ordering, separate tag and series persistence, modal-confirmed reset-to-default behavior, invalid enum values, and Livewire dirty-state saved notice behavior
- `tests/Feature/TagLibraryDisplaySettingsTest.php`
  - covers the Options Tag Library collapsed/expanded display default, Index group-ordering setting, and tag color surface toggles, including hydration, persistence, modal-confirmed reset-to-default behavior, global reset refresh, and stored option state
- `tests/Feature/ProductMetadataSettingsTest.php`
  - covers metadata-related Options components, including persistence, validation, individual/global reset behavior, independent optional-status switches, tooltips including the six Field Layout surface explanations and Updated Date Index/filter/sort explanations, product form settings, Index table width, separate and save-all field layout persistence, the default-on Notes-below-Title control, hidden-by-default Added/Updated Date Index controls, preservation of unsaved drafts in other layout blocks, field layout visibility/editability/order across each surface, generic fetched-bucket controls, current-locale labels, label-free layout storage, and originating-tab reload behavior
- `tests/Feature/ProductGenreMigrationTest.php`
  - covers the current schema's removal of global `genres.order` while preserving group/pivot order, migration of legacy product genre JSON into `genres` + `genre_product`, language row backfill into `genre_product_languages`, removal of old `genres.type` / `genres.language`, same product/tag attachments with both JP and EN language rows, and legacy migration compatibility when `genres.title_key` exists
- `tests/Feature/ProductMetadataMigrationTest.php`
  - covers metadata backfill from stored DLSite JSON, duplicate English description collapse, missing/invalid JSON skip behavior, and the rule that Series is not backfilled
- `tests/Feature/OptionsGeneralTest.php`
  - covers General/Field Layouts/Refetch tab rendering, invalid-tab fallback, shared modal configuration, empty Refetch state, distinct all/selected Refetch cards, latest-run-only linking, and cleanup/help placement
- `tests/Feature/OptionsRefetchCleanupTest.php`
  - covers always-visible cleanup rendering through `OptionsRefetchActions`, modal confirmation and cancellation, run deletion with cascaded result removal, private/public staged-content removal with Refetch roots and canonical Works files preserved, running/cancelling unavailability, the active-run recheck after confirmation, shared lifecycle-lock exclusion, and database-first cleanup when staged-file removal fails
- `tests/Feature/ProductImageCleanupTest.php`
  - covers command-wide one-by-one RJ folder cleanup, deterministic database-reference preservation, obsolete cover/sample removal, and protection for orphan/non-RJ folders, unknown filenames, nested files, and non-image files
- `tests/Feature/FullRefetchTest.php`
  - covers all/selected batching through result rows, including empty-start validation and custom-created RJ works, lifecycle-lock exclusion for run creation and review application during cleanup, the run-wide Refetch Images choice and help, reversible lean replacement schema, deterministic staging paths, full staged metadata fetches, thirteen ordered review tabs, shared review-select styling and tooltip assets, Livewire category validation and apply actions, newest-run-only behavior, incremental apply, checked retryable file promotion, actual-change-only JSON promotion, contributor-role and detailed-tag Updated Date behavior, full metadata/contributor/tag overwrite with user-owned fields preserved, independent cover/sample promotion, canonical-image restoration after a failed database update, updated-work-only obsolete-image cleanup, optional tag colors, custom-modal-confirmed pre-apply rejection, and partial-apply finish behavior
- `tests/Feature/OptionsRefetchProgressTest.php`
  - covers the Livewire refetch progress panel polling while a run is running/cancelling, its status and single fetched/failed/total summary, showing the cancel action only while running, and redirecting once review results are ready
- `tests/Feature/OptionsRefetchReviewTest.php`
  - covers initial and changed Livewire tabs, choice preservation across navigation and Apply Tab, validation, eligible-only Set Overwrite for All presets, preserved per-change overrides, read-only runs, shared custom-modal-confirmed Apply Tab/Apply All/Reject actions, accessible tab markup, and the no-inline-PHP review and prepared-value views
- `tests/Feature/OptionsWorkSearchTest.php`
  - covers the Livewire selected-work search, numeric RJ-desc visible order, and selected product preservation when filtered results change
- `tests/Feature/AuthenticationTest.php`
  - covers default-off access without recovery-state queries, Argon2id setup hashes, the shared 256-character maximum at setup/login, single-account creation, exact-case usernames, protected routes/mutations/Livewire updates, public help, generic login failures, five-attempt per-IP throttling and expiry, intended redirects, logout, login/setup/help/recovery markup across both authentication themes and both UI languages, and the 180-day remember cookie authenticating after session loss
- `tests/Feature/AuthenticationSettingsTest.php`
  - covers the separate non-resettable Authentication tab, guest-hidden authenticated controls, enable/setup and enable/login redirects, theme persistence, global-reset exclusion, current-password verification, rejected incorrect current passwords without credential mutation, the 256-character password maximum, password-change `wire:confirm` markup, confirmed password replacement, remember-token rotation, and logout after change
- `tests/Feature/AdminRecoveryTest.php`
  - covers the environment flag being ignored while authentication is off, forced one-time recovery while enabled, the 256-character password maximum without credential mutation, atomic rollback when recovery-marker persistence fails, consumed-state blocking, flag removal/restart state clearing, and unsupported multiple-user recovery
- `tests/Feature/AdminCommandTest.php`
  - covers masked console password reset, the 256-character password maximum without credential mutation, zero/multiple-user refusal, full user-table reset confirmation, and reset cancellation
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
  - covers global UI language default/normalization/persistence/reset, optional-status defaults/persistence/reset/batched hydration, field layout option persistence/fallbacks for Index/Edit/Filter/Create layouts including Notes-below-Title backward-compatible defaults, Index sort dropdown layout option persistence/fallbacks, automatic Series option normalization, age-appropriate DLSite link defaults/persistence/individual and global reset/batched hydration, product form theme normalization/Black default reset, Add/Edit modal defaults/persistence/invalid-action fallback/reset, Index table width and content-overflow normalization, and batched ProductIndex settings normalization/fallbacks
- `tests/Unit/Support/ProductIndexContentOverflowTest.php`
  - covers missing-setting defaults, discarded custom heights for disabled targets, per-value malformed-data fallback, unknown-target filtering, canonical height normalization, and the accepted/rejected CSS-length whitelist using non-default valid heights
- `tests/Unit/Models/ProductDLSiteUrlTest.php`
  - covers the no-age-access disabled Maniax path plus enabled Home mapping for exact All Ages and Maniax fallback for R15, R18, null, and malformed legacy ages
- `tests/Unit/Support/DLSite/DLSitePythonRunnerTest.php`
  - covers the Laravel Process command arrays for explicit work id, JSON/log destinations, optional image destination, project venv executable, disabled timeout, and normalized log-retention subprocess environment
- `tests/Unit/Support/DLSite/DLSiteWorkFetcherTest.php`
  - covers PHP-owned five-attempt retries, latest-manifest partial results, immediate success, strict manifest/JSON failures, and rejection of stale JSON fallback
- `tests/Unit/Support/RefetchDiffBuilderTest.php`
  - covers every metadata/creator/tag category, renamed fetched-tag distinction, case-only tag identity, content-hashed Cover changes, and independently unavailable Sample Images after any sample failure
- `tests/Unit/Logging/WeeklyRotatingFileHandlerTest.php`
  - covers UTC weekly filenames and boundaries, same-week appends, Monday file switching, first-write cleanup after same-week expiry, complete-week retention, selective cleanup, concurrent archive removal, invalid retention fallback, and non-blocking cleanup failures
- `tests/Unit/Logging/LoggingConfigurationTest.php`
  - covers the Laravel stack/custom Monolog channel configuration, locked writes, and a real configured channel write
- `tests/Unit/Support/GenreSyncPayloadTest.php`
  - covers shared `genre_product.source` sync payload creation, deduplication, fetched-over-custom precedence, and fetched language map creation
- `tests/Unit/Support/ProductGenreSyncTest.php`
  - covers syncing one product/tag attachment with multiple fetched language rows, replacing only the selected English or Japanese bucket, preserving other fetched languages and unsubmitted custom tags, fetched-over-custom precedence across languages, recursive parent/ancestor expansion for fetched and custom children, and touching the product timestamp only when its effective tag state changes
- `tests/Unit/Support/ProductContributorSyncTest.php`
  - covers case-folded contributor identity, circle maker id persistence, role-specific contributor replacement, same-contributor/different-role pivot isolation, and touching the selected product only when its effective contributor state changes
- `tests/Unit/Models/GenreTest.php`
  - covers title-key identity, including case-insensitive tag reuse, preserved display casing, and distinct Hiragana/Katakana variants, plus inverse parent/child relationship access
- `tests/Unit/Support/VisibleGenreAttachmentTest.php`
  - covers current-locale defaults, explicit language overrides, always-visible custom attachments, fetched-source gating, and one-row visibility for shared JP+EN attachments
- `tests/Unit/Support/ReturnTargetTest.php`
  - covers index-only return query/fragment normalization, malformed input fallback, ignored legacy return routes, and URL generation
- `tests/Unit/View/Components/Fields/EnumSelectFieldTest.php`
  - covers enum-backed field component defaults and option maps
- `tests/Unit/Models/RefetchStateTest.php`
  - covers generic refetch run/result state and category helpers

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
Add/Edit modal tests store both modal options through `App\Models\Option`, render all supported host pages, and assert option normalization, standalone link URLs, Livewire save/reset events, and modal completion responses without requiring a browser. The completion assertions also cover the dedicated stylesheet, semantic fallback card, deletion-specific RJ status, `_top` Continue link, conditional completion-script markup, and warning behavior. They do not execute the modal JavaScript. Native dialog, iframe, focus, mouse, messaging, completion-action handling, scrolling, and navigation behavior remain browser checks.
Age-appropriate DLSite link tests store `options.dlsite_age_appropriate_links_enabled` through `App\Models\Option`; query-log assertions verify hidden `age_category` remains unselected while disabled and is hydrated only when enabled.
Image-viewer tests store `options.index_image_viewer_enabled` through `App\Models\Option`; Livewire action assertions verify ordered browser URLs, retain missing positions, and cache-bust later valid files. Response assertions cover accessible dialog controls, the hidden safe new-tab link, conditional dialog/script inclusion, and migration-owned unsafe value cleanup.
Optional-status tests store the two switches in `options.optional_product_statuses` and verify that they affect only rendered form, filter, and Index controls.
Quick Add fetch-status tests render standalone/modal DLSite and Custom forms without executing JavaScript. They verify localized accessible markup, enabled submit controls, and server-rendered validation/error state; submit events, native browser validation, `pageshow` behavior, and visual readability remain browser checks.
Index content-overflow tests assert that disabled fields retain their content without overflow controls, enabled fields receive their configured heights, and Show all/Show less buttons reference the correct content IDs, including empty content and mixed enabled/disabled settings. CSS height limits, expanded ARIA state, and Show all/Show less interaction remain browser checks because the repository has no browser-layout test harness.

### Docker test setup

Docker tests use:

- `docker/.env.testing.docker` for Laravel's testing environment variables
- `database_test` as the MySQL host inside the Docker network
- `dbdata_test` as the separate Docker test database volume

The Docker test service is one-off and does not run during the normal app startup command unless it is requested directly.

## Manual Authentication Checks

- Sign in with Remember me, restart the browser normally, and confirm the login persists. Automated coverage verifies the persistent cookie's documented 180-day lifetime and authentication after session loss.
- Visually spot-check login, setup, forgot-password help, and environment recovery, distributing the pages across both Cherry and Black themes and both UI languages instead of checking every combination. Their complete theme/language/content markup matrix is automated.
- Change the password from the Authentication tab and exercise both cancel and accept on the browser confirmation. The `wire:confirm` contract, current-password validation, credential replacement, and logout are automated.
- In a trusted local environment only, enable `ADMIN_PASSWORD_RESET=true`, restart, complete one reset, and confirm all pages remain blocked by the removal message. Remove the variable, restart/recreate the app process, and confirm normal login resumes.

## Manual Image Viewer Checks

- Enable the viewer and confirm clicking a thumbnail opens the saved cover first and shows only the image counter. Cover-first ordering, viewer markup, and unchanged title links are automated.
- Confirm Previous and Next wrap between the cover and numerically ordered samples, keyboard navigation and close controls work, opening another work resets the viewer to its cover, and `View in full` opens the current loaded image in a new tab. Navigation controls and the safe new-tab markup are automated.
- Confirm a retained path for a missing download shows `No image` without removing its counter position, hides `View in full`, and leaves later valid images reachable. Missing-position retention and later valid URLs are automated.

## Manual Quick Add Fetch Status Checks

- In standalone and modal DLSite Quick Add, confirm the top and bottom Add work buttons and Enter reveal readable fetching-status text beneath the RJ field; spot-check both Cherry and Black themes. Its localized markup is automated.
- Confirm native browser validation prevents an empty required RJ field from revealing the message. Both rendered Add work controls are automatically verified as enabled.
- Confirm browser Back restores DLSite Quick Add with the message hidden. Custom Quick Add exclusion and validation/scraper error reload markup are automated.

## Manual Add/Edit Modal Checks

- On Index, confirm Quick Add and Edit navigate as standalone pages while the option is disabled, then open the native dialog with an ordinary left-click while enabled. Also smoke-test Quick Add from one secondary host such as Options. Enabled host markers and server-rendered standalone URLs are automated; disabled click behavior is not.
- Confirm middle-click, right-click, and Ctrl/Cmd/Shift/Alt-click keep native link behavior and do not open the modal.
- Confirm the header Close button, Escape, backdrop click, and the form's Close control dismiss the dialog, clear the iframe, and return focus without treating cancellation as a successful mutation. Modal Close-control markup and marker preservation are automated.
- Confirm DLSite/Custom Create switching, validation errors, autocomplete, responsive sizing, Edit's Delete confirmation, and Create/Edit styling work inside the iframe without inheriting or covering host-page UI. Mode/marker preservation, validation responses, autocomplete endpoints/markup, and Delete form markup are automated.
- Confirm the browser applies Laravel redirect, host-page refresh, and close-only completion choices. Server responses for successful create, update, and delete are automated.
- With Follow redirect selected, complete modal Quick Add from the plain Index, one filtered/paginated Index URL, and one non-Index host. Confirm each calculated destination is followed and the plain Index moves the viewport to the new `#RJ...` row. Destination calculation is automated.
- Optional release/resilience check: intentionally block parent messaging, then confirm the completion fallback displays and its Continue button navigates the full browser page. Fallback and `_top` link markup are automated; messaging and navigation execution remain browser checks.

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
