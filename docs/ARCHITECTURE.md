# Architecture

## Stack
- Backend: Laravel 12 (PHP 8.3)
- Frontend: Blade templates, Livewire for the Index list, Options work search/settings/refetch progress, and plain CSS/JS
- Database: MySQL 8
- Scraper: Python scripts invoked from Laravel (`python/DLSiteScraper.py`, `python/DLSiteTagFetcher.py`)
- Background work: Laravel database queues and job batches

## Main Application Flow
1. User opens list page (`GET /`).
2. `ProductController@index` renders `resources/views/Index.blade.php`, then `app/Livewire/ProductIndex.php` owns list filters, sorting, pagination, and URL query state.
3. `GET /tags` renders the self-contained Index-aligned tag library shell, then `app/Livewire/TagLibraryManager.php` owns tag search, empty tag creation/deletion, saved collapsed/expanded default state, and tag links back to the same index filter used on the list page.
4. `GET /options` renders the General tab by default, `GET /options?tab=field-layouts` renders the Field Layouts tab, and `GET /options?tab=refetch` renders the Refetch Tags tab.
5. User can create/edit/delete entries through forms.
6. Store flow (`POST /store`) validates input, runs scraper, reads scraped JSON, and creates a `products` row.
7. Custom store flow (`POST /store/custom`) validates manual input, skips scraper/network checks, stores the required local cover plus optional sample images, and creates a `products` row.
8. Update flow (`POST /update/{id}`) validates and updates editable fields.
9. Destroy flow (`POST /destroy/{id}`) removes DB row and related local files.
10. Refetch Tags starts a queued batch, stores per-work fetched/skipped results, shows progress, can cancel a running batch, then applies reviewed tag changes.

## Key Components
- Routes: `routes/web.php`
- Controller: `app/Http/Controllers/ProductController.php`
- Options controller: `app/Http/Controllers/OptionsController.php`
- Autocomplete controller: `app/Http/Controllers/AutocompleteController.php`
- Requests:
  - `app/Http/Requests/StartTagRefetchRequest.php`
  - `app/Http/Requests/ApplyTagRefetchRequest.php`
  - `app/Http/Requests/StoreProductRequest.php`
  - `app/Http/Requests/StoreCustomProductRequest.php`
  - `app/Http/Requests/UpdateProductRequest.php`
  - shared normalization/validation in `app/Http/Requests/BaseProductRequest.php`
- Model: `app/Models/Product.php`
- Contributor model: `app/Models/Contributor.php`
- App option model: `app/Models/Option.php`
- Refetch models:
  - `app/Models/TagRefetchRun.php`
  - `app/Models/TagRefetchWorkResult.php`
- Refetch job/support:
  - `app/Jobs/FetchProductTagsJob.php`
  - `app/Support/DLSite/DLSitePythonRunner.php`
  - `app/Support/TagRefetch/DLSiteTagFetcher.php`
  - `app/Support/TagRefetch/TagRefetchService.php`
- Shared genre sync helpers:
  - `app/Support/ProductGenreSync.php`
  - `app/Support/GenreSyncPayload.php`
- Shared contributor and DLSite metadata helpers:
  - `app/Support/ProductContributorSync.php`
  - `app/Support/DLSite/DLSiteWorkData.php`
- Product layout helpers:
  - `app/Enums/ProductContributorRole.php`
  - `app/Enums/ProductField.php`
  - `app/Support/ProductFieldLayout.php`
  - `ProductField` owns surface-specific field order, visibility locks, hidden defaults, and edit defaults; `ProductFieldLayout` normalizes stored layout rows and prepares Blade render metadata
- Shared visible tag helper:
  - `app/Support/VisibleGenreAttachment.php`
- Autocomplete helpers:
  - `app/Support/Autocomplete/AutocompleteMatcher.php`
  - `app/Support/Autocomplete/TagAutocompleteSearch.php`
  - `app/Support/Autocomplete/SeriesAutocompleteSearch.php`
