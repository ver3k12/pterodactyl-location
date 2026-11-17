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

for command_name in curl tar zip sed grep perl; do
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

BACKUP_DIR="$PANEL_DIR/storage/app/pterodactyl-location-backups/$(date +%Y%m%d-%H%M%S)"

ensure_console_hook_base() {
    local console_file="$PANEL_DIR/resources/scripts/components/server/console/ServerDetailsBlock.tsx"
    local hook_file="$PANEL_DIR/resources/scripts/blueprint/components/Server/Terminal/AfterInformation.tsx"

    if [[ ! -f "$console_file" ]]; then
        printf 'Error: expected console component not found: %s\n' "$console_file" >&2
        return 1
    fi

    if ! grep -Fq "import AfterInformation from '@blueprint/components/Server/Terminal/AfterInformation';" "$console_file" \
        || ! grep -Fq '<AfterInformation />' "$console_file"; then
        if ! grep -Fq "import { capitalize } from '@/lib/strings';" "$console_file" \
            || ! grep -Fq "title={'Network (Outbound)'}" "$console_file"; then
            printf 'Error: this panel console file does not match Pterodactyl 1.15.1; leaving it unchanged.\n' >&2
            return 1
        fi

        local console_backup="$BACKUP_DIR/resources/scripts/components/server/console/ServerDetailsBlock.tsx"
        mkdir -p "$(dirname "$console_backup")"
        cp -a "$console_file" "$console_backup"

        if ! grep -Fq "import AfterInformation from '@blueprint/components/Server/Terminal/AfterInformation';" "$console_file"; then
            perl -0pi -e 's|import \{ capitalize \} from \x27\@/lib/strings\x27;|$&\nimport AfterInformation from \x27\@blueprint/components/Server/Terminal/AfterInformation\x27;|' "$console_file"
        fi
        if ! grep -Fq '<AfterInformation />' "$console_file"; then
            perl -0pi -e 's|(<StatBlock icon=\{faCloudUploadAlt\} title=\{\x27Network \(Outbound\)\x27\}>.*?</StatBlock>)(\s*</div>)|$1\n            <AfterInformation />\n$2|s' "$console_file"
        fi

        if ! grep -Fq "import AfterInformation from '@blueprint/components/Server/Terminal/AfterInformation';" "$console_file" \
            || ! grep -Fq '<AfterInformation />' "$console_file"; then
            cp -a "$console_backup" "$console_file"
            printf 'Error: could not safely add the Blueprint console hook; the original file was restored.\n' >&2
            return 1
        fi
        printf 'Added missing Blueprint console hook; original file backed up to: %s\n' "$console_backup"
    fi

    if [[ ! -f "$hook_file" ]]; then
        mkdir -p "$(dirname "$hook_file")"
        printf '%s\n' \
            "import React from 'react';" \
            '' \
            '/* blueprint/import */' \
            '' \
            'export default () => (' \
            '    <>' \
            '        {/* blueprint/react */}' \
            '    </>' \
            ');' > "$hook_file"
    fi

    if ! grep -Fq '/* blueprint/import */' "$hook_file" || ! grep -Fq '{/* blueprint/react */}' "$hook_file"; then
        printf 'Error: Blueprint console hook template is missing its component markers: %s\n' "$hook_file" >&2
        return 1
    fi
}

ensure_console_hook_base

if [[ -e "$PANEL_DIR/$EXTENSION_ID.blueprint" ]]; then
    mkdir -p "$BACKUP_DIR"
    cp -a "$PANEL_DIR/$EXTENSION_ID.blueprint" "$BACKUP_DIR/"
    printf 'Previous extension package backed up to: %s\n' "$BACKUP_DIR/$EXTENSION_ID.blueprint"
fi

PACKAGE_IN_PANEL="$PANEL_DIR/$EXTENSION_ID.blueprint"
cp "$PACKAGE_FILE" "$PACKAGE_IN_PANEL"

INSTALLED_FILE="$PANEL_DIR/.blueprint/extensions/blueprint/private/db/installed_extensions"
if [[ -f "$INSTALLED_FILE" ]] && grep -Fq "|$EXTENSION_ID," "$INSTALLED_FILE"; then
    EXTENSION_STATE="$PANEL_DIR/.blueprint/extensions/$EXTENSION_ID"
    if [[ -d "$EXTENSION_STATE" ]]; then
        mkdir -p "$BACKUP_DIR"
        cp -a "$EXTENSION_STATE" "$BACKUP_DIR/blueprint-extension"
        printf 'Previous installed extension backed up to: %s\n' "$BACKUP_DIR/blueprint-extension"
    fi

    printf 'Removing the previous Blueprint extension before a clean install...\n'
    (
        cd "$PANEL_DIR"
        USER_CONFIRMED_REMOVAL=yes "$BLUEPRINT_COMMAND" -remove "$EXTENSION_ID"
    )
fi

printf 'Installing Blueprint extension and rebuilding the panel frontend...\n'
(
    cd "$PANEL_DIR"
    "$BLUEPRINT_COMMAND" -install "$EXTENSION_ID"
)

HOOK_FILE="$PANEL_DIR/resources/scripts/blueprint/components/Server/Terminal/AfterInformation.tsx"
COMPONENT_IMPORT="import PterodactyllocationComponent from '@blueprint/extensions/pterodactyllocation/RegionLocation';"
if ! grep -Fq "$COMPONENT_IMPORT" "$HOOK_FILE" || ! grep -Fq '<PterodactyllocationComponent />' "$HOOK_FILE"; then
    printf 'Blueprint did not inject the declared component; applying the extension slot and rebuilding once...\n'
    mkdir -p "$BACKUP_DIR"
    cp -a "$HOOK_FILE" "$BACKUP_DIR/AfterInformation.tsx"
    if ! grep -Fq "$COMPONENT_IMPORT" "$HOOK_FILE"; then
        perl -0pi -e 's~/\* blueprint/import \*/~/* blueprint/import */\nimport PterodactyllocationComponent from \x27\@blueprint/extensions/pterodactyllocation/RegionLocation\x27;~' "$HOOK_FILE"
    fi
    if ! grep -Fq '<PterodactyllocationComponent />' "$HOOK_FILE"; then
        perl -0pi -e 's~\{\s*/\*\s*blueprint/react\s*\*/\s*\}~{/* blueprint/react */}\n        <PterodactyllocationComponent />~' "$HOOK_FILE"
    fi
    if ! grep -Fq "$COMPONENT_IMPORT" "$HOOK_FILE" || ! grep -Fq '<PterodactyllocationComponent />' "$HOOK_FILE"; then
        printf 'Error: could not inject the Region component into the Blueprint slot.\n' >&2
        exit 1
    fi
    (
        cd "$PANEL_DIR"
        yarn run build:production --progress
        php artisan optimize:clear
    )
fi

printf '\nPterodactyl Location was installed through Blueprint.\n'
printf 'Open Admin Panel > Extensions > Pterodactyl Location to configure the DNS resolver.\n'