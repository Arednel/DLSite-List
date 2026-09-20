# Changelog

All notable changes to this project are documented in this file.

## Unreleased

### Added

* Added ZIP import/export for Works, Images, Tag Library data, and Options.
* Added paginated navigation to Refetch review categories.

### Changed

* Replaced the mobile Options tabs with an expandable navigation list.
* Reorganized the changelog into versioned sections grouped by change type.
* Updated architecture, configuration, and testing documentation to match current project behavior and improve clarity.
* Updated Docker upload limits and queue timing to support large, long-running library transfers.
* Updated Refetch and Import/Export review errors to use compact lists.

### Fixed

* Made Refetch image updates more reliable when interrupted.
* Fixed Refetch review dropdown overflowing their cards on mobile view.
* Configured Docker application processes to run as `www-data`, preventing shared cache and storage permission errors.

## 1.8.7 - 2026-09-08

*Field Layouts & UI Cleanup*

### Added

* Added optional Added/Updated Date, Notes below Title control in Field Layouts
* Added contextual help to Field Layouts and Tag Library controls

### Changed

* Replaced the Refetch Reject browser prompt with the shared confirmation modal
* Clarified Quick Add, Refetch, and field-layout wording and added an RJ code placeholder
* General UI Cleanup

## 1.8.6 - 2026-09-07

*Notes & Tags Overflow*

### Added

* Added configurable height limits and Show all/Show less controls for Index Notes and Tags

### Changed

* Updated README.md
* Code cleanup

## 1.8.5.1 - 2026-08-24

*App Name*

### Changed

* Set unique APP_NAME

## 1.8.5 - 2026-08-24

*Tag Library Sorting UI*

### Added

* Added primary/secondary Alphabetical and Work count sorting to All Tags with segmented Asc/Desc controls

### Changed

* Aligned filter layout, mobile spacing, help text, and close controls across Tag Library and Index

## 1.8.4 - 2026-08-24

*UI Text*

### Added

* Added RJ-specific modal deletion feedback and simplified DLSite fetch errors

### Changed

* Refined wording across Add/Edit, images, DLSite links, Refetch, authentication, and validation

## 1.8.3 - 2026-08-14

*Test Coverage & Documentation*

### Changed

* Expanded regression coverage for authentication, Image Viewer, Quick Add status, and work-form modals
* Updated docs

## 1.8.2 - 2026-08-09

*Auth Improvements*

### Changed

* Now current password is required before changing it in Auth settings

### Security

* Switched password hashing to Argon2id and capped passwords at 256 characters (Existing account password must be reset manually)

## 1.8.1 - 2026-08-04

*Tags Relationships, Renaming & Filters*

### Added

* Added parent/child tag relationships
* Added tag renaming
* Added filters for Tag Library

## 1.8.0 - 2026-08-04

*Refetch Fixes & Cleanup*

### Added

* Added modal confirmations and cleanup for Refetch runs

### Changed

* Simplified Index filters, product requests, and relationship syncing with Laravel/Livewire helpers

### Fixed

* Fixed Refetch image replacement with rollback-safe promotion, obsolete file cleanup, and browser refreshes for overwritten images
* Fixed Updated Date and canonical JSON changes to occur only for effective work updates

## 1.7.9 - 2026-08-01

*Full Work Refetch*

### Changed

* Replaced tag-only refetch with staged full-work refetch and per-category review
* Unified DLSite fetching for Add and Refetch
* Image download failures now save the work and show a warning
* Code cleanup

## 1.7.8 - 2026-07-29

*Optional Statuses & Index Polish*

### Added

* Added optional On Hold and Dropped statuses across forms, navigation, and filters

### Changed

* Refined responsive Index progress and Search/Filters layout

## 1.7.7 - 2026-07-28

*Settings UI Polish*

### Added

* Added icons to Options setting headings
* Added separate Save actions for each Field Layout block

## 1.7.6 - 2026-07-28

*Image Viewer*

### Added

* Added lazy loading for Index images
* Added a default-off Image Viewer for saved cover and sample images