- Livewire components:
  - `app/Livewire/ProductIndex.php`
  - `app/Livewire/IndexPaginationSettings.php`
  - `app/Livewire/AutocompleteSettings.php`
  - `app/Livewire/ProductFieldLayoutSettings.php`
  - `app/Livewire/AutoSeriesSettings.php`
  - `app/Livewire/IndexTableWidthSettings.php`
  - `app/Livewire/OptionsWorkSearch.php`
  - `app/Livewire/OptionsRefetchProgress.php`
- Livewire shared settings concern:
  - `app/Livewire/Concerns/ConfirmsOptionReset.php`
- Views: `resources/views/*.blade.php`
- Livewire views: `resources/views/livewire/*.blade.php`
- UI field components: `resources/views/components/fields/*.blade.php`
- UI field component classes: `app/View/Components/Fields/*.php`
- Scripts/CSS: `public/scripts/*`, `public/css/*`

Shared UI note:
- `resources/views/components/list-menu-float.blade.php` is reused by index/tag library
- desktop keeps the floating hover menu
- mobile uses a toggle button that opens the same menu as a left-side drawer
- `resources/views/Index.blade.php` hosts `ProductIndex` inside a sticky-footer page shell; the Livewire view keeps the desktop table on larger screens and switches to stacked cards on mobile so search/actions still fit
- `resources/views/Create.blade.php` switches between DLSite create and custom create modes, renders the saved Quick Add or Custom Quick Add field layout through a configurable row component, includes the same optional metadata/creator/Japanese-description/English-description rows as Edit Work hidden by default, and keeps required create fields locked visible; `ProductController` resolves the saved `product_form_theme` into a page-level theme class for `resources/views/Create.blade.php` and `resources/views/Edit.blade.php`, which use `public/css/edit.css` for both desktop and mobile form layouts and render reusable field components from `resources/views/components/fields/*.blade.php`
- `app/View/Components/Fields/*.php` provides the class-based field components used by those Blade views
- `AppServiceProvider` registers the enum-backed field component aliases used by `<x-fields.* />`
- the progress, score, priority, and re-listen field component classes read their select options from the matching enums in `app/Enums/*.php`
- Blade pages load CSS and JS from `public/` with `filemtime(public_path(...))` query strings for cache busting
- `public/scripts/autocomplete-text.js` and `public/css/autocomplete.css` provide opt-in autocomplete for tag CSV fields and single-value series fields through `data-autocomplete-*` attributes
- `resources/views/components/index/advanced-filters.blade.php` renders the index filter/sort modal; dynamic filter rows are prepared by `ProductFieldLayout` and rendered through anonymous index field components
- `resources/views/components/index/*.blade.php` contains the reusable filter/select/radio pieces used by the index modal; configurable Index table cells render inline in the Livewire view to avoid per-cell component overhead
- Index mobile cards place row actions after all rendered metadata fields so the edit action stays at the bottom of each work card
- `app/Livewire/ProductIndex.php` binds filter/sort properties to the URL query string, then normalizes that state into a `ProductIndexFilters` object
- `app/Enums/*.php` holds enum-backed filter options for progress, priority, tag match, sort fields including `Added to the site Date`, sort backend metadata, and the numeric rating scales
- `app/Models/Product.php` owns the Laravel 12 local scopes used by index filtering/search and keeps derived index keys in sync for RJ sorting and partial date sorting
- `app/Support/ProductIndexResults.php` builds the filtered product query, hydrates only Index title/status base columns plus attributes needed by visible Index fields, and keeps filter/sort-only columns in SQL for default/RJ/scalar/date sorts
- `ProductIndexResults` also prepares simple display strings for optional scalar Index cells such as partial listening dates, re-listen value, and priority so the Blade table does not call formatter or enum classes directly
- `ProductIndex` reads page size, Index layout, Filter layout, and table width through one batched `ProductIndexSettings` option lookup per render, uses Livewire computed properties for derived filter/query/options/sort-icon state, and derives return/progress/tag query arrays from that normalized state
- `ProductIndexSortField` owns valid sort keys, labels, SQL columns, and Advanced Filter sort dropdown layout normalization; the `index_sort_field_layout` option only changes which sort values the dropdown shows and does not disable enum-valid URL or table-header sorting
- Advanced Filter date ranges use explicit `*_from` and `*_to` URL/query keys. Start/finish date ranges compare against the derived `start_date_sort` / `end_date_sort` `YYYYMMDD` integers, while `created_at` and `updated_at` ranges use Laravel date-only timestamp filtering.
- Contributor sort fields order by each role's alphabetically first contributor name with nulls last. Circle sorting uses the first circle contributor name when present and falls back to `products.circle`.
- the advanced filter modal defaults to `All tags` matching and `Desc` sort direction until the user chooses something else
- `ProductIndexSettings` carries prepared Index columns, visible field ids, Filter fields, and table width CSS; `ProductIndex` only loads row-level tag/contributor data when those columns are visible and passes the grouped relation data directly to Blade before configurable columns render in the saved order
- Index tag links use one prepared base URL per render and append the numeric genre id in Blade, avoiding route generation for every rendered tag link
- Index Title and Image are part of the Index field layout; Title is locked visible but reorderable, while Image can be hidden or reordered like the optional metadata columns
- Edit Work keeps the RJ Code + Title display row fixed first, then renders the Edit field layout; the `title` layout row is locked visible and expands to the Japanese/English title inputs, while Age Category is hidden by default
- Index creator/circle filters query normalized contributor rows, circle filters also match `products.maker_id`, the `description` filter searches Japanese description text, and the `description_english` filter searches English description text. General Index search searches each description language only when that language's Index column is visible, unless `Search hidden descriptions` is enabled.
- `ProductContributorRole` owns the role-to-`ProductField` mapping used when Create/Edit field layouts decide whether contributor inputs are visible or editable
- `ProductField` owns the field layout metadata for Index, Edit, Filter, Quick Add, and Custom Quick Add surfaces so layout normalization and field enum behavior stay aligned
- Options field-layout default reset logic uses a shared option/surface map so adding another layout surface stays localized

