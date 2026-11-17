# Updating Pterodactyl Location

This procedure updates an existing Blueprint installation. Keep the same extension identifier (`pterodactyllocation`) and do not remove the extension first; Blueprint will recognize it as an update and preserve the DNS settings stored by the panel.

## 1. Back Up the Panel

Back up the panel files and database using your normal backup process. The installer also saves an existing `.blueprint` package, if present, under `storage/app/pterodactyl-location-backups/`.

## 2. Run the Updated Installer

Download and review the current installer, then run it against the same panel directory used for the original installation:

```bash
curl -fsSLo /tmp/pterodactyl-location-main.sh https://raw.githubusercontent.com/ver3k12/pterodactyl-location/main/main.sh
less /tmp/pterodactyl-location-main.sh
sudo bash /tmp/pterodactyl-location-main.sh /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with the panel's absolute path. The updated package is installed with the existing identifier. Blueprint updates the extension files, refreshes the API route and dashboard component, rebuilds the frontend, and clears its application caches.

Do not run `blueprint -remove pterodactyllocation` before updating. Removing it first is unnecessary and may discard extension-managed files.

## 3. Verify the Update

From the panel directory, confirm the route is registered:

```bash
php artisan route:list --path=pterodactyllocation
```

Then hard-refresh the browser, open a server console, and check that the Region statistic is visible. The DNS resolver setting remains available under Admin Panel > Extensions > Pterodactyl Location.

If the extension page appears but the Region statistic does not, confirm Blueprint completed the frontend build without errors, then hard-refresh again. Do not copy the plugin files over Nebula or the panel's React files; the extension is integrated through Blueprint components.