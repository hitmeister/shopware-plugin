#!/usr/bin/env bash

FILENAME="HitmeMarketplace-1.0.2.zip"

mkdir -p ../tmp-zip/Backend
cp -r ../HitmeMarketplace ../tmp-zip/Backend/
rm -rf ../tmp-zip/Backend/HitmeMarketplace/.git
rm -rf ../tmp-zip/Backend/HitmeMarketplace/zip.sh
cd ../tmp-zip/
zip -r $FILENAME Backend
mv $FILENAME ../
cd ../HitmeMarketplace
rm -r ../tmp-zip
