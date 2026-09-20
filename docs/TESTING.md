# Testing

This document describes how to run the project's existing tests and what each current test file covers.

For runtime architecture and data flow, see [ARCHITECTURE.md](ARCHITECTURE.md).  
For installation, environment variables, Options settings, recovery commands, and other configuration, see [CONFIGURATION.md](CONFIGURATION.md).

## Run Tests

### Laravel Unit + Feature

From the project root:

```bash
php artisan test
```

This is the normal Laravel test command for the project's Unit and Feature coverage.

### Performance

Performance tests are separate:

```bash
php artisan test --testsuite=Performance
```

`PerformanceSmokeTest` emits PHPUnit warnings above its timing thresholds. To keep the warning output while allowing a successful exit code:

```bash
php artisan test --testsuite=Performance --do-not-fail-on-phpunit-warning
```

### Weekly Logging and Python Runner Focused Run

Existing focused command:

```bash
php artisan test tests/Unit/Logging tests/Unit/Support/DLSite/DLSitePythonRunnerTest.php
```

### Python Tests

Run the project-owned Python `unittest` suite:

```bash
python -m unittest discover -s python/tests -v
```

### Run One Laravel Test/Class/Method

Use PHPUnit/Laravel filtering:

```bash
php artisan test --filter=<TestClassOrMethod>
```

### Docker Unit + Feature

```bash
docker compose --env-file docker/.env.docker --profile test run --rm --build tests
```

### Docker Performance

```bash
docker compose --env-file docker/.env.docker --profile test run --rm --build tests php artisan test --testsuite=Performance
```

The Docker `tests` service is one-off and does not start during normal application startup.

## Test Environment Setup

### Local

1. Copy:

```text
.env.testing.example
```

to:

```text
.env.testing
```

2. Configure the dedicated test database:

