# Configuration

## Environment Files
- `.env`: local/dev runtime
- `.env.example`: template for `.env`
- `.env.testing`: test runtime
- `.env.testing.example`: template for `.env.testing`
- `docker/.env.docker`: Docker Compose runtime for `app`, `database`, and `pma` containers
- `docker/.env.testing.docker`: Docker Compose runtime for the one-off `tests` service

## Simple Docker Setup
Run from the project root:
- `docker compose --env-file docker/.env.docker up --build`

This path:
- builds the PHP 8.3 app image from `docker/app.dockerfile`
- installs Composer dependencies and the Python scraper venv inside the app image
- builds the Nginx image from `docker/web.dockerfile`
- starts MySQL 8 and phpMyAdmin
- keeps the Docker test services behind the `test` Compose profile
- runs `php artisan migrate` through `docker/docker-app-entrypoint.sh`
- serves `/storage/*` directly from Nginx via `docker/vhost.conf`, so `php artisan storage:link` is not required for Docker

Access points:
- App: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8888`

Docker test services are assigned to the `test` Compose profile, so they do not start during the normal app startup command.

The app image copies `docker/.env.docker` to `.env` during build because some Laravel tooling expects `base_path('.env')` to exist. Runtime values still come from the Compose `env_file` entries, including `docker/.env.testing.docker` for the one-off `tests` service.

## Required Local Setup
1. Create `.env` from `.env.example` if it does not already exist.
2. Install PHP dependencies:
   - `composer install`
3. Configure app key:
   - `php artisan key:generate`
4. Run migrations:
   - `php artisan migrate`
5. Create storage symlink:
   - `php artisan storage:link`
6. Create Python venv and install scraper dependencies:
   - `python -m venv python/venv`
   - activate venv
   - `pip install -r python/requirements.txt`

## Database Settings
Main DB settings are in:
- `.env` for local/manual runtime
- `docker/.env.docker` for Docker Compose runtime
- `docker/.env.testing.docker` for Docker Compose test runtime

Relevant variables:
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

MySQL engine is configured as InnoDB in:
- `config/database.php` (`mysql.engine`)

Tag identity uses `genres.title_key` instead of the display `genres.title` column:
- `genres.title_key` is trimmed and Unicode case-folded by PHP
- `genres.title_key` uses binary collation so kana variants stay distinct
- `genres.title` keeps the user/DLSite display casing and is not the uniqueness column

The Tag Library can create manual empty tags:
- submitting a new tag title creates a `genres` row with zero `genre_product` pivots
- duplicate input is detected through `genres.title_key`, so case-only duplicates are not created
- empty tags are searchable and can be opened as Index tag filters before any work uses them
- empty tags can be deleted from the Tag Library only while they still have zero product pivots
- attached JP-only fetched tags remain stored but hidden from Tag Library

The Tag Library can organize tags into groups:
- group titles are stored in `genre_groups.title`
- group order is stored in `genre_groups.order`
- ungrouped tag order is stored in `genres.order`
- group membership and per-group tag order are stored in `genre_group_genre`
- adding a tag to a group resolves an existing `genres.title_key` match or creates a new empty tag, then attaches that group/tag membership
- the same tag can belong to multiple groups
- adding the same tag to the same group again does not create a duplicate membership
- removing a tag from a group deletes only that membership and keeps the tag row plus any other group memberships
- deleting a group deletes only that group row and its membership rows; tag rows and other memberships remain
- `genres.hidden_on_index` hides a specific tag from Index tag chips
- `genre_groups.hidden_on_index` hides every tag assigned to that group from Index without changing each tag's own hidden setting
- Index tag chips sort alphabetically by tag title by default; enabling `Enable group ordering on Index` switches visible grouped tags to group order and saved tag order inside each group, then shows ungrouped tags alphabetically. In both modes, a tag is excluded from Index when it is directly hidden or belongs to any hidden group.
- `genres.color` and `genre_groups.color` store optional `#RRGGBB` background/accent colors. `genres.text_color` and `genre_groups.text_color` store optional independent font colors. Group background/font colors override tag background/font colors independently by the same ordered membership rules used for display; inside a specific group card, that group color value wins for whichever color value it defines.

Tag Library filters apply only to the All Tags list. They can filter by Index visibility, grouped/ungrouped state, a specific group, and empty/used state while group management sections keep showing their current members.

