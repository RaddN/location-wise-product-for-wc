# Location Archive Template Guide

This plugin supports two professional location archive workflows.

## Native Location Archive

Use this when each location should have its own public archive URL.

1. Enable **Location URL Settings** in the plugin settings.
2. Choose the URL mode:
   - Query mode: `?store-location=location-slug`
   - Path mode: `/store-location/location-slug/`
3. Visit **Settings > Permalinks** and save if rewrite rules need refreshing.
4. Override the archive template in the active theme with:

```php
taxonomy-mulopimfwc_store_location.php
```

Inside that template, WordPress already knows the current location term:

```php
$location_id = get_queried_object()->term_id;
```

The plugin also exposes a safer helper:

```php
$location_id = mulopimfwc_get_current_location_archive_id();
$location = mulopimfwc_get_current_location_archive_term();
```

Native archives should keep using WooCommerce archive hooks and product-loop templates so theme wrappers, ordering, pagination, and product-card integrations continue to work.

## Custom Location Archive Page

Use this when a page builder, Gutenberg page, Elementor template, or custom PHP template should render a location archive outside the native taxonomy archive.

Fixed location examples:

```text
[mulopimfwc_location_archive id="123"]
[mulopimfwc_location_archive slug="downtown"]
[mulopimfwc_location_products id="123" columns="4" per_page="12" paginate="yes"]
```

URL-driven examples:

```text
/custom-location-page/?mulopimfwc_location_id=123
/custom-location-page/?mulopimfwc_location_slug=downtown
/custom-location-page/?store-location=downtown
```

Then place:

```text
[mulopimfwc_location_archive location="current"]
```

`location="current"` resolves the native archive term first, then explicit shortcode values, then supported URL parameters. It does not silently use the customer's selected-location cookie. To intentionally use the selected location cookie, use:

```text
[mulopimfwc_location_archive location="selected"]
[mulopimfwc_location_products location="selected"]
```

## Shortcode Reference

### `[mulopimfwc_location_archive]`

Renders a complete custom location archive with location details and/or a location product grid.

Example:

```text
[mulopimfwc_location_archive location="current" show_info="yes" show_products="yes" columns="4" per_page="12" paginate="yes"]
```

Parameters:

| Parameter | Purpose | Example values |
| --- | --- | --- |
| `id` | Fixed location term ID. Also accepts `current` or `selected`. | `id="123"`, `id="current"` |
| `slug` | Fixed location slug or hierarchical slug path. Also accepts `current` or `selected`. | `slug="new-york"`, `slug="usa/new-york"` |
| `location` | Location source when `id` and `slug` are not used. | `current`, `selected`, `123`, `new-york` |
| `show_info` | Show location information above the products. | `yes`, `no` |
| `show_products` | Show the location product grid. | `yes`, `no` |
| `show_filter` | Show the optional product filter area when available. | `yes`, `no` |
| `columns` | Product grid columns. | `columns="4"` |
| `per_page` | Products per page. | `per_page="12"` |
| `paginate` | Show pagination for the custom product loop. | `yes`, `no` |
| `orderby` | Product sort field. | `menu_order`, `date`, `title`, `rand`, `id` |
| `order` | Product sort direction. | `ASC`, `DESC` |
| `class` | Optional wrapper CSS class. | `class="my-location-archive"` |

### `[mulopimfwc_location_products]`

Renders only the product grid for a resolved location.

Example:

```text
[mulopimfwc_location_products location="current" columns="4" per_page="12" paginate="yes"]
```

Parameters:

| Parameter | Purpose | Example values |
| --- | --- | --- |
| `id` | Fixed location term ID. Also accepts `current` or `selected`. | `id="123"`, `id="current"` |
| `slug` | Fixed location slug or hierarchical slug path. Also accepts `current` or `selected`. | `slug="new-york"` |
| `location` | Location source for the product query. | `current`, `selected`, `123`, `new-york` |
| `columns` | Product grid columns. | `columns="4"` |
| `per_page` | Products per page. | `per_page="12"` |
| `paginate` | Show product pagination. | `yes`, `no` |
| `orderby` | Product sort field. | `menu_order`, `date`, `title`, `rand`, `id` |
| `order` | Product sort direction. | `ASC`, `DESC` |
| `class` | Optional wrapper CSS class. | `class="my-product-grid"` |

### `[mulopimfwc_location_info]`

Renders only location details. It now supports current archive/request context.

Examples:

```text
[mulopimfwc_location_info id="current"]
[mulopimfwc_location_info slug="current"]
[mulopimfwc_location_info location="current" layout="compact"]
```

Parameters:

| Parameter | Purpose | Example values |
| --- | --- | --- |
| `id` | Location ID, comma-separated IDs, `current`, or `selected`. | `id="123"`, `id="123,456"`, `id="current"` |
| `slug` | Location slug, comma-separated slugs, `current`, or `selected`. | `slug="new-york"`, `slug="current"` |
| `location` | Single location source when `id` and `slug` are not used. | `current`, `selected`, `123`, `new-york` |
| `layout` | Display layout. | `auto`, `tabs`, `compact`, `grid` |
| `search` | Show location search in tabbed/grid output. | `yes`, `no` |
| `compact` | Force compact single-location output. | `yes`, `no` |
| `limit` | Limit the number of locations when showing all locations. | `limit="5"` |
| `orderby` | Sort locations when showing all locations. | `name`, `id`, `count` |
| `order` | Location sort direction. | `ASC`, `DESC` |

### `[mulopimfwc_location_status]`

Renders an open/closed status badge. It now supports current archive/request context.

Examples:

```text
[mulopimfwc_location_status id="current"]
[mulopimfwc_location_status slug="current"]
```

Parameters:

| Parameter | Purpose | Example values |
| --- | --- | --- |
| `id` | Location term ID, `current`, or `selected`. | `id="123"`, `id="current"`, `id="selected"` |
| `slug` | Location slug, `current`, or `selected`. | `slug="new-york"`, `slug="current"`, `slug="selected"` |
| `taxonomy` | Taxonomy to resolve. Keep the default for location status. | `taxonomy="mulopimfwc_store_location"` |
| `class` | Optional wrapper CSS class. | `class="header-location-status"` |

## Reusable Helpers

```php
$location = mulopimfwc_resolve_location_term([
    'id' => 123,
]);

$location = mulopimfwc_resolve_location_term([
    'slug' => 'parent-location/child-location',
]);

$location = mulopimfwc_get_current_location_archive_term();
$location_id = mulopimfwc_get_current_location_archive_id();
```

For frontend custom archive output, resolve active locations only:

```php
$location = mulopimfwc_get_current_location_archive_term([
    'active_only' => true,
]);
```

## SEO Behavior

Native taxonomy archives keep the standard location archive SEO and schema behavior.

Custom URL-driven pages can output location archive schema when one of these URL parameters resolves a location:

```text
mulopimfwc_location_id
mulopimfwc_location_slug
the configured Location URL query key, for example store-location
```

For one-page-per-location builder pages that hardcode `[mulopimfwc_location_archive id="123"]`, set the page title and meta description in the page or SEO plugin because the location ID is not available from the request early enough for document-title filters.
