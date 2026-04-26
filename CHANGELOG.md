# Change Log

## Development

2026-04-26 -- 1.2.9 Python fix
  * Python fix for non Japanese systems

2026-04-26 -- 1.2.8 Docker fix
  * Changed index.css case

2026-04-26 -- 1.2.7 Options and Refetch Tags
  * Added an Options page with a "Refetch Tags" workflow
  * Added Livewire
  * Added database queue and batch support, Docker queue worker configuration, and worker setup docs

2026-04-25 -- 1.2.6 Custom RJ work creation and Fixes
  * Added a Custom Create mode for manually adding RJ works without running the scraper
  * Fixed user-entered custom tags. Now they stay editable in Edit Work, even when they reuse an existing fetched genre title
  * Updated validation, upload styling, tests, migrations, and project docs for the new custom work flow

2026-03-30 -- 1.2.5 CSS and Tag Library improvements
  * Tag Library now shows how many works use each visible tag
  * Improved mobile advanced-filter actions so Apply and Clear stay reachable on browsers with changing bottom UI
  * Added simple CSS/JS cache busting in Blade via `filemtime(public_path(...))`

2026-03-30 -- 1.2.4 Fixes and Optimizations
  * Removed the unused Laravel Excel dependency
  * Improved index filter handling and cleaned up Blade templates
  * Clicking on "Series" now opens all works from that series and resets the other index filters
  * Improved create/edit return navigation without relying on raw redirect URLs
  * Cleaned up create/edit form field components and enum-backed select handling
  * Switched additional create/edit date validation to Laravel 12 form request `after()` hooks
  * Changed "Age" placeholder to "All Works" in Advanced Filters
  * Updated tests and project docs for the current behavior

* 2026-03-26 -- 1.2.3 Fixes
  * Added padding for advanced options on mobile devices
  * Reordered labels in ProductScore.php
  * Updated docker logic

* 2026-03-26 -- 1.2.2 Docker Compose and fixes
  * Added docker-compose Quick Start
  * Fixed python save paths for Unix systems
  * Fixed index css file name related error

* 2026-03-16 -- 1.2.1 Query optimization
  * Simplified edit genre loading to use the same lightweight-query approach for fetched EN/custom tags

* 2026-03-16 -- 1.2.0 Mobile version
  * Added mobile layouts for index, create, and edit views
  * Added a mobile slide-in version of the shared floating menu
  * Added an advanced server-side filter/sort modal for the index page
  * Refactored index filtering into enums, a typed filter object, model scopes, and Blade components
  * Progress tabs now drop the current genre filter when switching list status

* 2026-03-16 -- 1.1.1 Query optimization
  * Simplified index tag loading to use one lightweight grouped query for visible EN/custom genres instead of eager-loading genre relations

* 2026-03-16 -- 1.1.0 Tag Library
  * Moved genres to `genres` + `genre_product` with Genre/GenreGroup models and migration from legacy product genre JSON fields
  * Updated list/index filtering and search to use related genres, and show English/custom genre titles from the genre library
  * Updated create/edit genre flow so fetched JP/EN genres stay attached, while added titles reuse existing genres when possible
  * Added Tag Library page with clickable genre links 
  * Extracted the floating side menu into a shared Blade component + CSS file

* 2026-02-17 -- 1.0.7 Notes Search
  * Search now also include user notes

* 2026-02-17 -- 1.0.6 Docs and Tags
  * Added project docs
  * Added ability to add tags that have comma inside

* 2026-02-17 -- 1.0.5 Tests and Cleanup
  * Cleanup and Fixes
  * Changed default mysql engine to InnoDB
  * Added ProductFactory
  * Added tests

* 2026-01-26 -- 1.0.4 Listening fields and Form Refactor
  * Added listening fields to products (start/end dates, re-listen times/value, priority)
  * Added migration for listening fields
  * Added create/edit UI for listening fields with validation (including date order)
  * Refactored create/edit forms into reusable field components
  * Improved request validation/normalization and RJ uniqueness

* 2026-01-26 -- 1.0.3 CSS Cleanup
  * Further Index.blade.php and Create.blade.php cleanup

* 2026-01-26 -- 1.0.2 CSS Cleanup
  * Index CSS cleanup
  * Edit CSS cleanup

* 2026-01-26 -- 1.0.1 CSS Cleanup
  * Index CSS cleanup

* 2026-01-23 -- 1.0.0 Python venv and Improvements
  * Moved python modules to .venv
    * Adjasted python logic accordingly
  * Added error message in cases of: inputting wrong RJ Code, RJ Geo-blocking orx Deleted work
  * Imporved controller store logic
  * Improved controller scrape logic

* 2026-01-23 -- 0.9.1 Composer version
  * Updated composer api version to 2.6.0

* 2026-01-23 -- 0.9.0 Laravel 12
  * Project updated to Laravel 12
  * Update with "composer update --with-all-dependencies"

* 2026-01-19 -- 0.8.4 Rating change
  * Changed rating words to sound less harsh

* 2026-01-01 -- 0.8.3 Japanese title change
  * Ability to change Japanese title
  * Better validation

* 2025-09-12 -- 0.8.2 Image download
  * Image download retry and validation
  * Changed default progress to "Plan to Listen"

* 2025-09-02 -- 0.8.1 Search
  * Added search to index page

* 2025-08-23 -- 0.8.0 Redirect and fixes
  * Added redirect
    * After editing, return to the same page and scroll to the edited work
    * "Go back" link added to Edit page
    * Redirect progress filter is updated only when progress value changes
  * Moved scripts and to own files
  * Product model fix
  * Changed sort order to "desc"

* 2025-08-23 -- 0.7.0 Sorting for columns and fixes
  * Removed ',' after custom tag being a link
  * Fixed series addition on work creation
  * Added sorting for **Score** column (numeric 1–10, "-" treated as 0)
  * Added sorting for **Title** column by RJ id (e.g., RJ123456)
  * Updated sort icons to show current order (⇅, ↑, ↓)
  * Improved default sorting behavior for non-special columns

* 2025-08-17 -- 0.6.0 Display by series and fixes
  * Removed Uppercase of tags (fixed diplay by tags)
  * Button for displaying all works
  * Series column and display by series
  * New lines in notes

* 2025-08-16 -- 0.5.0 Display by Age, Tags
  * Default sort by id "RJ"
  * Create page changes
  * Index page changes and fixes
  * Added custom Sakura image
  * Added display by age, tags

* 2025-08-15 -- 0.4.2 Custom favicon.ico
  * Added favicon.ico

* 2025-08-15 -- 0.4.1 Python fix
  * Fixed modules path

* 2025-08-15 -- 0.4.0 Local Images and Cleanup
  * Save images locally
  * Cleanup after work removal from database 

* 2025-08-06 -- 0.3.0 Progress Pages
  * Added different progress for Asmr pagers
  * Added notes to titles
  * Delete confirmation
  * Check for duplicate works
  * Accept RJ Code or link
  * Fixes

* 2025-04-17 -- 0.2.0 Editor
  * Editor
  * CRUD

* 2025-04-15 -- 0.1.0 DLSite Scraper
  * DLSite Scraper
  * Views
  * Database

* 2025-03-11 -- 0.0.1 Project start
  * Project start
