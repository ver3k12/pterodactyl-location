# Updating Pterodactyl Location

This procedure updates an existing Blueprint installation. The current package version is 3.0.5. The installer keeps the extension identifier (`pterodactyllocation`), backs up and removes the old Blueprint-managed files, then installs the latest package. DNS settings are stored by the panel and are preserved.

## 1. Back Up the Panel

Back up the panel files and database using your normal backup process. The installer saves the old `.blueprint` package and installed extension state, if present, under `storage/app/pterodactyl-location-backups/` before replacing it.

## 2. Run the Updated Installer

Download and review the current installer, then run it against the same panel directory used for the original installation:

```bash
curl -fsSLo /tmp/pterodactyl-location-main.sh https://raw.githubusercontent.com/ver3k12/pterodactyl-location/main/main.sh
less /tmp/pterodactyl-location-main.sh
sudo bash /tmp/pterodactyl-location-main.sh /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with the panel's absolute path. The installer backs up and removes the old Blueprint extension, then installs the latest package with the same identifier. If the console hook is missing, it backs up `ServerDetailsBlock.tsx` and restores the Blueprint hook before rebuilding. If Blueprint does not inject the Region component from `Components.yml`, the installer adds it to the hook and rebuilds once. The DNS resolver is stored in the panel's settings table and is not removed with the Blueprint extension. This clean reinstall should leave one extension entry. Version 3.0.3 added public alias fallback, 3.0.4 added country flags, and 3.0.5 adds an SFTP-node location fallback for private allocations without a usable alias.

Do not run `blueprint -remove pterodactyllocation` before updating. Removing it first is unnecessary and may discard extension-managed files.

## 3. Verify the Update

From the panel directory, confirm the route is registered:

```bash
php artisan route:list --path=pterodactyllocation
```

Then hard-refresh the browser, open a server console, and check that the Region statistic is visible. The DNS resolver setting remains available under Admin Panel > Extensions > Pterodactyl Location.

If the extension page appears but the Region statistic does not, confirm Blueprint completed the frontend build without errors, then hard-refresh again. Do not copy the plugin files over Nebula or the panel's React files; the extension is integrated through Blueprint components.