```dotenv
DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

3. Set:

```dotenv
APP_KEY=
```

`phpunit.xml` then overrides several effective test settings, including:

```text
CACHE_DRIVER=array
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
BCRYPT_ROUNDS=4
```

This means values such as file cache/session or SMTP mail in `.env.testing.example` are not the final values used by PHPUnit when an explicit `phpunit.xml` override exists.

Feature tests use Laravel `RefreshDatabase`, so use a dedicated test database.

### Docker

Docker tests use:
- `docker/.env.testing.docker`
- MySQL host `database_test`
- separate `dbdata_test` volume
- synchronous queue
- array cache/session/mail settings where configured by the supplied testing environment

The test database is separate from the normal Docker application database.

## Current Automated Test Inventory

### Feature Tests

#### `tests/Feature/AdminCommandTest.php`

Covers the existing administrator console recovery commands:
- password reset prompts/validation
- zero/multiple-user refusal
- full user reset confirmation/cancellation

#### `tests/Feature/AdminRecoveryTest.php`

Covers trusted-environment `ADMIN_PASSWORD_RESET` recovery, including enabled/disabled states, one-time consumption, restart/flag removal behavior, password validation, rollback, and unsupported account counts.

#### `tests/Feature/AuthenticationSettingsTest.php`

Covers the Authentication Options tab:
- enable/disable flow
- authentication theme
- account state
- password change
- current-password validation
- remember-token rotation
- logout after password change
- exclusion from global Options reset

#### `tests/Feature/AuthenticationTest.php`

Covers optional administrator authentication:
- default-off behavior
- setup/login/logout
- single-account setup
- exact-case usernames
- protected routes/mutations/Livewire requests
- public auth/help routes
- throttling
- intended redirects
- themes/locales
- Remember me persistence

#### `tests/Feature/AutocompleteControllerTest.php`

Covers tag/series autocomplete endpoints, matching/order rules, result limits, tag color payloads, group-over-tag color precedence, and autocomplete asset/data-attribute rendering.

#### `tests/Feature/AutocompleteSettingsTest.php`

Covers independent persisted ordering settings for tag and series autocomplete.

#### `tests/Feature/FullRefetchTest.php`

Covers the full Refetch workflow:
- all/selected runs
- run creation and batch/result rows
- staged fetch data
- image-check choice
- thirteen review categories
- apply/ignore/reject behavior
- incremental application
- lifecycle locking
- metadata/tag/contributor preservation/overwrite rules
- canonical JSON promotion
- cover/sample promotion and rollback
- obsolete-image cleanup
- Updated Date behavior

#### `tests/Feature/LibraryTransferTest.php`

Covers core transfer contracts, route/review smoke coverage, portable Options, new-work round trips, controlled downloads, and the shared library-mutation lock.

#### `tests/Feature/LibraryTransferExportTest.php`

Covers export selection/planning, deterministic work/image inventory, Tag Library/Options fragmentation, multipart manifests and size boundaries, work-level progress, source-change replanning, and downloads.

Regression coverage injects failures after successor-job insertion in every planning phase, verifies transaction rollback and successful retry, and checks stale planning calls.

#### `tests/Feature/LibraryTransferImportTest.php`

Covers multipart upload/validation, missing or replacement parts, archive/path/checksum safety, bounded analysis/checkpoint retries, image validation, existing/new work analysis, and data-only continuation.

#### `tests/Feature/LibraryTransferReviewTest.php`

Covers Ignore/Overwrite/Merge decisions, work/tag/group/relationship/option application, stale-conflict handling, atomic work updates, image promotion/recovery, timestamps, and read-only completed reviews.

Partial contributor document coverage verifies that omitted roles survive Overwrite and explicitly empty roles can still be cleared.

#### `tests/Feature/LibraryTransferLifecycleTest.php`

Covers supersession/cancellation, active-transfer guards, history cleanup, queued checkpoints/retries, generation and operation tokens, late callbacks, publication recovery, browser-started imports, and transfer-storage cleanup.

Storage cleanup coverage holds the upload lock before database registration to verify that a sweep skips active uploads and later removes only unreferenced files.

#### `tests/Feature/LibraryTransferConcurrencyTest.php`

Covers stale-value detection for work fields and typed Options using two MySQL connections and an older `REPEATABLE READ` snapshot. Never run this test against production.

#### `tests/Feature/GenreGroupRelationshipTest.php`

Covers:
- group-to-tag ordering and pivot data
- tag-to-group ordering
- Index-visible/hidden scopes
- persistence and clearing of tag/group background and text colors

#### `tests/Feature/IndexContentOverflowSettingsTest.php`

Covers saving/resetting Index Notes/Tags overflow settings and their validation/default behavior.

#### `tests/Feature/IndexImageViewerTest.php`

Covers the Image Viewer option and Index viewer rendering/data:
- enable/disable behavior
- image ordering/path handling
- accessible controls
- safe full-image links
- conditional script/dialog output

#### `tests/Feature/IndexPaginationSettingsTest.php`

Covers fixed, custom, and unlimited Index pagination settings and reset/default behavior.

#### `tests/Feature/IndexSearchSettingsTest.php`

Covers the `Search hidden descriptions` option and its effect on Index general search.

#### `tests/Feature/ListMenuFloatLocalizationTest.php`

Covers localized copy in the shared floating/drawer navigation and shared menu UI.

#### `tests/Feature/LocalizedPhpUiTest.php`

Covers localized PHP-rendered display state, including:
- localized month labels with stable numeric values
- localized Progress display with stable state values
- localized Age/Progress enum display without changing stored/backed values

#### `tests/Feature/OptionalProductStatusesTest.php`

Covers On Hold/Dropped configuration across Add/Edit forms, Index progress navigation, filters, existing product values, and all enabled/disabled combinations.

#### `tests/Feature/OptionsGeneralTest.php`

Covers:
- General-tab rendering and default selection
- Field Layouts content
- active-tab accessibility state
- invalid-tab fallback
- Refetch empty/latest-run presentation
- shared Quick Add modal configuration

#### `tests/Feature/OptionsRefetchCleanupTest.php`

Covers Refetch cleanup:
- confirmation/cancellation
- database cascade removal
- private/public staged-file cleanup
- preserved Refetch roots/canonical Works
- active-run blocking
- lifecycle lock
- database-first failure behavior

#### `tests/Feature/OptionsRefetchLocalizationTest.php`

Covers:
- localized Options/Refetch UI
- stable persisted/action values across locales
- unchanged raw scraped details where appropriate
- localized validation
- reset navigation behavior

#### `tests/Feature/OptionsRefetchProgressTest.php`

Covers Livewire Refetch progress polling, running/cancelling state, counts, Cancel visibility, and redirect to review.

#### `tests/Feature/OptionsRefetchReviewTest.php`

Covers Refetch review tabs, per-change choices, bulk overwrite presets, Apply Tab/Apply All/Reject/finish confirmations, validation, read-only state, and accessible tab/tabpanel relationships. Also covers 100-change pagination, pagination by individual changes rather than works, page navigation, category-switch page resets, choice preservation across pages, and applying choices from the complete run rather than only the visible page.

#### `tests/Feature/OptionsWorkSearchTest.php`

Covers selected-work Refetch search, RJ-desc ordering, and preservation of selected products while filtering.

#### `tests/Feature/ProductControllerTest.php`

Covers the main product HTTP workflows:
- Index filtering/sorting/display
- Create/Edit field layouts
- hidden/read-only field preservation
- DLSite Quick Add fetch/store/error behavior
- Custom Quick Add uploads
- tag/contributor synchronization
- metadata and partial-date updates
- automatic Series
- current-language fetched/custom tags
- Index-aware return navigation
- modal completion responses
- deletion and cleanup-error handling

#### `tests/Feature/ProductGenreMigrationTest.php`

Covers migration of legacy product genre data into normalized tag/product relationships.

#### `tests/Feature/ProductImageCleanupTest.php`

Covers `works:cleanup-images`, including preservation of referenced images and protection of orphan/unknown/nested/non-image files.

#### `tests/Feature/ProductIndexLivewireTest.php`

Covers the Livewire Index:
- pagination/query-string state
- filtering/search/date ranges
- sort behavior
- narrow hydration
- batched settings
- field visibility/order
- timestamps
- DLSite links
- Image Viewer
- Add/Edit modal links
- tags/colors/group ordering
- optional statuses
- responsive rendered structure
- content overflow
- return/query behavior

#### `tests/Feature/ProductMetadataMigrationTest.php`

Covers metadata-backfill behavior when canonical work JSON is missing or invalid, ensuring existing database metadata is preserved.

#### `tests/Feature/ProductMetadataSettingsTest.php`

Covers metadata-related UI settings:
- field layouts
- automatic Series
- DLSite links
- Add/Edit form theme
- Add/Edit modal
- Index table width
- overflow settings
- reset behavior

#### `tests/Feature/ProductSortKeysTest.php`

Covers derived RJ and partial-date sort keys and exact series sorting behavior.

#### `tests/Feature/ProductSurfaceLocalizationTest.php`

Covers localization across Index/Create/Edit product surfaces, including:
- document language
- field/help/accessibility text
- delete confirmation
- modal completion behavior
- validation messages

#### `tests/Feature/QuickAddFetchStatusTest.php`

Covers server-rendered DLSite Quick Add fetching-status markup across standalone/modal, theme, and locale variants; confirms Custom Quick Add excludes the DLSite status behavior.

#### `tests/Feature/ReturnTargetProductTest.php`

Covers product-aware Index return URLs, visibility checks, pagination/page cleanup, filters, hidden-description behavior, and current-language tag filtering.

#### `tests/Feature/SetUiLocaleTest.php`

Covers request middleware locale selection and fallback.

#### `tests/Feature/TagLibraryDisplaySettingsTest.php`

Covers Tag Library expanded/collapsed default, Index group-ordering setting, and tag-color surface configuration.

#### `tests/Feature/TagLibraryLocalizationTest.php`

Covers localized Tag Library labels, controls, and current-language behavior.

#### `tests/Feature/TagLibraryManagerTest.php`

Covers Tag Library behavior:
- current-language/custom/empty tags
- create/rename/delete
- groups and multi-group membership
- saved ordering
- Index visibility
- background/text colors
- Edit Tags mode
- group assignment UI
- parent/child relationships and cycle rejection
- ancestor backfill
- session filters/search/sorting
- Index links/counts

#### `tests/Feature/UiLanguageSettingsTest.php`

Covers saving/resetting the global UI language and resulting locale/redirect/notice behavior.

#### `tests/Feature/WorkFormModalTest.php`

Covers server-rendered Add/Edit modal completion fallback output and conditional completion-script behavior.

### Unit Tests

#### Top Level

`tests/Unit/LocalizedDisplayProvidersTest.php`
- Covers localized enum/Option display values, locale-independent stored field-layout data, and localized partial-date formatting while keeping stable underlying values.

`tests/Unit/UserFacingDisplayStateTest.php`
- Covers localized Refetch status/error presentation while stable status values and unknown scraper details remain unchanged.

#### Enums

`tests/Unit/Enums/ProductContributorRoleTest.php`
- Covers contributor role values and role-to-product-field mapping.

`tests/Unit/Enums/ProductIndexSortFieldTest.php`
- Covers valid Index sort fields, labels/backend metadata, and sort-dropdown behavior.

`tests/Unit/Enums/UiLanguageTest.php`
- Covers UI language values, labels, fallback, and fetched-tag language mapping.

#### Logging

`tests/Unit/Logging/LoggingConfigurationTest.php`
- Covers Laravel logging channel configuration, locked writes, and a real configured-channel write.

`tests/Unit/Logging/WeeklyRotatingFileHandlerTest.php`
- Covers UTC weekly filenames/boundaries, appends/switches, retention cleanup, invalid-retention fallback, concurrent archive removal, and non-blocking cleanup failures.

#### Models

`tests/Unit/Models/GenreTest.php`
- Covers normalized tag identity/display casing, Hiragana/Katakana distinction, and inverse parent/child relationships.

`tests/Unit/Models/OptionMetadataSettingsTest.php`
- Covers Option defaults, normalization, persistence, reset behavior, and batched metadata/Index settings.

`tests/Unit/Models/ProductDLSiteUrlTest.php`
- Covers default Maniax URLs and enabled age-aware Home/Maniax mapping.

`tests/Unit/Models/RefetchStateTest.php`
- Covers Refetch run/result state and category helpers.

#### Support

`tests/Unit/Support/AutocompleteMatcherTest.php`
- Covers autocomplete ranking and usage-order comparison.

`tests/Unit/Support/GenreSyncPayloadTest.php`
- Covers tag sync payload creation, de-duplication, source precedence, and fetched-language maps.

`tests/Unit/Support/ProductContributorSyncTest.php`
- Covers normalized contributor identity, maker id, role-specific sync, cross-role isolation, and Updated Date touching only on effective change.

`tests/Unit/Support/ProductFieldLayoutTest.php`
- Covers surface defaults/availability, localized fetched-tag labels, row normalization, required locks, editability, and prepared layout metadata.

`tests/Unit/Support/ProductGenreSyncTest.php`
- Covers fetched language buckets, custom/fetched preservation/precedence, recursive ancestor expansion, and Updated Date touching only on effective change.

`tests/Unit/Support/ProductIndexContentOverflowTest.php`
- Covers overflow defaults, malformed stored data, target filtering, canonical CSS-length normalization, and accepted/rejected units.

`tests/Unit/Support/ProductIndexFiltersTest.php`
- Covers Index query normalization, filter/date round trips, defaults, sort options, input keys, visibility groups, and query export.

`tests/Unit/Support/ProductIndexRowBuilderTest.php`
- Covers typed Index row construction from narrow product hydration, encoded Series/Circle/contributor filter URLs, preserved Edit return state/fragment, age-aware DLSite URLs, defaults for unhydrated optional attributes, and rejection of a missing required `work_name`.

`tests/Unit/Support/RefetchDiffBuilderTest.php`
- Covers all Refetch metadata/creator/tag categories, tag identity handling, cover hashing, and unavailable sample-image behavior.

`tests/Unit/Support/ReturnTargetTest.php`
- Covers Index-only return-query/fragment normalization, malformed input fallback, legacy route rejection, and URL generation.

`tests/Unit/Support/TagColorTest.php`
- Covers tag/group color normalization and effective group-over-tag background/text color selection.

`tests/Unit/Support/VisibleGenreAttachmentTest.php`
- Covers custom/current-language fetched visibility and one-row handling for shared JP/EN attachments.

#### DLSite Support

`tests/Unit/Support/DLSite/DLSitePythonRunnerTest.php`
- Covers Laravel Process command construction, explicit output destinations, venv executable, timeout behavior, and log-retention environment.

`tests/Unit/Support/DLSite/DLSiteWorkDataTest.php`
- Covers shared scraped metadata extraction for titles/descriptions/creators/maker data, English fallback behavior, product ids, and missing-id errors.

`tests/Unit/Support/DLSite/DLSiteWorkFetcherTest.php`
- Covers the PHP-owned five-attempt retry loop, manifest/JSON validation, partial results, immediate success, and rejection of stale JSON fallback.

#### View Components

`tests/Unit/View/Components/Fields/EnumSelectFieldTest.php`
- Covers enum-backed field component defaults and option maps.

### Performance

#### `tests/Performance/PerformanceSmokeTest.php`

Default fixture size:
- 500 works
- 500 tags
- 10,000 tag-product pivots
- contributor rows for each Index contributor role

Reports average response times for:
- default/full-column paginated Index
- default/full-column unlimited Index
- the same paths with configured tag colors
- filtered/search/tag Index paths
- Options tabs
- common/recalculated/filter-cleanup update return paths
- delete page-clamp return paths

Threshold behavior:
- over 500 ms -> PHPUnit warning
- over 1000 ms -> stronger warning text

### Python

#### `python/tests/test_weekly_logging.py`

Covers Python weekly logging parity with Laravel:
- UTC week calculation
- append/week-switch behavior
- retention cleanup
- same-week expiry handling
- concurrent removal
- invalid retention fallback
- production handler interface
- non-blocking cleanup failures

## Existing Test Implementation Boundaries

The current suite intentionally uses framework fakes for external/destructive boundaries:

- upload tests use `UploadedFile::fake()` and `Storage::fake('public')`
- Full Refetch tests use `Bus::fake()`, `Process::fake()`, and fake storage
- Library Import / Export tests use fake private/public storage and a fake bus where appropriate, while creating and reading real ZIP archives
- scraper-process tests use `Process::fake()` and `Process::preventStrayProcesses()`
- Livewire component tests use `Livewire::test()`
- Feature tests use `RefreshDatabase`

These are descriptions of the current suite, not additional setup steps.

The repository does not currently use a browser-layout automation harness. JavaScript execution, native dialog behavior, focus/mouse behavior, iframe messaging, and visual layout interactions remain manual checks where the PHP/HTML contract is already covered by automated tests.

## Existing Manual Browser Checks

These checks remain manual because they depend on browser behavior, JavaScript execution, native dialog/focus handling, or visual responsive behavior that the current automated suite does not execute directly.

### Authentication

- Sign in with Remember me, restart the browser normally, and confirm the login persists.
- Spot-check Login, Setup, Help, and Recovery pages in both authentication themes and both UI languages.
- Change the password and exercise both Cancel and Accept on the browser confirmation.
- In a trusted local environment:
  1. set `ADMIN_PASSWORD_RESET=true`
  2. restart/recreate the PHP process
  3. complete recovery
  4. disable/remove the flag
  5. restart/recreate the PHP process again
  6. confirm normal login resumes

### Image Viewer

- Enable the viewer and confirm clicking an Index thumbnail opens the saved cover first.
- Confirm Previous/Next wrap through the cover and ordered sample images.
- Confirm keyboard navigation and close controls.
- Open another work and confirm the viewer starts from that work's cover.
- Confirm `View in full` opens the currently displayed image in a new tab.
- Check behavior when one of the saved image paths is missing/unavailable and confirm the viewer remains usable.

### Quick Add Fetch Status

- Submit DLSite Quick Add using each available Add/submit control and confirm the green fetching state appears.
- Submit from a text field with Enter and confirm the same fetching state behavior.
- Confirm native required-field validation prevents the fetching state from appearing when the browser blocks submission.
- Confirm the fetching state does not disable the form while the request is in progress.
- Return through browser history and confirm `pageshow`/back-forward-cache restoration does not leave a stale fetching state visible.
- Confirm validation or scraper errors show the normal server-rendered error state instead of leaving the fetching state active.
- Confirm Custom Quick Add never renders or activates the DLSite fetching-status behavior.

### Mobile Options Navigation

Check representative widths:

```text
320px
375px
768px
769px
```

At mobile/tablet widths:
- open the shared Options navigation drawer
- confirm every Options tab remains reachable
- confirm the active tab/state remains understandable
- confirm disclosure open/close behavior
- confirm outside-click closes the drawer where expected
- confirm Escape closes it
- confirm keyboard focus remains usable after opening/closing

At the desktop breakpoint:
- confirm the desktop Options navigation replaces the mobile drawer as intended

### Add/Edit Modal

With Add/Edit modal mode enabled, exercise Quick Add and Edit from each supported host page.

Navigation/fallback:
- primary click opens the native dialog
- middle-click/modified-click keeps using the real standalone URL
- right-click/context-menu still exposes normal browser link behavior
- browsers/contexts without dialog interception can still navigate to the standalone page

Closing:
- modal Close control closes without reporting a successful change
- Escape closes the modal
- backdrop click closes the modal
- focus returns to a sensible host-page element after closing

Successful mutations:
- create/update/delete success follows the configured `redirect`, `refresh`, or `close` completion action
- redirect uses Laravel's calculated Index return URL
- refresh reloads the host page
- close leaves the host page in place

Resilience:
- confirm iframe/dialog focus and scrolling remain usable
- confirm browser Back/Forward behavior does not strand an unusable modal
- confirm modal completion still behaves sensibly if the host page state changed while the modal was open
- confirm standalone Create/Edit pages remain fully functional when opened directly
