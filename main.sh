#!/usr/bin/env bash

set -Eeuo pipefail

readonly ARCHIVE_URL="https://codeload.github.com/ver3k12/pterodactyl-location/tar.gz/refs/heads/main"

PANEL_DIR="${1:-}"
if [[ -z "$PANEL_DIR" ]]; then
    read -r -p "Path to the Pterodactyl panel: " PANEL_DIR
fi

if [[ -z "$PANEL_DIR" || ! -d "$PANEL_DIR" ]]; then
    printf 'Error: panel directory does not exist: %s\n' "$PANEL_DIR" >&2
    exit 1
fi

PANEL_DIR="$(cd "$PANEL_DIR" && pwd -P)"
if [[ ! -f "$PANEL_DIR/routes/api-client.php" || ! -f "$PANEL_DIR/routes/admin.php" ]]; then
    printf 'Error: %s does not look like a Pterodactyl panel directory.\n' "$PANEL_DIR" >&2
    exit 1
fi

for command_name in curl tar; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Error: required command not found: %s\n' "$command_name" >&2
        exit 1
    fi
done

WORK_DIR="$(mktemp -d)"
cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

ADDON_DIR=""
if [[ -n "${BASH_SOURCE[0]:-}" && -f "${BASH_SOURCE[0]}" ]]; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
    if [[ -d "$SCRIPT_DIR/resources" ]]; then
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
    resources/RegionLookupService.php \
    resources/ServerRegionController.php \
    resources/PterodactylRegionController.php \
    resources/PterodactylRegionSettingsFormRequest.php \
    resources/pterodactyl-region.blade.php \
    resources/ServerDetailsBlock.tsx \
    resources/RegionStatBlock.tsx; do
    if [[ ! -f "$ADDON_DIR/$source_file" ]]; then
        printf 'Error: addon source file is missing: %s\n' "$source_file" >&2
        exit 1
    fi
done

BACKUP_DIR="$PANEL_DIR/storage/app/pterodactyl-location-backups/$(date +%Y%m%d-%H%M%S)"
backup_existing() {
    local relative_path="$1"
    if [[ -e "$PANEL_DIR/$relative_path" ]]; then
        mkdir -p "$BACKUP_DIR/$(dirname "$relative_path")"
        cp -a "$PANEL_DIR/$relative_path" "$BACKUP_DIR/$relative_path"
    fi
}

copy_file() {
    local source_path="$1"
    local relative_path="$2"
    backup_existing "$relative_path"
    mkdir -p "$PANEL_DIR/$(dirname "$relative_path")"
    cp -p "$ADDON_DIR/$source_path" "$PANEL_DIR/$relative_path"
    printf 'Installed %s\n' "$relative_path"
}

copy_file resources/RegionLookupService.php app/Services/Region/RegionLookupService.php
copy_file resources/ServerRegionController.php app/Http/Controllers/Api/Client/Servers/ServerRegionController.php
copy_file resources/PterodactylRegionController.php app/Http/Controllers/Admin/Settings/PterodactylRegionController.php
copy_file resources/PterodactylRegionSettingsFormRequest.php app/Http/Requests/Admin/Settings/PterodactylRegionSettingsFormRequest.php
copy_file resources/pterodactyl-region.blade.php resources/views/admin/settings/pterodactyl-region.blade.php
copy_file resources/ServerDetailsBlock.tsx resources/scripts/components/server/console/ServerDetailsBlock.tsx
copy_file resources/RegionStatBlock.tsx resources/scripts/components/server/console/RegionStatBlock.tsx

mkdir -p "$PANEL_DIR/resources/scripts/assets/regions"
for source_path in "$ADDON_DIR"/resources/regions/*.svg; do
    relative_path="resources/scripts/assets/regions/$(basename "$source_path")"
    backup_existing "$relative_path"
    cp -p "$source_path" "$PANEL_DIR/$relative_path"
done
printf 'Installed region flag assets.\n'

printf '\nAddon files installed in: %s\n' "$PANEL_DIR"
if [[ -d "$BACKUP_DIR" ]]; then
    printf 'Previous files backed up to: %s\n' "$BACKUP_DIR"
fi

cat <<'EOF'

Next steps:
1. Register the client and admin routes as described in INSTALL.md.
2. If you have customized ServerDetailsBlock.tsx, merge your changes from the backup before building.
3. Run the build and verification commands from INSTALL.md.

The installer does not modify route files or run Composer/Yarn commands.
EOF