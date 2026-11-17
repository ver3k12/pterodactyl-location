#!/usr/bin/env bash

set -Eeuo pipefail

readonly ARCHIVE_URL="https://codeload.github.com/ver3k12/pterodactyl-location/tar.gz/refs/heads/main"
readonly EXTENSION_ID="pterodactyllocation"

PANEL_DIR="${1:-}"
if [[ -z "$PANEL_DIR" ]]; then
    read -r -p "Path to the Pterodactyl panel: " PANEL_DIR
fi

if [[ -z "$PANEL_DIR" || ! -d "$PANEL_DIR" ]]; then
    printf 'Error: panel directory does not exist: %s\n' "$PANEL_DIR" >&2
    exit 1
fi

PANEL_DIR="$(cd "$PANEL_DIR" && pwd -P)"
if [[ ! -f "$PANEL_DIR/routes/api-client.php" || ! -f "$PANEL_DIR/artisan" ]]; then
    printf 'Error: %s does not look like a Pterodactyl panel directory.\n' "$PANEL_DIR" >&2
    exit 1
fi

if [[ -x "$PANEL_DIR/blueprint" ]]; then
    BLUEPRINT_COMMAND="$PANEL_DIR/blueprint"
elif command -v blueprint >/dev/null 2>&1; then
    BLUEPRINT_COMMAND="$(command -v blueprint)"
else
    printf 'Error: Blueprint CLI was not found. Run this installer from a panel with Blueprint installed.\n' >&2
    exit 1
fi

for command_name in curl tar zip sed; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Error: required command not found: %s\n' "$command_name" >&2
        exit 1
    fi
done

WORK_DIR="$(mktemp -d)"
PACKAGE_IN_PANEL=""
cleanup() {
    if [[ -n "$PACKAGE_IN_PANEL" ]]; then
        rm -f "$PACKAGE_IN_PANEL"
    fi
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

ADDON_DIR=""
if [[ -n "${BASH_SOURCE[0]:-}" && -f "${BASH_SOURCE[0]}" ]]; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
    if [[ -d "$SCRIPT_DIR/resources" && -d "$SCRIPT_DIR/blueprint-extension" ]]; then
        ADDON_DIR="$SCRIPT_DIR"
    fi
fi

if [[ -z "$ADDON_DIR" ]]; then
    printf 'Downloading addon source...\n'
    curl -fsSL --retry 3 "$ARCHIVE_URL" -o "$WORK_DIR/addon.tar.gz"
    mkdir "$WORK_DIR/source"
    tar -xzf "$WORK_DIR/addon.tar.gz" --strip-components=1 -C "$WORK_DIR/source"
    ADDON_DIR="$WORK_DIR/source"
fi

for source_file in \
    blueprint-extension/conf.yml \
    blueprint-extension/admin/controller.php \
    blueprint-extension/admin/view.blade.php \
    blueprint-extension/dashboard/components/Components.yml \
    blueprint-extension/dashboard/components/RegionLocation.tsx \
    blueprint-extension/routes/client.php \
    resources/RegionLookupService.php \
    resources/ServerRegionController.php; do
    if [[ ! -f "$ADDON_DIR/$source_file" ]]; then
        printf 'Error: extension source file is missing: %s\n' "$source_file" >&2
        exit 1
    fi
done

PACKAGE_DIR="$WORK_DIR/package"
mkdir -p "$PACKAGE_DIR/app" "$PACKAGE_DIR/dashboard/components" "$PACKAGE_DIR/routes"
cp -R "$ADDON_DIR/blueprint-extension/." "$PACKAGE_DIR/"

sed 's|^namespace Pterodactyl\\Services\\Region;$|namespace Pterodactyl\\BlueprintFramework\\Extensions\\pterodactyllocation;|' \
    "$ADDON_DIR/resources/RegionLookupService.php" > "$PACKAGE_DIR/app/RegionLookupService.php"
sed \
    -e 's|^namespace Pterodactyl\\Http\\Controllers\\Api\\Client\\Servers;$|namespace Pterodactyl\\BlueprintFramework\\Extensions\\pterodactyllocation;|' \
    -e 's|^use Pterodactyl\\Services\\Region\\RegionLookupService;$|use Pterodactyl\\BlueprintFramework\\Extensions\\pterodactyllocation\\RegionLookupService;|' \
    "$ADDON_DIR/resources/ServerRegionController.php" > "$PACKAGE_DIR/app/ServerRegionController.php"

PACKAGE_FILE="$WORK_DIR/$EXTENSION_ID.blueprint"
(
    cd "$PACKAGE_DIR"
    zip -qr "$PACKAGE_FILE" .
)

if [[ ! -s "$PACKAGE_FILE" ]]; then
    printf 'Error: failed to create the Blueprint package.\n' >&2
    exit 1
fi

if [[ -e "$PANEL_DIR/$EXTENSION_ID.blueprint" ]]; then
    BACKUP_DIR="$PANEL_DIR/storage/app/pterodactyl-location-backups/$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    cp -a "$PANEL_DIR/$EXTENSION_ID.blueprint" "$BACKUP_DIR/"
    printf 'Previous extension package backed up to: %s\n' "$BACKUP_DIR/$EXTENSION_ID.blueprint"
fi

PACKAGE_IN_PANEL="$PANEL_DIR/$EXTENSION_ID.blueprint"
cp "$PACKAGE_FILE" "$PACKAGE_IN_PANEL"
printf 'Installing Blueprint extension and rebuilding the panel frontend...\n'
(
    cd "$PANEL_DIR"
    "$BLUEPRINT_COMMAND" -install "$EXTENSION_ID"
)

printf '\nPterodactyl Location was installed through Blueprint.\n'
printf 'Open Admin Panel > Extensions > Pterodactyl Location to configure the DNS resolver.\n'