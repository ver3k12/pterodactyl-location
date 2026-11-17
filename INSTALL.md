# Installation and Upgrade

Target: Pterodactyl Panel 1.15.2. Review [LICENSE](LICENSE) and obtain the necessary permission before using modified code. Back up the panel files and database before proceeding. Use your normal deployment account; do not run an unreviewed remote script as root.

## 1. Requirements

- A working Pterodactyl panel with its normal PHP and Node.js dependencies.
- PHP 8.2+ with mbstring and cURL compiled with HTTP/2 support.
- Node.js 22+ and Yarn Classic for the panel frontend build.
- A persistent shared Laravel cache, preferably Redis, to share API results and cooldowns across workers. An array cache is not suitable in production.
- Outbound HTTPS to `ipinfo.spacehost.ovh` and the selected DoH resolver.

The free API V2 quota is currently 1000 requests per day. No token is required for the free tier. This integration does not expose a paid API token field.

## 2. Copy the Addon Files

Set `ADDON` to this repository's actual absolute path. Run these commands from the panel directory, not the addon directory:

```bash
cd /var/www/pterodactyl
ADDON=/absolute/path/to/pterodactyl-location

install -d app/Services/Region
install -m 0644 "$ADDON/resources/RegionLookupService.php" app/Services/Region/RegionLookupService.php
install -m 0644 "$ADDON/resources/ServerRegionController.php" app/Http/Controllers/Api/Client/Servers/ServerRegionController.php
install -m 0644 "$ADDON/resources/PterodactylRegionController.php" app/Http/Controllers/Admin/Settings/PterodactylRegionController.php
install -m 0644 "$ADDON/resources/PterodactylRegionSettingsFormRequest.php" app/Http/Requests/Admin/Settings/PterodactylRegionSettingsFormRequest.php
install -m 0644 "$ADDON/resources/pterodactyl-region.blade.php" resources/views/admin/settings/pterodactyl-region.blade.php
install -m 0644 "$ADDON/resources/ServerDetailsBlock.tsx" resources/scripts/components/server/console/ServerDetailsBlock.tsx
install -m 0644 "$ADDON/resources/RegionStatBlock.tsx" resources/scripts/components/server/console/RegionStatBlock.tsx
install -d resources/scripts/assets/regions
cp "$ADDON"/resources/regions/*.svg resources/scripts/assets/regions/
```

If the console components have custom modifications, merge the addon changes rather than overwriting them. The original panel's `style.module.css` is reused. Do not copy the addon test manifest, lock file or vendor directory into the panel.

## 3. Register the Client Route

In the panel's `routes/api-client.php`, add this route **inside the existing `/servers/{server}` group**, next to the `/resources` route:

```php
Route::get('/region', \Pterodactyl\Http\Controllers\Api\Client\Servers\ServerRegionController::class)
    ->middleware('throttle:30,1');
```

Keep the existing server access and resource ownership middleware on that group. Do not register this route outside it or expose an unauthenticated arbitrary-IP lookup endpoint. The controller reads the server's own primary allocation; query-string addresses are not used.

## 4. Register the Admin Routes

In the panel's `routes/admin.php`, add these routes **inside the existing `settings` group**. If an earlier installation already registered them, keep only one copy:

```php
Route::get('/pterodactyl-region', [\Pterodactyl\Http\Controllers\Admin\Settings\PterodactylRegionController::class, 'index'])
    ->name('admin.settings.pterodactyl-region');
Route::patch('/pterodactyl-region', [\Pterodactyl\Http\Controllers\Admin\Settings\PterodactylRegionController::class, 'update']);
```

The settings page is available at `/admin/settings/pterodactyl-region`. Optionally add a navigation item to the existing settings navigation template using the named route above and `pterodactyl-region` as its active-tab key.

No changes to the frontend settings store, SettingsServiceProvider or region config are required. The addon reads its settings directly from the panel's settings repository.

## 5. Rebuild and Verify

From the panel directory:

```bash
composer dump-autoload
php artisan optimize:clear
yarn install --frozen-lockfile
yarn tsc
yarn build:production
php artisan route:list --path=region
```

Use the panel's normal production deployment process to restart long-running PHP workers if applicable. Retain the panel's existing Composer and Yarn lock files; updating the panel itself is a separate deployment task.

Open the admin location settings page, choose a resolver and save. Open a server console and check these cases:

- Public IPv4 or IPv6 allocation: country and optional city.
- `0.0.0.0`, private IP or loopback allocation: LocalHost with a server icon.
- Missing allocation: No allocation.
- Provider outage or exhausted quota: Location unavailable, while other console statistics continue working.
- Switching between servers while a lookup is pending: no stale region from the previous server.
- An unauthenticated user or a user without access to the server cannot access its region endpoint.

DNS is used only when the primary allocation contains a hostname. Normal numeric IP allocations do not need DNS. Display aliases are deliberately not resolved because they can point to proxies or a different machine.

## Troubleshooting

- **404 on region requests:** verify the client route is inside the server group, then clear the route cache.
- **Quad9 unavailable:** verify PHP cURL supports HTTP/2 and outbound HTTPS to `dns.quad9.net`. All DoH requests explicitly use HTTP/2.
- **No country flag:** country names still work; the globe icon is intentional when a flag is not bundled.
- **An error persists briefly:** failed lookups are cached for 60 seconds. Successful IP lookups are cached for 24 hours; hostname results for 5 minutes.
- **A private node shows LocalHost:** expected. The panel's public hostname is never substituted for the allocation.
- **Settings from an older installation:** Google and Cloudflare names remain supported. Unsupported resolver values safely use Cloudflare. Save settings to migrate the old IP provider value to API V2.