The All Tags list has a session-only `Edit tags` mode:
- when off, clicking a tag opens Index filtered by that tag
- when on, clicking a tag opens a tag settings modal instead of navigating
- the mode uses a switch-style toggle bound to the Livewire `tagEditMode` checkbox state
- the `Add group` field is inside the Tag Groups section header, next to group management
- `Enable group ordering on Index` is a persisted switch in the Tag Groups section and in Options; it is off by default, so saved group order affects Index tag-chip ordering only after enabling it
- tag edit modals and Tag Group cards include separate background color and font color controls, each with a color picker, manual hex input, and Clear action; empty colors use the normal default tag style
- manual color inputs use a muted `#000000` placeholder while an explicitly saved `#000000` value is shown as normal input text
- the group rows and modal use switch-style toggles for tag and group Index visibility
- Tag Library switch controls share the same `tag-library-switch-*` markup/CSS classes while keeping each native checkbox and Livewire binding intact
- the modal can search existing tag groups through a dropdown-style search field, add them as assignment plaques, and remove selected plaques before saving
- group search results are hidden until search text is entered; selected plaques and the empty selected-groups message stay below the search field
- existing memberships keep their current per-group order
- newly added memberships are appended to the end of each selected group
- Cancel, backdrop click, and Escape close the modal without persisting unsaved group plaque changes
- tags hidden directly or assigned to any hidden group show a compact red accessible status indicator inside the All Tags chip after the tag title and before the product count; group names and group-hidden state stay available through the Group filter and tag settings modal

Docker database services:
- `database` stores normal app data in the `dbdata` Docker volume
- `database_test` stores test data in the `dbdata_test` Docker volume and is used only by the `tests` service

## Queue and Scheduler
Refetch Tags runs through Laravel's database queue and job batches.

Relevant variable:
- `QUEUE_CONNECTION=database`

Required migrations create:
- `jobs`
- `job_batches`
- `tag_refetch_runs`
- `tag_refetch_work_results`

Run the queue worker from the project root while using Refetch Tags:
```bash
php artisan queue:work
```

Cancelling a Refetch Tags run is cooperative. Keep the queue worker running after pressing Cancel so the active fetch can finish and the remaining queued jobs can mark their work results as skipped.

`php artisan schedule:work` is only needed if a scheduled command is added. The project does not currently register a scheduled batch-pruning command.

## App Options
The `options` table stores app settings as scalar string values keyed by `options.key`.

Current settings:
- `index_per_page`: controls how many works the Index list renders per page
- `index_search_hidden_descriptions_enabled`: controls whether general Index search can match Japanese and English descriptions when their Index columns are hidden
- `tag_autocomplete_order`: controls how tag autocomplete suggestions are ordered
- `series_autocomplete_order`: controls how series autocomplete suggestions are ordered
- `auto_series_from_title_name`: controls whether DLSite create fills an empty Series from `japanese.title_name`
- `product_form_theme`: controls the Add Work, Add Custom Work, and Edit Work page theme. Defaults to `black`
- `tag_library_tags_expanded_by_default`: controls whether Tag Library opens with the full tag list shown
- `tag_library_index_group_ordering_enabled`: controls whether Index tag chips use tag group order instead of plain alphabetical title ordering
- `tag_color_surfaces`: JSON map controlling where stored tag/group background and font colors render. Defaults are `index=true`, `tag_library=true`, `autocomplete=false`, `edit_readonly=false`, and `refetch=false`.
  The Index surface keeps its color fast path inactive until at least one tag or tag group has a saved background/font color.
- `index_field_layout`: controls Index table field visibility/order
- `edit_field_layout`: controls Edit Work field visibility/order/editability
- `filter_field_layout`: controls Filter modal field visibility/order
- `quick_add_field_layout`: controls DLSite Create field visibility/order
- `custom_quick_add_field_layout`: controls Custom Create field visibility/order
- `index_sort_field_layout`: controls Advanced Filter sort value visibility/order
- `index_table_width`: controls the Index list/table width and top cover image width

Runtime note:
- `App\Models\Option` normalizes stored strings into the runtime values the app uses

Pagination default:
- `100`

Pagination built-in choices:
- `10`
- `25`
- `50`
- `100`
- `250`
- `500`
- `1000`
- `unlimited`

The General tab also accepts a custom positive integer. `unlimited` disables Index pagination and renders every matching work.

Tag editing defaults:
- Index Table Fields show one Tags column with separate Custom Tags and Fetched EN Tags visibility toggles; both buckets are visible by default
- Edit Form Fields show separate Fetched EN Tags and Custom Tags rows in that default order; both are visible by default
- Custom Tags editable: enabled by default in the Edit Form layout
- Fetched EN Tags editable: disabled by default unless its Edit Form row enables it

