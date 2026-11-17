# OldBearElementorExpansion

**OldBear Expansion for Elementor** amplía Elementor Free con las herramientas de diseño de sitio completo: Theme Builder con condiciones de visualización y widgets de tema. Es código propio (GPL-2.0), construido sobre la API pública de Elementor. No usa ni contiene código de Elementor Pro y no requiere licencia.

> «Elementor» es una marca de Elementor Ltd. Este proyecto es independiente y no está afiliado a Elementor Ltd.

## Estado

| Fase | Funcionalidad | Estado |
| --- | --- | --- |
| 1 | Theme Builder: cabecera, pie, entrada individual, archivo y 404, con condiciones | ✅ v0.1.0 |
| 1b | Modo limpio y convivencia con Elementor Pro | ✅ v0.2.0 |
| 1c | Widgets de Pro con implementación propia (31 widgets) | ✅ v0.3.0 |
| 2 | Etiquetas dinámicas en los widgets nativos | Pendiente |
| 3 | Loop Grid con plantilla de tarjeta | Pendiente |
| 4 | Formularios | Pendiente |
| 5 | Popups | Pendiente |
| 6 | Extras: CSS personalizado, cabecera fija, efectos de scroll, carrusel… | Pendiente |
| 7 | Interoperabilidad: abrir sitios diseñados con Elementor Pro (tipos de plantilla, condiciones y widgets guardados) | Pendiente |

**Objetivo:** ser al Elementor Pro lo que LibreOffice es a Microsoft Office: una alternativa libre que entiende los mismos documentos. La compatibilidad se construye a partir del formato de los datos que guarda Elementor, nunca copiando código de Pro.

## Requisitos

- WordPress 6.5+ y PHP 8.1+.
- Elementor Free 3.20+ (probado con 3.34.2).
- Un **tema clásico** (Hello Elementor, Astra, GeneratePress, OldBearStudios…). Los temas de bloques no usan `header.php`/`footer.php`.
- Con Elementor Pro activo, el plugin **convive**: el Theme Builder de Pro tiene prioridad y los widgets de OldBear siguen disponibles en el editor.

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

Sin Elementor Pro, los widgets se registran **con los mismos nombres internos que los de Pro** y en sus mismas categorías del panel. Elementor deja de mostrar el candado en ellos porque ya existen, y los ejecuta este plugin con su propio código. Los que aún no están implementados siguen bloqueados, salvo con el modo limpio, que los oculta.

| Categoría | Widgets |
| --- | --- |
| Pro | Menú de navegación (`nav-menu`), Formulario de búsqueda (`search`), Entradas (`posts`), Cita (`blockquote`), Llamada a la acción (`call-to-action`), Caja giratoria (`flip-box`), Tabla de precios (`price-table`), Lista de precios (`price-list`), Cuenta atrás (`countdown`), Botones para compartir (`share-buttons`), Titular animado (`animated-headline`), Tabla de contenidos (`table-of-contents`), Inicio de sesión (`login`), Resaltado de código (`code-highlight`), Plantilla (`template`) |
| Sitio | Logotipo y título del sitio, título de la página, título, imagen destacada, extracto e información de la entrada, título y entradas del archivo, caja de autor, navegación de entradas, comentarios, migas de pan, mapa del sitio e indicador de progreso (`theme-site-logo`, `theme-page-title`, `post-info`, `archive-posts`, `breadcrumbs`…) |
| Individual | Contenido de la entrada (`theme-post-content`) |

Las plantillas guardadas con versiones anteriores, que usan los nombres `obx-*`, se siguen mostrando igual. Esos nombres ya no aparecen en el panel para no duplicar widgets. Con Elementor Pro activo ocurre lo contrario: Pro conserva sus widgets y los de OldBear aparecen con sus nombres `obx-*` en las categorías Sitio, Entrada individual y Archivo.

> Los nombres de los widgets coinciden con los de Pro, pero los nombres de sus ajustes todavía no. Un diseño hecho con Pro se abrirá con el widget correcto, pero puede perder estilos u opciones hasta la fase 7. Por el mismo motivo, si más adelante activas Elementor Pro, revisa las páginas que usen estos widgets.

Pendientes de la lista de Pro: Formulario, Loop Grid/Carousel, Popups, Mega menú, Galería, Carruseles (diapositivas, medios, testimonios, reseñas), Portafolio, Lottie, Hotspot, Off-canvas, Lista de vídeos, botones de PayPal/Stripe, widgets de Facebook y WooCommerce.

En el editor, las plantillas de entrada y archivo se previsualizan con contenido real. La entrada concreta se elige en **Ajustes → Vista previa**.

### Compatibilidad con temas

- La cabecera y el pie sustituyen a `header.php`/`footer.php` del tema mediante los ganchos `get_header`/`get_footer`.
- Entrada, archivo y 404 sustituyen a la plantilla del tema mediante `template_include`.
- Si ningún otro plugin la define, se ofrece la función `elementor_theme_do_location()`, que usan temas como Hello Elementor o Astra.
- Filtros disponibles: `obx/location/template_id` (forzar o anular una plantilla) y `obx/skip_link_target` (destino del enlace «Saltar al contenido»).

## Modo limpio

Activado por defecto (**Ajustes → OldBear Expansion**). Oculta los anuncios de Elementor Pro sin desactivar ninguna función gratuita:

- widgets bloqueados «Pro» del panel, avisos del navegador y del panel, y la barra «Actualizar ahora» del editor;
- secciones de solo promoción en Avanzado y en los ajustes de página (CSS personalizado, atributos, condiciones de visualización, efectos de scroll y ratón, sticky);
- entradas de menú de Pro («Ventanas emergentes», «Elementos personalizados», «Actualizar», «Mejorar plan») y el enlace «Obtener Elementor Pro» en Plugins.

La entrada **Maquetador de temas** de Elementor abre el Theme Builder de este plugin. Se desengancha el módulo de promociones de Elementor quitando solo los ganchos de esa instancia; el filtro `obx/clean_mode` permite desactivarlo por código.

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
includes/clean/                    Modo limpio (anuncios de Pro) y página de ajustes
includes/theme-builder/            Tipos de documento, condiciones, ubicaciones y pantalla de administración
includes/widgets/                  Widgets (base con doble nombre obx-*/Pro, tema, sitio y generales)
templates/                         header.php, footer.php y location.php que imprime el plugin
assets/                            CSS y JS propios de los widgets (sin dependencias)
dev/seed.php · tests/              Demo de desarrollo y pruebas de integración (wp eval-file)
```

## Licencia

GPL-2.0-or-later. Consulta [LICENSE](LICENSE).
