<h2 align="center">DLSite List</h2>

Personal DLSite library for organizing your collection, inspired by [MyAnimeList](https://myanimelist.net)

## Quick Start (requires [Git](https://git-scm.com) and [Docker Compose](https://docs.docker.com/compose))

### 1) Run those commands

```bash
git clone https://github.com/Arednel/DLSite-List.git

cd DLSite-List

docker compose --env-file docker/.env.docker up --build -d
```

### 2) After startup
- DLSite List available at: `http://localhost:8080`
- phpMyAdmin available at: `http://localhost:8888` (uncomment in compose.yaml, disabled for security)

## Manual installation process

### Requirements
- PHP 8.3
- Composer
- MySQL 8
- [Python 3.14.6](https://www.python.org/downloads/release/python-3146) (tested with this version) and [pip](https://pypi.org/project/pip)

### 1) Create `.env` from `.env.example` then run from the project root:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
```

### 2) Create and activate the venv:
```bash
python -m venv python/venv
```

Activate it with:
- Windows: `python\venv\Scripts\activate`
- Linux/macOS: `source python/venv/bin/activate`

### 3) Install Python packages:

```bash
pip install -r python/requirements.txt
```

### 4) Run workers
```bash
php artisan queue:work
```

## Running tests
Create `.env.testing` from `.env.testing.example`, set test DB credentials, then run:

```bash
php artisan test
```

To run the test suite inside Docker with a dedicated test database:

```bash
docker compose --env-file docker/.env.docker --profile test run --rm --build tests
```

## Additional docs
- [Configuration](docs/CONFIGURATION.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Testing](docs/TESTING.md)

## Contributing
Contributions are very welcome.

## License

Distributed under the terms of the [MIT License](LICENSE), _DLSite List_ is free and open-source software.

## Issues
If you encounter any problems, please [file an issue](https://github.com/Arednel/DLSite-List/issues) along with a detailed description.

## Acknowledgements

- [bhrevol/dlsite-async](https://github.com/bhrevol/dlsite-async) — used to retrieve DLSite work metadata.