When Fetched EN Tags editing is enabled, Edit Work allows changing the fetched English tag bucket. Japanese-only fetched tags remain stored but hidden.

Automatic Series from DLSite `title_name` default:
- enabled

When enabled, DLSite create fills Series from `japanese.title_name` only if the Series field is empty. Manually entered Series values win. Custom create does not use this option.

Product form theme default:
- `black`

Product form theme choices:
- `cherry`: uses the same warm Cherry palette as Index, Tag Library, and Options
- `black`: preserves the previous dark Add/Edit form style

Index field layout default order:
- `image`
- `title` locked visible
- `score`
- `series`
- `age_category`
- `progress`
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description_japanese` hidden by default
- `description_english` hidden by default
- `tags` with Custom Tags and Fetched EN Tags visible by default
- `notes` hidden by default; Notes are already shown inside Title, and this row enables a separate column
- `start_date` hidden by default
- `end_date` hidden by default
- `num_re_listen_times` hidden by default
- `re_listen_value` hidden by default
- `priority` hidden by default

Edit form field layout default order:
- `progress`
- `score`
- `series`
- `title` locked visible
- `fetched_english_tags` shown as Fetched EN Tags
- `tags` shown as Custom Tags
- `notes`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `age_category` hidden by default
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description_japanese` hidden by default
- `description_english` hidden by default

Filter modal field layout default order:
- `title`
- `score`
- `series`
- `age_category`
- `progress`
- `notes`
- `priority`
- `num_re_listen_times`
- `re_listen_value`
- `tags` shown as Custom Tags
- `start_date` hidden by default
- `end_date` hidden by default
- `created_at` hidden by default
- `updated_at` hidden by default
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description_japanese` hidden by default
- `description_english` hidden by default

Quick Add field layout default order:
- `rj_code` locked visible
- `progress`
- `score`
- `series`
- `title`
- `tags` shown as Custom Tags
- `notes`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `age_category` hidden by default
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description_japanese` hidden by default
- `description_english` hidden by default

Custom Quick Add field layout default order:
- `rj_code` locked visible
- `progress`
- `score`
- `series`
- `title` locked visible
- `tags`
- `notes`
- `age_category` locked visible
- `image` locked visible
- `sample_images`
- `start_date`
- `end_date`
- `num_re_listen_times`
- `re_listen_value`
- `priority`
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default
- `description_japanese` hidden by default
- `description_english` hidden by default

Index sort field dropdown default order:
- `rj`
- `score`
- `series`
- `age_category`
- `progress`
- `priority`
- `num_re_listen_times`
- `re_listen_value`
- `start_date`
- `end_date`
- `created_at`
- `updated_at` hidden by default
- `circle` hidden by default
- `scenario` hidden by default
- `illustration` hidden by default
- `voice_actor` hidden by default
- `author` hidden by default

The Index Table Fields, Index Filter Fields, Edit Form Fields, Quick Add Form Fields, and Custom Quick Add Form Fields sections each store their own layout JSON in `options.value`. Rows can be reordered by dragging the row handle or by using the Up/Down buttons, and changes are persisted when Save is submitted. Field settings are keyed by field id while editing, so reordering rows does not change checkbox state. Unknown or duplicate field ids are ignored and missing known fields fall back to the surface default order. Index `title` is always visible but can still be reordered. Edit Form `title` is also locked visible and represents the Japanese/English title inputs after the fixed RJ Code + Title display row. Quick Add keeps `rj_code` locked visible. Custom Quick Add keeps `rj_code`, `title`, `age_category`, and `image` locked visible. In Index Table Fields, `tags` keeps one column/order row but stores separate Custom Tags and Fetched EN Tags visibility flags. In Edit Form Fields, Custom Tags use the `tags` row and Fetched EN Tags use the separate `fetched_english_tags` row so they can be ordered, shown, hidden, and made editable independently. Index Filter Fields and Index Sort Menu do not split Tags.

The Index Sort Menu section appears after Index Filter Fields and uses the same Options row controls to reorder and show/hide values in the Advanced Filter sort dropdowns. It only changes the dropdown presentation: valid URL sort state and sortable visible table columns keep sorting through `ProductIndexSortField`. Sortable optional Index headers include circle/creator columns, start/finish dates, total times re-listened, re-listen value, and priority when those columns are visible.

Create form layout note:
- hidden Quick Add fields are not persisted from submitted form data
- DLSite Create still keeps scraped DLSite metadata for hidden age, circle, creator, Japanese description, and English description rows
- visible DLSite Create metadata rows act as manual overrides when the user enters a value
- visible Custom Quick Add metadata rows are saved directly because custom works have no scraped fallback; hidden custom description language rows save `null`

