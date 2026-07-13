# Release Build Guide: EDD and Envato Packages

This plugin is maintained from one source tree and released as channel-specific ZIP files.

- `edd`: direct-sale build for the Plugincy/EDD store.
- `envato`: CodeCanyon build with marketplace-safe update/support copy and no default telemetry.

Run all commands from the plugin root:

```powershell
cd "C:\Users\GM Team\Local Sites\location-wise-product\app\public\wp-content\plugins\multi-location-product-and-inventory-management-pro"
```

## Before Building

Check the working tree first:

```powershell
git status --short
```

Use one commit/version as the source for both packages. If you are releasing a new version, update the plugin version once in the source tree before building either channel.

Required local tools:

- Windows PowerShell.
- `tar.exe`, available by default on modern Windows. The script falls back to `Compress-Archive` if `tar.exe` is missing.
- PHP CLI for syntax checks.
- `rg` for package verification.

## Build Commands

Build the EDD package:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\build\release.ps1 -Channel edd
```

Build the Envato package:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\build\release.ps1 -Channel envato
```

Build to a custom output directory:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\build\release.ps1 -Channel envato -OutputDir "C:\Users\GM Team\Desktop\release-zips"
```

Default output files:

```text
build\dist\multi-location-product-and-inventory-management-pro-edd.zip
build\dist\multi-location-product-and-inventory-management-pro-envato.zip
```

The generated `includes\build-channel.php` file exists only inside the staged release package. Do not add that file manually to the source tree.

## What Each Build Does

EDD build:

- Defines `MULOPIMFWC_RELEASE_CHANNEL` as `edd`.
- Includes `admin/license-page.php`.
- Includes `includes/analytics.php`.
- Keeps EDD activation, deactivation, license checks, renewal notices, and EDD-backed update hooks.
- Keeps current language files.

Envato build:

- Defines `MULOPIMFWC_RELEASE_CHANNEL` as `envato`.
- Keeps the plugin header name unchanged.
- Removes channel-incompatible tagged blocks from the main plugin file.
- Removes the direct-store activation screen file.
- Removes the direct-store tracking file.
- Removes current language files because they contain stale direct-store activation references. Regenerate channel-clean language files later if translations are needed for Envato.
- Adds `CODECANYON-UPDATES.txt`.
- Uses the `Updates & Support` settings tab instead of the direct-store activation screen.
- Makes premium-feature checks return enabled by default for the CodeCanyon package.
- Does not include buyer-facing negative comparison copy about other sales channels or activation systems.

Both builds exclude repo/dev/internal artifacts:

- `.git`, `.github`, `.vscode`, `.cursor`, `.playwright-mcp`, `.playwright-cli`, `output`
- `build`
- `docs`
- nested ZIP files
- backup translation files
- `.gitignore`

## Verification Checklist

Check PHP syntax in the source tree:

```powershell
php -l .\multi-location-product-and-inventory-management-pro.php
php -l .\admin\settings.php
php -l .\includes\release-channel.php
php -l .\admin\envato-support-page.php
```

Confirm the Envato ZIP does not ship EDD files or internal artifacts:

```powershell
tar -tf .\build\dist\multi-location-product-and-inventory-management-pro-envato.zip | Select-String -Pattern "admin/license-page.php|includes/analytics.php|languages/|docs/|\.git|\.github|\.vscode|\.cursor|multi-location-product-and-inventory-management-pro.zip"
```

Expected result: no output.

Confirm the EDD ZIP still ships the EDD files:

```powershell
tar -tf .\build\dist\multi-location-product-and-inventory-management-pro-edd.zip | Select-String -Pattern "admin/license-page.php|includes/analytics.php"
```

Expected result: both files are listed.

Extract and scan the Envato package for forbidden buyer-facing/direct-store strings:

```powershell
$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("mulopimfwc-envato-check-" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $tempRoot | Out-Null
tar -xf .\build\dist\multi-location-product-and-inventory-management-pro-envato.zip -C $tempRoot
$pluginRoot = Join-Path $tempRoot "multi-location-product-and-inventory-management-pro"
rg -n "edd_action|activate_license|deactivate_license|check_license|get_version|10817|plugincy\.com/checkout|plugincy\.com/my-account|mulopimfwc_background_license_check|wp_ajax_nopriv_mulopimfwc_background_license_check|product-analytics|license_key|admin/license-page\.php|includes/analytics\.php|License Key|Renew License|Manage Your Licenses|does not require an EDD|does not require.*license|purchase-code|purchase code" -S $pluginRoot
Select-String -Path (Join-Path $pluginRoot "multi-location-product-and-inventory-management-pro.php") -Pattern "Plugin Name:"
php -l (Join-Path $pluginRoot "multi-location-product-and-inventory-management-pro.php")
Remove-Item -LiteralPath $tempRoot -Recurse -Force
```

Expected result:

- `rg` should return no matches.
- The plugin name should match the source plugin name.
- PHP syntax should report no errors.

## Runtime Smoke Tests

EDD build:

- Install the EDD ZIP on a clean WooCommerce test site.
- Confirm the license tab appears as `Plugin License`.
- Confirm EDD license activation/deactivation still works.
- Confirm update checks still run only when the EDD license is valid.

Envato build:

- Install the Envato ZIP on a clean WooCommerce test site.
- Confirm paid features are not locked.
- Confirm the settings tab appears as `Updates & Support`.
- Confirm no frontend/admin request is made to EDD license endpoints during normal browsing.
- Confirm update instructions point to Envato downloads.
- Confirm no visible Envato-build text mentions other sales channels, activation systems, or purchase validation.

## Where To Change Channel Behavior

Channel detection and stubs:

```text
includes/release-channel.php
```

Envato support/update UI:

```text
admin/envato-support-page.php
```

Build packaging rules:

```text
build/release.ps1
```

EDD-only blocks in the main plugin file are wrapped with:

```text
// <mulopimfwc-edd-only>
...
// </mulopimfwc-edd-only>
```

The Envato build script removes those tagged blocks from the staged package.

## Adding Another Channel Later

To add another marketplace/build channel:

1. Add the channel name to the `ValidateSet` in `build/release.ps1`.
2. Add the channel to `mulopimfwc_get_release_channel()` in `includes/release-channel.php`.
3. Decide whether the channel should use EDD licensing, no activation, or a new marketplace-specific verification path.
4. Add a channel-specific support/update page if the buyer flow differs from EDD and Envato.
5. Update the release script to remove channel-incompatible files, strings, translations, and docs.
6. Add a verification grep pattern for any code or copy that must not ship in that channel.
7. Build and test the new package from a clean WordPress + WooCommerce install.

Do not fork the plugin into a separate long-lived repo unless a marketplace requires a permanently different product. Keep product fixes in the shared source tree and let the build channel control packaging.

## Common Problems

`Compress-Archive` file-lock errors:

- The release script prefers `tar.exe` because PowerShell Archive can lock copied files in Local Sites paths.
- If `tar.exe` is unavailable, install/enable it or run the script from a normal local disk path.

Forbidden strings appear only in translation files:

- Regenerate channel-specific `.pot`, `.po`, and `.mo` files after removing EDD strings.
- Until then, keep the current language files out of the Envato package.

Envato ZIP still includes EDD files:

- Rebuild with `-Channel envato`.
- Check `includes\build-channel.php` inside the ZIP. It must define `MULOPIMFWC_RELEASE_CHANNEL` as `envato`.

EDD ZIP lost license behavior:

- Check that `admin/license-page.php` and `includes/analytics.php` are present in the EDD ZIP.
- Check that EDD-only blocks in the source are still wrapped but not deleted.
