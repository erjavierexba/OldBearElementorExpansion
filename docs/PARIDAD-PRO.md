# Paridad con Elementor Pro

Qué le falta a OldBear Expansion para cubrir el 100 % de Elementor Pro, cómo se ha medido y en qué orden se aborda. Este documento es la hoja de ruta de las versiones 0.8 en adelante: marca cada casilla al terminar una pieza y añade la versión en la que salió.

> Regla de siempre: la compatibilidad se construye a partir del **formato de los datos** (nombres de ajustes, tipos, valores por defecto y opciones). Etiquetas, textos, marcado, CSS y JavaScript son propios. No se copia código de Elementor Pro.

## 1. Cómo se ha medido

**Fecha:** 17-11-2026.

**Referencia:** dos sitios reales con Elementor Pro licenciado:

| Sitio | Elementor | Elementor Pro | Extras |
| --- | --- | --- | --- |
| Referencia A | 4.2.4 | 4.2.3 | Premium Addons, Header Footer Elementor |
| Referencia B | 4.1.4 | 4.1.2 | WooCommerce 10.9, ElementsKit Lite |
| OBX (entorno `bin/setup.sh`) | 4.4.1 | — | OldBear Expansion 0.7.3, WooCommerce 11.3 |

**Método:** `dev/inventory.php` (ejecutado con `wp eval-file`) vuelca lo que Elementor tiene registrado en tiempo de ejecución:

- cada widget con todos sus controles (clave, tipo, sección, valor por defecto, opciones, condiciones y campos de repetidores);
- elementos (contenedor, sección, columna y la pestaña Avanzado común);
- tipos de documento con sus controles y los ajustes del sitio (kit);
- etiquetas dinámicas, experimentos, acciones y campos del formulario, condiciones y ubicaciones del Theme Builder, tipos de contenido, rutas REST y shortcodes.

El inventario fuerza `Performance::$is_frontend = false`. Bajo WP-CLI Elementor cree estar en el frontend y descarta los controles de estilo; sin ese ajuste la comparación sale falseada.

Los controles que añaden plugins de terceros (`premium_*`, `pa_*`, `ekit_*`, `hfe-*`) se descartan.

Elementor 4.4 ya no crea en PHP las variantes por dispositivo de los controles adaptables (`columns_tablet`, `row_gap_mobile`…): las añade el editor. Los sitios de referencia usan un Elementor anterior, así que desde 0.9.0 `parity-spec.py` no cuenta como ausente la variante `_tablet`/`_mobile`/… de una clave que OBX sí tiene. Ese ajuste quitó unos 540 falsos huecos de los anexos.

**Datos locales (no versionados):** `dev/pro-spec/` está en `.gitignore`. Contiene:

- `spec-missing.json`: cada ajuste ausente con su tipo, valor por defecto, opciones y condiciones. Es la especificación a seguir al implementar.
- `inventory-pro-*.json.gz`: los inventarios completos.

Para regenerarlos:

1. Ejecuta `dev/inventory.php` en un sitio con Pro y en el entorno de desarrollo.
2. Genera la especificación: `python3 dev/parity-spec.py obx.json pro-a.json.gz pro-b.json.gz > dev/pro-spec/spec-missing.json`.
3. Genera los anexos de este documento: `python3 dev/parity-appendix.py dev/pro-spec/spec-missing.json`.

**Lectura de las cifras.** Elementor solo guarda los valores que difieren del valor por defecto. Un ajuste ausente significa que una plantilla hecha con Pro **pierde ese ajuste sin aviso** al abrirse con OBX. Por eso cada ajuste nuevo debe usar la misma clave, el mismo valor por defecto y las mismas opciones que Pro.

## 2. Resumen

| Área | Pro | OBX 0.7.3 | Hueco |
| --- | --- | --- | --- |
| Widgets | 110 | 61 con nombre de Pro | 49 widgets ausentes |
| Ajustes en widgets compartidos | — | — | ~2.000 ajustes en 61 widgets (anexo A) |
| Etiquetas dinámicas | 51 (con las de Free) | 41 (con las de Free) | 11 + integraciones ACF, Toolset y Pods (0.8.1: solo quedan `off-canvas`, Toolset y Pods) |
| Tipos de documento | — | — | `widget` (global), `code_snippet`, `product-post`; ajustes ausentes en todas las plantillas (anexo C) |
| Condiciones del Theme Builder | 41 (con WooCommerce) | ~30 | hijos de término en archivos, formatos, medios y WooCommerce |
| Ajustes del sitio | pestaña WooCommerce, transiciones de página, scroll snap | — | 3 bloques |
| Avanzado común | condiciones por elemento, origen de transformación, rango de escala | — | 4 ajustes |
| Fondo de contenedor, sección y columna | efectos de movimiento del fondo | — | 38 ajustes |

**Ya a la par** (solo les falta el ajuste común de §3): formulario (acciones y tipos de campo), caja giratoria, tabla de precios, llamada a la acción, cuenta atrás, titular animado, inicio de sesión, botones para compartir, plantilla, resaltado de código (salvo 15 lenguajes), contenido de la entrada y los títulos del Theme Builder.

## 3. Funciones generales

### 3.1 Avanzado común y fondos

- [x] `motion_fx_transform_origin_x` / `_y` (choose, `center`; visibles con rotación o escala).
- [x] `motion_fx_scale_range` (slider, `{start:20,end:80}` en %).
- [x] Opción vacía `''` en `motion_fx_translateY_direction`, `motion_fx_translateX_direction` y `motion_fx_rotateZ_direction`.
- [x] `handle_motion_fx_asset_loading` (oculto).
- [x] **Efectos de movimiento del fondo** en contenedor, sección y columna: `background_motion_fx_*` (scroll: translateY, translateX, opacidad, desenfoque, rotación y escala; ratón: seguimiento e inclinación; `devices` y `range`). El sitio de referencia A los usa en su cabecera.
- [x] **Condiciones de visualización por elemento**: `e_display_conditions` (oculto) en widgets, contenedores, secciones y columnas, junto a «Clases CSS» de la pestaña Avanzado, como en Pro.
  - Formato de Pro: `[ JSON ]`, una lista de grupos (basta con que se cumpla uno) de condiciones (deben cumplirse todas): `{"condition":…,"comparator":…,…}`; fechas `m-d-Y`, horas `m-d-Y H:i`, valores buscables `[{id, text}]`. Una lista plana antigua cuenta como un grupo.
  - Las 22 condiciones de Pro: sesión, rol, fecha de registro, fecha actual, día de la semana, hora del día (UTC, como Pro), título/padre/autor de la página, título/autor/categorías/etiquetas/imagen destacada/comentarios/fecha de publicación y de modificación de la entrada, archivo de categorías/etiquetas/autores, URL de origen y etiquetas dinámicas (título del archivo, datos de la imagen destacada, datos del autor y campos personalizados).
  - Modal propio en el editor (grupos «o», condiciones «y», búsqueda de entradas, términos y autores) con el número de condiciones junto al botón. En el editor el elemento siempre se ve; en la web se omite con sus hijos y no sale de la caché de elementos.

### 3.2 Plantillas del Theme Builder (cabecera, pie, individual, archivo, búsqueda, 404, producto y archivo de productos)

- [x] **Vista previa con las claves de Pro:** `preview_type` (select, p. ej. `single/post`, `archive/recent_posts`, `search`…), `preview_id` (query), `preview_search_term` (text) y `apply_preview` (botón).
  - `obx_preview_id` se sigue leyendo como alias.
  - Los tipos de vista previa de archivo (término, autor, fecha, tipo de contenido) y de búsqueda cambian el contexto de los widgets del Theme Builder.
- [x] `content_wrapper_html_tag` (`div`, `main`, `article`, `header`, `footer`, `section`, `aside`, `nav`; por defecto `div`): etiqueta del envoltorio de la plantilla.
- [x] `page_template` en plantillas individuales y de archivo (`''`, `elementor_canvas`, `elementor_header_footer`). Con `elementor_canvas` la ubicación se imprime sin la cabecera ni el pie del tema.
- [x] Pestaña **Estilo** de la plantilla (`section_page_style`): `margin`, `padding` y grupo de fondo `background_*`.
- [x] `custom_css` en todas las plantillas, popup y elemento de bucle.
- [x] Plantillas guardadas de tipo `section` y `floating-buttons`: Pro también les añade `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term` y `apply_preview` (0.8.1).

### 3.3 Ventanas emergentes

Ajustes de Pro que faltan o que guardamos con otra clave:

- [x] `content_position` (`top`/`center`/`bottom`, con altura distinta de `auto`).
- [x] Animaciones por dispositivo `entrance_animation_tablet/_mobile` y `exit_animation_tablet/_mobile`, y `entrance_animation_duration` (1,2 s).
- [x] Estilo de la ventana: fondo `background_*`, borde `border_*`, `border_radius` y sombra `box_shadow_*` (sombra activada por defecto).
- [x] Superposición: `overlay_background_*`.
- [x] Botón de cierre:
  - `close_button_position` (`''` o `outside`), `close_button_vertical` y `close_button_horizontal`;
  - `close_button_color` y `close_button_hover_color`, `icon_size`.
- [x] Avanzado:
  - `close_button_delay`, `close_automatically`;
  - `prevent_close_on_esc_key`, `prevent_scroll`, `avoid_multiple_popups`, `a11y_navigation` (sí por defecto);
  - `open_selector` (hoy `open_by_selector`), `margin`, `padding` y `classes`.
- [x] Mantener las claves `obx_*` como alias de lectura, sin escribirlas en plantillas nuevas.

### 3.4 Ajustes del sitio (kit)

- [x] **Transiciones de página** (`settings_page_transitions_*`):
  - animación de entrada y salida, duración y botón de prueba;
  - precargador de tipo animación, icono o imagen, con color, tamaño, retraso, rotación y opacidad.
  - Elementor 4.4 ya muestra la pestaña, pero sin estos controles ni el código que los ejecuta.
  - El sitio de referencia B la usa.
- [x] **Scroll snap** (`scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`), en el kit y en las páginas.
- [x] Pestaña **WooCommerce** (0.10.0) (`settings-woocommerce`):
  - `woocommerce_{cart,checkout,myaccount,terms,purchase_summary,shop}_page_id` y `woocommerce_notices_elements`;
  - estilos de avisos: `error_message_*`, `notice_message_*`, `message_buttons_styles` e `info_buttons_styles`.
  - Al guardar el kit se actualizan las páginas de WooCommerce; tras el pago, el cliente llega a la página del resumen de la compra.
  - Diferencia con Pro: los selectores de página son `select2` en lugar del control `query` de Pro; los datos guardados son los mismos.

### 3.5 Condiciones del Theme Builder

Ya tenemos: general, singular (todo, portada, por tipo, `in_{tax}`, `in_{tax}_children`, `child_of`, `any_child_of`, `by_author`, `{tipo}_by_author`, 404) y archivo (todo, entradas, tipo, taxonomía, autor, fecha, búsqueda). Faltan:

- [x] Archivos de hijos de un término: `child_of_{tax}` y `any_child_of_{tax}`, en taxonomías jerárquicas.
- [x] Formatos de entrada: `post_format` en archivos e `in_post_format` en contenido individual.
- [x] Medios: `attachment` y `attachment_by_author`.
- [x] Grupo **WooCommerce**:
  - `woocommerce` y `product_archive`;
  - `shop_page`, `product_search`;
  - `product_brand`, `product_cat`, `product_tag` (archivo);
  - `in_product_*` y `in_product_*_children`, `product_by_author`.

### 3.6 Etiquetas dinámicas

- [x] `archive-meta`, `featured-image-data`, `request-arg` (antes la registrábamos como `request-parameter`, que se mantiene como alias).
- [x] Acción `reload-page`.
- [x] Acción `off-canvas` (abrir, cerrar o alternar un Off-Canvas de la página, con el ID del widget como Pro). El desplegable lista los Off-Canvas de los documentos abiertos en el editor.
- [x] WooCommerce: `woocommerce-add-to-cart`, `woocommerce-category-image-tag`, `woocommerce-product-content-tag`, `woocommerce-product-gallery-tag`, `woocommerce-product-sale-tag`, `woocommerce-product-terms-tag`.
- [ ] Integraciones, que solo se registran si el plugin está activo:
  - [x] ACF: `acf-text`, `acf-image`, `acf-url`, `acf-gallery`, `acf-file`, `acf-number`, `acf-color` (clave `key` = `field_key:field_name`, como Pro; entrada, término, usuario y páginas de opciones). Sin pruebas automáticas: el entorno de desarrollo no tiene ACF.
  - [ ] Toolset (`toolset-*`) y Pods (`pods-*`).

### 3.7 Otras piezas de Pro

- [ ] Shortcode `[elementor-template id="…"]`.
- [x] Widget global: documento `widget` (plantilla con el widget y `_elementor_template_widget_type`) y widget `global` con `templateID`, guardados como Pro. En el editor: pestaña «Globales», «Guardar como global» en el menú contextual, panel bloqueado con «Editar» y «Desvincular», las copias de la página cambian a la vez y al publicar se actualiza la plantilla; la web regenera el CSS de las páginas que la usan (`_elementor_global_widget_included_posts`).
- [ ] Notas del editor (módulo `notes`).
- [ ] Fuentes de Adobe (Typekit) e importación de paquetes de iconos (Fontello, IcoMoon, Fontastic). Hoy solo hay SVG sueltos.
- [ ] Comandos WP-CLI de Pro (`wp elementor-pro theme-builder clear-conditions`, etc.).
- [ ] Editor v4 de Pro: interacciones, variables de Pro, Collection Loop y formulario atómico (`e-form-*`). Dependen de los experimentos del editor nuevo; se abordan cuando ese editor sea el predeterminado.
- Fuera de alcance: licencia, biblioteca en la nube, capturas de plantillas (servicio remoto) y avisos o planes de Pro.

## 4. Widgets ausentes (49; quedan 36)

Los widgets nuevos usan en el HTML los nombres de clase, ID y variables CSS de Pro (`e-n-menu`, `e-off-canvas`, `--n-menu-*`, `--e-off-canvas-*`, envoltorio `elementor-widget-n-menu`), así el CSS personalizado de las webs que vienen de Pro sigue funcionando. Los widgets anteriores (`obx-…`) se migran en el punto 4.

| Prioridad | Widgets |
| --- | --- |
| ✅ | `global`: ver §3.7 (widget global). Se pinta con las clases de Pro (`elementor-widget-global elementor-global-N elementor-widget-{tipo}`) y el CSS usa el selector `.elementor-global-N`. |
| ✅ | `mega-menu` (Menú): todas las claves de Pro (elementos con título, enlace, icono, ID y contenido desplegable; ancho completo o ajustado y su posición, disposición horizontal o desplegable, punto de ruptura, abrir al pasar el ratón o al hacer clic, animación, botón del menú, desplazamiento horizontal y todos los estilos por estado). Cada elemento tiene su contenedor dentro del `<li>`, también en el editor (repetidores intercalados de Elementor). El contenido a ancho completo ocupa el contenedor de estiramiento del kit o la página; el ajustado se coloca junto a su título sin salirse de la pantalla y se abre hacia arriba si no cabe. Elemento actual por URL y anclas activas mientras su sección está en pantalla. |
| ✅ | `off-canvas`: widget anidado con un contenedor, con las claves de Pro (posición, ancho, altura, animaciones de entrada y salida, duración, capa, no cerrar con la capa o Esc, bloquear el desplazamiento, etiqueta HTML, fondo, borde y sombra). En el editor se ve abierto mientras «Modo de edición» está activo; en la web se abre con la acción `off-canvas`, devuelve el foco al cerrar y mantiene el teclado dentro del panel. |
| ~~Alta (WooCommerce)~~ | ✅ 0.10.0: `wc-add-to-cart`, `wc-products`, `wc-categories`, `wc-elements`, `woocommerce-notices`, `woocommerce-purchase-summary`, `wc-archive-products`, `wc-single-elements` |
| ✅ | `hotspot`: sobre el widget Imagen de Elementor, como Pro (mismas claves de imagen y estilos); puntos redondos, con icono y/o etiqueta, enlace, posición en % por dispositivo, tamaño propio, animaciones (latido, expandir, superposición) y entrada en secuencia; tooltip al hacer clic, al pasar el ratón o siempre visible, con animaciones por dirección y posición propia por punto. Marcado y variables de Pro (`e-hotspot…`, `--hotspot-*`, `--tooltip-*`). |
| ✅ | `lottie`: animaciones Lottie (JSON) desde la biblioteca de medios o una URL externa (solo para quien puede publicar páginas), con leyenda (título, leyenda del archivo o propia) y enlace; disparo al entrar en pantalla (zona configurable), al hacer clic (con retardo del enlace), al pasar el ratón (con zona y comportamiento al salir), ligado al desplazamiento (ventana o página entera) o siempre; bucle con número de veces, velocidad, puntos de inicio y fin, reproducción invertida, SVG o Canvas y carga diferida. Reproduce con lottie-web 5.12.2 (MIT) incluido en el plugin y una animación por defecto propia. Marcado y variables de Pro (`e-lottie…`, `--lottie-container-*`, `--caption-*`). |
| Media | `portfolio`, `reviews`, `video-playlist` |
| Media | Botones flotantes de Pro: `contact-buttons-var-1`, `-3` a `-10`, `floating-bars-var-2` y `-3` |
| Media | Link in bio de Pro: `link-in-bio-var-2` a `-7` |
| Baja | `paypal-button`, `stripe-button` (requieren pasarela) y los de Facebook: `facebook-button`, `facebook-comments`, `facebook-embed`, `facebook-page` |
| Editor v4 | `e-form-input`, `e-form-label`, `e-form-select`, `e-form-checkbox`, `e-form-radio-button`, `e-form-textarea`, `e-form-date-picker`, `e-form-time-picker`, `e-form-file-upload`, `e-form-submit-button` |

