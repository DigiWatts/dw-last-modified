#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/.."

PLUGIN_FILE="dw-last-modified.php"

# -------------------------------------
# Determine version
# -------------------------------------
# Prefer .release-please-manifest.json, which release-please bumps to the new
# version inside its release PR. `git describe` returns the most recent
# *reachable* tag, which at PR time is the previous release - using it would
# bake the old version into the released zip.
VERSION=""
if [ -f .release-please-manifest.json ]; then
	if command -v jq >/dev/null 2>&1; then
		VERSION=$(jq -r '.["."] // empty' .release-please-manifest.json 2>/dev/null)
	else
		VERSION=$(grep -oE '"\.": *"[^"]+"' .release-please-manifest.json | head -1 | sed -E 's/.*"([^"]+)"$/\1/')
	fi
fi

if [ -z "$VERSION" ] || [ "$VERSION" = "null" ]; then
	if VERSION_RAW=$(git describe --tags --abbrev=0 2>/dev/null); then
		VERSION="${VERSION_RAW#v}"
	else
		echo "[ERROR] Could not determine a version from the manifest or from git tags"
		exit 2
	fi
fi

echo "Setting plugin version to $VERSION"

if [ ! -f "./$PLUGIN_FILE" ]; then
	echo "[ERROR] ./$PLUGIN_FILE not found!"
	exit 2
fi

# ------------------------------------------------
# Update version fields
# ------------------------------------------------
# The plugin header is a plain /* */ block with tab-indented fields, so the
# header line is matched as leading whitespace followed by `Version:`.
if [[ "$(uname)" == "Darwin" ]]; then
	SED_INPLACE=(-i '')
else
	SED_INPLACE=(-i)
fi

sed "${SED_INPLACE[@]}" -E "s/^([[:space:]]*)Version:[[:space:]]*.*/\1Version: $VERSION/" "./$PLUGIN_FILE"
sed "${SED_INPLACE[@]}" "s/define( 'DW_LAST_MODIFIED_VERSION', '[^']*' );/define( 'DW_LAST_MODIFIED_VERSION', '$VERSION' );/" "./$PLUGIN_FILE"

if [ -f ./readme.txt ]; then
	sed "${SED_INPLACE[@]}" -E "s/^Stable tag:[[:space:]]*.*/Stable tag: $VERSION/" ./readme.txt
fi

# ------------------------------------------------
# Verify - the update proxy reads the header, so a silent miss ships a
# plugin that never reports an update as available.
# ------------------------------------------------
if ! grep -Eq "^[[:space:]]*Version:[[:space:]]*$VERSION([[:space:]]|$)" "./$PLUGIN_FILE"; then
	echo "[ERROR] Failed to update the Version header in $PLUGIN_FILE"
	grep -nE "Version:" "./$PLUGIN_FILE" || true
	exit 3
fi

if ! grep -q "define( 'DW_LAST_MODIFIED_VERSION', '$VERSION' );" "./$PLUGIN_FILE"; then
	echo "[ERROR] Failed to update DW_LAST_MODIFIED_VERSION in $PLUGIN_FILE"
	exit 3
fi

echo "Updated $PLUGIN_FILE to version $VERSION"
