# Installation and Upgrade

Target: Pterodactyl Panel 1.15.1 with Blueprint beta-2026-08. Review [LICENSE](LICENSE) and obtain the necessary permission before using modified code. Back up the panel files and database before proceeding. Use your normal deployment account; do not run an unreviewed remote script as root.

## 1. Requirements

- A working Pterodactyl panel with its normal PHP and Node.js dependencies.
- Blueprint installed in the panel, with its `blueprint` CLI available in the panel directory or `PATH`. This extension targets Blueprint `beta-2026-08`; other versions may show a compatibility warning.
- PHP 8.2+ with mbstring and cURL compiled with HTTP/2 support.
- Node.js 22+ and Yarn Classic for the panel frontend build.
- A persistent shared Laravel cache, preferably Redis, to share API results and cooldowns across workers. An array cache is not suitable in production.
- Outbound HTTPS to `ipinfo.spacehost.ovh` and the selected DoH resolver.

The free API V2 quota is currently 1000 requests per day. No token is required for the free tier. This integration does not expose a paid API token field.

## 2. Automated Blueprint Installation

The script creates a temporary `.blueprint` package and installs it using the panel's Blueprint CLI. If this extension is already installed, the script backs up its Blueprint state, removes that version, and installs the new package using the same identifier. It checks that the console has Blueprint's `AfterInformation` hook; if missing, it backs up `ServerDetailsBlock.tsx` and adds only the hook import and render slot. Blueprint registers the admin page, authenticated client API route and dashboard component, then rebuilds the frontend. The DNS resolver is stored in the panel settings and is preserved. Review [main.sh](main.sh) before running it. From a trusted shell on the panel server, run:

```bash
curl -fsSLo /tmp/pterodactyl-location-main.sh https://raw.githubusercontent.com/ver3k12/pterodactyl-location/main/main.sh
less /tmp/pterodactyl-location-main.sh
sudo bash /tmp/pterodactyl-location-main.sh /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with the panel's absolute path. Blueprint must be available as `$PANEL_DIR/blueprint` or in `PATH`. Alternatively, clone or download this repository and run `sudo bash main.sh /absolute/path/to/pterodactyl`. The script downloads the latest source from the `main` branch when it is not run from a local checkout.

The extension adds a separate Region statistic through Blueprint, with country flags served from its own public asset directory. The installer does not replace `ServerDetailsBlock.tsx`; it only adds the missing Blueprint hook when needed, with a backup under `storage/app/pterodactyl-location-backups/`. It does not copy the addon's test manifest, lock file or vendor directory into the panel.

For updates to an existing installation, follow [update.md](update.md). Do not manually remove the extension; the installer handles replacement and backup.

## 3. Verify

After the script completes, from the panel directory:

```bash
php artisan route:list --path=pterodactyllocation
```

The installer handles extension registration and the frontend rebuild. The default resolver is Cloudflare; change it optionally under Admin Panel > Extensions > Pterodactyl Location. In a server console, check these cases:

- Public IPv4 or IPv6 allocation: country and optional city.
- `0.0.0.0`, private IP or loopback allocation: LocalHost with a server icon.
- Missing allocation: No allocation.
- Provider outage or exhausted quota: Location unavailable, while other console statistics continue working.
- Switching between servers while a lookup is pending: no stale region from the previous server.
- An unauthenticated user or a user without access to the server cannot access its region endpoint.

DNS is used for hostname allocations. Public primary IPs are geolocated directly. Private/local primary allocations always use the node's SFTP hostname for an approximate region, even if a server-specific display alias is configured. The panel hostname and browser address are never used, and the statistic labels this as an SFTP node location.

## Troubleshooting

- **Blueprint command not found:** run the installer as `sudo bash main.sh /absolute/path/to/pterodactyl` or ensure the panel's `blueprint` CLI is in `PATH`.
- **404 on region requests:** verify the extension is installed in Blueprint and clear the route cache with `php artisan optimize:clear`.
- **Quad9 unavailable:** verify PHP cURL supports HTTP/2 and outbound HTTPS to `dns.quad9.net`. All DoH requests explicitly use HTTP/2.
- **No country flag:** the Blueprint component uses a globe icon and does not install assets into the panel's shared asset directory.
- **An error persists briefly:** failed lookups are cached for 60 seconds. Successful IP lookups are cached for 24 hours; hostname results for 5 minutes.
- **A private node shows LocalHost:** expected if the node's SFTP hostname does not resolve to a public address.
- **Settings from an older installation:** Google and Cloudflare names remain supported. Unsupported resolver values safely use Cloudflare. Save settings to migrate the old IP provider value to API V2.