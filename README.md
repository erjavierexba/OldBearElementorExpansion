# OldBearElementorExpansion

**OldBear Expansion for Elementor** amplía Elementor Free con las herramientas de diseño de sitio completo: Theme Builder con condiciones de visualización y widgets de tema. Es código propio (GPL-2.0), construido sobre la API pública de Elementor. No usa ni contiene código de Elementor Pro y no requiere licencia.

> «Elementor» es una marca de Elementor Ltd. Este proyecto es independiente y no está afiliado a Elementor Ltd.

## Estado

| Fase | Funcionalidad | Estado |
| --- | --- | --- |
| 1 | Theme Builder: cabecera, pie, entrada individual, archivo y 404, con condiciones | ✅ v0.1.0 |
| 1b | Modo limpio y convivencia con Elementor Pro | ✅ v0.2.0 |
| 1c | Widgets de Pro con implementación propia | ✅ v0.3.0 |
| 7 | Interoperabilidad: formato de datos de Elementor Pro (tipos de plantilla, condiciones, nombres y ajustes de widgets), importación de kits y conversión | ✅ v0.4.0 |
| 4 | Formulario (campos, pasos, archivos, correo, webhook, envíos guardados) | ✅ v0.4.0 |
| 6a | Carruseles (testimonios y carrusel anidado) y galería | ✅ v0.4.0 |
| 2 | Etiquetas dinámicas en los widgets nativos | ✅ v0.5.0 |
| 3 | Loop Grid / Loop Carousel, filtro de taxonomías y tarjetas editables | ✅ v0.5.0 |
| 5 | Popups con condiciones, disparadores y reglas de frecuencia | ✅ v0.5.0 |
| 6b | CSS y atributos personalizados, sticky, efectos, diapositivas y carrusel de medios | ✅ v0.5.0 |
| 8 | Widgets de producto, archivo, carrito, compra y cuenta de WooCommerce | ✅ v0.5.0 |
| 9 | Dashboard Pro: fuentes, iconos, código personalizado y edición de solo contenido por rol | ✅ v0.6.0 |
| 10 | Pantallas de Elementor como con Pro: escritorio, Inicio, Quick Start, barra superior y enlaces de las herramientas Pro | ✅ v0.6.1 |
| 11 | Theme Builder dentro de la app de Elementor (pantalla completa y modal del editor), con condiciones al publicar | ✅ v0.7.0 |

**Objetivo:** ser al Elementor Pro lo que LibreOffice es a Microsoft Office: una alternativa libre que entiende los mismos documentos. La compatibilidad se construye a partir del formato de los datos que guarda Elementor, nunca copiando código de Pro.

## Requisitos

- WordPress 6.5+ y PHP 8.1+.
- Elementor Free 3.20+ (probado con 3.34.2).
- Un **tema clásico** (Hello Elementor, Astra, GeneratePress, OldBearStudios…). Los temas de bloques no usan `header.php`/`footer.php`.
- Con Elementor Pro activo, el plugin **convive**: el Theme Builder de Pro tiene prioridad y los widgets de OldBear siguen disponibles en el editor.

## Uso

1. Abre el **Theme Builder** desde donde lo ofrece Elementor: el menú (**Maquetador de temas**), el menú del editor, la barra de administración o `Ctrl+Shift+E`. Es la pantalla de Elementor (`admin.php?page=elementor-app#/site-editor`), a pantalla completa o en un modal dentro del editor, con nuestras plantillas en lugar de la promoción de Pro:
   - **All Parts** muestra todas las plantillas con su estado y dónde se usan; cada parte del sitio (cabecera, pie, página, entrada, archivo, búsqueda, producto, archivo de productos, 404 y elemento de bucle) tiene su lista y su botón «+».
   - Cada tarjeta tiene **Editar**, **Editar condiciones**, **Vista previa**, **Exportar**, **Cambiar nombre** y **Eliminar**.
   - «Añadir nueva» crea un borrador con la condición lógica de su tipo y abre el editor.