### Changed

* Normalized existing image references to local paths while retaining potential paths for missing downloads

## 1.7.5 - 2026-07-27

*Optional Authentication*

### Security

* Added turned off by default authentication

## 1.7.4 - 2026-07-26

*Code Cleanup*

### Changed

* Set specific Livewire version to v4.3.3
* Simplified Product Index filter state
* Simplified Tag Library validation and group membership handling

### Fixed

* Font Awesome version fixe

## 1.7.3 - 2026-07-25

*Product Index Performance & View Cleanup*

### Changed

* Improved Product Index rendering performance by preparing row data and links before Blade
* Simplified Options and pagination views

## 1.7.2 - 2026-07-18

*Form Fixes & Performance Suite*

### Changed

* Moved Performance Smoke tests into separate Test Suite

### Fixed

* Fixed Quick add/Edit spacing and non-editable tags fields

## 1.7.1 - 2026-07-18

*Localization and Locale-Aware Tags*

### Added

* Added a global English/Japanese language setting
* Added rough (automatically translated) Japanese locale

### Changed

* Made fetched-tag display, search, filters, editing, and Tag Library results follow the selected language

### Fixed

* General small code fixes and simplifications

## 1.7.0 - 2026-07-14

*Quick Add Fetch Status and Fix*

### Added

* Added in-progress message below the RJ field while DLSite Quick Add submits and fetches work data

### Fixed

* Fixed modal Quick Add's Follow redirect on the plain Index so the refreshed page moves to the newly added work

## 1.6.9 - 2026-07-14

*Age-Appropriate DLSite Links*

### Added

* Added a default off General option that opens All Ages Index work links on DLSite Home while keeping R15 and R18 links on Maniax

## 1.6.8 - 2026-07-14

*Disabled phpMyAdmin*

### Changed

* Disabled phpMyAdmin in compose.yaml (can be uncommented to turn back on)

## 1.6.7 - 2026-07-14

*Work Form Modals*

### Added

* Added an optional native-dialog modal for Quick Add on Index, Options, Tag Library, and Refetch pages and for Edit Work on Index
* Added configurable redirect, refresh, and close-only behavior after successful create, update, or delete actions

## 1.6.6 - 2026-07-13

*Log Rotation*

### Added

* Added Monday-based UTC weekly log files for Laravel and the Python scraper

## 1.6.5 - 2026-07-13

*Code Cleanup*

### Changed

* Simplified product routing, validation, option defaults, and relationship queries using Laravel conventions
* Removed unused wrappers and cleaned up related tests

## 1.6.4 - 2026-07-11

*License & Dependencies*

### Added

* Added MIT License

### Changed

* Updated README.md
* Updated dlsite-async, requests, pillow python package (requires venv update)
* Updated python requirements to 3.14.6+

## 1.6.3 - 2026-07-10

*UI Cleanup*

### Added

* Added Field Layouts helper tooltips for the Updated Date filter and sort options

### Changed

* Updated per-setting saved notices

### Fixed

* General fixes

## 1.6.2 - 2026-07-10

*Add, Edit Work UI Style*

### Added

* Added Cherry/Black theme options for Add/Edit form pages

## 1.6.1 - 2026-07-08

*Options UI Style*

### Added

* Added section header bands, switch controls, and cleaner mobile Field Layout controls

### Changed

* Restyled Options and Refetch pages to match the compact Index and Tag Library theme

## 1.6.0 - 2026-07-06

*Tag Library Mobile View*

### Fixed

* Improved Tag Library edit modal sizing on mobile so it keeps top spacing and scrolls within the viewport

## 1.5.9 - 2026-07-05

*Tag Layouts*

### Added

* Added separate Custom Tags and Fetched EN Tags visibility controls for the Index Tags column

### Changed

* Split Edit Form tag layout into independently orderable Custom Tags and Fetched EN Tags rows, with only Custom Tags editable by default
* Renamed Quick Add and Custom Quick Add tag rows to Custom Tags

## 1.5.8 - 2026-07-04

*Split Description Layouts*

