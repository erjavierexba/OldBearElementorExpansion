# OldBearElementorExpansion

**OldBear Expansion for Elementor** amplía Elementor Free con las herramientas de diseño de sitio completo: Theme Builder con condiciones de visualización y widgets de tema. Es código propio (GPL-2.0), construido sobre la API pública de Elementor. No usa ni contiene código de Elementor Pro y no requiere licencia.

> «Elementor» es una marca de Elementor Ltd. Este proyecto es independiente y no está afiliado a Elementor Ltd.

## Estado

| Fase | Funcionalidad | Estado |
| --- | --- | --- |
| 1 | Theme Builder: cabecera, pie, entrada individual, archivo y 404, con condiciones | ✅ v0.1.0 |
| 2 | Etiquetas dinámicas en los widgets nativos | Pendiente |
| 3 | Loop Grid con plantilla de tarjeta | Pendiente |
| 4 | Formularios | Pendiente |
| 5 | Popups | Pendiente |
| 6 | Extras: CSS personalizado, cabecera fija, efectos de scroll, carrusel… | Pendiente |

## Requisitos

- WordPress 6.5+ y PHP 8.1+.
- Elementor Free 3.20+ (probado con 3.34.2).
- Un **tema clásico** (Hello Elementor, Astra, GeneratePress, OldBearStudios…). Los temas de bloques no usan `header.php`/`footer.php`.
- Si Elementor Pro está activo, el plugin se pone en pausa para no duplicar el Theme Builder.

## Uso

1. **Plantillas → Theme Builder** muestra cada ubicación, sus plantillas y sus condiciones. «Añadir…» crea la plantilla y abre Elementor.
2. Diseña con widgets nativos y los de las categorías **Sitio**, **Entrada individual** y **Archivo**.
3. En **Ajustes de la plantilla → Condiciones de visualización** añade reglas de incluir/excluir. Publica.

También puedes crear las plantillas desde **Plantillas → Añadir nueva**: aparecen como tipos Cabecera, Pie de página, Entrada individual, Archivo y Página 404.

### Condiciones

| Regla | Aplica en |
| --- | --- |
| Todo el sitio | Cualquier página (solo cabecera y pie) |
| Contenido individual · Todo / Portada / {tipo} | Entradas, páginas o un tipo de contenido; opcionalmente IDs concretos |
| Contenido individual · Con término de {taxonomía} | Contenidos que tienen esos términos (IDs obligatorios) |
| Archivos · Todos / Página de entradas / {tipo} / {taxonomía} / Autor / Fecha / Búsqueda | Listados |
| Página 404 | Páginas no encontradas |

Si varias plantillas coinciden, gana la más específica: portada/404 > ID concreto > «con término» > tipo o taxonomía > todos los individuales o archivos > todo el sitio. Con el mismo nivel gana la más reciente. Una regla de **excluir** que coincida descarta la plantilla.

### Widgets

| Categoría | Widgets |
| --- | --- |
| Sitio | Logotipo del sitio, Título del sitio, Menú de navegación (desplegable en móvil/tableta, submenús), Formulario de búsqueda |
| Entrada individual | Título, Contenido, Imagen destacada, Extracto, Información (fecha, autor, términos, comentarios) |
| Archivo | Título del archivo, Entradas del archivo (rejilla adaptable con paginación) |

En el editor, las plantillas de entrada y archivo se previsualizan con contenido real. La entrada concreta se elige en **Ajustes → Vista previa**.

### Compatibilidad con temas

- La cabecera y el pie sustituyen a `header.php`/`footer.php` del tema mediante los ganchos `get_header`/`get_footer`.
- Entrada, archivo y 404 sustituyen a la plantilla del tema mediante `template_include`.
- Si ningún otro plugin la define, se ofrece la función `elementor_theme_do_location()`, que usan temas como Hello Elementor o Astra.
- Filtros disponibles: `obx/location/template_id` (forzar o anular una plantilla) y `obx/skip_link_target` (destino del enlace «Saltar al contenido»).

## Desarrollo

```bash
bash bin/setup.sh   # WordPress + Elementor + Hello Elementor + demo en http://localhost:8088 (acceso en .dev-access.txt)
bash bin/test.sh    # lint PHP, pruebas de integración y comprobaciones HTTP
bash bin/build.sh   # dist/oldbear-elementor-expansion-<versión>.zip para instalar en cualquier WordPress
docker compose down -v   # borra el entorno de desarrollo
```

Usa otro puerto con `OBX_PORT=8099 bash bin/setup.sh` y otra versión de Elementor con `OBX_ELEMENTOR_VERSION=…`. La CI de GitHub Actions ejecuta lo mismo en cada push y publica el ZIP como artefacto.

### Estructura

```
oldbear-elementor-expansion.php    Cabecera del plugin y arranque
includes/class-plugin.php          Comprobaciones (Elementor, versión, Pro) y carga de módulos
includes/class-context.php         Entrada/consulta real o de vista previa para los widgets
includes/compat.php                elementor_theme_do_location() para temas compatibles
includes/theme-builder/            Tipos de documento, condiciones, ubicaciones y pantalla de administración
includes/widgets/                  Widgets de sitio, entrada y archivo
templates/                         header.php, footer.php y location.php que imprime el plugin
dev/seed.php · tests/              Demo de desarrollo y pruebas de integración (wp eval-file)
```

## Licencia

GPL-2.0-or-later. Consulta [LICENSE](LICENSE).