## Data Model
`products` table stores:
- DLSite identifiers and titles
- fetched maker/circle metadata
- Japanese and English descriptions
- progress/listening metadata (`progress`, dates, re-listen fields, priority)
- local image paths; `sample_images` is a JSON column cast to an array by `Product`, so Eloquent create/update calls use PHP arrays and Laravel serializes them

`genres` table stores one row per tag title:
- `title` as the display text
- `title_key` as the unique identity key
- normalized integer `order` for the ungrouped tag list
- `hidden_on_index`, which hides only that tag from Index tag chips

`title_key` is built from the trimmed title with Unicode case folding, then stored with a binary collation. This keeps tag matching case-insensitive while still treating Hiragana/Katakana variants as separate tags.

`genre_groups` stores optional genre group definitions:
- `title` as the display text
- normalized integer `order`, used before tag order when Index tag chips render
- `hidden_on_index`, which hides every assigned tag from Index without changing each tag's own hidden setting

`genre_group_genre` is the many-to-many pivot table between `genre_groups` and `genres`:
- `genre_group_id`
- `genre_id`
- normalized integer `order` for that tag inside that group

The index visibility/tag group migration backfills legacy `genres.group_id` assignments into `genre_group_genre` before dropping the old single-group column, using the normalized genre order as the initial per-group pivot order.

A tag can belong to multiple groups. Index tag chips sort alphabetically by tag title by default. When the persisted `Enable group ordering on Index` option is enabled, grouped tags sort by group order and saved tag order inside each group, then ungrouped tags sort alphabetically. Each multi-group tag renders once through its first visible group membership. In both modes, a tag is hidden from Index when it is directly hidden or belongs to any hidden group, even if it also belongs to visible groups.

`Genre::groups()` and `GenreGroup::genres()` centralize the Laravel many-to-many ordering for tag groups. They expose the pivot `id` and `order` fields, include pivot timestamps, and apply the default group/tag order used by Tag Library. `Genre::visibleOnIndex()`, `Genre::hiddenOnIndex()`, `GenreGroup::visibleOnIndex()`, and `GenreGroup::hiddenOnIndex()` keep reusable index visibility rules in the models while `ProductIndexResults` keeps its raw SQL tag-chip query for page-level performance.