Sus ajustes completos están en el anexo B.

## 5. Widgets existentes con huecos importantes

El detalle de cada ajuste está en el anexo A. Las carencias funcionales que más se notan:

- **Loop Grid** (0.9.0, a la par salvo `e_display_conditions`):
  - [x] `_skin`: entradas, productos (`product_query_*`: rebajados, destacados, relacionados, ventas dirigidas y cruzadas, orden por precio, popularidad o valoración) y términos (`post_taxonomy_*`, `product_taxonomy_*`). Dentro de un bucle de términos, el título, la descripción, la dirección y los metadatos del archivo muestran el término.
  - [x] Paginación de Pro: carga AJAX con desplazamiento, paginación independiente, botón «Cargar más» con los estilos de botón de Elementor (claves de `Button_Trait`), icono de carga y mensaje final opcional. Se corrigió además que «Cargar más» y el desplazamiento infinito no funcionaban en Loop Grid.
  - [x] Mensaje de «sin resultados» (`enable_nothing_found_message`, etiqueta, alineación y estilos) y plantillas alternativas en posición fija (`static_position`).
  - Lo que queda en el inventario son opciones propias de cada sitio (tipos de contenido y términos) y pestañas de la interfaz sin datos (`*_query_args`, `pagination_colors`).
- **Loop Carousel** (0.9.0, a la par salvo `e_display_conditions`):
  - [x] `_skin` y las mismas consultas que Loop Grid, también con términos.
  - [x] `direction`, `offset_sides`/`offset_width` (diapositivas vecinas visibles), posición de cada flecha por orientación y desplazamiento, flechas dentro o fuera, estilos normal/hover de flechas (color, fondo, borde y sombra), puntos (colores, posición personalizada, dentro o fuera), fracción (tipografía, color y posición) y barra de progreso.
  - [x] Iconos y valores por defecto de Pro (`eicon-chevron-*`, 3/2/1 diapositivas). Las claves propias anteriores (`arrows_normal_color`, `dots_color_*`) se convierten al abrir.
  - Los métodos de navegación de Pro están en el trait `Carousel` para aplicarlos después al carrusel anidado (§5, fase 0.11.0).
- **Search** (0.9.0, a la par salvo `e_display_conditions`):
  - [x] Resultados en directo (`live_results`, `template_id`, `minimum_search_characters`, `number_of_columns`, `number_of_items`, `equal_height`, `enable_loader`): cada resultado se pinta con una plantilla «Elemento de bucle». Se piden a la ruta REST `obx/v1/search`, que lee los ajustes guardados del widget (solo de documentos publicados o editables por el usuario).
  - [x] Consulta `search_query_*` con «Todos» (`any`) por defecto, inclusiones, exclusiones, fecha, orden e `query_id` (acción `elementor/query/{ID}`).
  - [x] Paginación de resultados (números, anterior/siguiente, límite de páginas, acortar) y mensaje de «sin resultados» con su etiqueta.
  - [x] Estilos del campo en normal y al escribir (fondo, texto, icono, borde, sombra, transición), icono de borrar, botón (posición del icono, normal/hover, animación), caja de resultados (ancho del campo o del widget, ancho personalizado, alineación, altura máxima, separaciones, indicador de carga), paginación y mensaje.
  - [x] Teclado: flechas para recorrer los resultados, Escape para cerrar; `autocomplete` del navegador desactivado por defecto, como en Pro.
  - Queda en el inventario solo ruido: tipos de contenido propios de cada sitio y la pestaña `search_query_query_args`.
- **Formulario** (0.9.1, a la par salvo `e_display_conditions`):
  - [x] Las 14 acciones de Pro, en su orden. Las listas de correo (Mailchimp, Drip, ActiveCampaign, GetResponse, Kit/ConvertKit y MailerLite, clásico o nuevo) suscriben con la correspondencia de campos (`*_fields_map`, filas `remote_id`/`local_id`), grupos, etiquetas, doble confirmación, día del ciclo o resuscripción.
  - [x] Claves globales en Elementor → Ajustes → Integraciones, guardadas en las mismas opciones que Pro (`elementor_pro_mailchimp_api_key`, `elementor_pro_activecampaign_api_url`…); cada formulario puede usar otra clave (`*_api_key_source: custom`).
  - [x] En el editor, el botón «Cargar desde…» trae listas, grupos, etiquetas y campos del servicio (ruta REST `obx/v1/integrations/{servicio}`, solo para quien puede editar) y los recuerda para mostrar los nombres guardados.
  - [x] Slack y Discord con título, texto, campos, hora y color; acción de popup (abrir otro o cerrar el actual, también «no volver a mostrarlo»); `submissions_metadata`.
  - [x] Si una integración falla, el envío responde con el mensaje de error del servidor, como Pro, y quien puede editar la página ve el motivo.
  - [x] Estilos: alineación del contenido del botón, duración de la transición, colores secundarios de los pasos por estado (`steps_state`), separador entre pasos y radio y color del porcentaje de la barra.
  - Diferencia con Pro: `*_fields_map` es un repetidor normal de Elementor con los ID escritos a mano; los datos guardados tienen la misma forma.
- **WooCommerce**:
  - [x] carrito, finalizar compra y Mi cuenta (0.10.0): diseño en una o dos columnas, columna fija, textos y secciones personalizables, todos sus estilos. Los textos y campos también se aplican en las recargas AJAX de WooCommerce (totales del carrito y revisión del pedido); el escritorio de Mi cuenta puede ser una plantilla;
  - [x] mini carrito (0.10.0): tipo lateral o desplegable, apertura y actualización automáticas con los fragmentos del carrito de WooCommerce, botones y estilos;
  - [x] resumen de la compra y avisos de WooCommerce (0.10.0): el pedido se busca con su clave, como WooCommerce; los avisos se imprimen en el widget en lugar de en su sitio habitual;
  - [x] Productos, Productos del archivo, Relacionados y Ventas dirigidas (0.10.0): claves de Pro (`rows`, `paginate`, `allow_order`, `show_result_count`, `query_*`, títulos por origen, caja, imagen, título, valoración, precio, botón, «Ver carrito», paginación e insignia de oferta). Se pintan con las plantillas de WooCommerce (`content-product.php`) en una rejilla propia; la tienda pagina con «columnas × filas» del widget de la plantilla de archivo que la muestra. Los ajustes de versiones anteriores (`posts_per_page`, `orderby`…) se convierten al abrir;
  - [x] widgets de producto (título, precio, meta, pestañas, añadir al carrito, imágenes…) (0.10.0): sus secciones de estilo con las claves de Pro; los ajustes `woo_*` de versiones anteriores se convierten;
  - [x] widgets generales y obsoletos de Pro (0.10.0): `wc-add-to-cart` (botón de Elementor con o sin cantidad), `wc-categories` (shortcode `[product_categories]` en la rejilla), `wc-elements` (shortcodes de las páginas de WooCommerce), y los obsoletos `wc-products`, `wc-archive-products` y `wc-single-elements`, ocultos en el panel como en Pro, que siguen pintando las páginas importadas.
- **Entradas y archivo de entradas**: columnas y alineación por diseño, masonry, sombra de tarjetas, estilos del bloque y opciones de "cargar más".
- **Diapositivas**:
  - `slides_name`, altura, etiquetas del título y la descripción, navegación, velocidad, animación del contenido y posiciones;
  - campos de diapositiva: fondo, Ken Burns y estilo propio;
  - estilos de título, descripción, botón y navegación.
- **Carrusel de medios**: efecto (`slide`, `coverflow`), `slideshow` con miniaturas, diapositivas de vídeo, overlay, leyenda, icono y estilos del lightbox.
- **Carrusel anidado**: dirección, desplazamiento lateral, iconos de navegación por orientación, estilos de flechas, puntos y fracción.
- **Galería**: columnas, altura ideal, separación, animaciones del puntero y del overlay, colores de la barra de filtros.
- **Menú de navegación**: animaciones del puntero (`animation_line`, `framed`, `background`, `text`) e icono del submenú.
- **Mapa del sitio**: casi todo (`sitemap_items`, `sitemap_columns`, consulta, exclusiones y estilos).
- **Ajustes sueltos**:
  - logotipo e imagen destacada: lightbox y efectos al pasar el ratón;
  - navegación de entradas: iconos de flecha;
  - tabla de contenidos: `include_exclude_tags`;
  - testimonios: alineación izquierda y derecha, estilo de tarjeta;
  - buscador clásico: estilos del modo completo;
  - resaltado de código: 15 lenguajes más.

## 6. Plan de trabajo

Orden pensado para que lo que se importa desde Pro se vea igual cuanto antes:

| Fase | Contenido | Estado |
| --- | --- | --- |
| 0.8.0 | §3.1 (excepto condiciones por elemento), §3.2, §3.3 y §3.4 (excepto WooCommerce): claves de Pro en plantillas, popups, kit y fondos | ✅ |
| 0.8.1 | §3.5 condiciones, §3.6 etiquetas dinámicas (excepto `off-canvas`, Toolset y Pods) y vista previa de secciones guardadas | ✅ |
| 0.9.0 | Loop Grid/Carousel completos y Search | ✅ |
| 0.9.1 | Formulario: integraciones y acción de popup | ✅ |
| 0.10.0 | WooCommerce: widgets ausentes, ajustes de kit y estilos | ✅ |
| 0.11.0 | Carruseles, galería, menú, mapa del sitio y resto del anexo A | ☐ |
| 0.12.0 | Mega menú, Off-canvas, widget global y shortcode | ☐ |
| 0.13.0 | Hotspot, Lottie, Portafolio, Reseñas y Lista de vídeos | ☐ |
| 0.14.0 | Botones flotantes y Link in bio de Pro | ☐ |
| 1.0.0 | Condiciones por elemento, notas, fuentes de Adobe, paquetes de iconos, WP-CLI y pagos | ☐ |
| — | Editor v4 (formulario atómico, interacciones, variables, Collection Loop) | según evolucione Elementor |

### Estado tras 0.8.0

Nuevo inventario de OBX comparado con Pro 4.2.3 (ajustes ausentes por tipo de documento o elemento):

| Tipo | Antes | Después |
| --- | --- | --- |
| Cabecera y pie | 42 | 0 |
| Individual y archivo | 46 | 0 |
| Popup | 106 | 0 |
| Elemento de bucle | 8 | 0 |
| Contenedor, sección y columna | 41 | 1 (`e_display_conditions`, fase 1.0.0) |
| Página | 4 | 0 |
| Sección y botones flotantes guardados | 5 | 0 (0.8.1) |
| Kit | — | 0 (pestaña WooCommerce en 0.10.0) |

`tests/parity.php` comprueba las claves, los valores por defecto y el renderizado de todo lo anterior.

### Estado tras 0.8.1

- Condiciones: se leen y escriben con los nombres de Pro, también con el grupo delante (`include/woocommerce/product_archive/shop_page`). Las reglas antiguas `singular/product` y `archive/product_archive` se convierten en `product` y `product_archive`.
- Etiquetas dinámicas: solo falta `off-canvas` respecto a Pro 4.2.3 (con WooCommerce).
- `tests/parity.php`: 194 pruebas.

### Estado tras 0.9.0

| Widget | Antes | Después |
| --- | --- | --- |
| `loop-grid` | 169 | 13 (opciones propias de cada sitio y pestañas sin datos) |
| `loop-carousel` | 114 | 12 |
| `search` | 296 | 3 (opciones propias de cada sitio, una pestaña y `e_display_conditions`) |

- `tests/parity.php`: 405 pruebas.

### Estado tras 0.9.1

| Widget | Antes | Después |
| --- | --- | --- |
| `form` | 67 | 1 (`e_display_conditions`, fase 1.0.0) |

- `tests/parity.php`: 488 pruebas (las peticiones a los servicios se simulan con `pre_http_request`).

### Estado tras 0.10.0

| Widget o documento | Antes | Después |
| --- | --- | --- |
| `woocommerce-checkout-page` | 700 | 1 |
| `woocommerce-my-account` | 548 | 1 |
| `woocommerce-cart` | 470 | 1 |
| `woocommerce-menu-cart` | 214 | 1 |
| `woocommerce-archive-products` | 183 | 2 (`query_query_args`, una pestaña sin datos) |
| `woocommerce-products` | 169 | 2 |
| `woocommerce-product-related` y `-upsell` | 153 | 1 |
| `woocommerce-product-add-to-cart` | 90 | 1 |
| Resto de widgets de producto (título, precio, meta, pestañas, imágenes, valoración, existencias…) | 11–46 | 1 |
| `woocommerce-notices`, `woocommerce-purchase-summary` | ausentes | 1 |
| `wc-add-to-cart`, `wc-elements`, `wc-single-elements` | ausentes | 1 |
| `wc-categories`, `wc-products`, `wc-archive-products` | ausentes | 1–3 (opciones con los ID de términos del sitio de referencia) |
| Kit (pestaña WooCommerce) | ausente | 0 |

El hueco que queda en todos es `e_display_conditions` (condiciones por elemento, fase 1.0.0).

- `tests/parity.php`: 1149 pruebas.

Cada fase:

1. Implementa con la especificación de `dev/pro-spec/spec-missing.json`.
2. Añade pruebas en `tests/` que comprueben claves, valores por defecto y renderizado.
3. Vuelve a ejecutar el inventario para confirmar que el hueco se ha cerrado.

## Anexo A · Ajustes ausentes por widget

Generado desde el inventario (Pro 4.2.3 y 4.1.2 con WooCommerce frente a OBX 0.7.3). Los grupos de controles se muestran como `prefijo_grupo*` (fondo, tipografía, borde, sombra…); las variantes por dispositivo se cuentan una vez. Se omiten los ajustes comunes a todos los widgets (ver §3).

### `woocommerce-checkout-page` — 163 ajustes

