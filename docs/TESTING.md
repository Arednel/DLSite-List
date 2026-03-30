# Testing

## Scope
Current automated coverage is in Laravel PHPUnit tests:
- `tests/Feature/ProductControllerTest.php`
  - covers index filtering/sorting, create/edit pages, validation, update flow, and destroy flow
- `tests/Feature/ProductGenreMigrationTest.php`
  - covers migration of legacy product genre JSON into `genres` + `genre_product`
- `tests/Unit/Support/ProductIndexFiltersTest.php`
  - covers query normalization, defaults, and query export helpers
- `tests/Unit/Support/ReturnTargetTest.php`
  - covers return-route normalization, index-only query/fragment handling, and URL generation
- `tests/Unit/View/Components/Fields/EnumSelectFieldTest.php`
  - covers enum-backed field component defaults and option maps
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

## Running Tests
- Run all tests:
  - `php artisan test`
- Run a filtered subset:
  - `php artisan test --filter=ProductControllerTest`