Index table width default:
- `default`

Index table width choices:
- `default`: current 1024px list width
- `wide`: 1400px list width
- `full`: 100% of the available page width
- custom CSS length or percentage, for example `1600px`, `90%`, `80vw`, `72rem`, or `64em`

This width is applied to the Index list/table panel and the top cover image. The top cover image keeps a capped desktop height, and product row thumbnails keep their fixed list size.

Options page tabs:
- `General` is the default tab and contains Index Pagination, Index Search, Index Table Width, Series Metadata, Autocomplete, Tag Library settings, and Reset All Options
- `Field Layouts` is the second tab and contains Index Table Fields, Index Filter Fields, Index Sort Menu, Edit Form Fields, Quick Add Form Fields, Custom Quick Add Form Fields, and Reset All Options
- `Refetch` contains the tag refetch workflow

Options reset behavior:
- each visible Options setting has a modal-confirmed `Reset to default` action
- `Reset All Options` is shown on the General and Field Layouts tabs, opens the same Options confirmation modal, and resets settings from both tabs together
- reset buttons are right-aligned in full-width Options action rows
- reset confirmation modals are teleported to the document body so they stay centered in the viewport instead of inside the Options panel
- reset confirmation modals close from Cancel, Escape, or clicking outside the modal card
- the global reset confirmation button is disabled for 3 seconds and shows a countdown before it can be clicked
- reset defaults are pagination `100`, hidden-description search disabled, table width `default`, all five default field layouts, all default Index sort dropdown values, automatic Series enabled, product form theme `black`, Tag Library collapsed, Index group ordering disabled, and autocomplete `usage`
- global reset does not change products, tags, refetch runs, legacy hidden fallback keys, or unrelated future option rows

Index search defaults:
- general Index search ignores Japanese and English descriptions while their Index columns are hidden
- showing `description_japanese` makes general search include `products.description`
- showing `description_english` makes general search include `products.description_english`
- enabling `Search hidden descriptions` makes general search include both description columns even while both Index columns are hidden
- the explicit Japanese Description filter uses the `description` query key and searches `products.description`
- the explicit English Description filter uses the `description_english` query key and searches `products.description_english`

Tag Library defaults:
- collapsed by default
- when enabled, `/tags` opens with the full tag list shown
- typing in Tag Library search still opens matching results regardless of this default
- Index group ordering is disabled by default
- when enabled, Index tag chips use saved group order, saved tag order inside groups, then ungrouped tags alphabetically instead of plain alphabetical title ordering
- the Options page shows inline helper tooltips for the expanded-list, Index group-ordering, and Field Layouts Updated Date filter/sort switches
- tag background/font colors render on Index and Tag Library by default, while Autocomplete suggestions, Edit readonly tags, and Refetch review tags stay uncolored until enabled in Options. Edit readonly tag colors render inline inside the normal readonly text field.

Autocomplete ordering default:
- `usage`

Autocomplete ordering choices:
- `usage`: orders matching suggestions by attached work count and then title
- `first_word`: shows values that start with the typed query before later-word matches, then orders each group by attached work count and title

## Scraper Runtime Paths
- Python script: `python/DLSiteScraper.py`
- Tags-only Python script: `python/DLSiteTagFetcher.py`
- Python requirements: `python/requirements.txt`
- Scraped JSON output: `storage/app/Works/*.json`
- Scraped image output: `storage/app/public/Works/{RJ}/*`

Scraped JSON files are also used by the product metadata backfill migration. The migration reads `storage/app/Works/{RJ}.json` when it exists and skips missing or invalid JSON without blocking the migration.

`python/DLSiteTagFetcher.py` is used only by the Options -> Refetch Tags workflow. It prints JP/EN genre JSON to stdout and does not write scraped JSON or download images.

## Custom Work Upload Paths
- Required custom cover upload output: `storage/app/public/Works/{RJ}/cover.{ext}`
- Optional custom sample upload output: `storage/app/public/Works/{RJ}/sample_1.{ext}`, `sample_2.{ext}`, etc.
- Custom cover and sample uploads are validated as image files up to 20 MB each.
- Stored public paths use the existing `/storage` link format, for example `storage/Works/{RJ}/sample_1.jpg`
- Custom cover uploads set `products.work_image` to the public path with the uploaded image extension, for example `storage/Works/{RJ}/cover.png`
- Local/manual setup still needs `php artisan storage:link` so uploaded custom images are browser-accessible
