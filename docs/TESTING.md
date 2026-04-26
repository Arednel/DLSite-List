# Testing

## Scope
Current automated coverage is in Laravel PHPUnit tests:
- `tests/Feature/ProductControllerTest.php`
  - covers index filtering/sorting, create/edit pages, custom create/upload flow, editable custom tag source behavior, index image selection, validation, update flow, and destroy flow
- `tests/Feature/ProductGenreMigrationTest.php`
  - covers migration of legacy product genre JSON into `genres` + `genre_product`, including pivot source values
- `tests/Feature/OptionsControllerTest.php`
  - covers the Options page, latest-refetch link, Refetch Tags request validation, queue batch creation, selected/all work scopes, progress JSON, tags-only job results, skipped errors/custom-only works, review rendering/change indicators, newest-run-only apply controls, and apply behavior for stale tag move/remove choices
- `tests/Feature/OptionsRefetchProgressTest.php`
  - covers the Livewire refetch progress panel polling only while a run is active and redirecting once review results are ready
- `tests/Feature/OptionsWorkSearchTest.php`
  - covers the Livewire selected-work search and selected product preservation when filtered results change
- `tests/Unit/Support/ProductIndexFiltersTest.php`
  - covers query normalization, defaults, and query export helpers
- `tests/Unit/Support/ReturnTargetTest.php`
  - covers return-route normalization, index-only query/fragment handling, and URL generation
- `tests/Unit/View/Components/Fields/EnumSelectFieldTest.php`
  - covers enum-backed field component defaults and option maps
- `tests/Unit/Models/TagRefetchStateTest.php`
  - covers refetch run/result state helper methods used by Blade and controller code, including run summaries and result change-bucket helpers
- `tests/Unit/ExampleTest.php`
  - contains the default baseline unit test

There are no project-owned Python tests.

## Test Environment Setup
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

Livewire component tests use `Livewire::test()` to update component state without a browser.

## Running Tests
- Run all tests:
  - `php artisan test`
- Run a filtered subset:
  - `php artisan test --filter=ProductControllerTest`
  - `php artisan test --filter=OptionsControllerTest`
  - `php artisan test --filter=OptionsRefetchProgressTest`
  - `php artisan test --filter=OptionsWorkSearchTest`