### Changed

* Split configurable Japanese and English Description fields across Index, Filter, Edit, Quick Add, and Custom Quick Add layouts

### Fixed

* Removed extra padding at the bottom on mobile in Edit/Quick Add

## 1.5.7 - 2026-07-03

*Code Cleanup*

### Changed

* HTML, CSS Code Cleanup

## 1.5.6 - 2026-07-03

*Tooltip Tap Support*

### Added

* Added click/tap popup fallback for title tooltips on mobile and desktop

## 1.5.5 - 2026-07-03

*Index tag ordering Fix*

### Fixed

* Fixed Index group ordering so grouped tags keep saved group/tag order while ungrouped tags sort alphabetically

## 1.5.4 - 2026-07-02

*Tag Colors & Edit Form Update*

### Added

* Added tag and tag-group background/font colors with configurable display surfaces for Index, Tag Library, autocomplete, Edit readonly tags, and Refetch review

### Changed

* Improved Edit/Add form metadata layout with compact Age, placeholders, readonly div-style fields, configurable readonly titles, and tighter tag help icons
* Update related docs, tests

## 1.5.3 - 2026-06-25

*Index Search & Layout Polish*

### Added

* Added Index Search option to allow general search through hidden descriptions

### Changed

* Renamed and reordered Field Layouts sections for clearer Index/Form grouping
* Changed general Index search to ignore descriptions while the Description column is hidden
* Replaced contributor sort aggregate raw SQL with query-builder ordering

### Fixed

* Fixed Index footer not being pinned to the bottom in mobile view

## 1.5.2 - 2026-06-24

*Tag Groups*

### Added

* Added Tag Groups any-hidden-group Index hiding and ordering
* Added Edit Tags mode with a tag settings modal for Index visibility and group assignments

### Changed

* Aligned Index and Tag Library tag ordering with tag-group relationship ordering

## 1.5.1 - 2026-06-10

*Tag Library Management*

### Added

* Added Livewire tag search, manual empty tag creation, collapsed all-tags display, and empty-tag deletion with confirmation
* Added an Options setting to open Tag Library with all tags collapsed or shown by default

## 1.5.0 - 2026-06-10

*Tag Library UI Style*

### Changed

* Refreshed Tag Library with a self-contained Index-aligned chip directory layout and style

## 1.4.9 - 2026-06-10

*Options Page Tabs*

### Changed

* Split Options into General and Field Layouts tabs

## 1.4.8 - 2026-06-09

*Code Simplifications*

### Changed

* Cast product sample images as arrays and simplified product create/factory/test handling
* Consolidated field-layout option default resets through a shared surface map

## 1.4.7 - 2026-06-09

*Tests Cleanup*

### Changed

* Tests Cleanup

## 1.4.6 - 2026-06-05

*Index Mobile Polish*

### Changed

* Cleaned up mobile Index card field widths so short optional fields share rows and long fields stay full-width

## 1.4.5 - 2026-06-05

*Configurable Filter Sort*

### Added

* Added configurable Advanced Filter sort dropdown order/visibility
* Added hidden-by-default Index notes/listening columns, date range filters, and contributor/timestamp sort options

### Changed

* Changed default test Performance Iterations from 3 to 5 for better statistics

## 1.4.4 - 2026-06-05

*Field Layout Metadata Cleanup*

### Changed

* Consolidated configurable field layout surface metadata in `ProductField`

## 1.4.3 - 2026-06-03

*Code Cleanup*

### Changed

* Simplified product form submitted-field checks, readonly edit values, index filters, layout reordering, and refetch progress counts with Laravel and Livewire helpers
* Updated focused tests for the shared field-layout reorder handler

## 1.4.2 - 2026-06-02

*Contributor Info, Customizable Layouts and Performance*

### Added

* Added normalized contributor info
* Added configurable field layouts for Index, Edit, Filter, Quick Add, and Custom Quick Add. Added Options controls for them.

### Changed

* Optimized Index rendering with batched option reads, conditional relation loading, inline cells, prepared tag links, and narrower product hydration
* General cleanup, and updated tests/docs