`checkout_layout`, `sticky_right_column`, `sticky_right_column_offset`, `billing_details_section_title`, `billing_details_alignment`, `billing_details_form_fields`, `additional_information_active`, `additional_information_form_fields`, `order_summary_section_title`, `order_summary_alignment`, `coupon_section_display`, `coupon_section_title_text`, `coupon_section_title_link_text`, `coupon_alignment`, `coupon_button_alignment`, `terms_conditions_message_text`, `terms_conditions_link_text`, `purchase_button_alignment`, `sections_background*`, `section_normal_box_shadow*`, `sections_border*`, `sections_padding`, `sections_margin`, `sections_title_color`, `sections_titles_typography*`, `sections_titles_text_shadow*`, `sections_title_spacing`, `sections_secondary_title_color`, `sections_secondary_titles_typography*`, `sections_secondary_title_spacing`, `sections_descriptions_color`, `sections_descriptions_typography*`, `sections_descriptions_spacing`, `sections_messages_color`, `sections_messages_typography*`, `sections_checkboxes_color`, `sections_checkboxes_typography*`, `sections_radio_buttons_color`, `sections_radio_buttons_typography*`, `links_normal_color`, `links_hover_color`, `forms_columns_gap`, `forms_rows_gap`, `forms_label_color`, `forms_label_typography*`, `forms_label_spacing`, `forms_field_typography*`, `forms_fields_normal_color`, `forms_fields_normal_background*`, `forms_fields_normal_box_shadow*`, `forms_fields_focus_color`, `forms_fields_focus_background*`, `forms_fields_focus_box_shadow*`, `forms_fields_focus_border*`, `forms_fields_focus_transition_duration`, `forms_fields_border*`, `forms_fields_padding`, `forms_button_typography*`, `forms_button_text_shadow*`, `forms_buttons_normal_text_color`, `forms_buttons_normal_background*`, `forms_buttons_normal_box_shadow*`, `forms_buttons_hover_text_color`, `forms_buttons_hover_background*`, `forms_buttons_focus_box_shadow*`, `forms_buttons_hover_border*`, `forms_buttons_hover_transition_duration`, `forms_buttons_hover_animation`, `forms_buttons_border*`, `forms_buttons_padding`, `order_summary_rows_gap`, `order_summary_items_color`, `order_summary_items_typography*`, `order_summary_variations_color`, `order_summary_variations_typography*`, `order_summary_items_divider_color`, `order_summary_items_divider_weight`, `order_summary_totals_color`, `order_summary_totals_typography*`, `order_summary_totals_divider_color`, `order_summary_totals_divider_weight`, `purchase_button_typography*`, `purchase_button_text_shadow*`, `purchase_button_normal_text_color`, `purchase_button_normal_background*`, `purchase_button_normal_box_shadow*`, `purchase_button_hover_text_color`, `purchase_button_hover_background*`, `purchase_button_hover_box_shadow*`, `purchase_button_hover_border*`, `purchase_button_hover_transition_duration`, `purchase_button_hover_animation`, `purchase_button_border*`, `purchase_button_padding`, `section_checkout_show_customize_elements`, `customize_returning_customer_background*`, `returning_customers_section_normal_box_shadow*`, `returning_customers_border*`, `returning_customers_padding`, `returning_customers_margin`, `returning_customers_secondary_title_color`, `returning_customers_content_typography*`, `returning_customers_description_color`, `returning_customers_description_typography*`, `returning_customers_checkboxes_color`, `returning_customers_checkboxes_typography*`, `returning_customers_normal_links_color`, `returning_customers_hover_links_color`, `customize_billing_details_background*`, `billing_details_section_normal_box_shadow*`, `billing_details_border*`, `billing_details_padding`, `billing_details_margin`, `billing_details_titles_color`, `billing_details_titles_typography*`, `billing_details_titles_text_shadow*`, `billing_details_checkboxes_color`, `billing_details_checkboxes_typography*`, `customize_additional_information_background*`, `additional_information_section_normal_box_shadow*`, `additional_information_border*`, `additional_information_padding`, `additional_information_margin`, `additional_information_titles_color`, `additional_information_titles_typography*`, `additional_information_titles_text_shadow*`, `customize_coupon_background*`, `coupon_section_normal_box_shadow*`, `coupon_border*`, `coupon_padding`, `coupon_margin`, `coupon_secondary_title_color`, `coupon_content_typography*`, `coupon_normal_links_color`, `coupon_hover_links_color`, `customize_order_summary_background*`, `order_summary_section_normal_box_shadow*`, `order_summary_border*`, `order_summary_padding`, `order_summary_margin`, `order_summary_titles_color`, `order_summary_titles_typography*`, `order_summary_titles_text_shadow*`, `order_summary_descriptions_color`, `order_summary_descriptions_typography*`, `order_summary_radios_color`, `order_summary_radio_typography*`, `customize_payment_background*`, `payment_section_normal_box_shadow*`, `payment_border*`, `payment_padding`, `payment_margin`, `payment_info_box_title_background*`, `payment_description_color`, `payment_description_typography*`, `payment_messages_color`, `payment_messages_typography*`, `payment_checkboxes_color`, `payment_checkboxes_typography*`, `payment_radio_color`, `payment_radio_typography*`, `payment_normal_color`, `payment_hover_color`

### `loop-grid` — 142 ajustes

`_skin`, `columns`, `edit_handle_selector`, `product_query_post_type`, `product_query_posts_ids`, `product_query_include`, `product_query_include_term_ids`, `product_query_include_authors`, `product_query_exclude`, `product_query_exclude_ids`, `product_query_exclude_term_ids`, `product_query_orderby`, `product_query_order`, `post_taxonomy_query_post_type`, `post_taxonomy_query_filter_by`, `post_taxonomy_query_posts_ids`, `post_taxonomy_query_include_term_ids`, `post_taxonomy_query_include_authors`, `post_taxonomy_query_exclude_ids`, `post_taxonomy_query_exclude_term_ids`, `post_taxonomy_query_exclude_authors`, `post_taxonomy_child_of`, `post_taxonomy_posts_ids`, `post_taxonomy_exclude_ids`, `post_taxonomy_orderby`, `post_taxonomy_order`, `post_taxonomy_avoid_duplicates`, `post_taxonomy_hide_empty`, `post_taxonomy_offset`, `post_taxonomy_hierarchical`, `post_taxonomy_child_taxonomy_depth`, `post_taxonomy_term_taxonomy_id`, `product_taxonomy_query_post_type`, `product_taxonomy_query_filter_by`, `product_taxonomy_query_posts_ids`, `product_taxonomy_query_include_term_ids`, `product_taxonomy_query_include_authors`, `product_taxonomy_query_exclude_ids`, `product_taxonomy_query_exclude_term_ids`, `product_taxonomy_query_exclude_authors`, `product_taxonomy_child_of`, `product_taxonomy_posts_ids`, `product_taxonomy_exclude_ids`, `product_taxonomy_orderby`, `product_taxonomy_order`, `product_taxonomy_avoid_duplicates`, `product_taxonomy_hide_empty`, `product_taxonomy_offset`, `product_taxonomy_hierarchical`, `product_taxonomy_child_taxonomy_depth`, `product_taxonomy_term_taxonomy_id`, `load_more_button_align`, `pagination_load_type`, `auto_scroll`, `auto_scroll_offset`, `pagination_individual_handle`, `load_more_spinner`, `selected_icon`, `icon_align`, `icon_indent`, `button_css_id`, `load_more_no_posts_message_align`, `load_more_no_posts_message_switcher`, `content_align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_shadow_text_shadow*`, `button_text_color`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `button_box_shadow*`, `hover_color`, `button_background*`, `button_hover_border*`, `button_hover_box_shadow*`, `button_hover_transition_duration`, `hover_animation`, `border_border*`, `border_width`, `border_color`, `border_radius`, `text_padding`, `load_more_no_posts_message_typography*`, `load_more_no_posts_message_font_family`, `load_more_no_posts_message_font_size`, `load_more_no_posts_message_font_weight`, `load_more_no_posts_message_text_transform`, `load_more_no_posts_message_font_style`, `load_more_no_posts_message_text_decoration`, `load_more_no_posts_message_line_height`, `load_more_no_posts_message_letter_spacing`, `load_more_no_posts_message_word_spacing`, `load_more_no_posts_message_color`, `load_more_spinner_color`, `load_more_spacing`, `enable_nothing_found_message`, `nothing_found_message_align`, `nothing_found_message_html_tag`, `row_gap`, `nothing_found_message_space_from_top`, `nothing_found_message_space_from_bottom`, `nothing_found_message_typography*`, `nothing_found_message_color`, `nothing_found_message_text_shadow*`, `nothing_found_message_text_stroke*`

- `alternate_templates` (repetidor) sin los campos `static_position`
- `post_query_related_taxonomies` sin las opciones `post_format`, `product_type`, `product_visibility`, `product_shipping_class`, `pos_product_visibility`
- `pagination_align` sin las opciones `start`, `end`

### `woocommerce-cart` — 115 ajustes

`cart_layout`, `sticky_right_column`, `sticky_right_column_offset`, `update_cart_button_text`, `update_cart_button_alignment`, `section_coupon_display`, `apply_coupon_button_text`, `apply_coupon_button_alignment`, `totals_section_title`, `totals_section_title_alignment`, `update_shipping_button_text`, `update_shipping_button_alignment`, `checkout_button_text`, `checkout_button_alignment`, `update_cart_automatically`, `additional_template_switch`, `additional_template_select`, `sections_background*`, `section_normal_box_shadow*`, `sections_border*`, `sections_padding`, `sections_margin`, `sections_title_color`, `sections_titles_typography*`, `sections_titles_text_shadow*`, `sections_title_spacing`, `sections_descriptions_color`, `sections_descriptions_typography*`, `sections_descriptions_spacing`, `links_normal_color`, `links_hover_color`, `sections_radio_buttons_color`, `sections_radio_buttons_typography*`, `forms_rows_gap`, `forms_field_typography*`, `forms_fields_normal_color`, `forms_fields_normal_background*`, `forms_fields_normal_box_shadow*`, `forms_fields_focus_color`, `forms_fields_focus_background*`, `forms_fields_focus_box_shadow*`, `forms_fields_focus_border*`, `forms_fields_focus_transition_duration`, `forms_fields_border*`, `forms_fields_padding`, `forms_button_typography*`, `forms_button_text_shadow*`, `forms_buttons_normal_text_color`, `forms_buttons_normal_background*`, `forms_buttons_normal_box_shadow*`, `forms_buttons_hover_text_color`, `forms_buttons_hover_background*`, `forms_buttons_focus_box_shadow*`, `forms_buttons_hover_border*`, `forms_buttons_hover_transition_duration`, `forms_buttons_hover_animation`, `forms_buttons_border*`, `forms_buttons_padding`, `order_summary_rows_gap`, `order_summary_title_color`, `order_summary_title_typography*`, `order_summary_title_text_shadow*`, `order_summary_title_spacing`, `order_summary_color`, `order_summary_items_typography*`, `order_summary_variations_color`, `order_summary_variations_typography*`, `product_link_normal_color`, `product_link_hover_color`, `order_summary_items_divider_color`, `order_summary_items_divider_weight`, `order_summary_quantity_border*`, `order_summary_remove_icon_normal_color`, `order_summary_remove_icon_hover_color`, `totals_rows_gap`, `totals_color`, `totals_typography*`, `totals_divider_color`, `totals_divider_weight`, `checkout_button_typography*`, `checkout_button_text_shadow*`, `checkout_button_normal_text_color`, `checkout_button_normal_background*`, `checkout_button_normal_box_shadow*`, `checkout_button_hover_text_color`, `checkout_button_hover_background*`, `checkout_button_hover_box_shadow*`, `checkout_button_hover_border*`, `checkout_button_hover_transition_duration`, `checkout_button_hover_animation`, `checkout_button_border*`, `checkout_button_padding`, `section_cart_show_customize_elements`, `order_summary_section_background*`, `order_summary_section_normal_box_shadow*`, `order_summary_section_border*`, `order_summary_section_padding`, `order_summary_section_margin`, `sections_totals_background*`, `totals_section_normal_box_shadow*`, `totals_section_border*`, `checkout_sections_padding`, `totals_section_margin`, `totals_section_titles_color`, `totals_section_titles_typography*`, `totals_section_titles_text_shadow*`, `totals_section_content_color`, `totals_section_content_typography*`, `totals_section_links_normal_color`, `totals_section_links_hover_color`, `customize_coupon_background*`, `customize_coupon_section_normal_box_shadow*`, `customize_coupon_section_border*`, `customize_coupon_section_padding`, `customize_coupon_section_margin`

### `posts` — 103 ajustes

`classic_columns`, `classic_masonry`, `classic_read_more_alignment`, `cards_columns`, `cards_read_more_alignment`, `full_content_item_ratio`, `full_content_open_new_tab`, `classic_row_gap`, `classic_box_shadow*`, `classic_box_bg_color_hover`, `classic_box_border*`, `classic_text_stroke*`, `cards_row_gap`, `cards_avatar_size`, `cards_text_stroke*`, `full_content_box_shadow*`, `full_content_box_bg_color_hover`, `full_content_box_border*`, `full_content_text_stroke*`, `full_content_excerpt_color`, `full_content_excerpt_typography*`, `full_content_excerpt_spacing`, `full_content_read_more_color`, `full_content_read_more_typography*`, `full_content_read_more_spacing`, `pagination_individual_handle`, `load_more_spinner`, `selected_icon`, `icon_align`, `icon_indent`, `button_css_id`, `load_more_no_posts_message_align`, `load_more_no_posts_message_switcher`, `align`, `content_align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_shadow_text_shadow*`, `button_text_color`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `button_box_shadow*`, `hover_color`, `button_background*`, `button_hover_border*`, `button_hover_box_shadow*`, `button_hover_transition_duration`, `hover_animation`, `border_border*`, `border_width`, `border_color`, `border_radius`, `text_padding`, `load_more_no_posts_message_typography*`, `load_more_no_posts_message_font_family`, `load_more_no_posts_message_font_size`, `load_more_no_posts_message_font_weight`, `load_more_no_posts_message_text_transform`, `load_more_no_posts_message_font_style`, `load_more_no_posts_message_text_decoration`, `load_more_no_posts_message_line_height`, `load_more_no_posts_message_letter_spacing`, `load_more_no_posts_message_word_spacing`, `load_more_no_posts_message_color`, `load_more_spinner_color`, `load_more_spacing`

- `cards_badge_taxonomy` sin las opciones `post_format`
- `posts_related_taxonomies` sin las opciones `post_format`, `product_type`, `product_visibility`, `product_shipping_class`, `pos_product_visibility`
- `pagination_align` sin las opciones `start`, `end`

### `archive-posts` — 102 ajustes

`archive_classic_columns`, `archive_classic_masonry`, `archive_classic_read_more_alignment`, `archive_cards_columns`, `archive_cards_read_more_alignment`, `archive_full_content_item_ratio`, `archive_full_content_open_new_tab`, `archive_classic_row_gap`, `archive_classic_text_stroke*`, `archive_cards_row_gap`, `archive_cards_avatar_size`, `archive_cards_text_stroke*`, `archive_full_content_box_shadow*`, `archive_full_content_box_bg_color_hover`, `archive_full_content_box_border*`, `archive_full_content_text_stroke*`, `archive_full_content_excerpt_color`, `archive_full_content_excerpt_typography*`, `archive_full_content_excerpt_spacing`, `archive_full_content_read_more_color`, `archive_full_content_read_more_typography*`, `archive_full_content_read_more_spacing`, `pagination_individual_handle`, `load_more_spinner`, `selected_icon`, `icon_align`, `icon_indent`, `button_css_id`, `load_more_no_posts_message_align`, `load_more_no_posts_message_switcher`, `align`, `content_align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_shadow_text_shadow*`, `button_text_color`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `button_box_shadow*`, `hover_color`, `button_background*`, `button_hover_border*`, `button_hover_box_shadow*`, `button_hover_transition_duration`, `hover_animation`, `border_border*`, `border_width`, `border_color`, `border_radius`, `text_padding`, `load_more_no_posts_message_typography*`, `load_more_no_posts_message_font_family`, `load_more_no_posts_message_font_size`, `load_more_no_posts_message_font_weight`, `load_more_no_posts_message_text_transform`, `load_more_no_posts_message_font_style`, `load_more_no_posts_message_text_decoration`, `load_more_no_posts_message_line_height`, `load_more_no_posts_message_letter_spacing`, `load_more_no_posts_message_word_spacing`, `load_more_no_posts_message_color`, `load_more_spinner_color`, `load_more_spacing`, `nothing_found_color`, `nothing_found_typography*`

- `archive_cards_badge_taxonomy` sin las opciones `post_format`
- `pagination_align` sin las opciones `start`, `end`

### `woocommerce-menu-cart` — 100 ajustes

