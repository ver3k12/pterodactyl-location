[English](README.md) | [Русский](README_RU.md) | [Nederlands](README_NL.md)

# Pterodactyl Location 3.0.5

Server location statistics for Pterodactyl Panel. Based on Pterodactyl Region by the original authors credited in [LICENSE](LICENSE).

## Compatibility

Integration target: Pterodactyl Panel **1.15.1**, Blueprint **beta-2026-08**, PHP **8.2+**, Node.js **22+** and Yarn Classic. The client console hook and server-access middleware are compatible with Panel 1.15.1. Existing React, TypeScript, Font Awesome and Laravel dependencies come from the panel; this addon does not replace them with incompatible major versions.

PHP cURL with HTTP/2 support and mbstring are required. The panel must have outbound HTTPS access. This is a panel addon, not a standalone application.

## Changes

- Geolocation uses only [ipinfo.spacehost.ovh API V2](https://ipinfo.spacehost.ovh/docs), with an explicit IP and hostname lookups disabled.
- Requests run on the backend, avoiding browser CORS restrictions and sharing cache across users.
- Successful IP lookups are cached for 24 hours; hostname results for 5 minutes; errors for 1 minute. A 429 response puts API calls on a shared cooldown.
- DNS-over-HTTPS supports Google, Cloudflare (default), OpenDNS and Quad9, including IPv6-only hostnames. HTTP/2 is required by Quad9.
- Local, private, unspecified, link-local, CGNAT, multicast and reserved addresses display **LocalHost**, without contacting the geolocation API. This includes `0.0.0.0`, `127.0.0.1`, `::`, `::1`, IPv4-mapped IPv6, `localhost` and `.local` names.
- The lookup uses the primary allocation when it is public. For private/local allocations it uses a configured public alias, or falls back to the node's SFTP hostname; the label identifies this as node location. If neither is usable it displays **LocalHost**. The panel hostname and browser address are never used.
- Missing city names no longer produce an infinity symbol. Countries without a bundled flag use a globe icon.
- Timeouts, malformed DNS responses, missing records, API errors and rate limits produce a stable unavailable state. Failed DNS never falls back to auto-detecting an unrelated IP.
- Switching servers cannot apply stale responses to the new server. No IP addresses or lookup URLs are logged in the browser.
- Admin settings no longer depend on the previous author's update server.

`LocalHost` is a display label for non-public addresses, not a claim that the game server is running on the panel machine. Public IP geolocation is approximate, particularly for anycast addresses.

## Installation

The installer packages this addon as a Blueprint extension, registers its API and dashboard component, restores the console hook if a panel update removed it, bundles country flags in the extension's public directory, and rebuilds the frontend. If the extension is already installed, it backs up and removes the old Blueprint files before installing the new package; panel DNS settings are preserved. Download and review the script before running it; replace the panel path if needed:

```bash
curl -fsSLo /tmp/pterodactyl-location-main.sh https://raw.githubusercontent.com/ver3k12/pterodactyl-location/main/main.sh
less /tmp/pterodactyl-location-main.sh
sudo bash /tmp/pterodactyl-location-main.sh /var/www/pterodactyl
```

Back up the panel and database first. This requires Blueprint to be installed in the panel. See [INSTALL.md](INSTALL.md) for requirements and verification, and [update.md](update.md) to update an existing installation. Blueprint integration and installer edits are credited to `merv1k`; original authors remain credited in [LICENSE](LICENSE). Do not run the old remote installer; it installs a different version.

Existing `DNS-Google` and `DNS-Cloudflare` settings continue to work. Old IP provider selections are ignored; API V2 is always used. Open the admin settings page and save once to update the stored provider name.

## Tests

The root Composer manifest is **only a standalone test harness**. Never copy it or its lock file over the panel's own Composer files.

```bash
composer install
composer test
composer audit
```

Tests cover address classification, DNS wire responses, all resolver selections, API V2 mapping, shared cache, HTTP/2, rate limits, timeouts and prevention of accidental external lookups for local addresses.

## License

The existing [LICENSE](LICENSE) is unchanged and restricts modification and redistribution. Confirm permission with the rights holders before modifying, deploying modified code or redistributing it. No relicensing is implied by these changes.
