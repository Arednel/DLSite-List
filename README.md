<h2 align="center">DLSite List</h2>

DLSite List is a single-user, self-hosted personal DLsite library for organizing your collection, inspired by [MyAnimeList](https://myanimelist.net)'s "Cherry Blossom" theme.

It supports DLsite works with RJ, BJ, and VJ codes - Voice / ASMR, Games, Manga, Music, etc.

## Features

### ☰ Library Management

- **Add works:** Fetch metadata from DLsite, import multiple DLsite works at once, or create custom entries with your own details and images.
- **Edit works:** Edit work information and add details like current status, score, start and finish dates, priorities, and re-listens.
- **Tag Library:** Add your own tags and organize them into groups. Add parent/child relationships, customize tag and group colors, control visibility, and change their order on the Index.
- **Search and Filter:** Search, filter, and sort your library.
- **Image Viewer:** View saved covers and sample images.

### ⇄ Updates & Transfers

- **Refetch:** Check DLsite for updated metadata, covers, and sample images, and review changes before applying them.
- **Import and Export:** Export and Import your entire library or selected works, images, Tag Library data, and Options using multipart ZIP files. Review imported changes before applying them.

### ⚙ Customization & Settings

- **Display options:** Customize pagination, search behavior, table width, columns, and how Notes and Tags are displayed.
- **Field layouts:** Configure field visibility and order for filters, sort menus, add and edit forms.
- **UI Wording:** Choose general or content-focused UI wording for listening, reading, games, video, music, and artwork.
- **Authentication:** Optional single-user authentication.
- **Languages:** English and Japanese UI with separate fetched tags for each language. The Japanese UI is mostly auto-translated, so quality may vary.

## Screenshots

### Index

<p align="center">
  <a href="docs/screenshots/index_1.png"><img src="docs/screenshots/index_1.png" alt="DLSite List Index 1" width="31%"></a>
  <a href="docs/screenshots/index_2.png"><img src="docs/screenshots/index_2.png" alt="DLSite List Index 2" width="31%"></a>
  <a href="docs/screenshots/index_3.png"><img src="docs/screenshots/index_3.png" alt="DLSite List Index 3" width="31%"></a>
</p>

### Quick Add, Options, Tag Library

<p align="center">
  <a href="docs/screenshots/quick_add_1.png"><img src="docs/screenshots/quick_add_1.png" alt="Quick Add" width="31%"></a>
  <a href="docs/screenshots/options_1.png"><img src="docs/screenshots/options_1.png" alt="Options" width="31%"></a>
  <a href="docs/screenshots/tag-library_1.png" target="_blank"><img src="docs/screenshots/tag-library_1.png" alt="Tag Library" width="31%"></a>
</p>

## Quick Start (requires [Docker Compose 2.34.0+](https://docs.docker.com/compose))

### 1) Run this command

```bash
docker compose -f oci://ghcr.io/arednel/dlsite-list:latest up -d -y
```

### 2) After startup

- DLSite List is available at: `http://localhost:8080`
- Authentication is disabled by default. To add username and password, open `Options -> Authentication` and turn on `Require administrator login`.

## Manual Installation Process

### Requirements

- PHP 8.3
- A web server configured to run PHP, for example Apache or Nginx
- Composer
- MySQL 8
- [Python 3.14.6](https://www.python.org/downloads/release/python-3146) (tested with this version) and [pip](https://pypi.org/project/pip)

Manual installation is tested with WAMP on Windows. Follow the [manual installation guide](docs/CONFIGURATION.md#local--manual-setup) for requirements and setup instructions.

## Running Tests

Create `.env.testing` from `.env.testing.example`, set test DB credentials, then run:

```bash
php artisan test
```

To run the test suite inside Docker with a dedicated test database:

```bash
docker compose --env-file docker/.env.docker -f compose.dev.yaml --profile test run --rm --build tests
```

## Documentation

- [Configuration](docs/CONFIGURATION.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Testing](docs/TESTING.md)

## Contributing

Contributions are very welcome.
To learn more, see the [Contributor Guide](CONTRIBUTING.md).

## License

Distributed under the terms of the [MIT License](LICENSE), _DLSite List_ is free and open-source software.

## Issues

If you encounter any problems, please [file an issue](https://github.com/Arednel/DLSite-List/issues) along with a detailed description.

## Acknowledgements

- [bhrevol/dlsite-async](https://github.com/bhrevol/dlsite-async) — used to retrieve DLsite work metadata.