2. Diseña con widgets nativos y los de las categorías **Sitio**, **Entrada individual** y **Archivo**.
3. Al **publicar** la plantilla por primera vez se abre el diálogo de condiciones (incluir/excluir). También se editan desde la tarjeta o en **Ajustes de la plantilla → Condiciones de visualización**.

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
| Pro | Menú de navegación (`nav-menu`), Buscador (`search`, `search-form`), Entradas (`posts`), Formulario (`form`), Cita (`blockquote`), Llamada a la acción (`call-to-action`), Caja giratoria (`flip-box`), Tabla de precios (`price-table`), Lista de precios (`price-list`), Cuenta atrás (`countdown`), Botones para compartir (`share-buttons`), Titular animado (`animated-headline`), Tabla de contenidos (`table-of-contents`), Inicio de sesión (`login`), Resaltado de código (`code-highlight`), Diapositivas (`slides`), carruseles de testimonios, medios y anidado, Galería (`gallery`) y Plantilla (`template`) |
| Sitio | Logotipo y título del sitio, título de la página, título, imagen destacada, extracto e información de la entrada, título y entradas del archivo, caja de autor, navegación de entradas, comentarios, migas de pan, mapa del sitio e indicador de progreso (`theme-site-logo`, `theme-page-title`, `post-info`, `archive-posts`, `breadcrumbs`…) |
| Individual | Contenido de la entrada (`theme-post-content`) |
| Loop | Rejilla (`loop-grid`), carrusel (`loop-carousel`) y filtro de taxonomías (`taxonomy-filter`) con tarjetas `loop-item` |
| WooCommerce | Migas, carrito del menú, widgets de producto, productos relacionados/dirigidos, archivo, catálogo, carrito, finalizar compra y Mi cuenta |

Las plantillas guardadas con versiones anteriores, que usan los nombres `obx-*`, se siguen mostrando igual. Esos nombres ya no aparecen en el panel para no duplicar widgets. Con Elementor Pro activo ocurre lo contrario: Pro conserva sus widgets y los de OldBear aparecen con sus nombres `obx-*` en las categorías Sitio, Entrada individual y Archivo.

No pretende clonar todo el catálogo comercial. Quedan fuera Mega menú, Reseñas, Portafolio, Lottie, Hotspot, Off-canvas, Lista de vídeos, botones de PayPal/Stripe y widgets de Facebook. Las piezas necesarias para los kits estudiados —incluidos Loop Builder y WooCommerce— sí están cubiertas.

### Loop Builder

En **Theme Builder → Elemento de bucle** crea una tarjeta y edítala como cualquier plantilla de Elementor. Los widgets Loop Grid y Loop Carousel consultan entradas, páginas o productos y renderizan esa tarjeta para cada resultado. El filtro de taxonomías apunta al ID de un Loop Grid y conserva URLs navegables, por lo que funciona también sin JavaScript.

### Ventanas emergentes

**Plantillas → Ventanas emergentes** permite crear documentos `popup` compatibles con el tipo de Elementor Pro. Sus condiciones deciden en qué páginas están disponibles; pueden abrirse al cargar, al desplazarse, tras inactividad, al intentar salir, mediante un selector CSS o con la etiqueta dinámica «Ventana emergente». Incluyen cierre accesible, límite por visitante y bloqueo opcional del desplazamiento.

### Etiquetas dinámicas y extras

Los controles nativos con soporte dinámico pueden usar datos de entrada, archivo, sitio, usuario, autor, comentarios, acciones y producto. En **Avanzado** funcionan CSS personalizado, atributos filtrados, sticky y efectos de scroll/ratón con las mismas claves de datos de Pro. Los atributos capaces de ejecutar scripts o alterar enlaces se descartan en el servidor.

### Herramientas del dashboard

El menú moderno de Elementor incluye equivalentes funcionales de sus herramientas locales de Pro:

- **Editor → Plantillas**: Maquetador de temas (el Theme Builder de este plugin, dentro de la app de Elementor), plantillas guardadas y Popups.
- **Editor → Envíos**: registros de formularios con detalle, estado y metadatos.
- **Editor → Administrador de roles**: sin acceso, edición completa o edición de solo contenido.
- **Editor → Elementos personalizados → Fuentes**: carga WOFF2, WOFF, TTF y OTF, con peso y estilo; las familias aparecen en los controles de Tipografía.
- **Editor → Elementos personalizados → Iconos**: carga SVG saneado y biblioteca propia dentro del selector de iconos.
- **Editor → Elementos personalizados → Código**: HTML, CSS o JavaScript en `head`, inicio o final de `body`, con prioridad y condiciones de visualización.