## 1.4.1 - 2026-05-30

*UI Polish*

### Added

* Added Options page timings to performance smoke coverage

### Fixed

* Fixed single-value autocomplete reopening after selection and smoothed mobile menu closing
* Small fixes

## 1.4.0 - 2026-05-29

*Autocomplete*

### Added

* Added database-backed Danbooru-style autocomplete for tag and series fields
* Added reusable autocomplete CSS/JS for Index, Create, and Edit fields
* Added Options controls for tag and series autocomplete ordering

### Changed

* Updated tests and docs

### Fixed

* Small fixes

## 1.3.9 - 2026-05-29

*Enum Validation*

### Changed

* Tightened product form validation with enum-backed rules for progress, score, priority, and re-listen value
* Updated tests and docs

## 1.3.8 - 2026-05-26

*Refetch Cancellation and Fixes*

### Added

* Added a Cancel Refetch action for running Refetch Tags batches

### Changed

* Updated tests and docs

### Fixed

* Preserved the Create page Go Back target after DLSite scraper validation errors

## 1.3.7 - 2026-05-26

*Tag Identity Key*

### Changed

* Moved genre uniqueness from display titles to case-insensitive `title_key` values
* Kept Hiragana/Katakana tag variants distinct so both kana forms can coexist on one work
* Updated Refetch Tags comparisons, tests, and docs for the new tag identity rule

## 1.3.6 - 2026-05-24

*Tag Edit Toggle*

### Added

* Added an Options toggle for editing fetched English tags from Edit Work

## 1.3.5 - 2026-05-24

*Multilingual Tag Sync*

### Added

* Added Refetch Tags review controls for new tags, stale tags, and custom-to-fetched overlaps

### Changed

* Reworked genre language handling so one tag title can be attached as fetched JP, fetched EN, or custom per product
* Updated Index, Edit, Tag Library, and Refetch Tags to show English/custom-visible tags while preserving JP-only fetched tags
* Simplified shared genre sync and visible-tag query logic with expanded tests and documentation
* Cleaned up CSS, HTML

## 1.3.4.1 - 2026-05-23

*Docker Tests*

### Added

* Added "--rm", so docker test container automatically removed after tests

## 1.3.4 - 2026-05-23

*Docker Tests and Scraper Process Cleanup*

### Added

* Added a shared DLSite Python runner using Laravel Process for scraper and tag-fetcher execution
* Added Docker test runner support with an isolated MySQL test database

### Changed

* Centralized genre sync payload creation with fetched-over-custom precedence
* Expanded tests and updated project documentation

## 1.3.3 - 2026-05-22

*CSS, HTML Cleanup*

### Changed

* Cleaned up unused CSS classes, HTML code
* Moved CSS used both in Options and Tag Library to it's own CSS file
* Renamed some CSS classes for better clarity

## 1.3.2 - 2026-05-19

*Performance Improvements*

### Added

* Added heavier performance smoke coverage for Index, update redirect, and delete redirect workflows
* Added redirect tests for saved-page fast paths, full-query visibility shortcuts, unchanged visibility updates, stale return cleanup, and custom tag changes

### Changed

* Optimized update return redirects with saved-page and full-query visibility fast paths

## 1.3.1 - 2026-05-18

*Workflow Refinements*

### Changed

* Reworked create/edit/delete return navigation around index-only return state, stable create back links, visible-work anchors, and delete page fallback
* Centralized index query keys and visibility filter groups in `ProductIndexFilters`
* Simplified selected-work search, refetch tag comparison, product index queries, and return-target helper code
* Logged destroy file-cleanup failures without blocking product deletion
* Hardened malformed create back-link input and expanded edge-case coverage for return navigation, destroy cleanup, and refetch tag diffs
* Cleaned up CSS, tests, and project docs

## 1.3.0 - 2026-05-14

*Livewire Index pagination and Sorting*

### Added

* Added configurable Index page size in Options, including fixed, custom, and unlimited modes

### Changed

