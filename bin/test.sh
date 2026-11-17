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

# Clean mode: no locked Pro widgets are injected into the editor config.
wp eval 'echo json_encode(\Elementor\Api::get_promotion_widgets());' | grep -qx '\[\]' || { echo "FALLO: el modo limpio no vacía los widgets promocionales"; exit 1; }
echo "OK modo limpio"

# Coexistence with Elementor Pro (simulated through the obx/pro_active filter).
docker compose run --rm -T cli sh -c "mkdir -p wp-content/mu-plugins && printf '%s\n' '<?php' \"add_filter('obx/pro_active', '__return_true');\" > wp-content/mu-plugins/obx-simulate-pro.php"
trap 'docker compose run --rm -T cli rm -f wp-content/mu-plugins/obx-simulate-pro.php >/dev/null 2>&1; rm -rf "$tmp"' EXIT
wp eval '$w = \Elementor\Plugin::$instance->widgets_manager->get_widget_types(); exit(isset($w["obx-nav-menu"]) && !\Elementor\Plugin::$instance->documents->get_document_type("obx-header", false) ? 0 : 1);' \
    || { echo "FALLO: con Pro activo deben quedar los widgets y ceder el Theme Builder"; exit 1; }
got="$(fetch /hola-desde-la-madriguera/)"
test "$got" = 200 && ! grep -q 'id="obx-header"' "$tmp/page.html" && ! grep -qE 'Fatal error|Warning:' "$tmp/page.html" \
    || { echo "FALLO: convivencia con Pro"; exit 1; }
docker compose run --rm -T cli rm -f wp-content/mu-plugins/obx-simulate-pro.php >/dev/null 2>&1
echo "OK convivencia con Elementor Pro (simulada)"
echo "Todas las comprobaciones HTTP han pasado."
