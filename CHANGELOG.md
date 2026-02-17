# Change Log

## Development

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
  * * Adjasted python logic accordingly
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