`icon`, `menu_icon_svg`, `hide_empty_indicator`, `alignment`, `cart_type`, `open_cart`, `side_cart_alignment`, `mini_cart_alignment`, `mini_cart_spacing`, `close_cart_button_show`, `close_cart_icon_svg`, `close_cart_button_alignment`, `show_remove_icon`, `remove_item_button_position`, `price_quantity_position`, `show_divider`, `view_cart_button_show`, `view_cart_button_alignment`, `checkout_button_show`, `checkout_button_alignment`, `checkout_button_display`, `buttons_position`, `automatically_open_cart`, `automatically_update_cart`, `toggle_button_text_color`, `toggle_button_icon_color`, `toggle_button_background*`, `toggle_button_border*`, `toggle_button_normal_box_shadow*`, `toggle_button_hover_text_color`, `toggle_button_hover_icon_color`, `toggle_button_hover_background*`, `toggle_button_hover_border*`, `toggle_button_hover_box_shadow*`, `toggle_button_typography*`, `toggle_icon_size`, `toggle_icon_spacing`, `toggle_icon_position`, `toggle_button_padding`, `items_indicator_text_color`, `items_indicator_background*`, `items_indicator_distance`, `background_color`, `border_type`, `border_width`, `border_color`, `border_radius`, `cart_box_shadow*`, `cart_padding`, `close_cart_icon_size`, `close_cart_icon_color`, `close_cart_icon_hover_color`, `remove_item_button_size`, `remove_item_button_color`, `remove_item_button_hover_color`, `subtotal_color`, `subtotal_typography*`, `subtotal_alignment`, `subtotal_divider_style`, `subtotal_divider_width`, `subtotal_divider_color`, `product_title_typography*`, `product_title_color`, `product_title_hover_color`, `product_variations_color`, `product_variations_typography*`, `product_price_color`, `product_price_typography*`, `product_quantity_color`, `product_quantity_typography*`, `divider_style`, `divider_color`, `divider_width`, `divider_gap`, `buttons_layout`, `space_between_buttons`, `product_buttons_typography*`, `button_border*`, `view_cart_buttons_typography*`, `view_cart_button_text_color`, `view_cart_button_background*`, `view_cart_button_hover_text_color`, `view_cart_button_hover_background*`, `view_cart_button_border*`, `view_cart_border*`, `view_cart_button_box_shadow*`, `view_cart_button_padding`, `cart_checkout_button_typography*`, `checkout_button_text_color`, `checkout_button_background*`, `checkout_button_hover_text_color`, `checkout_button_hover_background*`, `checkout_button_border*`, `checkout_border*`, `view_checkout_button_border*`, `view_checkout_button_box_shadow*`, `view_checkout_button_padding`, `cart_empty_message_typography*`, `empty_message_color`, `empty_message_alignment`

### `loop-carousel` — 98 ajustes

`_skin`, `edit_handle_selector`, `product_query_post_type`, `product_query_posts_ids`, `product_query_include`, `product_query_include_term_ids`, `product_query_include_authors`, `product_query_exclude`, `product_query_exclude_ids`, `product_query_exclude_term_ids`, `product_query_orderby`, `product_query_order`, `post_taxonomy_query_post_type`, `post_taxonomy_query_filter_by`, `post_taxonomy_query_posts_ids`, `post_taxonomy_query_include_term_ids`, `post_taxonomy_query_include_authors`, `post_taxonomy_query_exclude_ids`, `post_taxonomy_query_exclude_term_ids`, `post_taxonomy_query_exclude_authors`, `post_taxonomy_child_of`, `post_taxonomy_posts_ids`, `post_taxonomy_exclude_ids`, `post_taxonomy_orderby`, `post_taxonomy_order`, `post_taxonomy_avoid_duplicates`, `post_taxonomy_hide_empty`, `post_taxonomy_offset`, `post_taxonomy_hierarchical`, `post_taxonomy_child_taxonomy_depth`, `post_taxonomy_term_taxonomy_id`, `product_taxonomy_query_post_type`, `product_taxonomy_query_filter_by`, `product_taxonomy_query_posts_ids`, `product_taxonomy_query_include_term_ids`, `product_taxonomy_query_include_authors`, `product_taxonomy_query_exclude_ids`, `product_taxonomy_query_exclude_term_ids`, `product_taxonomy_query_exclude_authors`, `product_taxonomy_child_of`, `product_taxonomy_posts_ids`, `product_taxonomy_exclude_ids`, `product_taxonomy_orderby`, `product_taxonomy_order`, `product_taxonomy_avoid_duplicates`, `product_taxonomy_hide_empty`, `product_taxonomy_offset`, `product_taxonomy_hierarchical`, `product_taxonomy_child_taxonomy_depth`, `product_taxonomy_term_taxonomy_id`, `direction`, `offset_sides`, `offset_width`, `navigation_previous_icon_horizontal_orientation`, `navigation_previous_icon_horizontal_position`, `navigation_previous_icon_vertical_orientation`, `navigation_previous_icon_vertical_position`, `navigation_next_icon_horizontal_orientation`, `navigation_next_icon_horizontal_position`, `navigation_next_icon_vertical_orientation`, `navigation_next_icon_vertical_position`, `image_spacing_custom`, `arrow_normal_color`, `arrows_normal_box_shadow*`, `arrow_hover_color`, `arrows_hover_background*`, `arrows_hover_border*`, `arrows_hover_box_shadow*`, `arrows_position`, `dots_normal_color`, `dots_hover_color`, `dots_custom_position`, `dots_horizontal_position`, `dots_horizontal_offset`, `dots_vertical_position`, `dots_vertical_offset`, `dots_position`, `typography_fraction_typography*`, `typography_fraction_font_family`, `typography_fraction_font_size`, `typography_fraction_font_weight`, `typography_fraction_text_transform`, `typography_fraction_font_style`, `typography_fraction_text_decoration`, `typography_fraction_line_height`, `typography_fraction_letter_spacing`, `typography_fraction_word_spacing`, `fraction_color`, `fraction_custom_position`, `fraction_horizontal_position`, `fraction_horizontal_offset`, `fraction_vertical_position`, `fraction_vertical_offset`, `fraction_position`, `fraction_pagination_spacing`, `progressbar_height`, `progressbar_normal_color`, `progressbar_hover_color`

- `post_query_related_taxonomies` sin las opciones `post_format`, `product_type`, `product_visibility`, `product_shipping_class`, `pos_product_visibility`

### `search` — 96 ajustes

`autocomplete`, `live_results`, `template_id`, `minimum_search_characters`, `number_of_columns`, `number_of_items`, `equal_height`, `enable_loader`, `enable_nothing_found_message`, `nothing_found_message_text`, `nothing_found_message_html_tag`, `search_query_post_type`, `search_query_posts_ids`, `search_query_include`, `search_query_include_term_ids`, `search_query_include_authors`, `search_query_exclude`, `search_query_exclude_ids`, `search_query_exclude_term_ids`, `search_query_exclude_authors`, `search_query_select_date`, `search_query_date_before`, `search_query_date_after`, `search_query_orderby`, `search_query_order`, `search_query_ignore_sticky_posts`, `search_query_query_id`, `pagination_type_options`, `pagination_prev_label`, `pagination_next_label`, `page_limit_settings`, `pagination_shorten_settings`, `search_field_background*`, `search_field_input_text_color_normal`, `search_field_icon_search_normal_color_normal`, `search_field_border*`, `search_field_box_shadow*`, `search_field_input_text_color_focus`, `search_field_icon_search_normal_color_focus`, `search_field_search_input_transition`, `search_field_icon_label_size`, `search_field_icon_gap`, `icon_clear_size`, `icon_clear_normal_color`, `icon_clear_hover_color`, `icon_clear_hover_transition`, `icon_submit_position`, `icon_submit_gap`, `submit_text_color_normal`, `submit_icon_color_normal`, `submit_background*`, `submit_border*`, `submit_box_shadow*`, `submit_text_color_hover`, `submit_icon_color_hover`, `submit_hover_animation`, `submit_hover_transition`, `results_background*`, `results_border*`, `results_box_shadow*`, `results_padding`, `results_distance_from_search_field`, `results_is_dropdown_width`, `results_is_custom_width`, `results_custom_width`, `results_max_height`, `results_alignment`, `search_result_column_gap`, `search_result_row_gap`, `search_result_loader_icon_color`, `search_result_loader_icon_size`, `style_additional_settings_alignment`, `style_additional_settings_vertical_position`, `typography_title_typography*`, `typography_title_font_family`, `typography_title_font_size`, `typography_title_font_weight`, `typography_title_text_transform`, `typography_title_font_style`, `typography_title_text_decoration`, `typography_title_line_height`, `typography_title_letter_spacing`, `typography_title_word_spacing`, `style_additional_settings_normal_color`, `style_additional_settings_hover_color`, `style_button_color_icon_active`, `page_numbers_space_between`, `top_spacing`, `bottom_spacing`, `nothing_found_message_space_from_top`, `nothing_found_message_space_from_bottom`, `nothing_found_message_alignment`, `nothing_found_message_typography*`, `nothing_found_message_color`, `nothing_found_message_text_shadow*`, `nothing_found_message_text_stroke*`

### `woocommerce-my-account` — 94 ajustes

`tabs_layout`, `tabs_content_spacing`, `tabs_position`, `tabs`, `tabs_alignment`, `customize_dashboard_check`, `customize_dashboard_select`, `tabs_typography*`, `tabs_normal_background*`, `tabs_normal_box_shadow*`, `tabs_normal_color`, `tabs_hover_background*`, `tabs_hover_box_shadow*`, `tabs_hover_color`, `tabs_hover_border*`, `tabs_active_background*`, `tabs_active_box_shadow*`, `tabs_active_color`, `tabs_active_border*`, `tabs_border*`, `tabs_padding`, `tabs_spacing`, `tabs_divider_color`, `tabs_divider_weight`, `my_account_sections_background*`, `my_account_sections_box_shadow*`, `sections_border*`, `sections_padding`, `typography_section_titles_color`, `section_titles_typography*`, `section_title_spacing`, `general_text_color`, `general_text_typography*`, `login_messages_color`, `login_messages_typography*`, `checkboxes_color`, `checkboxes_typography*`, `payment_methods_radio_buttons_color`, `payment_methods_radio_buttons_typography*`, `links_normal_color`, `links_hover_color`, `forms_columns_gap`, `forms_rows_gap`, `forms_label_color`, `forms_label_typography*`, `forms_label_spacing`, `forms_field_typography*`, `forms_fields_normal_color`, `forms_fields_normal_background*`, `forms_fields_normal_box_shadow*`, `forms_fields_focus_color`, `forms_fields_focus_background*`, `forms_fields_focus_box_shadow*`, `forms_fields_focus_border*`, `forms_fields_focus_transition_duration`, `forms_fields_border*`, `forms_fields_padding`, `forms_button_typography*`, `forms_button_text_shadow*`, `forms_buttons_normal_text_color`, `forms_buttons_background*`, `forms_buttons_normal_box_shadow*`, `forms_buttons_hover_text_color`, `forms_buttons_hover_background*`, `forms_buttons_focus_box_shadow*`, `forms_buttons_hover_border*`, `forms_buttons_hover_transition_duration`, `forms_buttons_hover_animation`, `forms_buttons_border*`, `forms_buttons_padding`, `tables_rows_gap`, `tables_title_color`, `tables_titles_typography*`, `tables_titles_text_shadow*`, `tables_items_color`, `tables_items_typography*`, `variations_color`, `variations_typography*`, `tables_links_normal_color`, `tables_links_hover_color`, `tables_divider_border*`, `tables_button_typography*`, `tables_button_text_shadow*`, `tables_button_normal_text_color`, `tables_button_normal_background*`, `tables_button_normal_box_shadow*`, `tables_button_hover_text_color`, `tables_button_hover_background*`, `tables_button_hover_box_shadow*`, `tables_button_hover_border*`, `tables_button_hover_transition_duration`, `tables_button_hover_animation`, `tables_button_border*`, `tables_button_padding`

### `woocommerce-archive-products` — 83 ajustes

`columns`, `rows`, `paginate`, `allow_order`, `show_result_count`, `section_query`, `query_post_type`, `query_posts_ids`, `query_include`, `query_include_term_ids`, `query_include_authors`, `query_exclude`, `query_exclude_ids`, `query_exclude_term_ids`, `query_orderby`, `query_order`, `products_title_show`, `products_related_products_title_text`, `products_upsells_title_text`, `products_cross_sells_title_text`, `products_title_alignment`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `title_spacing`, `star_color`, `empty_star_color`, `star_size`, `rating_spacing`, `price_color`, `price_typography*`, `old_price_color`, `old_price_typography*`, `button_text_color`, `button_background*`, `button_border*`, `button_typography*`, `button_hover_color`, `button_hover_background*`, `button_hover_border*`, `button_text_padding`, `button_spacing`, `automatically_align_buttons`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `products_title_color`, `products_title_typography*`, `products_title_spacing`, `box_border*`, `box_padding`, `box_shadow_box_shadow*`, `box_bg_color`, `box_shadow_hover_box_shadow*`, `box_bg_color_hover`, `pagination_spacing`, `show_pagination_border*`, `pagination_border*`, `pagination_padding`, `pagination_typography*`, `pagination_link_color`, `pagination_link_bg_color`, `pagination_link_color_hover`, `pagination_link_bg_color_hover`, `pagination_link_color_active`, `pagination_link_bg_color_active`, `show_onsale_flash`, `onsale_text_color`, `onsale_text_background*`, `onsale_typography*`, `onsale_border*`, `onsale_width`, `onsale_height`, `onsale_horizontal_position`, `onsale_distance`, `nothing_found_message`, `nothing_found_color`, `nothing_found_typography*`

### `woocommerce-products` — 77 ajustes

`columns`, `rows`, `paginate`, `allow_order`, `show_result_count`, `query_include`, `query_include_term_ids`, `query_include_authors`, `query_exclude`, `query_exclude_ids`, `query_exclude_term_ids`, `query_orderby`, `query_order`, `products_title_show`, `products_related_products_title_text`, `products_upsells_title_text`, `products_cross_sells_title_text`, `products_title_alignment`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `title_spacing`, `star_color`, `empty_star_color`, `star_size`, `rating_spacing`, `price_color`, `price_typography*`, `old_price_color`, `old_price_typography*`, `button_text_color`, `button_background*`, `button_border*`, `button_typography*`, `button_hover_color`, `button_hover_background*`, `button_hover_border*`, `button_text_padding`, `button_spacing`, `automatically_align_buttons`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `products_title_color`, `products_title_typography*`, `products_title_spacing`, `box_border*`, `box_padding`, `box_shadow_box_shadow*`, `box_bg_color`, `box_shadow_hover_box_shadow*`, `box_bg_color_hover`, `pagination_spacing`, `show_pagination_border*`, `pagination_border*`, `pagination_padding`, `pagination_typography*`, `pagination_link_color`, `pagination_link_bg_color`, `pagination_link_color_hover`, `pagination_link_bg_color_hover`, `pagination_link_color_active`, `pagination_link_bg_color_active`, `show_onsale_flash`, `onsale_text_color`, `onsale_text_background*`, `onsale_typography*`, `onsale_border*`, `onsale_width`, `onsale_height`, `onsale_horizontal_position`, `onsale_distance`

- `query_post_type` sin las opciones `product`, `related_products`, `upsells`, `cross_sells`

### `form` — 65 ajustes

`button_width`, `submissions_metadata`, `mailchimp_api_key_source`, `mailchimp_api_key`, `mailchimp_list`, `mailchimp_groups`, `mailchimp_tags`, `mailchimp_double_opt_in`, `mailchimp_fields_map`, `drip_api_token_source`, `drip_custom_api_token`, `drip_account`, `drip_fields_map`, `drip_custom_fields`, `tags`, `activecampaign_api_credentials_source`, `activecampaign_api_key`, `activecampaign_api_url`, `activecampaign_list`, `activecampaign_fields_map`, `activecampaign_tags`, `getresponse_api_key_source`, `getresponse_custom_api_key`, `getresponse_list`, `getresponse_dayofcycle`, `getresponse_fields_map`, `convertkit_api_key_source`, `convertkit_custom_api_key`, `convertkit_form`, `convertkit_fields_map`, `convertkit_tags`, `mailerlite_api_key_source`, `mailerlite_custom_api_key`, `mailerlite_group`, `mailerlite_fields_map`, `allow_resubscribe`, `slack_webhook`, `slack_channel`, `slack_username`, `slack_pretext`, `slack_title`, `slack_text`, `slack_add_fields`, `slack_add_ts`, `slack_webhook_color`, `discord_webhook`, `discord_username`, `discord_avatar_url`, `discord_title`, `discord_content`, `discord_form_data`, `discord_ts`, `discord_webhook_color`, `popup_action`, `popup_action_popup_id`, `popup_action_do_not_show_again`, `button_content_align`, `hover_transition_duration`, `step_inactive_secondary_color`, `step_active_secondary_color`, `step_completed_secondary_color`, `step_divider_width`, `step_divider_gap`, `step_progress_bar_border*`, `step_progress_bar_percentage_color`

