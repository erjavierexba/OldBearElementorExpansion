#!/usr/bin/env bash
# Starts the development site with Elementor Free, Hello Elementor and demo templates.
set -euo pipefail
cd "$(dirname -- "${BASH_SOURCE[0]}")/.."
port="${OBX_PORT:-8088}"
elementor="${OBX_ELEMENTOR_VERSION:-3.34.2}"
export OBX_PORT="$port"
wp() { docker compose run --rm -T cli wp "$@"; }

docker compose up -d db wordpress
for _ in $(seq 1 40); do wp core version >/dev/null 2>&1 && break; sleep 2; done
if ! wp core is-installed >/dev/null 2>&1; then
    password="${OBX_ADMIN_PASSWORD:-$(openssl rand -hex 12)}"
    wp core install --url="http://localhost:${port}" --title="OldBear Expansion Dev" --admin_user=admin \
        --admin_password="$password" --admin_email=admin@example.test --skip-email
    (umask 077; printf 'http://localhost:%s/wp-admin/\nUsuario: admin\nContraseña: %s\n' "$port" "$password" > .dev-access.txt)
fi
wp language core is-installed es_ES || wp language core install es_ES
wp site switch-language es_ES
wp plugin is-installed elementor || wp plugin install elementor --version="$elementor"
wp theme is-installed hello-elementor || wp theme install hello-elementor
wp theme activate hello-elementor
wp plugin activate elementor oldbear-elementor-expansion
wp language plugin install elementor es_ES || true
wp language theme install hello-elementor es_ES || true
wp rewrite structure '/%postname%/'
wp rewrite flush
wp eval-file wp-content/plugins/oldbear-elementor-expansion/dev/seed.php
printf '\nListo: http://localhost:%s/  ·  Panel: http://localhost:%s/wp-admin/  ·  Acceso en .dev-access.txt\n' "$port" "$port"
