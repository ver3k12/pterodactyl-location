# Updating Pterodactyl Location

The current Blueprint package version is **3.0.7**. The installer backs up and replaces the existing `pterodactyllocation` extension, preserves DNS settings stored by the panel, and rebuilds the frontend.

## Update

Back up the panel and database using your normal process. Download and review the installer, then run it against the same panel directory:

```bash
curl -fsSLo /tmp/pterodactyl-location-main.sh https://raw.githubusercontent.com/ver3k12/pterodactyl-location/main/main.sh
less /tmp/pterodactyl-location-main.sh
sudo bash /tmp/pterodactyl-location-main.sh /var/www/pterodactyl
```

Replace `/var/www/pterodactyl` with the panel's absolute path. The installer saves the old Blueprint package and extension state under `storage/app/pterodactyl-location-backups/`, then removes and reinstalls the extension with the same identifier. Do not remove the extension manually first.

If the console hook is missing, the installer backs up `ServerDetailsBlock.tsx` and restores the Blueprint hook before rebuilding. If Blueprint does not inject the Region component, the installer adds it to the hook and rebuilds once. Settings remain in the panel database.

## Location Source

A public primary allocation is geolocated directly. For a private allocation, the node's SFTP hostname is used even if a server-specific display alias is set. The Region statistic labels this as an SFTP node location. If the SFTP hostname cannot be resolved to a public address, it displays **LocalHost**.

## Verify

From the panel directory, confirm the route and hard-refresh a server console:

```bash
php artisan route:list --path=pterodactyllocation
```