- `submit_actions` sin las opciones `mailchimp`, `drip`, `activecampaign`, `getresponse`, `convertkit`, `mailerlite`, `slack`, `discord`, `popup`

### `woocommerce-product-upsell` — 62 ajustes

`columns`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `title_spacing`, `star_color`, `empty_star_color`, `star_size`, `rating_spacing`, `price_color`, `price_typography*`, `old_price_color`, `old_price_typography*`, `button_text_color`, `button_background*`, `button_border*`, `button_typography*`, `button_hover_color`, `button_hover_background*`, `button_hover_border*`, `button_text_padding`, `button_spacing`, `automatically_align_buttons`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `show_heading`, `heading_color`, `heading_typography*`, `heading_text_align`, `heading_spacing`, `box_border*`, `box_padding`, `box_shadow_box_shadow*`, `box_bg_color`, `box_shadow_hover_box_shadow*`, `box_bg_color_hover`, `pagination_spacing`, `show_pagination_border*`, `pagination_border*`, `pagination_padding`, `pagination_typography*`, `pagination_link_color`, `pagination_link_bg_color`, `pagination_link_color_hover`, `pagination_link_bg_color_hover`, `pagination_link_color_active`, `pagination_link_bg_color_active`, `show_onsale_flash`, `onsale_text_color`, `onsale_text_background*`, `onsale_typography*`, `onsale_border*`, `onsale_width`, `onsale_height`, `onsale_horizontal_position`, `onsale_distance`

- `orderby` sin las opciones `menu_order`

### `woocommerce-product-related` — 62 ajustes

`columns`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `title_spacing`, `star_color`, `empty_star_color`, `star_size`, `rating_spacing`, `price_color`, `price_typography*`, `old_price_color`, `old_price_typography*`, `button_text_color`, `button_background*`, `button_border*`, `button_typography*`, `button_hover_color`, `button_hover_background*`, `button_hover_border*`, `button_text_padding`, `button_spacing`, `automatically_align_buttons`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `show_heading`, `heading_color`, `heading_typography*`, `heading_text_align`, `heading_spacing`, `box_border*`, `box_padding`, `box_shadow_box_shadow*`, `box_bg_color`, `box_shadow_hover_box_shadow*`, `box_bg_color_hover`, `pagination_spacing`, `show_pagination_border*`, `pagination_border*`, `pagination_padding`, `pagination_typography*`, `pagination_link_color`, `pagination_link_bg_color`, `pagination_link_color_hover`, `pagination_link_bg_color_hover`, `pagination_link_color_active`, `pagination_link_bg_color_active`, `show_onsale_flash`, `onsale_text_color`, `onsale_text_background*`, `onsale_typography*`, `onsale_border*`, `onsale_width`, `onsale_height`, `onsale_horizontal_position`, `onsale_distance`

- `orderby` sin las opciones `menu_order`

### `nested-carousel` — 47 ajustes

`direction`, `offset_sides`, `offset_width`, `navigation_previous_icon_horizontal_orientation`, `navigation_previous_icon_vertical_orientation`, `navigation_next_icon_horizontal_orientation`, `navigation_next_icon_vertical_orientation`, `image_spacing_custom`, `content_background*`, `content_border*`, `border_radius`, `arrow_normal_color`, `arrows_normal_box_shadow*`, `arrow_hover_color`, `arrows_hover_background*`, `arrows_hover_box_shadow*`, `arrows_position`, `dots_gap`, `dots_normal_color`, `dots_hover_color`, `dots_custom_position`, `dots_horizontal_position`, `dots_horizontal_offset`, `dots_vertical_position`, `dots_vertical_offset`, `dots_position`, `typography_fraction_typography*`, `typography_fraction_font_family`, `typography_fraction_font_size`, `typography_fraction_font_weight`, `typography_fraction_text_transform`, `typography_fraction_font_style`, `typography_fraction_text_decoration`, `typography_fraction_line_height`, `typography_fraction_letter_spacing`, `typography_fraction_word_spacing`, `fraction_color`, `fraction_custom_position`, `fraction_horizontal_position`, `fraction_horizontal_offset`, `fraction_vertical_position`, `fraction_vertical_offset`, `fraction_position`, `fraction_pagination_spacing`, `progressbar_height`, `progressbar_normal_color`, `progressbar_hover_color`

### `media-carousel` — 32 ajustes

`slides_name`, `effect`, `slideshow_height`, `slideshow_slides_per_view`, `thumbs_ratio`, `centered_slides`, `width`, `overlay`, `caption`, `icon`, `overlay_animation`, `image_size_size`, `image_size_custom_dimension`, `image_fit`, `lazyload`, `space_between`, `slide_background*`, `slide_border*`, `slide_padding`, `pagination_position`, `pagination_gap`, `play_icon_color`, `play_icon_size`, `play_icon_text_shadow*`, `overlay_background*`, `overlay_color`, `caption_typography*`, `icon_size`, `lightbox_color`, `lightbox_ui_color`, `lightbox_ui_hover_color`, `lightbox_video_width`

- `slides` (repetidor) sin los campos `type`, `image_link_to_type`, `image_link_to`, `video`

### `woocommerce-product-add-to-cart` — 31 ajustes

`layout`, `alignment`, `button_typography*`, `button_border*`, `button_padding`, `button_text_color`, `button_bg_color`, `button_text_color_hover`, `button_bg_color_hover`, `button_transition`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `spacing`, `quantity_typography*`, `quantity_border*`, `quantity_padding`, `quantity_text_color`, `quantity_bg_color`, `quantity_text_color_focus`, `quantity_bg_color_focus`, `quantity_transition`, `variations_width`, `variations_spacing`, `variations_space_between`, `variations_label_color_focus`, `variations_label_typography*`, `variations_select_color`, `variations_select_bg_color`, `variations_select_border*`, `variations_select_typography*`

### `slides` — 28 ajustes

`slides_name`, `slides_height`, `slides_title_tag`, `slides_description_tag`, `navigation`, `transition_speed`, `content_animation`, `slides_padding`, `slides_horizontal_position`, `slides_vertical_position`, `slides_text_align`, `text_shadow_text_shadow*`, `heading_spacing`, `description_spacing`, `button_size`, `button_border*`, `button_text_color`, `button_background*`, `button_hover_text_color`, `button_hover_background*`, `button_hover_border*`, `button_hover_transition_duration`, `arrows_position`, `dots_position`, `dots_gap`, `dots_size`, `dots_color_inactive`, `dots_color`

- `slides` (repetidor) sin los campos `slides_repeater`, `background`, `background_ken_burns`, `zoom_direction`, `background_overlay_blend_mode`, `content`, `link_click`, `style`, `custom_style`, `content_color`, `repeater_text_shadow_text_shadow_type`, `repeater_text_shadow_text_shadow`

### `woocommerce-product-title` — 21 ajustes

`title`, `link`, `size`, `align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_stroke_text_stroke*`, `text_stroke_stroke_color`, `text_shadow_text_shadow*`, `blend_mode`, `title_color`, `title_hover_color`, `title_hover_color_transition_duration`

- `header_size` sin las opciones `h5`, `h6`, `span`

### `woocommerce-product-meta` — 18 ajustes

`view`, `space_between`, `divider`, `divider_style`, `divider_weight`, `divider_width`, `divider_height`, `divider_color`, `text_typography*`, `text_color`, `link_typography*`, `link_color`, `category_caption_single`, `category_caption_plural`, `tag_caption_single`, `tag_caption_plural`, `sku_caption`, `sku_missing_caption`

### `woocommerce-product-price` — 16 ajustes

`text_align`, `price_color`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `sale_price_color`, `sale_price_typography*`, `price_block`, `sale_price_spacing`

### `sitemap` — 15 ajustes

`sitemap_items`, `sitemap_columns`, `sitemap_title_tag`, `sitemap_add_nofollow`, `sitemap_exclude`, `sitemap_exclude_ids`, `sitemap_password_protected`, `sitemap_list_indent`, `sitemap_section_padding`, `sitemap_title_color`, `sitemap_title_typography*`, `sitemap_list_item_color`, `sitemap_list_item_typography*`, `sitemap_bullet_color`, `sitemap_list_item_bullet_style`

### `woocommerce-product-data-tabs` — 14 ajustes

`tab_text_color`, `tab_bg_color`, `tabs_border*`, `active_tab_text_color`, `active_tab_bg_color`, `active_tabs_border*`, `tab_typography*`, `tab_border*`, `text_color`, `content_typography*`, `heading_color`, `content_heading_typography*`, `panel_border*`, `panel_box_shadow*`

### `testimonial-carousel` — 13 ajustes

`image_size_size`, `image_size_custom_dimension`, `space_between`, `text_stroke_text_stroke*`, `text_stroke_stroke_color`, `content_text_shadow*`, `pagination_gap`, `background_color`, `text_padding`, `border_radius`, `border`, `border_color`, `border_width`

- `alignment` sin las opciones `left`, `right`
- `alignment_tablet` sin las opciones `left`, `right`
- `alignment_mobile` sin las opciones `left`, `right`

### `gallery` — 12 ajustes

`columns`, `ideal_row_height`, `gap`, `animation_line`, `animation_framed`, `animation_background*`, `animation_text`, `background_overlay_hover_animation`, `background_overlay_animation_duration`, `content_animation_duration`, `content_sequenced_animation`, `galleries_title_color_hover_pointer_bg`

### `woocommerce-product-content` — 12 ajustes

`align`, `text_color`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`

### `theme-site-logo` — 11 ajustes

`change_logo_cta`, `open_lightbox`, `css_filters_hover_css_filter`, `css_filters_hover_blur`, `css_filters_hover_brightness`, `css_filters_hover_contrast`, `css_filters_hover_saturate`, `css_filters_hover_hue`, `background_hover_transition`, `hover_animation`, `caption_text_shadow*`

- `link_to` sin las opciones `site_url`

### `theme-post-featured-image` — 10 ajustes

`open_lightbox`, `css_filters_hover_css_filter`, `css_filters_hover_blur`, `css_filters_hover_brightness`, `css_filters_hover_contrast`, `css_filters_hover_saturate`, `css_filters_hover_hue`, `background_hover_transition`, `hover_animation`, `caption_text_shadow*`

### `search-form` — 9 ajustes

`icon_size_minimal`, `overlay_background*`, `input_box_shadow*`, `button_border*`, `toggle_color`, `toggle_background*`, `toggle_color_hover`, `toggle_icon_size`, `toggle_border*`

- `toggle_align` sin las opciones `start`, `end`

### `woocommerce-product-rating` — 7 ajustes

`star_color`, `empty_star_color`, `link_color`, `text_typography*`, `star_size`, `space_between`, `alignment`

### `nav-menu` — 5 ajustes

`animation_line`, `animation_framed`, `animation_background*`, `animation_text`, `toggle_icon_hover_animation`

- `menu` sin las opciones `menu-principal`

### `post-navigation` — 5 ajustes

`arrow_previous_icon`, `arrow_next_icon`, `label_hover_color_transition_duration`, `hover_color_transition_duration`, `arrow_hover_color_transition_duration`

- `product_taxonomy` sin las opciones `product_type`, `product_visibility`, `product_shipping_class`, `pos_product_visibility`

### `woocommerce-product-additional-information` — 5 ajustes

`show_heading`, `heading_color`, `heading_typography*`, `content_color`, `content_typography*`

### `woocommerce-product-images` — 4 ajustes

`image_border*`, `spacing`, `thumbs_border*`, `spacing_thumbs`

### `woocommerce-breadcrumb` — 4 ajustes

`text_color`, `link_color`, `text_typography*`, `alignment`

### `author-box` — 3 ajustes

`avatar_size`, `button_background*`, `button_hover_transition_duration`

### `woocommerce-product-short-description` — 3 ajustes

`text_align`, `text_color`, `text_typography*`

### `woocommerce-archive-description` — 3 ajustes

`text_align`, `text_color`, `text_typography*`

### `theme-site-title` — 2 ajustes

`title`, `title_hover_color_transition_duration`

### `theme-page-title` — 2 ajustes

`title`, `title_hover_color_transition_duration`

### `theme-post-title` — 2 ajustes

`title`, `title_hover_color_transition_duration`

### `theme-archive-title` — 2 ajustes

`title`, `title_hover_color_transition_duration`

### `woocommerce-product-stock` — 2 ajustes

`text_color`, `text_typography*`

### `theme-post-excerpt` — 1 ajustes

`link_hover_color_transition_duration`

### `login` — 1 ajustes

`align`

### `call-to-action` — 1 ajustes

`animation_class`

### `table-of-contents` — 1 ajustes

`min_height`

### `post-comments` — 1 ajustes

`skin_temp`

### `post-info` — 1 ajustes

`icon_align`

- `icon_align` sin las opciones `left`, `right`

### `taxonomy-filter` — 1 ajustes

`item_alignment_horizontal`

### `code-highlight` — 0 ajustes

- `language` sin las opciones `html`, `sass`, `less`, `tsx`, `http`, `xml`, `svg`, `dart`, `julia`, `scala`, `haskell`, `objectivec`, `visual-basic,`, `r`, `aspnet`

## Anexo B · Widgets ausentes y sus ajustes

### `contact-buttons-var-1` · Multi Chat · general

`chat_button_section`, `chat_aria_label`, `chat_button_icon`, `chat_button_show_dot`, `top_bar_section`, `top_bar_title`, `top_bar_subtitle`, `top_bar_image`, `top_bar_show_dot`, `message_bubble_section`, `message_bubble_name`, `message_bubble_body`, `chat_button_time_format`, `contact_section`, `contact_cta_text`, `contact_repeater`, `style_chat_button`, `style_chat_button_size`, `style_button_color_icon`, `style_button_color_background*`, `style_button_color_icon_hover`, `style_button_color_hover_animation`, `style_chat_button_animation`, `style_chat_button_animation_duration`, `style_chat_button_animation_delay`, `style_chat_button_box_shadow*`, `style_top_bar_section`, `style_top_bar_image_size*`, `style_top_bar_title_color`, `style_top_bar_title_typography*`, `style_top_bar_subtitle_color`, `style_top_bar_subtitle_typography*`, `style_top_bar_close_button_color`, `style_top_bar_background*`, `style_bubble_section`, `style_bubble_name_color`, `style_bubble_name_typography*`, `style_bubble_message_color`, `style_bubble_message_typography*`, `style_bubble_time_color`, `style_bubble_time_typography*`, `style_bubble_background*`, `style_contact_section`, `style_contact_text_color`, `style_contact_text_typography*`, `style_contact_button_size`, `style_contact_button_color_icon`, `style_contact_button_color_background*`, `style_contact_button_color_icon_hover`, `style_contact_button_hover_animation`, `style_chat_box_section`, `style_chat_box_bg_color`, `style_chat_box_width`, `style_chat_box_corners`, `style_chat_box_box_shadow*`, `style_chat_box_entrance_animation`, `style_chat_box_exit_animation`, `style_chat_box_animation_duration`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-10` · Interactive · general