* Rebuilt the Index page around Livewire filters, sorting, URL state, and pagination
* Moved RJ and partial date sorting to stored SQL sort keys and cleaned up related Index/filter code
* Changed footer text
* Updated tests and docs

## 1.2.9.1 - 2026-05-03

*Cleanup*

### Added

* Added description for refetch tags

### Changed

* CSS Cleanup

## 1.2.9 - 2026-04-26

*Python fix*

### Fixed

* Python fix for non Japanese systems

## 1.2.8 - 2026-04-26

*Docker fix*

### Fixed

* Changed index.css case

## 1.2.7 - 2026-04-26

*Options and Refetch Tags*

### Added

* Added an Options page with a "Refetch Tags" workflow
* Added Livewire
* Added database queue and batch support, Docker queue worker configuration, and worker setup docs

## 1.2.6 - 2026-04-25

*Custom RJ work creation and Fixes*

### Added

* Added a Custom Create mode for manually adding RJ works without running the scraper

### Changed

* Updated validation, upload styling, tests, migrations, and project docs for the new custom work flow

### Fixed

* Fixed user-entered custom tags. Now they stay editable in Edit Work, even when they reuse an existing fetched genre title

## 1.2.5 - 2026-03-30

*CSS and Tag Library improvements*

### Added

* Added simple CSS/JS cache busting in Blade via `filemtime(public_path(...))`
* Tag Library now shows how many works use each visible tag

### Fixed

* Improved mobile advanced-filter actions so Apply and Clear stay reachable on browsers with changing bottom UI

## 1.2.4 - 2026-03-30

*Fixes and Optimizations*

### Changed

* Improved index filter handling and cleaned up Blade templates
* Clicking on "Series" now opens all works from that series and resets the other index filters
* Improved create/edit return navigation without relying on raw redirect URLs
* Cleaned up create/edit form field components and enum-backed select handling
* Switched additional create/edit date validation to Laravel 12 form request `after()` hooks
* Changed "Age" placeholder to "All Works" in Advanced Filters
* Updated tests and project docs for the current behavior
* Removed the unused Laravel Excel dependency

## 1.2.3 - 2026-03-26

*Fixes*

### Changed

* Added padding for advanced options on mobile devices
* Reordered labels in ProductScore.php
* Updated docker logic

## 1.2.2 - 2026-03-26

*Docker Compose and fixes*

### Added

* Added docker-compose Quick Start

### Fixed

* Fixed python save paths for Unix systems
* Fixed index css file name related error

## 1.2.1 - 2026-03-16

*Query optimization*

### Changed

* Simplified edit genre loading to use the same lightweight-query approach for fetched EN/custom tags

## 1.2.0 - 2026-03-16

*Mobile version*

### Added

* Added mobile layouts for index, create, and edit views
* Added a mobile slide-in version of the shared floating menu
* Added an advanced server-side filter/sort modal for the index page

### Changed

* Refactored index filtering into enums, a typed filter object, model scopes, and Blade components
* Progress tabs now drop the current genre filter when switching list status

## 1.1.1 - 2026-03-16

*Query optimization*

### Changed

* Simplified index tag loading to use one lightweight grouped query for visible EN/custom genres instead of eager-loading genre relations

## 1.1.0 - 2026-03-16

*Tag Library*

### Added

* Added Tag Library page with clickable genre links

### Changed

* Moved genres to `genres` + `genre_product` with Genre/GenreGroup models and migration from legacy product genre JSON fields
* Updated list/index filtering and search to use related genres, and show English/custom genre titles from the genre library
* Updated create/edit genre flow so fetched JP/EN genres stay attached, while added titles reuse existing genres when possible
* Extracted the floating side menu into a shared Blade component + CSS file

## 1.0.7 - 2026-02-17

*Notes Search*

### Added

* Search now also include user notes

## 1.0.6 - 2026-02-17

*Docs and Tags*

### Added

* Added project docs
* Added ability to add tags that have comma inside

## 1.0.5 - 2026-02-17

*Tests and Cleanup*

### Added

* Added ProductFactory
* Added tests