Con el modo limpio, las pantallas propias de Elementor se ven como en un sitio con Pro:

- **Escritorio**: la tarjeta «Elementor Overview» de siempre, con la versión de OldBear Expansion bajo la de Elementor y sin el enlace «Upgrade».
- **Elementor → Inicio / Quick Start**: sin el botón «Upgrade» de la barra superior; las tarjetas de Theme Builder, Popups, Envíos, Fuentes, Iconos, Código y Roles muestran «Abrir» en lugar de «Hacerse Pro»; Quick Start muestra el contenido para usuarios de Pro, sin el anuncio lateral de ofertas.
- **Enlaces de Pro**: las direcciones a las que apunta Elementor (`admin.php?page=e-popups`, `e-form-submissions`, `e-custom-fonts`, `e-custom-icons`, `e-custom-code`, `elementor-theme-builder`) abren las pantallas de este plugin.
- **Theme Builder** (`elementor-app#/site-editor`): la app de Elementor registra nuestras rutas en lugar de su página de promoción, con los componentes de la propia app (barra lateral de partes del sitio, tarjetas, diálogos). Las condiciones guardadas desde la app se reflejan en el editor abierto. En WordPress 7.1+ la app se sirve con la misma `Document-Isolation-Policy` que el editor para que el modal pueda comunicarse con él. Fuentes, iconos y código usan los tipos de contenido de Pro (`elementor_font`, `elementor_icons`, `elementor_snippet`).

Para eso, la interfaz «Elementor One» recibe `elementor-pro` como herramienta instalada en su lista de plugins (solo esa lectura; instalar o activar no cambia). No se simula ninguna licencia.

Licencia, soporte, plantillas en la nube y los plugins independientes de Elementor (accesibilidad, correo e imágenes) son servicios externos, no funciones locales del dashboard, y no se simulan.

## Interoperabilidad con Elementor Pro

Desde la v0.4.0 los datos se guardan **en el mismo formato que Elementor Pro**, así que un sitio puede pasar de uno a otro en ambas direcciones:

- **Plantillas**: los tipos (`header`, `footer`, `single-post`, `single-page`, `single`, `archive`, `search-results`, `error-404`) y las condiciones (`_elementor_conditions`, p. ej. `include/singular/post/12`) son los de Pro. Al guardar se escriben en los dos formatos.
- **Widgets**: mismos nombres internos, mismas claves de ajustes (incluidas las de apariencia, como `classic_columns` o `archive_cards_title_color`, y la consulta `posts_*`) y mismos valores por defecto en interruptores, listas y números. Como Elementor solo guarda lo que difiere del valor por defecto, un diseño hecho con Pro se ve igual aquí. Los textos por defecto son propios, en español.
- **Acción `elementor/query/{id}`**: las consultas personalizadas de los widgets de entradas funcionan igual.
- **Kits**: Elementor Free no importa las plantillas de Theme Builder de un kit sin Pro. Este plugin sí, con sus condiciones (formato de Elementor y metadatos de los kits de Envato).
- **Formulario**: mismas claves (`form_fields`, `submit_actions`, `email_to`…). Los envíos van a un punto REST propio que vuelve a leer la configuración del documento guardado, nunca del navegador. Tiene campo trampa, límite de envíos por visitante, validación en el servidor y protección contra inyección de cabeceras. Los envíos guardados aparecen en **Elementor → Envíos**.
- **Datos antiguos**: las plantillas hechas con versiones anteriores (nombres `obx-*` o claves propias) se traducen al vuelo al mostrarse. Para dejarlas guardadas en formato Pro: **Plantillas → Theme Builder → Convertir al formato de Elementor Pro**, o `wp obx convert` (`--dry-run` para contar sin cambiar nada).
- **Loop, popups y producto**: reconoce `loop-item`, `popup`, `product` y `product-archive`; los widgets de Loop, acciones de popup, etiquetas dinámicas y controles WooCommerce usan sus nombres de Pro.
- **Al activar Elementor Pro**: convierte antes los datos antiguos y, una vez activo, guarda una vez las condiciones de cada plantilla para que Pro regenere su caché.