`contact_section`, `contact_aria_label`, `contact_repeater`, `style_contact_section`, `style_contact_button_size`, `style_contact_button_text_color`, `style_contact_typography*`, `style_contact_button_bg_color`, `style_contact_buttons_spacing`, `style_contact_corners`, `style_contact_icons_box_shadow*`, `style_contact_padding`, `style_contact_custom_animation_transition`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-3` · Info Button Box · general

`chat_button_section`, `chat_aria_label`, `chat_button_icon`, `top_bar_section`, `top_bar_title`, `contact_section`, `contact_repeater`, `send_button_section`, `send_button_text`, `send_button_url`, `style_chat_button`, `style_chat_button_size`, `style_button_color_icon`, `style_button_color_background*`, `style_button_color_icon_hover`, `style_button_color_hover_animation`, `style_button_color_icon_active`, `style_chat_button_animation`, `style_chat_button_animation_duration`, `style_chat_button_animation_delay`, `style_chat_button_box_shadow*`, `style_top_bar_section`, `style_top_bar_title_color`, `style_top_bar_title_typography*`, `style_top_bar_close_button_color`, `style_info_links_section`, `style_info_links_icon_position`, `style_info_links_icon_spacing`, `style_info_links_link_spacing`, `style_info_links_typography*`, `style_info_links_normal_text_color`, `style_info_links_hover_text_color`, `style_info_links_hover_animation`, `style_info_links_dividers`, `style_info_links_divider_color`, `style_info_links_divider_weight`, `style_send_section`, `style_send_typography*`, `style_send_normal_text_color`, `style_send_normal_background*`, `style_send_hover_text_color`, `style_send_hover_background*`, `style_send_hover_animation`, `style_chat_button_padding`, `style_chat_box_section`, `style_chat_box_bg_color`, `style_chat_box_corners`, `style_chat_box_box_shadow*`, `style_chat_box_padding`, `style_chat_box_entrance_animation`, `style_chat_box_exit_animation`, `style_chat_box_animation_duration`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-4` · Vertical Links · general

`chat_button_section`, `chat_aria_label`, `chat_button_icon`, `contact_section`, `contact_repeater`, `style_chat_button`, `style_chat_button_size`, `style_button_color_icon`, `style_button_color_background*`, `style_button_color_icon_hover`, `style_button_color_hover_animation`, `style_button_color_icon_active`, `style_chat_button_animation`, `style_chat_button_animation_duration`, `style_chat_button_animation_delay`, `style_chat_button_box_shadow*`, `style_contact_section`, `style_contact_button_color_icon`, `style_contact_button_color_background*`, `style_contact_button_color_icon_hover`, `style_contact_buttons_spacing`, `style_contact_icons_box_shadow*`, `style_contact_tooltip_text_color`, `style_contact_tooltip_typography*`, `style_contact_tooltip_bg_color`, `style_chat_box_entrance_animation`, `style_chat_box_exit_animation`, `style_chat_box_animation_duration`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-5` · Classic · general

`chat_button_section`, `chat_aria_label`, `chat_button_platform`, `chat_button_mail`, `chat_button_mail_subject`, `chat_button_mail_body`, `chat_button_number`, `chat_button_username`, `chat_button_viber_action`, `chat_button_waze`, `chat_button_url`, `chat_button_show_dot`, `style_chat_button`, `style_chat_button_size`, `style_button_color_select`, `style_button_color_icon`, `style_button_color_background*`, `style_button_color_select_hover`, `style_button_color_icon_hover`, `style_button_color_hover_animation`, `style_chat_button_animation`, `style_chat_button_animation_duration`, `style_chat_button_animation_delay`, `style_chat_button_box_shadow*`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-6` · Quick Access Bar · general

`contact_section`, `contact_aria_label`, `contact_repeater`, `style_contact_section`, `style_contact_button_size`, `style_contact_button_color_icon`, `style_contact_button_color_icon_hover`, `style_contact_button_hover_animation`, `style_contact_button_bar_bg_color`, `style_contact_button_bar_corners`, `style_contact_button_bar_padding`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-7` · Mobile Bar · general

`chat_button_section`, `chat_aria_label`, `chat_button_platform`, `chat_button_mail`, `chat_button_mail_subject`, `chat_button_mail_body`, `chat_button_number`, `chat_button_username`, `chat_button_viber_action`, `chat_button_waze`, `chat_button_url`, `chat_button_display_text_select`, `chat_button_display_text`, `style_chat_button`, `style_chat_button_horizontal_position`, `style_chat_button_spacing`, `style_top_bar_title_typography*`, `style_button_color_select`, `style_button_color_icon`, `style_button_color_background*`, `style_button_color_select_hover`, `style_button_color_icon_hover`, `style_button_color_hover_animation`, `style_chat_button_padding`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_mobile_full_width`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-8` · Resource Box · general

`chat_button_section`, `chat_aria_label`, `chat_button_icon`, `top_bar_section`, `top_bar_title`, `top_bar_subtitle`, `contact_section`, `contact_aria_label`, `contact_repeater`, `style_chat_button`, `style_chat_button_size`, `style_button_color_icon`, `style_button_color_background*`, `style_button_color_icon_hover`, `style_button_color_hover_animation`, `style_button_color_icon_active`, `style_chat_button_animation`, `style_chat_button_animation_duration`, `style_chat_button_animation_delay`, `style_chat_button_box_shadow*`, `style_top_bar_section`, `style_top_bar_title_color`, `style_top_bar_title_typography*`, `style_top_bar_subtitle_color`, `style_top_bar_subtitle_typography*`, `style_top_bar_background*`, `style_resource_links_section`, `style_resource_links_button_size`, `style_resource_links_color_select`, `style_contact_icon_color`, `style_resource_links_title_color`, `style_resource_links_title_typography*`, `style_resource_links_description_color`, `style_resource_links_description_typography*`, `style_resource_links_bg_color`, `style_resource_links_hover_animation`, `style_chat_box_section`, `style_chat_box_bg_color`, `style_chat_box_width`, `style_chat_box_corners`, `style_chat_box_box_shadow*`, `style_chat_box_entrance_animation`, `style_chat_box_exit_animation`, `style_chat_box_animation_duration`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `contact-buttons-var-9` · Animated Classic · general

`chat_button_section`, `chat_aria_label`, `chat_button_platform`, `chat_button_mail`, `chat_button_mail_subject`, `chat_button_mail_body`, `chat_button_number`, `chat_button_username`, `chat_button_viber_action`, `chat_button_waze`, `chat_button_url`, `chat_button_display_text`, `style_chat_button`, `style_chat_button_size`, `style_top_bar_title_typography*`, `style_platform_control_select`, `style_button_color_icon`, `style_button_color_background*`, `style_chat_button_drop_shadow_box_shadow*`, `style_chat_button_padding`, `style_chat_button_custom_animation_transition`, `advanced_layout_section`, `advanced_horizontal_position`, `advanced_horizontal_offset`, `advanced_vertical_position`, `advanced_vertical_offset`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `e-form-checkbox` · Checkbox · atomic-form



### `e-form-date-picker` · Date picker · atomic-form



### `e-form-file-upload` · File Upload · atomic-form



### `e-form-input` · Input · atomic-form



### `e-form-label` · Label · atomic-form



### `e-form-radio-button` · Radio button · atomic-form



### `e-form-select` · Select · atomic-form



### `e-form-submit-button` · Submit button · atomic-form



### `e-form-textarea` · Text area · atomic-form



### `e-form-time-picker` · Time picker · atomic-form



### `facebook-button` · Facebook Button · pro-elements

`section_content`, `type`, `layout`, `size`, `color_scheme`, `show_share`, `show_faces`, `url_type`, `url_format`, `url`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `facebook-comments` · Facebook Comments · pro-elements

`section_content`, `comments_number`, `order_by`, `url_type`, `url_format`, `url`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `facebook-embed` · Facebook Embed · pro-elements

`section_content`, `type`, `post_url`, `video_url`, `comment_url`, `include_parent`, `show_text`, `video_allowfullscreen`, `video_autoplay`, `video_show_captions`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `facebook-page` · Facebook Page · pro-elements

`section_content`, `url`, `tabs`, `small_header`, `show_cover`, `show_facepile`, `show_cta`, `height`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `floating-bars-var-2` · Ticker · general

`headlines_content`, `headlines_repeater`, `style_ticker`, `style_ticker_animation_type`, `style_ticker_scroll_direction`, `style_ticker_scroll_speed`, `style_ticker_pause_hover`, `floating_bar_content_section`, `floating_bar_pause_switch`, `floating_bar_pause_icon`, `floating_bar_play_icon`, `floating_bar_close_switch`, `accessible_name`, `style_headlines`, `style_headlines_icon_color`, `style_headlines_icon_position`, `style_headlines_icon_size`, `style_headlines_icon_spacing`, `style_headline_text_typography*`, `style_headline_text_color`, `style_headline_text_color_hover`, `style_floating_bar`, `style_floating_bar_horizontal_position`, `style_floating_bar_controls_size`, `floating_bar_pause_button_color`, `floating_bar_pause_bg_color`, `floating_bar_close_button_color`, `style_floating_bar_close_button_size`, `floating_bar_close_bg_color`, `style_floating_bar_element_spacing`, `style_floating_bar_padding`, `floating_bar_background*`, `advanced_layout_section`, `advanced_vertical_position`, `advanced_toggle_sticky`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `floating-bars-var-3` · Coupon · general

`announcement_content_section`, `announcement_icon`, `announcement_text`, `coupon_content_section`, `coupon_code`, `coupon_copy_icon`, `coupon_success_message`, `coupon_success_icon`, `floating_bar_content_section`, `floating_bar_close_switch`, `accessible_name`, `style_announcement`, `style_announcement_icon_color`, `style_announcement_icon_position`, `style_announcement_icon_size`, `style_announcement_text_typography*`, `style_announcement_text_color`, `style_coupon`, `style_coupon_type`, `style_coupon_icon_position`, `style_coupon_icon_spacing`, `style_coupon_typography*`, `style_coupon_text_color`, `style_coupon_bg_color`, `style_coupon_show_border*`, `style_coupon_border*`, `style_coupon_success_message_duration`, `style_coupon_padding`, `style_coupon_animation`, `style_coupon_animation_duration`, `style_coupon_animation_delay`, `style_floating_bar`, `floating_bar_close_button_position`, `floating_bar_close_button_color`, `style_floating_bar_close_button_size`, `style_floating_bar_elements_align`, `style_floating_bar_elements_spacing`, `style_floating_bar_elements_padding`, `floating_bar_background*`, `advanced_layout_section`, `advanced_vertical_position`, `advanced_toggle_sticky`, `advanced_responsive_section`, `hide_desktop`, `hide`, `advanced_custom_controls_section`, `advanced_custom_css_id`, `advanced_custom_css_classes`, `section_custom_css`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`

### `global` · Global · pro-elements



### `hotspot` · Hotspot · pro-elements

`section_image`, `image`, `image_size`, `image_custom_dimension`, `section_style_image`, `align`, `width`, `space`, `height`, `object-fit`, `object-position`, `opacity`, `css_filters_css_filter`, `css_filters_blur`, `css_filters_brightness`, `css_filters_contrast`, `css_filters_saturate`, `css_filters_hue`, `opacity_hover`, `css_filters_hover_css_filter`, `css_filters_hover_blur`, `css_filters_hover_brightness`, `css_filters_hover_contrast`, `css_filters_hover_saturate`, `css_filters_hover_hue`, `background_hover_transition`, `image_border*`, `image_box_shadow*`, `caption_typography*`, `caption_text_shadow*`, `hotspot_section`, `hotspot`, `hotspot_animation`, `hotspot_sequenced_animation`, `hotspot_sequenced_animation_duration`, `tooltip_section`, `tooltip_position`, `tooltip_trigger`, `tooltip_animation`, `tooltip_animation_duration`, `section_style_hotspot`, `style_hotspot_color`, `style_hotspot_size`, `style_typography*`, `style_hotspot_width`, `style_hotspot_height`, `style_hotspot_box_color`, `style_hotspot_padding`, `style_hotspot_border*`, `style_hotspot_box_shadow*`, `section_style_tooltip`, `style_tooltip_text_color`, `style_tooltip_typography*`, `style_tooltip_align`, `style_tooltip_width`, `style_tooltip_max_width`, `style_tooltip_padding`, `style_tooltip_color`, `style_tooltip_border*`, `style_tooltip_box_shadow*`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `link-in-bio-var-2` · Classic · link-in-bio

`identity_section`, `identity_image_cover`, `identity_image_cover_position`, `identity_image`, `identity_image_position`, `bio_section`, `bio_heading`, `bio_heading_tag`, `bio_title`, `bio_title_tag`, `bio_description`, `cta_section`, `cta_link`, `icons_section`, `icon`, `identity_section_style`, `identity_image_height`, `identity_image_bottom_show_border*`, `identity_image_bottom_border*`, `identity_image_size*`, `identity_image_show_border*`, `identity_image_border*`, `bio_section_style`, `bio_heading_text_color`, `bio_heading_typography*`, `bio_title_text_color`, `bio_title_typography*`, `bio_description_text_color`, `bio_description_typography*`, `cta_links_section_style`, `cta_links_type`, `cta_links_text_color`, `cta_links_background*`, `cta_links_typography*`, `cta_links_show_border*`, `cta_links_border*`, `cta_links_corners`, `cta_links_padding`, `icons_section_style`, `icons_color`, `icons_size`, `background_border*`, `background_overlay_opacity`, `background_show_border*`, `advanced_layout_full_width_custom`, `advanced_layout_width`, `advanced_layout_content_width`, `advanced_layout_full_screen_height`, `advanced_layout_full_screen_height_controls`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `link-in-bio-var-3` · Showcase · link-in-bio

`identity_section`, `identity_image_style`, `identity_image`, `identity_image_position`, `bio_section`, `bio_heading`, `bio_heading_tag`, `bio_title`, `bio_title_tag`, `bio_description`, `icons_section`, `icon`, `cta_section`, `cta_link`, `identity_section_style`, `identity_image_size*`, `identity_image_shape`, `identity_image_show_border*`, `identity_image_border*`, `identity_image_height`, `identity_image_bottom_show_border*`, `identity_image_bottom_border*`, `bio_section_style`, `bio_heading_text_color`, `bio_heading_typography*`, `bio_title_text_color`, `bio_title_typography*`, `bio_description_text_color`, `bio_description_typography*`, `icons_section_style`, `icons_color`, `icons_size`, `cta_links_section_style`, `cta_links_text_color`, `cta_links_background*`, `cta_links_typography*`, `cta_links_show_border*`, `cta_links_border*`, `background_border*`, `background_overlay_opacity`, `background_show_border*`, `advanced_layout_full_width_custom`, `advanced_layout_width`, `advanced_layout_content_width`, `advanced_layout_full_screen_height`, `advanced_layout_full_screen_height_controls`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `link-in-bio-var-4` · Links · link-in-bio

`identity_section`, `identity_image`, `identity_image_position`, `bio_section`, `bio_heading`, `bio_heading_tag`, `bio_title`, `bio_title_tag`, `bio_description`, `cta_section`, `cta_link`, `icons_section`, `icon`, `identity_section_style`, `identity_image_size*`, `identity_image_shape`, `identity_image_show_border*`, `identity_image_border*`, `identity_image_height`, `identity_image_bottom_show_border*`, `identity_image_bottom_border*`, `bio_section_style`, `bio_heading_text_color`, `bio_heading_typography*`, `bio_title_text_color`, `bio_title_typography*`, `bio_description_text_color`, `bio_description_typography*`, `cta_links_section_style`, `cta_links_text_color`, `cta_links_typography*`, `cta_links_divider_color`, `cta_links_divider_width`, `icons_section_style`, `icons_color`, `icons_size`, `background_border*`, `background_overlay_opacity`, `background_show_border*`, `advanced_layout_full_width_custom`, `advanced_layout_width`, `advanced_layout_content_width`, `advanced_layout_full_screen_height`, `advanced_layout_full_screen_height_controls`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `link-in-bio-var-5` · Services · link-in-bio

`identity_section`, `identity_image_style`, `identity_image`, `identity_image_position`, `bio_section`, `bio_heading`, `bio_heading_tag`, `bio_title`, `bio_title_tag`, `bio_description`, `icons_section`, `icon`, `cta_section`, `cta_link`, `identity_section_style`, `identity_image_size*`, `identity_image_shape`, `identity_image_show_border*`, `identity_image_border*`, `identity_image_height`, `identity_image_bottom_show_border*`, `identity_image_bottom_border*`, `bio_section_style`, `bio_heading_text_color`, `bio_heading_typography*`, `bio_title_text_color`, `bio_title_typography*`, `bio_description_text_color`, `bio_description_typography*`, `icons_section_style`, `icons_color`, `icons_size`, `cta_links_section_style`, `cta_links_text_color`, `cta_links_background*`, `cta_links_typography*`, `cta_links_show_border*`, `cta_links_border*`, `background_border*`, `background_overlay_opacity`, `background_show_border*`, `advanced_layout_full_width_custom`, `advanced_layout_width`, `advanced_layout_content_width`, `advanced_layout_full_screen_height`, `advanced_layout_full_screen_height_controls`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `link-in-bio-var-6` · Portfolio Bio · link-in-bio

