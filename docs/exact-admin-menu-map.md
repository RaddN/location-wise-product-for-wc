# Exact Admin Menu Map

Date: 2026-03-19

Product: `Multi Location Product & Inventory Management for WooCommerce`

## Purpose

Use this page as the exact admin navigation reference for:

- documentation,
- support replies,
- Tawk.to / Apollo training,
- onboarding steps,
- screenshot annotation.

## Important Naming Rule

Use the exact root menu label:

`Location Manage`

Do not rename it in docs/support as:

- `Multi Location Products`
- `Multi Location Product`
- `Location Management`
- `Location Settings`

Those names may sound similar, but they are not the exact current admin menu label.

## Main Admin Menu

WordPress Admin

- `Location Manage`
  - `Dashboard`
  - `Locations`
  - `Stock Central`
  - `Location Managers`
  - `Settings`

## Exact Menu Paths

- `WordPress Admin -> Location Manage -> Dashboard`
- `WordPress Admin -> Location Manage -> Locations`
- `WordPress Admin -> Location Manage -> Stock Central`
- `WordPress Admin -> Location Manage -> Location Managers`
- `WordPress Admin -> Location Manage -> Settings`

## Developer Slugs

| Visible Label | Menu/Page Slug |
| --- | --- |
| Location Manage | `multi-location-product-and-inventory-management-pro` |
| Dashboard | `multi-location-product-and-inventory-management-pro` |
| Locations | `edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc` |
| Stock Central | `location-stock-management` |
| Location Managers | `location-managers` |
| Settings | `multi-location-product-and-inventory-management-settings` |

## Settings Page Tab Map

Inside:

`WordPress Admin -> Location Manage -> Settings`

The visible top navigation tabs are:

1. `General`
2. `Inventory`
3. `Product Visibility`
4. `Popup`
5. `Order & Cart`
6. `Notifications`
7. `Location Wise Everything`
8. `User Experience`
9. `Location Info Management`
10. `Text Management`
11. `Advanced`
12. `Plugin License`

## Settings Tab Paths

- `WordPress Admin -> Location Manage -> Settings -> General`
- `WordPress Admin -> Location Manage -> Settings -> Inventory`
- `WordPress Admin -> Location Manage -> Settings -> Product Visibility`
- `WordPress Admin -> Location Manage -> Settings -> Popup`
- `WordPress Admin -> Location Manage -> Settings -> Order & Cart`
- `WordPress Admin -> Location Manage -> Settings -> Notifications`
- `WordPress Admin -> Location Manage -> Settings -> Location Wise Everything`
- `WordPress Admin -> Location Manage -> Settings -> User Experience`
- `WordPress Admin -> Location Manage -> Settings -> Location Info Management`
- `WordPress Admin -> Location Manage -> Settings -> Text Management`
- `WordPress Admin -> Location Manage -> Settings -> Advanced`
- `WordPress Admin -> Location Manage -> Settings -> Plugin License`

## Location Wise Everything Subtabs

Inside:

`WordPress Admin -> Location Manage -> Settings -> Location Wise Everything`

The visible subtabs are:

1. `Shipping`
2. `Payments`
3. `Tax`
4. `Discounts`
5. `Reviews`
6. `SEO`

## Notes On Hidden / Non-Primary Internal Areas

- There is an internal `Bundles` content container in code, but it is not exposed as a visible subtab in the current Settings UI.
- There is an internal `Others` content container in code, but it is not exposed as a visible subtab in the current Settings UI.
- The `Locations` page is the taxonomy management screen for `mulopimfwc_store_location`.

## Recommended Support Wording

Use phrases like:

- `Go to WordPress Admin -> Location Manage -> Settings.`
- `Open WordPress Admin -> Location Manage -> Stock Central.`
- `Navigate to WordPress Admin -> Location Manage -> Locations.`

Avoid phrases like:

- `Go to Multi Location Products -> API & Webhooks.`
- `Open Multi Location Products -> Notifications.`

Those are not the exact current menu names.

## Apollo Training Rule

When giving navigation instructions, Apollo should:

- always use `Location Manage` as the root menu name,
- use the exact tab label from this page,
- not invent missing submenu names,
- not convert internal sections into fake menu entries,
- say `I cannot confirm that path from the current admin menu map` if a path is not listed here.
