#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_NAME="Foodeza ERP.app"
BUILD_DIR="$ROOT/desktop/src-tauri/target/release/bundle/macos"

echo "Building Foodeza ERP..."
cd "$ROOT"
npm run build:desktop

# Tauri may output to CARGO_TARGET_DIR; prefer project bundle path.
if [ ! -d "$BUILD_DIR/$APP_NAME" ]; then
  echo "Build finished but app not found at: $BUILD_DIR/$APP_NAME"
  exit 1
fi

echo "Installing to Applications..."
rm -rf "/Applications/$APP_NAME"
cp -R "$BUILD_DIR/$APP_NAME" "/Applications/$APP_NAME"

echo "Done. Open from Finder → Applications → Foodeza ERP"