Se ha comprobado renderizando 167 plantillas de 10 kits comerciales «Elementor Pro Template Kit» (no incluidos en el repositorio) sin avisos de PHP. Las pruebas usan solo datos sintéticos.

### Origen del formato

Las claves, tipos, valores por defecto y opciones de los ajustes se tomaron como **especificación de formato** de un volcado público del registro de controles de widgets (GPL-2.0, derivado del proyecto «pro-elements»), y de qué claves aparecen guardadas en kits reales. De ahí solo se usan los nombres y valores de los datos. Etiquetas, textos de la interfaz, marcado HTML, CSS y JavaScript son propios. No se ha copiado código de Elementor Pro ni se ha hecho ingeniería inversa de él.

En el editor, las plantillas de entrada y archivo se previsualizan con contenido real. La entrada concreta se elige en **Ajustes → Vista previa**.

### Compatibilidad con temas

- La cabecera y el pie sustituyen a `header.php`/`footer.php` del tema mediante los ganchos `get_header`/`get_footer`.
- Entrada, archivo y 404 sustituyen a la plantilla del tema mediante `template_include`.
- Si ningún otro plugin la define, se ofrece la función `elementor_theme_do_location()`, que usan temas como Hello Elementor o Astra.
- Filtros disponibles: `obx/location/template_id` (forzar o anular una plantilla) y `obx/skip_link_target` (destino del enlace «Saltar al contenido»).

## Modo limpio

Activado por defecto (**Ajustes → OldBear Expansion**). Oculta los anuncios de Elementor Pro sin desactivar ninguna función gratuita:

- widgets bloqueados «Pro» del panel, avisos del navegador y del panel, la barra «Actualizar ahora» del editor y la barra fija de ofertas del panel de edición;
- el banner de ofertas del escritorio (p. ej. Black Friday), la promoción lateral de Quick Start y el botón «Upgrade» de la barra de Elementor;
- secciones de solo promoción en Avanzado y en los ajustes de página (CSS personalizado, atributos, condiciones de visualización, efectos de scroll y ratón, sticky);
- entradas de menú de Pro («Ventanas emergentes», «Elementos personalizados», «Actualizar», «Mejorar plan») y el enlace «Obtener Elementor Pro» en Plugins.

La entrada **Maquetador de temas** de Elementor abre el Theme Builder de este plugin dentro de la app de Elementor (la tabla clásica sigue en `admin.php?page=obx-theme-builder`). Se desengancha el módulo de promociones de Elementor quitando solo los ganchos de ese módulo y de los objetos que crea (banner de conversión, avisos de temporada); el filtro `obx/clean_mode` permite desactivarlo por código.

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
includes/class-converter.php       Conversión de datos antiguos al formato de Pro (botón y wp obx convert)
includes/forms/                    Envío del formulario (REST), acciones y envíos guardados
includes/admin/                    Dashboard: menús, fuentes, iconos, código y restricciones por rol
includes/dynamic/                  Etiquetas dinámicas compatibles con el formato de Pro
includes/extras/                   CSS, atributos, sticky y efectos de movimiento
includes/popups/                   Condiciones, disparadores y runtime de ventanas emergentes
includes/compat.php                elementor_theme_do_location() para temas compatibles
includes/clean/                    Modo limpio (anuncios de Pro) y página de ajustes
includes/theme-builder/            Tipos de documento, condiciones, ubicaciones, Theme Builder en la app de Elementor (REST) y pantalla clásica
includes/widgets/                  Widgets (base con doble nombre obx-*/Pro, tema, sitio, entradas, generales, formulario y carruseles)
templates/                         header.php, footer.php y location.php que imprime el plugin
assets/                            CSS y JS propios (sin dependencias; los carruseles usan scroll-snap)
dev/seed.php · tests/              Demo de desarrollo y pruebas de integración (wp eval-file)
```

## Licencia

GPL-2.0-or-later. Consulta [LICENSE](LICENSE).
