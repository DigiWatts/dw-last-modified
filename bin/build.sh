#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/.."

PLUGIN_SLUG="dw-last-modified"
BUILD_DIR="./build"
ZIP_NAME="${PLUGIN_SLUG}.zip"

# Stage inside a slug-named subdirectory so the archive has a top-level
# dw-last-modified/ folder, which is what WordPress's plugin updater expects
# when it replaces an installed plugin.
STAGE_DIR="$BUILD_DIR/$PLUGIN_SLUG"

rm -rf "$BUILD_DIR" "$ZIP_NAME"
mkdir -p "$STAGE_DIR"

# The plugin is a single file with no runtime dependencies, so there is
# deliberately no vendor/ in the distribution. composer/installers is only
# used to place the plugin when installed via composer, and phpunit is dev-only.
cp dw-last-modified.php "$STAGE_DIR/"
cp readme.txt "$STAGE_DIR/"

find "$STAGE_DIR" -name '.DS_Store' -delete

cd "$BUILD_DIR"
zip -q -r "../$ZIP_NAME" "$PLUGIN_SLUG"
cd ..

rm -rf "$BUILD_DIR"

# Fail loudly rather than uploading an empty release asset.
if [ ! -s "$ZIP_NAME" ]; then
	echo "[ERROR] $ZIP_NAME was not created"
	exit 1
fi

echo "Build complete: $ZIP_NAME"
unzip -l "$ZIP_NAME"