Tag and group colors are stored as nullable `#RRGGBB` strings. `genres.color` / `genre_groups.color` control the tag background/accent color, while `genres.text_color` / `genre_groups.text_color` control the font color. `app/Support/TagColor.php` normalizes colors and batches effective color-pair lookup for autocomplete, Edit readonly tags, and Refetch review tags, including one ordered group-color lookup that resolves background and font colors together. Index tag colors stay in `ProductIndexResults` so the Index can select the needed color columns or subqueries without hydrating tag relationships. Group background/font colors override tag background/font colors independently through ordered group membership; when rendering inside a specific Tag Library group card, that card's group color value wins for whichever value is set. Edit readonly tag colors render as inline text marks inside the normal readonly field container, not as separate tag plaques.

`genre_product` is the many-to-many pivot table between `products` and `genres`.
It also stores a `source` value:
- `fetched` for scraper-provided genre attachments
- `custom` for tags the user typed into the editable Custom Tags field

`contributors` stores normalized creator/circle names:
- `name` as the display text
- `name_key` as the unique identity key, using the same trimmed Unicode case-folding approach as `Genre::titleKey()`
- optional `maker_id`

`contributor_product` is the many-to-many pivot table between `products` and `contributors`.
It stores a `role` value for the product-specific relationship:
- `circle`
- `scenario`
- `voice_actor`
- `illustration`
- `author`

`genre_product_languages` stores the fetched language buckets for each product/tag attachment:
- `genre_product_id`
- `language`

Current language values are `jp` and `en`, but the column is a string so future language codes can be added without changing the global genre row. Custom pivot rows do not have language rows. A fetched tag can have both `jp` and `en` rows for the same product, so titles like `ASMR` and `VTuber` remain a single `genres` row while still recording that DLSite returned the tag in both languages.

Current English/custom UI surfaces show:
- custom pivot rows
- fetched pivot rows with an `en` language row

JP-only fetched tags stay attached and stored, but are hidden from the current Index/Edit/Tag Library UI until a Japanese tag UI exists.

Tag Library extends that visibility rule by also showing empty `genres` rows with zero `genre_product` pivots. These empty tags can be created manually from `/tags`, searched immediately, linked to Index filters, and deleted only while they still have no product pivots. Attached JP-only fetched tags remain hidden. The General Options tab controls whether the full tag list starts collapsed or expanded when `/tags` opens and whether Index tag chips use saved group order.

The `/tags` Livewire manager also owns tag groups, group/tag order, and Index visibility:
- the Tag Groups section contains the group creation form so group creation stays with group management
- group deletion removes only that group's membership rows and the group row
- adding a title to a group resolves an existing tag by `title_key` or creates a new empty tag before attaching the membership
- removing a tag from a group detaches only that membership, so other group memberships remain
- group hidden state hides every assigned tag from Index without changing each tag's own hidden setting
- the persisted `Enable group ordering on Index` option is off by default; when enabled, Index tag chips use saved group order, saved tag order inside groups, then ungrouped tags alphabetically instead of plain alphabetical title ordering
- tag background/font color editing is handled in the tag settings modal, and group background/font color editing is handled on each group card; rendering is controlled by the `tag_color_surfaces` option map
- the All Tags list can be filtered by Index visibility, group status, specific group, and empty/used state without hiding group management rows
- the All Tags list has a session-only Edit Tags mode; normal mode keeps tag chips as Index filter links, while edit mode changes tag clicks into Livewire actions that open a teleported tag settings modal
- the tag settings modal updates tag-level Index visibility and group memberships in one save, preserving existing pivot orders and appending newly selected memberships to the end of their groups
- Edit Tags, Hide Tag on Index, and Hide Group on Index use shared `tag-library-switch-*` CSS/markup classes around native Livewire-bound checkboxes
- tags hidden directly or assigned to any hidden group render a compact accessible red status indicator inside the All Tags chip, while group names and group-hidden state remain in filters, group management, and the tag settings modal
- tag and group order values are normalized before rendering, so Tag Library and Index use normal ordered queries; Tag Library counts and visibility filters use Eloquent relationship helpers and counts for total pivots and English/custom-visible products

`tag_refetch_runs` stores each Options -> Refetch Tags batch:
- batch id and status
- selected product ids
- total/processed/fetched/skipped counts
- started/completed/cancelled/applied timestamps