`identity_section`, `identity_image_style`, `identity_image`, `identity_image_position`, `bio_section`, `bio_heading`, `bio_heading_tag`, `bio_title`, `bio_title_tag`, `bio_description`, `icons_section`, `icon`, `image_links_section`, `image_links_per_row`, `image_links`, `identity_section_style`, `identity_image_size*`, `identity_image_shape`, `identity_image_show_border*`, `identity_image_border*`, `identity_image_height`, `identity_image_bottom_show_border*`, `identity_image_bottom_border*`, `bio_section_style`, `bio_heading_text_color`, `bio_heading_typography*`, `bio_title_text_color`, `bio_title_typography*`, `bio_description_text_color`, `bio_description_typography*`, `icons_section_style`, `icons_color`, `icons_size`, `image_links_section_style`, `image_links_height`, `image_links_show_border*`, `image_links_border*`, `background_border*`, `background_overlay_opacity`, `background_show_border*`, `advanced_layout_full_width_custom`, `advanced_layout_width`, `advanced_layout_content_width`, `advanced_layout_full_screen_height`, `advanced_layout_full_screen_height_controls`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `link-in-bio-var-7` · Business Card · link-in-bio

`identity_section`, `identity_image_cover`, `identity_image_cover_position`, `identity_image`, `identity_image_position`, `bio_section`, `bio_heading`, `bio_heading_tag`, `bio_title`, `bio_title_tag`, `bio_about`, `bio_about_tag`, `bio_description`, `icons_section`, `icons_per_row`, `icon`, `cta_section`, `cta_section_button_text`, `cta_link_type`, `cta_link_file`, `cta_link_url`, `cta_link_mail`, `cta_link_mail_subject`, `cta_link_mail_body`, `cta_link_number`, `cta_link_location`, `cta_link_username`, `identity_section_style`, `identity_image_height`, `identity_image_bottom_show_border*`, `identity_image_bottom_border*`, `identity_image_size*`, `identity_image_show_border*`, `identity_image_border*`, `identity_section_style_cover_divider_bottom`, `identity_section_style_cover_divider_bottom_color`, `identity_section_style_cover_divider_bottom_width`, `identity_section_style_cover_divider_bottom_height`, `identity_section_style_cover_divider_bottom_negative`, `bio_section_style`, `bio_heading_text_color`, `bio_heading_typography*`, `bio_title_text_color`, `bio_title_typography*`, `bio_about_text_color`, `bio_about_typography*`, `bio_description_text_color`, `bio_description_typography*`, `icons_section_style`, `icons_color`, `icons_color_background*`, `icons_border*`, `icons_size`, `icons_color_text`, `icons_typography*`, `cta_links_section_style`, `cta_links_text_color`, `cta_links_background*`, `cta_links_typography*`, `cta_links_show_border*`, `cta_links_border*`, `cta_links_corners`, `cta_links_padding`, `background_border*`, `background_overlay_opacity`, `background_show_border*`, `advanced_layout_full_width_custom`, `advanced_layout_width`, `advanced_layout_content_width`, `advanced_layout_full_screen_height`, `advanced_layout_full_screen_height_controls`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `lottie` · Lottie · pro-elements

`lottie`, `source`, `source_external_url`, `source_json`, `align`, `caption_source`, `caption`, `link_to`, `custom_link`, `settings`, `trigger`, `viewport`, `effects_relative_to`, `loop`, `number_of_times`, `link_timeout`, `on_hover_out`, `hover_area`, `play_speed`, `start_point`, `end_point`, `reverse_animation`, `renderer`, `lazyload`, `style`, `width`, `space`, `opacity`, `css_filters_css_filter`, `css_filters_blur`, `css_filters_brightness`, `css_filters_contrast`, `css_filters_saturate`, `css_filters_hue`, `opacity_hover`, `css_filters_hover_css_filter`, `css_filters_hover_blur`, `css_filters_hover_brightness`, `css_filters_hover_contrast`, `css_filters_hover_saturate`, `css_filters_hover_hue`, `background_hover_transition`, `section_style_caption`, `caption_align`, `text_color`, `caption_typography*`, `caption_space`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `mega-menu` · Menu · pro-elements, theme-elements

`section_layout`, `menu_name`, `menu_items`, `content_width`, `content_horizontal_position`, `item_layout`, `item_position_horizontal`, `item_position_dropdown`, `menu_item_icon`, `menu_item_icon_active`, `section_dropdown_effect`, `open_on`, `open_animation`, `open_animation_duration`, `menu_toggle_section`, `toggle_align`, `menu_toggle_icon_normal`, `menu_toggle_icon_hover_animation`, `menu_toggle_animation_duration`, `menu_toggle_icon_active`, `section_responsive_mega_menu`, `horizontal_scroll`, `breakpoint_selector`, `section_menu_items_style`, `menu_item_title_space_between`, `menu_item_title_distance_from_content`, `menu_item_title_typography*`, `menu_item_title_text_color_normal`, `menu_item_title_text_shadow*`, `menu_item_title_background*`, `menu_item_title_box_border*`, `menu_item_title_box_shadow*`, `menu_divider`, `menu_divider_style`, `menu_divider_width`, `menu_divider_height`, `menu_divider_color`, `menu_item_title_text_color_hover`, `hover_animation`, `menu_item_title_transition_duration`, `menu_item_title_text_color_active`, `menu_item_title_border*`, `menu_item_title_padding`, `icon_section_style`, `icon_position`, `icon_size`, `icon_spacing`, `icon_color`, `icon_color_hover`, `icon_color_active`, `section_dropdown_indicator_style`, `style_dropdown_indicator_size`, `style_dropdown_indicator_rotate`, `style_dropdown_indicator_space`, `menu_dropdown_indicator_color_normal`, `menu_dropdown_indicator_color_hover`, `menu_dropdown_indicator_color_active`, `section_menu_toggle_style`, `style_menu_toggle_icon_size`, `menu_toggle_icon_color_normal`, `menu_toggle_icon_background*`, `menu_toggle_icon_border*`, `menu_toggle_icon_box_shadow*`, `menu_toggle_icon_color_hover`, `menu_toggle_icon_animation_duration`, `menu_toggle_icon_color_active`, `menu_toggle_icon__padding`, `menu_toggle_icon_distance_from_dropdown`, `section_content_style`, `content_background*`, `content_border*`, `content_shadow_box_shadow*`, `content_padding`, `section_dropdown_menu_style`, `dropdown_menu_item_text_color_normal`, `dropdown_menu_item_background*`, `dropdown_menu_item_box_shadow*`, `dropdown_menu_item_text_color_active`, `menu_dropdown_box_border*`, `menu_dropdown_box_shadow*`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `off-canvas` · Off-Canvas · pro-elements

`section_layout`, `editing_mode`, `off_canvas_name`, `horizontal_position`, `vertical_position`, `width`, `height`, `custom_height`, `section_settings`, `entrance_animation`, `exit_animation`, `offcanvas_animation_duration`, `is_not_close_on_overlay`, `is_not_close_on_esc_overlay`, `prevent_scroll`, `wrapper_html_tag`, `section_background*`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `border_border*`, `border_width`, `border_color`, `border_radius`, `box_shadow_box_shadow*`, `section_overlay`, `has_overlay`, `overlay_background*`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `paypal-button` · PayPal Button · pro-elements

`section_account`, `merchant_account`, `email`, `sdk_token`, `type`, `product_name`, `product_sku`, `product_price`, `donation_type`, `donation_amount`, `currency`, `billing_cycle`, `auto_renewal`, `quantity`, `shipping_price`, `tax_type`, `tax_rate`, `section_button`, `text`, `selected_icon`, `icon_align`, `icon_indent`, `button_css_id`, `section_style`, `align`, `content_align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_shadow_text_shadow*`, `button_text_color`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `button_box_shadow*`, `hover_color`, `button_background*`, `button_hover_border*`, `button_hover_box_shadow*`, `button_hover_transition_duration`, `hover_animation`, `border_border*`, `border_width`, `border_color`, `border_radius`, `text_padding`, `section_settings`, `redirect_after_success`, `sandbox_mode`, `sandbox_email`, `open_in_new_window`, `custom_messages`, `error_message_global`, `error_message_payment`, `section_messages_style`, `message_typography*`, `message_color_global`, `message_color_payment`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `portfolio` · Portfolio · pro-elements

`section_layout`, `columns`, `posts_per_page`, `thumbnail_size_size`, `masonry`, `item_ratio`, `show_title`, `title_tag`, `section_query`, `posts_post_type`, `posts_posts_ids`, `posts_include`, `posts_include_term_ids`, `posts_related_taxonomies`, `posts_include_authors`, `posts_exclude`, `posts_exclude_ids`, `posts_exclude_term_ids`, `posts_exclude_authors`, `posts_avoid_duplicates`, `posts_offset`, `posts_related_fallback`, `posts_fallback_ids`, `posts_select_date`, `posts_date_before`, `posts_date_after`, `posts_orderby`, `posts_order`, `posts_ignore_sticky_posts`, `posts_query_id`, `filter_bar`, `show_filter_bar`, `taxonomy`, `section_design_layout`, `item_gap`, `column_gap`, `row_gap`, `border_radius`, `section_design_overlay`, `color_background*`, `color_title`, `typography_title_typography*`, `typography_title_font_family`, `typography_title_font_size`, `typography_title_font_weight`, `typography_title_text_transform`, `typography_title_font_style`, `typography_title_text_decoration`, `typography_title_line_height`, `typography_title_letter_spacing`, `typography_title_word_spacing`, `section_design_filter`, `color_filter`, `color_filter_active`, `typography_filter_typography*`, `typography_filter_font_family`, `typography_filter_font_size`, `typography_filter_font_weight`, `typography_filter_text_transform`, `typography_filter_font_style`, `typography_filter_text_decoration`, `typography_filter_line_height`, `typography_filter_letter_spacing`, `typography_filter_word_spacing`, `filter_item_spacing`, `filter_spacing`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `reviews` · Reviews · pro-elements

`section_slides`, `slides_name`, `slides`, `slides_per_view`, `slides_to_scroll`, `width`, `section_additional_options`, `show_arrows`, `pagination`, `speed`, `autoplay`, `autoplay_speed`, `loop`, `pause_on_hover`, `pause_on_interaction`, `image_size_size`, `image_size_custom_dimension`, `lazyload`, `section_slides_style`, `space_between`, `slide_background*`, `slide_border*`, `slide_padding`, `header_background*`, `content_gap`, `show_separator`, `separator_color`, `separator_size`, `section_content_style`, `name_color`, `name_typography*`, `title_color`, `title_typography*`, `content_color`, `content_typography*`, `section_image_style`, `image_size`, `image_gap`, `image_border*`, `section_icon_style`, `icon_color`, `icon_custom_color`, `icon_size`, `section_rating_style`, `star_style`, `unmarked_star_style`, `star_size`, `star_space`, `stars_color`, `stars_unmarked_color`, `section_navigation`, `arrows_size`, `arrows_color`, `pagination_gap`, `pagination_size`, `pagination_color_inactive`, `pagination_color`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `stripe-button` · Stripe Button · pro-elements

`section_stripe_account`, `product_name`, `donation_type`, `donation_amount`, `shipping_price`, `tax_rate`, `stripe_currency`, `stripe_product_price`, `stripe_quantity`, `shipping_amount`, `stripe_test_env_tax_rates_list`, `stripe_live_env_tax_rates_list`, `section_button`, `text`, `selected_icon`, `icon_align`, `icon_indent`, `button_css_id`, `section_style`, `align`, `content_align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_shadow_text_shadow*`, `button_text_color`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `button_box_shadow*`, `hover_color`, `button_background*`, `button_hover_border*`, `button_hover_box_shadow*`, `button_hover_transition_duration`, `hover_animation`, `border_border*`, `border_width`, `border_color`, `border_radius`, `text_padding`, `section_settings`, `redirect_after_success`, `sandbox_mode`, `open_in_new_window`, `custom_messages`, `error_message_global`, `error_message_payment`, `section_messages_style`, `message_typography*`, `message_color_global`, `message_color_payment`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `video-playlist` · Video Playlist · pro-elements

`section_playlist`, `tabs_direction`, `playlist_title`, `playlist_title_tag`, `tabs`, `section_inner_tab`, `inner_tab_title_1`, `inner_tab_title_2`, `inner_tab_is_content_collapsible`, `inner_tab_label_show_more`, `inner_tab_label_show_less`, `inner_tab_collapsible_height`, `section_image_overlay`, `show_image_overlay`, `image_overlay`, `image_overlay_size`, `image_overlay_custom_dimension`, `show_play_icon`, `section_additional_options`, `autoplay_on_load`, `autoplay_next`, `show_watched_indication`, `show_video_count`, `show_duration`, `show_thumbnail`, `play_icon`, `watched_icon`, `lazy_load`, `section_style_layout`, `tabs_alignment`, `layout_height`, `section_style_top_bar`, `playlist_name_background*`, `playlist_name_color`, `playlist_name_typography*`, `videos_amount_color`, `videos_amount_typography*`, `section_style_videos`, `normal_background*`, `normal_color`, `normal_typography*`, `normal_duration_color`, `normal_duration_typography*`, `normal_icon_color`, `normal_icon_top_text_shadow*`, `normal_icon_size`, `normal_separator_style`, `normal_separator_weight`, `normal_separator_color`, `active_background*`, `active_color`, `active_typography*`, `active_duration_color`, `active_duration_typography*`, `active_icon_color`, `active_icon_top_text_shadow*`, `active_icon_size`, `active_separator_style`, `active_separator_weight`, `active_separator_color`, `section_style_sections`, `section_background*`, `section_color`, `section_typography*`, `section_border*`, `section_inner_tab_style`, `inner_tab_border*`, `inner_tab_background*`, `inner_tab_title_color`, `inner_tab_active_title_color`, `inner_tab_title_typography*`, `inner_tab_content_color`, `inner_tab_content_typography*`, `inner_tab_content_padding`, `inner_tab_normal_show_more_color`, `inner_tab_hover_show_more_color`, `inner_tab_hover_show_more_color_transition_duration`, `inner_tab_show_more_typography*`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `wc-add-to-cart` · Custom Add To Cart · woocommerce-elements

`section_product`, `product_id`, `show_quantity`, `quantity`, `section_button`, `button_type`, `text`, `link`, `size`, `selected_icon`, `icon_align`, `icon_indent`, `button_css_id`, `section_style`, `align`, `content_align`, `typography_typography*`, `typography_font_family`, `typography_font_size`, `typography_font_weight`, `typography_text_transform`, `typography_font_style`, `typography_text_decoration`, `typography_line_height`, `typography_letter_spacing`, `typography_word_spacing`, `text_shadow_text_shadow*`, `button_text_color`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `button_box_shadow*`, `hover_color`, `button_background*`, `button_hover_border*`, `button_hover_box_shadow*`, `button_hover_transition_duration`, `hover_animation`, `border_border*`, `border_width`, `border_color`, `border_radius`, `text_padding`, `section_layout`, `layout`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `wc-archive-products` · Archive Products · woocommerce-elements-archive

`section_content`, `paginate`, `allow_order`, `show_result_count`, `section_query`, `query_post_type`, `query_posts_ids`, `query_include`, `query_include_term_ids`, `query_include_authors`, `query_exclude`, `query_exclude_ids`, `query_exclude_term_ids`, `query_orderby`, `query_order`, `section_products_title`, `products_title_show`, `products_related_products_title_text`, `products_upsells_title_text`, `products_cross_sells_title_text`, `products_title_alignment`, `section_products_style`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `title_spacing`, `star_color`, `empty_star_color`, `star_size`, `rating_spacing`, `price_color`, `price_typography*`, `old_price_color`, `old_price_typography*`, `button_text_color`, `button_background*`, `button_border*`, `button_typography*`, `button_hover_color`, `button_hover_background*`, `button_hover_border*`, `button_text_padding`, `button_spacing`, `automatically_align_buttons`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `section_design_box`, `products_title_style`, `products_title_color`, `products_title_typography*`, `products_title_spacing`, `box_border*`, `box_padding`, `box_shadow_box_shadow*`, `box_bg_color`, `box_shadow_hover_box_shadow*`, `box_bg_color_hover`, `section_pagination_style`, `pagination_spacing`, `show_pagination_border*`, `pagination_border*`, `pagination_padding`, `pagination_typography*`, `pagination_link_color`, `pagination_link_bg_color`, `pagination_link_color_hover`, `pagination_link_bg_color_hover`, `pagination_link_color_active`, `pagination_link_bg_color_active`, `sale_flash_style`, `show_onsale_flash`, `onsale_text_color`, `onsale_text_background*`, `onsale_typography*`, `onsale_border*`, `onsale_width`, `onsale_height`, `onsale_horizontal_position`, `onsale_distance`, `section_advanced`, `nothing_found_message`, `section_nothing_found_style`, `nothing_found_color`, `nothing_found_typography*`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `wc-categories` · Product Categories · woocommerce-elements

