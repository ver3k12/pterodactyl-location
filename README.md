[English](README.md) | [Русский](README_RU.md) | [Nederlands](README_NL.md)

# Pterodactyl Location 3.0.0

Server location statistics for Pterodactyl Panel. Based on Pterodactyl Region by the original authors credited in [LICENSE](LICENSE).

## Compatibility

Integration target: Pterodactyl Panel **1.15.2**, PHP **8.2+**, Node.js **22+** and Yarn Classic. Existing React, TypeScript, Font Awesome and Laravel dependencies come from the panel; this addon does not replace them with incompatible major versions.

PHP cURL with HTTP/2 support and mbstring are required. The panel must have outbound HTTPS access. This is a panel addon, not a standalone application.

## Changes

- Geolocation uses only [ipinfo.spacehost.ovh API V2](https://ipinfo.spacehost.ovh/docs), with an explicit IP and hostname lookups disabled.
- Requests run on the backend, avoiding browser CORS restrictions and sharing cache across users.
- Successful IP lookups are cached for 24 hours; hostname results for 5 minutes; errors for 1 minute. A 429 response puts API calls on a shared cooldown.
- DNS-over-HTTPS supports Google, Cloudflare (default), OpenDNS and Quad9, including IPv6-only hostnames. HTTP/2 is required by Quad9.
- Local, private, unspecified, link-local, CGNAT, multicast and reserved addresses display **LocalHost**, without contacting the geolocation API. This includes `0.0.0.0`, `127.0.0.1`, `::`, `::1`, IPv4-mapped IPv6, `localhost` and `.local` names.
- The lookup always uses the primary allocation, never the panel hostname, browser address or display alias. Private allocations behind NAT intentionally remain LocalHost.
- Missing city names no longer produce an infinity symbol. Countries without a bundled flag use a globe icon.
- Timeouts, malformed DNS responses, missing records, API errors and rate limits produce a stable unavailable state. Failed DNS never falls back to auto-detecting an unrelated IP.
- Switching servers cannot apply stale responses to the new server. No IP addresses or lookup URLs are logged in the browser.
- Admin settings no longer depend on the previous author's update server.

`LocalHost` is a display label for non-public addresses, not a claim that the game server is running on the panel machine. Public IP geolocation is approximate, particularly for anycast addresses.

## Installation

Follow [INSTALL.md](INSTALL.md). Back up your panel and database first, and merge component changes if you use a custom theme. Do not run the old remote installer; it installs a different version.

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