`tag_refetch_work_results` stores each product result for a run:
- fetched JP/EN tags
- new JP/EN tags
- stale JP/EN tags
- custom tags that DLSite now returns as JP/EN fetched tags
- skipped error text
- chosen new-tag handling for JP/EN
- chosen stale-tag handling for JP/EN
- chosen custom-to-fetched handling

Queue tables:
- `jobs` stores pending database queue jobs
- `job_batches` stores Laravel batch metadata

`options` stores app-level settings as scalar string values:
- `index_per_page` controls Index pagination, defaults to `100`, accepts fixed choices (`10`, `25`, `50`, `100`, `250`, `500`, `1000`) or any positive integer, and can be set to `unlimited`
- `index_search_hidden_descriptions_enabled` controls whether general Index search can match both description languages when their Index columns are hidden, and defaults to disabled
- `tag_autocomplete_order` controls tag suggestion ordering, defaults to `usage`, and can be set to `first_word`
- `series_autocomplete_order` controls series suggestion ordering, defaults to `usage`, and can be set to `first_word`
- `auto_series_from_title_name` controls whether DLSite create fills an empty Series field from `japanese.title_name`, and defaults to enabled
- `tag_library_index_group_ordering_enabled` controls whether Index tag chips use group order, and defaults to disabled
- `tag_color_surfaces` controls where stored tag/group background and font colors render, with Index and Tag Library enabled by default and Autocomplete/Edit readonly/Refetch disabled by default
- `index_field_layout`, `edit_field_layout`, `filter_field_layout`, `quick_add_field_layout`, and `custom_quick_add_field_layout` store surface-specific configurable field order/visibility/editability layouts
- `index_sort_field_layout` stores Advanced Filter sort dropdown order/visibility while leaving valid sort execution enum-backed
- `index_table_width` controls the Index list/table width
- `App\Models\Option` normalizes stored scalar strings into the runtime values the app uses

Current repo-level Index sort-key indexes:
- `products.id` remains the primary key
- `products.rj_number`, `start_date_sort`, and `end_date_sort` are derived nullable integer sort keys with indexes used by Index SQL sorting

Migration note:
- legacy `products.genre`, `products.genre_english`, and `products.genre_custom` JSON columns are migrated into
  `genres` + `genre_product` by `2026_03_16_160000_convert_product_genre_titles_to_ids.php`
- after conversion, those legacy JSON columns are dropped
- legacy global `genres.type` / `genres.language` metadata is migrated into product-specific
  `genre_product_languages` rows by `2026_05_24_000000_create_genre_product_languages_table.php`
- the `genre_product_languages` down migration restores old global metadata best-effort because one fetched title can now belong to multiple languages for the same product
- `2026_05_26_000000_add_title_key_to_genres_table.php` moves tag uniqueness from `genres.title` to `genres.title_key`; rollback can fail if kana-distinct tags were added because the old `genres.title` unique index used MySQL's broader text collation
- `2026_05_30_000000_create_contributors_table.php` adds normalized creator/circle metadata in `contributors` and `contributor_product`
- `2026_05_30_000001_expand_metadata_options_columns.php` changes description and option values to nullable text so longer descriptions/layout JSON can be stored
- `2026_05_30_000002_backfill_product_metadata_from_storage.php` reads matching `storage/app/Works/{RJ}.json` files to backfill maker/circle, descriptions, and contributor pivots; missing or invalid JSON is skipped and `series` is not backfilled

