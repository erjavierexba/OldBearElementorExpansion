#!/usr/bin/env bash
# Lint, PHP integration tests and HTTP checks against the development site (run bin/setup.sh first).
set -euo pipefail
cd "$(dirname -- "${BASH_SOURCE[0]}")/.."
port="${OBX_PORT:-8088}"
base="http://localhost:${port}"
wp() { docker compose run --rm -T cli wp "$@"; }

docker compose exec -T wordpress sh -c 'cd wp-content/plugins/oldbear-elementor-expansion && find . -name "*.php" -not -path "./.git/*" -print0 | xargs -0 -n1 php -l' | grep -v '^No syntax errors' || true
wp eval-file wp-content/plugins/oldbear-elementor-expansion/tests/theme-builder.php

tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
fetch() { curl -s -o "$tmp/page.html" -w '%{http_code}' "$base$1"; }
check() { # path status needle...
    local path="$1" status="$2"; shift 2
    local got; got="$(fetch "$path")"
    test "$got" = "$status" || { echo "FALLO $path: HTTP $got (esperado $status)"; exit 1; }
    for needle in "$@"; do grep -q -- "$needle" "$tmp/page.html" || { echo "FALLO $path: falta «$needle»"; exit 1; }; done
    if grep -qE 'Fatal error|Warning:|Notice:|Deprecated:' "$tmp/page.html"; then echo "FALLO $path: errores PHP en la página"; grep -E 'Fatal error|Warning:|Notice:|Deprecated:' "$tmp/page.html" | head -3; exit 1; fi
    echo "OK $path"
}
check / 200 'id="obx-header"' 'elementor-widget-obx-nav-menu' 'id="obx-footer"' 'obx-demo-archive'
check /hola-desde-la-madriguera/ 200 'obx-demo-single' 'elementor-widget-obx-post-title' 'Hola desde la madriguera' 'obx-post-content'
check '/?s=madriguera' 200 'obx-demo-archive' 'Resultados de búsqueda'
check /category/diario/ 200 'obx-demo-archive' 'obx-posts'
check /no-existe-esta-pagina/ 404 'obx-demo-404'
echo "Todas las comprobaciones HTTP han pasado."
