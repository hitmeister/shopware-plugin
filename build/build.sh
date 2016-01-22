#!/usr/bin/env bash

SRC_DIR=".."
DIST_DIR="Backend/HitmeMarketplace"
IGNORE_LIST=(".git" ".gitignore" "build" "vendor" "composer.json" "composer.lock")

rm -rf "${DIST_DIR}"
rm -rf "HitmeMarketplace.zip"

echo "Destination directory created."
mkdir -p "${DIST_DIR}"

echo "Looking for files ..."
for file in `ls "${SRC_DIR}"`; do
    if [[ " ${IGNORE_LIST[@]} " =~ " ${file} " ]]; then
        echo "Skipped: ${file}"
        continue
    fi
    cp -rf "${SRC_DIR}/${file}" "${DIST_DIR}/"
    echo "Copied: ${file}"
done

echo "Copying SDK..."
if [ ! -d "${SRC_DIR}/vendor/hitmeister/api-sdk/src" ]; then
    echo "SDK not found, please run 'compoer install'"
    exit 1
fi

mkdir -p "${DIST_DIR}/Lib"
cp -rf "${SRC_DIR}/vendor/hitmeister/api-sdk/src" "${DIST_DIR}/Lib/Api"
echo "SDK copied."

echo "Making ZIP..."
zip -r HitmeMarketplace.zip Backend
rm -rf Backend