### Changed

* Changed default mysql engine to InnoDB

### Fixed

* Cleanup and Fixes

## 1.0.4 - 2026-01-26

*Listening fields and Form Refactor*

### Added

* Added listening fields to products (start/end dates, re-listen times/value, priority)
* Added migration for listening fields
* Added create/edit UI for listening fields with validation (including date order)

### Changed

* Refactored create/edit forms into reusable field components
* Improved request validation/normalization and RJ uniqueness

## 1.0.3 - 2026-01-26

*CSS Cleanup*

### Changed

* Further Index.blade.php and Create.blade.php cleanup

## 1.0.2 - 2026-01-26

*CSS Cleanup*

### Changed

* Index CSS cleanup
* Edit CSS cleanup

## 1.0.1 - 2026-01-26

*CSS Cleanup*

### Changed

* Index CSS cleanup

## 1.0.0 - 2026-01-23

*Python venv and Improvements*

### Added

* Added error message in cases of: inputting wrong RJ Code, RJ Geo-blocking orx Deleted work

### Changed

* Moved python modules to .venv
  * Adjasted python logic accordingly

* Imporved controller store logic
* Improved controller scrape logic

## 0.9.1 - 2026-01-23

*Composer version*

### Changed

* Updated composer api version to 2.6.0

## 0.9.0 - 2026-01-23

*Laravel 12*

### Changed

* Project updated to Laravel 12
* Update with "composer update --with-all-dependencies"

## 0.8.4 - 2026-01-19

*Rating change*

### Changed

* Changed rating words to sound less harsh

## 0.8.3 - 2026-01-01

*Japanese title change*

### Added

* Ability to change Japanese title

### Changed

* Better validation

## 0.8.2 - 2025-09-12

*Image download*

### Changed

* Image download retry and validation
* Changed default progress to "Plan to Listen"

## 0.8.1 - 2025-09-02

*Search*

### Added

* Added search to index page

## 0.8.0 - 2025-08-23

*Redirect and fixes*

### Added

* Added redirect
  * After editing, return to the same page and scroll to the edited work
  * "Go back" link added to Edit page
  * Redirect progress filter is updated only when progress value changes

### Changed

* Moved scripts and to own files
* Changed sort order to "desc"

### Fixed

* Product model fix

## 0.7.0 - 2025-08-23

*Sorting for columns and fixes*

### Added

* Added sorting for *Score column (numeric 1–10, "-" treated as 0)
* Added sorting for *Title column by RJ id (e.g., RJ123456)

### Changed

* Updated sort icons to show current order (⇅, ↑, ↓)

* Improved default sorting behavior for non-special columns

### Fixed

* Fixed series addition on work creation
* Removed ',' after custom tag being a link

## 0.6.0 - 2025-08-17

*Display by series and fixes*

### Added

* Button for displaying all works
* Series column and display by series

### Changed

* New lines in notes

### Fixed

* Removed Uppercase of tags (fixed diplay by tags)

## 0.5.0 - 2025-08-16

*Display by Age, Tags*

### Added

* Added custom Sakura image
* Added display by age, tags

### Changed

* Default sort by id "RJ"
* Create page changes
* Index page changes and fixes

## 0.4.2 - 2025-08-15

*Custom favicon.ico*

### Added

* Added favicon.ico

## 0.4.1 - 2025-08-15

*Python fix*

### Fixed

* Fixed modules path

## 0.4.0 - 2025-08-15

*Local Images and Cleanup*

### Added

* Save images locally
* Cleanup after work removal from database

## 0.3.0 - 2025-08-06

*Progress Pages*

### Added

* Added different progress for Asmr pagers
* Added notes to titles
* Delete confirmation
* Check for duplicate works
* Accept RJ Code or link

### Fixed

* Fixes

## 0.2.0 - 2025-04-17

*Editor*

### Added

* Editor
* CRUD

## 0.1.0 - 2025-04-15

*DLSite Scraper*

### Added

* DLSite Scraper
* Views
* Database

## 0.0.1 - 2025-03-11

*Project start*
