#!/usr/bin/env bash
# Builds the clean deployment ZIP (no data, no uploads, no tests): dist/eshop-saas-site-php.zip
set -e
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist/pkg
for f in index.php cron.php .htaccess README.md app assets; do cp -r "$f" dist/pkg/; done
mkdir -p dist/pkg/data dist/pkg/uploads
cp data/.htaccess data/index.html dist/pkg/data/
cp uploads/.htaccess uploads/index.html dist/pkg/uploads/
find dist/pkg -name '.DS_Store' -delete
(cd dist/pkg && zip -qr -X ../eshop-saas-site-php.zip . -x '*.sqlite*')
rm -rf dist/pkg
ls -la dist/eshop-saas-site-php.zip