`section_layout`, `columns`, `number`, `section_filter`, `source`, `categories`, `parent`, `hide_empty`, `orderby`, `order`, `section_products_style`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `count_color`, `count_typography*`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `wc-elements` · WooCommerce Pages · woocommerce-elements

`section_product`, `element`, `product_id`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `wc-products` · Woo - Products- · woocommerce-elements

`Deprecated`, `section_layout`, `classic_columns`, `posts_per_page`, `section_filter`, `posts_post_type`, `posts_posts_ids`, `posts_authors`, `posts_product_brand_ids`, `posts_product_cat_ids`, `posts_product_tag_ids`, `filter_by`, `orderby`, `order`, `exclude`, `exclude_ids`, `avoid_duplicates`, `section_products_style`, `products_class`, `column_gap`, `row_gap`, `align`, `image_border*`, `image_spacing`, `title_color`, `title_typography*`, `title_spacing`, `star_color`, `empty_star_color`, `star_size`, `rating_spacing`, `price_color`, `price_typography*`, `old_price_color`, `old_price_typography*`, `button_text_color`, `button_background*`, `button_border*`, `button_typography*`, `button_hover_color`, `button_hover_background*`, `button_hover_border*`, `button_text_padding`, `button_spacing`, `automatically_align_buttons`, `view_cart_color`, `view_cart_typography*`, `view_cart_spacing`, `section_design_box`, `box_border*`, `box_padding`, `box_shadow_box_shadow*`, `box_bg_color`, `box_shadow_hover_box_shadow*`, `box_bg_color_hover`, `section_pagination_style`, `pagination_spacing`, `show_pagination_border*`, `pagination_border*`, `pagination_padding`, `pagination_typography*`, `pagination_link_color`, `pagination_link_bg_color`, `pagination_link_color_hover`, `pagination_link_bg_color_hover`, `pagination_link_color_active`, `pagination_link_bg_color_active`, `sale_flash_style`, `show_onsale_flash`, `onsale_text_color`, `onsale_text_background*`, `onsale_typography*`, `onsale_border*`, `onsale_width`, `onsale_height`, `onsale_horizontal_position`, `onsale_distance`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `wc-single-elements` · Woo - Single Elements · woocommerce-elements-single

`section_product`, `element`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `woocommerce-notices` · WooCommerce Notices · woocommerce-elements

`section`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

### `woocommerce-purchase-summary` · Purchase Summary · woocommerce-elements

`confirmation_message`, `confirmation_message_active`, `confirmation_message_text`, `confirmation_message_alignment`, `payment_details`, `payment_details_number`, `payment_details_date`, `payment_details_email`, `payment_details_total`, `payment_details_payment`, `bank_details`, `bank_details_text`, `bank_details_alignment`, `downloads`, `downloads_text`, `downloads_alignment`, `order_summary`, `order_summary_text`, `order_summary_alignment`, `billing_details`, `billing_details_text`, `billing_details_alignment`, `shipping_details`, `shipping_details_text`, `shipping_details_alignment`, `preview_order`, `preview_order_type`, `preview_order_custom`, `sections_tabs_style`, `sections_background*`, `sections_box_shadow*`, `sections_border*`, `sections_padding`, `sections_spacing`, `typography_title`, `confirmation_message_color`, `confirmation_message_typography*`, `confirmation_message_text_shadow*`, `titles_color`, `titles_typography*`, `titles_text_shadow*`, `titles_spacing`, `general_text_color`, `general_text_typography*`, `payment_details_title`, `payment_details_space_between`, `payment_details_titles_color`, `payment_details_titles_typography*`, `payment_details_titles_text_shadow*`, `payment_details_titles_spacing`, `payment_details_items_color`, `payment_details_items_typography*`, `payment_details_border*`, `bank_details_title`, `bank_details_space_between`, `account_title_color`, `account_title_typography*`, `account_title_text_shadow*`, `account_title_spacing`, `bank_details_titles_color`, `bank_details_titles_typography*`, `bank_details_titles_text_shadow*`, `bank_details_titles_spacing`, `bank_details_items_color`, `bank_details_items_typography*`, `bank_details_border*`, `order_details_title`, `order_details_rows_gap`, `order_details_titles_totals_color`, `order_details_titles_totals_typography*`, `order_details_titles_totals_text_shadow*`, `order_details_items_color`, `order_details_items_typography*`, `order_details_variations_color`, `order_details_variations_typography*`, `order_details_product_links_normal_color`, `order_details_product_links_hover_color`, `order_details_border*`, `order_details_button_typography*`, `order_details_button_text_shadow*`, `order_details_button_normal_text_color`, `order_details_button_normal_background*`, `order_details_button_normal_box_shadow*`, `order_details_button_hover_text_color`, `order_details_button_hover_background*`, `order_details_button_hover_box_shadow*`, `order_details_button_hover_border*`, `order_details_button_hover_transition_duration`, `order_details_button_hover_animation`, `order_details_button_border*`, `order_details_button_padding`, `section_effects`, `sticky_offset`, `sticky_effects_offset`, `sticky_anchor_link_offset`, `animation_duration`, `hide_desktop`, `hide`, `section_custom_css`

## Anexo C · Documentos

- **`post`** (4): `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`wp-post`** (4): `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`wp-page`** (4): `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`kit`** (81): `settings_page_transitions_background*`, `settings_page_transitions_entrance_animation`, `settings_page_transitions_exit_animation`, `settings_page_transitions_animation_duration`, `settings_page_transitions_page_transition_play_button`, `settings_page_transitions_preloader_type`, `settings_page_transitions_preloader_icon`, `settings_page_transitions_preloader_image`, `settings_page_transitions_preloader_animation_type`, `settings_page_transitions_preloader_animation`, `settings_page_transitions_preloader_animation_duration`, `settings_page_transitions_preloader_delay`, `settings_page_transitions_preloader_color`, `settings_page_transitions_preloader_size`, `settings_page_transitions_preloader_rotate`, `settings_page_transitions_preloader_width`, `settings_page_transitions_preloader_max_width`, `settings_page_transitions_preloader_opacity`, `settings_page_transitions_preloader_play_button`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`, `woocommerce_cart_page_id`, `woocommerce_checkout_page_id`, `woocommerce_myaccount_page_id`, `woocommerce_terms_page_id`, `woocommerce_purchase_summary_page_id`, `woocommerce_shop_page_id`, `woocommerce_notices_elements`, `error_message_text_color`, `error_message_text_typography*`, `error_message_text_shadow*`, `error_message_icon_color`, `error_message_link_typography*`, `error_message_normal_links_color`, `error_message_hover_links_color`, `error_notice_box_background*`, `error_notice_box_box_shadow*`, `error_notice_box_border*`, `message_message_text_color`, `message_message_text_typography*`, `message_message_text_shadow*`, `message_message_icon_color`, `notice_message_link_typography*`, `notice_message_normal_links_color`, `notice_message_hover_links_color`, `message_notice_box_background*`, `message_notice_box_box_shadow*`, `message_notice_box_border*`, `message_button_typography*`, `message_button_text_shadow*`, `message_buttons_normal_text_color`, `message_buttons_normal_background*`, `message_buttons_normal_box_shadow*`, `message_buttons_hover_text_color`, `message_buttons_hover_background*`, `message_buttons_focus_box_shadow*`, `message_buttons_hover_border*`, `message_buttons_hover_transition_duration`, `message_buttons_border*`, `message_buttons_padding`, `info_message_text_color`, `info_message_text_typography*`, `info_message_text_shadow*`, `info_message_icon_color`, `info_notice_box_background*`, `info_notice_box_box_shadow*`, `info_notice_box_border*`, `info_button_typography*`, `info_button_text_shadow*`, `info_buttons_normal_text_color`, `info_buttons_normal_background*`, `info_buttons_normal_box_shadow*`, `info_buttons_hover_text_color`, `info_buttons_hover_background*`, `info_buttons_focus_box_shadow*`, `info_buttons_hover_border*`, `info_buttons_hover_transition_duration`, `info_buttons_border*`, `info_buttons_padding`
- **`section`** (5): `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`
- **`floating-buttons`** (5): `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`
- `e-div-block`: tipo de documento ausente (free).
- `e-flexbox`: tipo de documento ausente (free).
- `e-form`: tipo de documento ausente (free).
- `elementor_component`: tipo de documento ausente (free).
- `widget`: tipo de documento ausente (pro).
- **`popup`** (67): `assets_loading_hidden_control`, `content_position`, `entrance_animation`, `exit_animation`, `entrance_animation_duration`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `border_border*`, `border_width`, `border_color`, `border_radius`, `box_shadow_box_shadow*`, `overlay_background*`, `close_button_position`, `close_button_vertical`, `close_button_horizontal`, `close_button_color`, `close_button_background*`, `close_button_hover_color`, `close_button_hover_background*`, `icon_size`, `close_button_delay`, `close_automatically`, `prevent_close_on_background*`, `prevent_close_on_esc_key`, `prevent_scroll`, `avoid_multiple_popups`, `a11y_navigation`, `open_selector`, `margin`, `padding`, `classes`
- **`header`** (40): `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `hidden_header_footer_style_control`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`
- **`footer`** (40): `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `hidden_header_footer_style_control`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`
- **`single`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`single-post`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`single-page`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`archive`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`search-results`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`error-404`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- `code_snippet`: tipo de documento ausente (pro).
- `product-post`: tipo de documento ausente (pro).
- **`product`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`product-archive`** (44): `page_template`, `content_wrapper_html_tag`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `margin`, `padding`, `background_background*`, `background_color`, `background_color_stop`, `background_color_b`, `background_color_b_stop`, `background_gradient_type`, `background_gradient_angle`, `background_gradient_position`, `background_image`, `background_position`, `background_xpos`, `background_ypos`, `background_attachment`, `background_repeat`, `background_size`, `background_bg_width`, `background_video_link`, `background_video_start`, `background_video_end`, `background_play_once`, `background_play_on`, `background_privacy_mode`, `background_video_fallback`, `background_slideshow_gallery`, `background_slideshow_loop`, `background_slideshow_slide_duration`, `background_slideshow_slide_transition`, `background_slideshow_transition_duration`, `background_slideshow_background*`, `background_slideshow_lazyload`, `background_slideshow_ken_burns`, `background_slideshow_ken_burns_zoom_direction`, `scroll_snap`, `scroll_snap_position`, `scroll_snap_padding`, `force_stop`
- **`loop-item`** (7): `preview_width`, `preview_type`, `preview_id`, `preview_search_term`, `apply_preview`, `source`, `apply_query_source`

## Anexo D · Elementos (contenedor, sección, columna, común)

- **`section`**: `background_motion_fx_motion_fx_scrolling`, `background_motion_fx_translateY_effect`, `background_motion_fx_translateY_direction`, `background_motion_fx_translateY_speed`, `background_motion_fx_translateY_affectedRange`, `background_motion_fx_translateX_effect`, `background_motion_fx_translateX_direction`, `background_motion_fx_translateX_speed`, `background_motion_fx_translateX_affectedRange`, `background_motion_fx_opacity_effect`, `background_motion_fx_opacity_direction`, `background_motion_fx_opacity_level`, `background_motion_fx_opacity_range`, `background_motion_fx_blur_effect`, `background_motion_fx_blur_direction`, `background_motion_fx_blur_level`, `background_motion_fx_blur_range`, `background_motion_fx_rotateZ_direction`, `background_motion_fx_rotateZ_speed`, `background_motion_fx_rotateZ_affectedRange`, `background_motion_fx_scale_effect`, `background_motion_fx_scale_direction`, `background_motion_fx_scale_speed`, `background_motion_fx_scale_range`, `background_motion_fx_devices`, `background_motion_fx_range`, `background_motion_fx_motion_fx_mouse`, `background_motion_fx_mouseTrack_effect`, `background_motion_fx_mouseTrack_direction`, `background_motion_fx_mouseTrack_speed`, `background_motion_fx_tilt_direction`, `background_motion_fx_tilt_speed`, `background_handle_motion_fx_asset_loading`
- **`column`**: `background_motion_fx_motion_fx_scrolling`, `background_motion_fx_translateY_effect`, `background_motion_fx_translateY_direction`, `background_motion_fx_translateY_speed`, `background_motion_fx_translateY_affectedRange`, `background_motion_fx_translateX_effect`, `background_motion_fx_translateX_direction`, `background_motion_fx_translateX_speed`, `background_motion_fx_translateX_affectedRange`, `background_motion_fx_opacity_effect`, `background_motion_fx_opacity_direction`, `background_motion_fx_opacity_level`, `background_motion_fx_opacity_range`, `background_motion_fx_blur_effect`, `background_motion_fx_blur_direction`, `background_motion_fx_blur_level`, `background_motion_fx_blur_range`, `background_motion_fx_rotateZ_direction`, `background_motion_fx_rotateZ_speed`, `background_motion_fx_rotateZ_affectedRange`, `background_motion_fx_scale_effect`, `background_motion_fx_scale_direction`, `background_motion_fx_scale_speed`, `background_motion_fx_scale_range`, `background_motion_fx_devices`, `background_motion_fx_range`, `background_motion_fx_motion_fx_mouse`, `background_motion_fx_mouseTrack_effect`, `background_motion_fx_mouseTrack_direction`, `background_motion_fx_mouseTrack_speed`, `background_motion_fx_tilt_direction`, `background_motion_fx_tilt_speed`, `background_handle_motion_fx_asset_loading`
- **`container`**: `background_motion_fx_motion_fx_scrolling`, `background_motion_fx_translateY_effect`, `background_motion_fx_translateY_direction`, `background_motion_fx_translateY_speed`, `background_motion_fx_translateY_affectedRange`, `background_motion_fx_translateX_effect`, `background_motion_fx_translateX_direction`, `background_motion_fx_translateX_speed`, `background_motion_fx_translateX_affectedRange`, `background_motion_fx_opacity_effect`, `background_motion_fx_opacity_direction`, `background_motion_fx_opacity_level`, `background_motion_fx_opacity_range`, `background_motion_fx_blur_effect`, `background_motion_fx_blur_direction`, `background_motion_fx_blur_level`, `background_motion_fx_blur_range`, `background_motion_fx_rotateZ_direction`, `background_motion_fx_rotateZ_speed`, `background_motion_fx_rotateZ_affectedRange`, `background_motion_fx_scale_effect`, `background_motion_fx_scale_direction`, `background_motion_fx_scale_speed`, `background_motion_fx_scale_range`, `background_motion_fx_devices`, `background_motion_fx_range`, `background_motion_fx_motion_fx_mouse`, `background_motion_fx_mouseTrack_effect`, `background_motion_fx_mouseTrack_direction`, `background_motion_fx_mouseTrack_speed`, `background_motion_fx_tilt_direction`, `background_motion_fx_tilt_speed`, `background_handle_motion_fx_asset_loading`
- **`__common`**: `e_display_conditions`, `motion_fx_translateY_direction`, `motion_fx_translateX_direction`, `motion_fx_rotateZ_direction`, `motion_fx_scale_range`, `motion_fx_transform_origin_x`, `motion_fx_transform_origin_y`, `handle_motion_fx_asset_loading`
- **`__common-optimized`**: `e_display_conditions`, `motion_fx_translateY_direction`, `motion_fx_translateX_direction`, `motion_fx_rotateZ_direction`, `motion_fx_scale_range`, `motion_fx_transform_origin_x`, `motion_fx_transform_origin_y`, `handle_motion_fx_asset_loading`