Runtime note:
- `ProductIndex` shows English + custom genres through one lightweight grouped query from `genre_product` + `genres` + `genre_groups` for the current page only when the Tags column is visible, loads contributor pivots only when visible contributor columns need them, and passes those grouped results directly to Blade keyed by product id
- `ProductContributorSync` syncs contributor pivots through `Product::contributorsForRole()` and Laravel's role-scoped many-to-many `syncWithPivotValues()` so replacing one creator role does not detach the same contributor from another role
- index cover images are rendered directly from `products.work_image` only when the Image column is visible
- `products.start_date` and `products.end_date` JSON remain the editable/display source of truth; their `*_sort` columns store `YYYYMMDD` integers with missing month/day as `00`
- `ProductIndex` keeps its filter/sort state in the URL through Livewire's `queryString()` config, then normalizes that state into `app/Support/ProductIndexFilters.php`
- `app/Support/ProductIndexFilters.php` provides the normalized filter query used by progress tabs, preserved search state, tag links, explicit Livewire query-string keys, and the visibility-affecting filter groups used by return redirects
- Index genre links, tag filters, genre-backed search, edit tag loading, and Tag Library use `VisibleGenreAttachment` for the same visible-tag rule as rendered tags: custom source or fetched `en` language row
- Index tag chips exclude tags hidden by their own `genres.hidden_on_index` flag or assigned to any hidden group. The raw Index query first checks whether any hidden groups exist and skips the hidden-group anti-join when none do, so libraries without hidden groups keep the cheaper tag query path. By default tags sort alphabetically by title; when group ordering is enabled they render each remaining grouped tag once through its first visible group membership, then render ungrouped tags after grouped tags.
- Index tag-chip color resolution is query-based for performance: when Index colors are disabled or no tag/group colors are configured, the color columns are not selected and the group-color lookup is skipped. When enabled and at least one color exists, the query resolves tag background/font colors plus the first ordered group background/font colors. Effective color decoration is cached per unique genre id for the current page, and uncolored tags render as plain links without the colored-chip class/style path.
- opening and closing the advanced filter modal is local Alpine state registered in `public/scripts/index-advanced-filters.js`, not Livewire state, so showing the modal does not rerun the Index query or reset draft filter values
- changing advanced sort draft fields is client-side/deferred through that Alpine component until Apply, so the modal does not send requests while choosing primary/secondary sort columns
- desktop table headers and the advanced sort modal both update the same Livewire-backed server-side sort state
- the Index Sort Menu setting affects only the Advanced Filter sort dropdown option map; table headers and restored query-string sorts still validate and sort through `ProductIndexSortField`
- Index pagination uses Livewire/Laravel paginator links with the project pagination view and Livewire's scroll target data to return to `#progress-menu`, keeping progress tabs, search, and Filter visible after page changes
- switching progress tabs keeps the rest of the index request state, but intentionally drops the current `genre` filter
- clicking a series link opens the index with only the exact `series` filter applied
- `/autocomplete/tags` returns all stored genre titles, including JP/EN/custom tags
- `/autocomplete/series` returns distinct non-empty series values
- autocomplete matching uses word-prefix behavior for Latin-style text and substring matching for non-ASCII input so Japanese tag text can be found naturally
- autocomplete search is split into small tag and series search helpers that share the same matcher/ranking logic
- autocomplete ordering is configurable per source from Options: `usage` orders all matches by attached work count and then title; `first_word` puts values starting with the typed query before later-word matches, then orders each group by attached work count and title
- `app/Support/ReturnTarget.php` normalizes index-only return state (`return_query`, `return_fragment`) used by create/edit/update/destroy flows and builds index URLs with Laravel URI helpers
- successful create/update redirects prioritize showing the created/edited work on the Index: `ReturnTarget` first keeps the saved page when the work is already visible there, then avoids per-filter cleanup when the full query still matches, otherwise drops filters that would hide the work, preserves matching filters and sort state, and uses `ProductIndexResults` to calculate the correct page before appending the work anchor
- update detects whether visibility-affecting product fields or custom tags changed before redirecting, so unchanged edits can trust the current index query unless the saved return state no longer contains the work; `maker_id` is included in that product-field check because circle filters also match maker IDs
- destroy keeps the saved index query but clamps stale page numbers to the last valid page after deletion; storage cleanup uses Laravel storage deletes and logs cleanup failures without blocking product deletion
- create-page Go Back ignores malformed `return_url` input, uses Laravel previous URL behavior with the Index as fallback, preserves that back URL while switching between DLSite Create and Custom Create, and restores the flashed return target after validation or scraper errors
- Create pages read Quick Add or Custom Quick Add field layouts from Options and render only the visible rows, while keeping required RJ/custom title/age/cover rows visible even if stored option JSON tries to hide them. Hidden Create layout fields are ignored on submit; DLSite Create keeps scraped metadata for hidden age/circle/creator/Japanese-description/English-description rows and always preserves scraped fetched JP/EN tags, while visible Custom Tags can add submitted custom tags. Custom Quick Add saves visible custom metadata and Custom Tags directly because it has no scraper fallback, so hidden custom description language rows save `null` and hidden Custom Tags are ignored
- create/store resolves scraped/custom titles into `genres` rows and syncs the pivot
- DLSite create parses scraped JSON through `DLSiteWorkData`, collapses duplicate English title/description values to `null`, syncs contributor roles, and fills an empty Series from `japanese.title_name` only when `auto_series_from_title_name` is enabled
- edit loads the fetched English/custom genre rows independently, while keeping fetched non-custom Japanese genres attached automatically
- update reads user-added genres from the form, stores them as `genre_product.source = custom`, and can reuse an existing fetched genre row while keeping it editable for that product
- Index Table Fields keeps one `tags` order row and one Tags column, but stores separate Custom Tags and Fetched EN Tags visibility flags; the Index tag cell renders only enabled buckets, and general Index search searches tags only when that Tags column is enabled
- the Edit Form field layout uses separate `tags` and `fetched_english_tags` rows for Custom Tags and Fetched EN Tags; update only syncs tag buckets whose row is visible and editable, preserves disabled/hidden buckets, preserves hidden `jp` fetched rows, and still keeps fetched-over-custom precedence
- Edit Work reads the edit field layout from Options; hidden or read-only metadata/listening fields are not cleared during save because the update request only applies submitted/editable field groups, including nested `add[...]` date/re-listen/priority inputs
- `ProductController` builds the editable product update payload from a field-to-column map keyed by `ProductField`, maps Japanese and English description layout rows independently to `products.description` and `products.description_english`, and keeps special cases such as duplicate English descriptions and contributor/tag syncing outside the map
- custom create stores user-uploaded covers/samples in `storage/app/public/Works/{RJ}`, saves the uploaded cover public path in `products.work_image`, and attaches custom tags through the same genre resolver used by update
- product create/update and refetch apply use `app/Support/ProductGenreSync.php` to sync `genre_product.source` and `genre_product_languages` together
- `app/Support/GenreSyncPayload.php` keeps fetched-over-custom source precedence and builds the fetched language map used by `ProductGenreSync`
- `app/Models/Genre.php` resolves tag titles by `title_key`, preserving the existing display title when the new input only differs by case
- Options -> Refetch Tags dispatches one queued `FetchProductTagsJob` per selected product and stores results before any product tags are changed
- running refetch runs can be cancelled from the progress page; cancellation changes the run from `running` to `cancelling`, cancels that run's Laravel batch, lets any already-started Python fetch finish, and moves the run to `review` after pending results become fetched or skipped
- cancelled-before-fetch work results are stored as skipped results, while fetched results completed before or during cancellation remain reviewable and can be applied
- the refetch progress panel is rendered by Livewire and polls every second while the run is active (`running` or `cancelling`)
- the Options page has separate `General`, `Field Layouts`, and `Refetch` tabs; validation errors from refetch forms reopen the Refetch tab
- the Refetch tab links to the latest refetch run when at least one run exists
- the General tab includes an Index Pagination setting powered by Livewire and persisted in `options.index_per_page`; changing the mode can reveal the custom-value input immediately, but the setting is only persisted when Save is submitted
- the General tab includes Livewire autocomplete ordering settings persisted in `options.tag_autocomplete_order` and `options.series_autocomplete_order`
- the General tab includes Livewire settings for automatic Series from DLSite `title_name`, Add/Edit form page theme, and Index table width
- the Field Layouts tab includes Index Table Custom Tags/Fetched EN Tags visibility toggles inside one Tags row, keeps Index Filter Fields and Index Sort Menu unsplit, and uses separate Edit Form rows for Custom Tags and Fetched EN Tags
- the Field Layouts tab orders its Livewire settings as Index Table Fields, Index Filter Fields, Index Sort Menu, Edit Form Fields, Quick Add Form Fields, and Custom Quick Add Form Fields; field layout rows use Livewire `wire:sort` drag handles plus Up/Down buttons, keep checkbox state in field-keyed maps while editing, and are persisted only when Save is submitted
- Options Livewire settings components share saved notice, validation-reset, and reset-confirmation state through `ConfirmsOptionReset`
- the General and Field Layouts tabs include right-aligned, body-teleported, modal-confirmed reset actions for each visible setting group plus a global `Reset All Options` action; modal confirm buttons use a destructive red style, modals close from Cancel/Escape/backdrop clicks, global reset adds a 3-second client-side countdown before its confirm button unlocks, and global reset restores visible General and Field Layouts settings while leaving product/refetch data and unrelated option rows alone
- the selected-work search on the Refetch tab is rendered by Livewire and uses Laravel query helpers for the ID/title match
- the Refetch tab work list and queued all/selected refetch ids use numeric RJ descending order, matching the Index default order
- custom-only works are skipped during refetch because they do not have DLSite metadata to fetch from
- applying a refetch run attaches new fetched tags with `genre_product.source = fetched` and one language row per fetched bucket, unless the review form ignores new JP/EN tags globally or for that work
- Refetch review colors are resolved once per review page from existing stored genres by `title_key` when the refetch color surface is enabled; fetched tags that do not yet exist in `genres` render with the default style, and the Blade view receives prepared tag rows instead of doing color lookups itself
- stale fetched JP/EN actions remove only that language row when another fetched language remains; the tag moves to `genre_product.source = custom` only when no fetched language rows remain and the selected stale action is move-to-custom
- custom tags that DLSite now returns as fetched are promoted to fetched by default; the review form has global and per-work controls to keep those tags custom instead
- refetch diff/apply reads current fetched/custom tags through the Product genre relationships and compares titles by the same `Genre::titleKey()` identity rule used for storage
- existing custom tags are preserved, and unused global fetched `genres` rows are detached from products but not deleted
- only the newest review run shows apply controls; older review runs are read-only to avoid applying stale review decisions after a newer fetch
- each review result row shows compact indicators for new JP, new EN, stale JP, stale EN, and custom-to-fetched changes when those buckets are present
- refetch Blade views use small state/summary helpers on `TagRefetchRun` and `TagRefetchWorkResult` instead of checking raw status constants or counting buckets in controllers

