# Testing

## Scope
Current automated coverage is focused on Laravel PHPUnit tests:
- Feature tests in `tests/Feature`

There are currently no project-owned Python tests.

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

## Running Tests
- Run all tests:
  - `php artisan test`