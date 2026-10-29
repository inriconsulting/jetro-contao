#!/bin/sh
# Echtes Contao mit MySQL: Paket installieren, Webserver starten, Vertrag über HTTP prüfen.
# Erwartet: CONTAO (z. B. 5.3.*), DATABASE_URL, Paket unter /pkg bzw. $PKG.
set -eu
PKG=${PKG:-$(cd "$(dirname "$0")/../.." && pwd)}
APP=${APP:-/tmp/contao-app}
rm -rf "$APP"
composer create-project --no-interaction --no-progress --no-scripts "contao/managed-edition:${CONTAO}" "$APP" >/dev/null
cd "$APP"
composer config repositories.jetro "{\"type\":\"path\",\"url\":\"$PKG\",\"options\":{\"symlink\":false}}"
composer config minimum-stability dev
composer config prefer-stable true
composer require --no-interaction --no-progress --no-scripts "jetro-tools/contao-bundle:*" >/dev/null
printf 'DATABASE_URL=%s\nAPP_SECRET=integration\n' "$DATABASE_URL" > .env.local
php vendor/bin/contao-setup >/tmp/contao-setup.log 2>&1 || { cat /tmp/contao-setup.log; exit 1; }
php vendor/bin/contao-console contao:migrate --no-interaction --no-backup >/tmp/contao-migrate.log 2>&1 || { cat /tmp/contao-migrate.log; exit 1; }
php -S 127.0.0.1:8090 -t public public/index.php >/tmp/contao-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2
php "$PKG/tests/Integration/smoke.php" "$APP" http://127.0.0.1:8090
