#!/usr/bin/env bash
# Builds an installable ZIP (Plugins → Add New → Upload) without development files.
set -euo pipefail
cd "$(dirname -- "${BASH_SOURCE[0]}")/.."
version="$(sed -n 's/^ \* Version: *//p' oldbear-elementor-expansion.php)"
slug=oldbear-elementor-expansion
rm -rf dist && mkdir -p "dist/$slug"
cp -R oldbear-elementor-expansion.php includes templates assets LICENSE README.md "dist/$slug/"
(cd dist && zip -qr "$slug-$version.zip" "$slug" && rm -rf "$slug")
echo "dist/$slug-$version.zip"