## Scraper Integration
- `app/Support/DLSite/DLSitePythonRunner.php` runs Python scripts through Laravel's Process facade with the project `python/venv`.
- Product create runs `python/DLSiteScraper.py` through `DLSitePythonRunner`.
- Python fetches Japanese/English DLSite metadata, stores JSON in `storage/app/Works`, and downloads images to `storage/app/public/Works/{RJ}`.
- The stored JSON is also the source for metadata backfill migrations when a matching `products.rj_number` exists.
- Custom create does not run the scraper and does not create or read scraped JSON.
- `DLSiteTagFetcher` runs `python/DLSiteTagFetcher.py` through `DLSitePythonRunner` for the Refetch Tags queue job.
- `python/DLSiteTagFetcher.py` fetches tags only, returns `japanese.genre` and `english.genre` JSON through stdout, and does not write files.
- Known DLSite fetch errors are stored as skipped work results and are shown on the review page.

## Validation / Normalization Notes
- RJ input can be raw RJ code or URL containing RJ code.
- `BaseProductRequest` normalizes the create/edit `add[...]` fields in `prepareForValidation()`, then runs date-part and date-order checks through the form request `after()` hook.
- `BaseProductRequest` validates progress, score, priority, and re-listen value against the matching product enums so form input cannot drift from the UI option sets.
- `StoreCustomProductRequest` keeps RJ-format and uniqueness validation, requires Japanese title, age category, and cover image, and validates cover/sample uploads as images up to 20 MB each.
- `StartTagRefetchRequest` validates all/selected refetch scope and resolves the product ids before the controller creates a run.
- `ApplyTagRefetchRequest` validates new-tag, stale-tag, and custom-to-fetched actions, then blocks applying any run except the newest review run.
- Custom tags are comma-separated and parsed with CSV rules:
  - commas inside a tag are supported via quotes
  - example: `"Junior / Senior (at work, school, etc)", Office Lady`
- autocomplete inserts selected tag suggestions with the same CSV quote rules and appends `, ` so the next tag can be typed immediately
