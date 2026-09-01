#!/usr/bin/env bash
# Prepare (or repair) a local WordPress test site for the Playwright e2e suite.
#
# Requires: docker, wp-cli, php >= 7.4 with mysqli.
#
#   WP_SITE_DIR=/path/to/site WP_PORT=8877 bash scripts/e2e-setup.sh
#
# Idempotent: skips steps that are already in place. The fixture mu-plugin
# seeds itself on first request; the Playwright global setup resets meta
# before each test.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_SITE_DIR="${WP_SITE_DIR:-/tmp/wp-site-mudrava-e2e}"
WP_VERSION="${WP_VERSION:-latest}"
WP_PORT="${WP_PORT:-8877}"
DB_NAME="${DB_NAME:-wp}"
DB_USER="${DB_USER:-wp}"
DB_PASS="${DB_PASS:-wp}"
DB_ROOT_PASS="${DB_ROOT_PASS:-root}"
DB_PORT="${DB_PORT:-13306}"
ADMIN_PASS="${ADMIN_PASS:-adminpass123}"
CONTAINER="${CONTAINER:-mudrava-wp-mysql}"
PLUGIN_SLUG="$(basename "$REPO")"
WP="wp --allow-root"

mkdir -p "$WP_SITE_DIR"

# If a database is already listening on the port (e.g. a CI service container),
# reuse it instead of starting our own to avoid host port collisions.
external_db=0
if (exec 3<>"/dev/tcp/127.0.0.1/$DB_PORT") 2>/dev/null; then
	exec 3>&- 3<&-
	external_db=1
fi

if [ "$external_db" -eq 1 ]; then
	echo "Using external database on 127.0.0.1:$DB_PORT"
else
	if docker ps -a --format '{{.Names}}' | grep -qx "$CONTAINER"; then
		docker ps --format '{{.Names}}' | grep -qx "$CONTAINER" || docker start "$CONTAINER" >/dev/null
	else
		docker run -d --name "$CONTAINER" \
			-e MYSQL_ROOT_PASSWORD="$DB_ROOT_PASS" \
			-e MYSQL_DATABASE="$DB_NAME" \
			-e MYSQL_USER="$DB_USER" \
			-e MYSQL_PASSWORD="$DB_PASS" \
			-p "$DB_PORT:3306" mariadb:11 >/dev/null
	fi

	ready=0
	for _ in $(seq 1 30); do
		if docker exec "$CONTAINER" mariadb-admin -uroot -p"$DB_ROOT_PASS" ping >/dev/null 2>&1; then
			ready=1
			break
		fi
		sleep 1
	done

	if [ "$ready" -ne 1 ]; then
		echo "MySQL container $CONTAINER not ready after 30s" >&2
		exit 1
	fi
fi

if [ ! -f "$WP_SITE_DIR/wp-settings.php" ]; then
	$WP core download --version="$WP_VERSION" --path="$WP_SITE_DIR"
fi

if [ ! -f "$WP_SITE_DIR/wp-config.php" ]; then
	$WP config create --path="$WP_SITE_DIR" --dbname="$DB_NAME" --dbuser="$DB_USER" \
		--dbpass="$DB_PASS" --dbhost="127.0.0.1:$DB_PORT" --dbprefix=wp_
fi

if ! $WP core is-installed --path="$WP_SITE_DIR" >/dev/null 2>&1; then
	$WP db create --path="$WP_SITE_DIR" || true
	$WP core install --path="$WP_SITE_DIR" \
		--url="http://127.0.0.1:$WP_PORT" --title="Mudrava E2E" \
		--admin_user=admin --admin_password="$ADMIN_PASS" \
		--admin_email=admin@example.com --skip-email
	$WP option update permalink_structure '/%postname%/' --path="$WP_SITE_DIR"
	$WP rewrite structure '/%postname%/' --path="$WP_SITE_DIR"
fi

if ! $WP plugin list --path="$WP_SITE_DIR" --field=name | grep -qx advanced-custom-fields; then
	$WP plugin install advanced-custom-fields --activate --path="$WP_SITE_DIR"
fi

MUDRAVA_SITE_DIR="$WP_SITE_DIR" bash "$REPO/scripts/sync-live.sh"
$WP plugin activate "$PLUGIN_SLUG" --path="$WP_SITE_DIR" >/dev/null

mkdir -p "$WP_SITE_DIR/wp-content/mu-plugins"
cp "$REPO/tests/e2e/fixtures/mudrava-e2e-fixture.php" "$WP_SITE_DIR/wp-content/mu-plugins/"

pkill -f "php -S 127.0.0.1:$WP_PORT" >/dev/null 2>&1 || true
sleep 1
nohup php -d memory_limit=2G -S "127.0.0.1:$WP_PORT" -t "$WP_SITE_DIR" "$REPO/tests/e2e/fixtures/router.php" \
	>"${TMPDIR:-/tmp}/mudrava-e2e-php.log" 2>&1 &

http_ready=0
for _ in $(seq 1 20); do
	if curl -fsS "http://127.0.0.1:$WP_PORT/" >/dev/null; then
		http_ready=1
		break
	fi
	sleep 1
done

if [ "$http_ready" -ne 1 ]; then
	echo "HTTP server did not come up on port $WP_PORT" >&2
	cat "${TMPDIR:-/tmp}/mudrava-e2e-php.log" >&2 || true
	exit 1
fi

echo "Site ready at http://127.0.0.1:$WP_PORT"
echo "Run: WP_BASE_URL=http://127.0.0.1:$WP_PORT WP_SITE_PATH=$WP_SITE_DIR npx playwright test"
