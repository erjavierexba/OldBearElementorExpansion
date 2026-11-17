<?php
/**
 * Data saved with Elementor Pro's setting keys renders the same with this plugin, and data saved
 * by earlier versions of this plugin is translated to those keys. Synthetic fixtures only.
 * Run with `wp eval-file`.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;

$checks = 0;
$assert = static function ($ok, string $message) use (&$checks) {
    if (!$ok) { fwrite(STDERR, "Fallo: {$message}\n"); throw new RuntimeException($message); }
    $checks++;
};
$admin = get_users(['role'=>'administrator','number'=>1]);
wp_set_current_user($admin[0]->ID);
$uid = static fn() => substr(str_replace('-', '', wp_generate_uuid4()), 0, 7);
$widget = static fn(string $type, array $settings = []) => ['id'=>$uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$created = [];
$render = static function (array $widgets, bool $as_guest = false) use ($uid, &$created): string {
    $page = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'obx-interop ' . $uid()]);
    $created[] = $page;
    $document = Elementor::$instance->documents->get($page);
    $document->set_is_built_with_elementor(true);
    $document->save(['elements'=>[['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>[],'elements'=>$widgets]]]);
    $user = get_current_user_id();
    if ($as_guest) { wp_set_current_user(0); }
    $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($page);
    if ($as_guest) { wp_set_current_user($user); }
    return $html;
};

$cat_a = wp_insert_term('obx-interop-a ' . $uid(), 'category');
$cat_b = wp_insert_term('obx-interop-b ' . $uid(), 'category');
$posts = [];
// Fixed dates far in the past: no other post of the site can fall between them (post navigation).
foreach (['Alfa','Beta','Gamma','Delta'] as $i => $title) {
    $posts[$title] = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'obx-interop ' . $title,'post_content'=>'Texto de ' . $title . ' con varias palabras para el extracto del widget.','post_date'=>sprintf('2001-02-03 1%d:00:00', $i),'post_category'=>[$i < 3 ? $cat_a['term_id'] : $cat_b['term_id']]]);
    $created[] = $posts[$title];
}
$menu_id = wp_create_nav_menu('obx-interop-menu-' . $uid());
wp_update_nav_menu_item($menu_id, 0, ['menu-item-title'=>'Elemento interop','menu-item-url'=>home_url('/x/'),'menu-item-status'=>'publish']);
$menu = wp_get_nav_menu_object($menu_id);

try {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query(['p'=>$posts['Alfa']]);
    $wp_query->the_post();

    // Posts widget saved by Elementor Pro: classic skin, terms filter, ascending order, numbers pagination.
    $html = $render([$widget('posts', [
        '_skin'=>'classic','classic_columns'=>'2','classic_posts_per_page'=>2,'classic_meta_data'=>['author','date'],'classic_meta_separator'=>'~',
        'classic_read_more_text'=>'Seguir »','classic_title_tag'=>'h5','classic_thumbnail'=>'none',
        'posts_post_type'=>'post','posts_include'=>['terms'],'posts_include_term_ids'=>[(string) $cat_a['term_id']],'posts_exclude'=>[],
        'posts_orderby'=>'post_title','posts_order'=>'asc','pagination_type'=>'numbers_and_prev_next','pagination_prev_label'=>'&laquo; Ant','pagination_next_label'=>'Sig &raquo;',
    ])]);
    $assert(preg_match_all('~<article class="obx-post ~', $html) === 2, 'posts: classic_posts_per_page debe limitar a 2 entradas.');
    $assert(strpos($html, 'obx-interop Alfa') < strpos($html, 'obx-interop Beta') && !str_contains($html, 'obx-interop Delta') && !str_contains($html, 'obx-interop Gamma'), 'posts: orden por título ascendente y filtro de términos.');
    $assert(str_contains($html, '<h5 class="obx-post__title">') && str_contains($html, 'Seguir »') && str_contains($html, 'obx-post__author'), 'posts: claves classic_title_tag, classic_read_more_text y classic_meta_data.');
    $assert(str_contains($html, 'class="obx-pagination"') && str_contains($html, '&laquo; Ant') && str_contains($html, 'e-page-'), 'posts: paginación con etiquetas propias.');

    // Cards skin, exclude terms, current post excluded.
    $html = $render([$widget('posts', ['_skin'=>'cards','cards_posts_per_page'=>10,'cards_show_badge'=>'yes','cards_badge_taxonomy'=>'category','posts_exclude'=>['terms','current_post'],'posts_exclude_term_ids'=>[(string) $cat_b['term_id']],'posts_include'=>['terms'],'posts_include_term_ids'=>[(string) $cat_a['term_id'],(string) $cat_b['term_id']]])]);
    $assert(str_contains($html, 'obx-posts--cards') && str_contains($html, 'obx-post__card'), 'posts: skin de tarjetas.');
    $assert(!str_contains($html, 'obx-interop Delta') && !str_contains($html, 'obx-interop Alfa') && str_contains($html, 'obx-interop Beta'), 'posts: exclusión de términos y de la entrada actual.');

    // Manual selection.
    $html = $render([$widget('posts', ['posts_post_type'=>'by_id','posts_posts_ids'=>[(string) $posts['Delta']],'classic_posts_per_page'=>5])]);
    $assert(str_contains($html, 'obx-interop Delta') && preg_match_all('~<article class="obx-post ~', $html) === 1, 'posts: selección manual por ID.');

    // Pro's custom query hook.
    $hooked = false;
    add_action('elementor/query/obx_interop', static function ($query, $w) use (&$hooked) { $hooked = $query instanceof WP_Query && $w instanceof \Elementor\Widget_Base; $query->set('posts_per_page', 1); }, 10, 2);
    $html = $render([$widget('posts', ['posts_query_id'=>'obx_interop','classic_posts_per_page'=>5,'posts_include'=>['terms'],'posts_include_term_ids'=>[(string) $cat_a['term_id']]])]);
    $assert($hooked && preg_match_all('~<article class="obx-post ~', $html) === 1, 'posts: la acción elementor/query/{id} debe poder modificar la consulta.');

    // Legacy obx-archive-posts settings → archive_classic_* keys.
    $up = \OldBearExpansion\Widgets\Archive_Posts::upgrade_cards(['columns'=>'2','show_image'=>'','title_tag'=>'h4','read_more'=>'Más','show_date'=>'yes','pagination'=>'','empty'=>'Nada','title_typography_typography'=>'custom','card_bg'=>'#fff'], 'archive_classic_');
    $assert(($up['_skin'] ?? '') === 'archive_classic' && $up['archive_classic_columns'] === '2' && $up['archive_classic_thumbnail'] === 'none' && $up['archive_classic_title_tag'] === 'h4', 'archive-posts: migración de diseño.');
    $assert($up['archive_classic_read_more_text'] === 'Más' && $up['archive_classic_meta_data'] === ['date'] && $up['pagination_type'] === '' && $up['nothing_found_message'] === 'Nada', 'archive-posts: migración de textos y paginación.');
    $assert(($up['archive_classic_title_typography_typography'] ?? '') === 'custom' && ($up['archive_classic_box_bg_color'] ?? '') === '#fff' && !isset($up['columns'], $up['card_bg']), 'archive-posts: migración de estilos.');

    // v0.3 «posts» data saved under the Pro name with our old keys.
    $html = $render([$widget('posts', ['post_type'=>'post','posts_per_page'=>1,'show_image'=>'yes','read_more'=>'Antiguo','exclude_current'=>'yes'])]);
    $assert(preg_match_all('~<article class="obx-post ~', $html) === 1 && str_contains($html, 'Antiguo'), 'posts: los datos de la v0.3 deben traducirse.');

    // Post info with Pro's icon_list repeater.
    $html = $render([$widget('post-info', ['view'=>'inline','divider'=>'yes','icon_list'=>[
        ['_id'=>'a1','type'=>'author','text_prefix'=>'Por','show_icon'=>'none'],
        ['_id'=>'a2','type'=>'date','date_format'=>'1'],
        ['_id'=>'a3','type'=>'terms','taxonomy'=>['category']],
        ['_id'=>'a4','type'=>'custom','custom_text'=>'Texto <libre>','custom_url'=>['url'=>'https://example.org/']],
    ]])]);
    $assert(str_contains($html, 'obx-post-info--divider') && str_contains($html, '>Por</span>'), 'post-info: prefijo y separador.');
    $assert(str_contains($html, get_the_date('Y-m-d', $posts['Alfa'])), 'post-info: formato de fecha «1» (Y-m-d).');
    $assert(str_contains($html, esc_html($cat_a['term_id'] ? get_term($cat_a['term_id'])->name : '')) && str_contains($html, 'Texto &lt;libre&gt;') && str_contains($html, 'https://example.org/'), 'post-info: términos y elemento personalizado escapado.');
    $assert(str_contains($html, 'elementor-repeater-item-a2'), 'post-info: clase por elemento del repetidor.');

    // Legacy obx-post-info → icon_list.
    $html = $render([$widget('obx-post-info', ['show_date'=>'yes','show_author'=>'','show_terms'=>'yes','show_comments'=>'yes'])]);
    $assert(str_contains($html, 'obx-post-info__item--date') && !str_contains($html, 'obx-post-info__item--author') && str_contains($html, 'obx-post-info__item--terms'), 'post-info: migración de interruptores antiguos.');

    // Nav menu saved by Pro references the menu by slug; ours used the ID.
    $html = $render([$widget('nav-menu', ['menu'=>$menu->slug,'layout'=>'horizontal','pointer'=>'framed','dropdown'=>'mobile'])]);
    $assert(str_contains($html, 'Elemento interop') && str_contains($html, 'obx-nav--pointer-framed') && str_contains($html, 'obx-nav--bp-mobile'), 'nav-menu: menú por slug, indicador y punto de corte.');
    $html = $render([$widget('obx-nav-menu', ['menu'=>(string) $menu_id,'breakpoint'=>'none','align'=>'center'])]);
    $assert(str_contains($html, 'Elemento interop') && str_contains($html, 'obx-nav--bp-none'), 'nav-menu: datos antiguos con ID de menú.');

    // Heading keys on theme widgets, and their CSS.
    $html = $render([$widget('theme-post-title', ['header_size'=>'h4','title_color'=>'#123456','align'=>'center'])]);
    $assert((bool) preg_match('~<h4 class="elementor-heading-title [^"]*">obx-interop Alfa</h4>~', $html), 'theme-post-title: header_size.');
    $css_page = end($created);
    $css = (new \Elementor\Core\Files\CSS\Post($css_page));
    $css->update();
    $assert(str_contains((string) $css->get_content(), '#123456'), 'theme-post-title: title_color genera CSS.');

    // Legacy Pro «search-form» keys.
    $html = $render([$widget('search-form', ['skin'=>'classic','placeholder'=>'Busca aquí','button_type'=>'text','button_text'=>'Ir'])]);
    $assert(str_contains($html, 'placeholder="Busca aquí"') && str_contains($html, '>Ir</button>'), 'search-form: claves de Pro.');
    $html = $render([$widget('search-form', ['skin'=>'full_screen'])]);
    $assert(str_contains($html, 'obx-search-overlay') && str_contains($html, 'hidden'), 'search-form: pantalla completa.');
    $html = $render([$widget('search', ['search_input_placeholder_text'=>'Nuevo buscador','submit_trigger'=>'key_enter'])]);
    $assert(str_contains($html, 'Nuevo buscador') && !str_contains($html, 'obx-search__submit'), 'search: claves del widget actual.');
    // General purpose widgets saved by Elementor Pro.
    $html = $render([$widget('price-table', ['currency_symbol'=>'euro','price'=>'19,90','currency_format'=>',','sale'=>'yes','original_price'=>'25','period'=>'Mensual','period_position'=>'beside','features_list'=>[['_id'=>'f1','item_text'=>'Uno <b>']],'show_ribbon'=>'yes','ribbon_title'=>'Top','button_text'=>'Comprar','link'=>['url'=>'https://example.org/buy']])]);
    $assert(str_contains($html, '>€</span>') && str_contains($html, 'obx-price-table__integer">19<') && str_contains($html, 'obx-price-table__fraction">90<'), 'price-table: símbolo y formato de moneda.');
    $assert(str_contains($html, 'Uno &lt;b&gt;') && str_contains($html, 'obx-ribbon__inner">Top<') && str_contains($html, 'obx-price-table__original') && str_contains($html, 'https://example.org/buy'), 'price-table: características, cinta, oferta y enlace.');
    $html = $render([$widget('price-table', [])]);
    $assert(str_contains($html, '>$</span>') && str_contains($html, 'obx-ribbon__inner'), 'price-table: valores por defecto de Pro (dólar y cinta visible).');

    $html = $render([$widget('code-highlight', ['language'=>'php','code'=>'<?php echo 1;','line_numbers'=>'','copy_to_clipboard'=>'copy-to-clipboard','theme'=>'okaidia'])]);
    $assert(str_contains($html, 'obx-code--theme-okaidia') && !str_contains($html, 'obx-code--numbers') && str_contains($html, 'obx-code__copy') && str_contains($html, '&lt;?php'), 'code-highlight: valores de retorno de Pro y tema.');
    $html = $render([$widget('code-highlight', [])]);
    $assert(str_contains($html, 'obx-code--numbers') && str_contains($html, 'obx-code--theme-default'), 'code-highlight: por defecto con números de línea y tema claro.');

    $html = $render([$widget('countdown', ['countdown_type'=>'due_date','due_date'=>'2030-01-01 10:00','expire_actions'=>['message','redirect'],'message_after_expire'=>'Fin','expire_redirect_url'=>['url'=>'https://example.org/'],'show_seconds'=>'','custom_labels'=>'yes','label_days'=>'Jornadas'])]);
    $assert(str_contains($html, 'data-expire="message,redirect"') && str_contains($html, 'Jornadas') && !str_contains($html, 'data-unit="seconds"') && str_contains($html, '>Fin</div>'), 'countdown: expire_actions múltiple y etiquetas.');

    $html = $render([$widget('share-buttons', ['share_buttons'=>[['_id'=>'s1','button'=>'x-twitter'],['_id'=>'s2','button'=>'whatsapp','text'=>'Wasap']],'skin'=>'framed','shape'=>'circle','view'=>'icon-text','share_url_type'=>'custom','share_url'=>['url'=>'https://example.org/p']])]);
    $assert(str_contains($html, 'obx-share__button--x-twitter') && str_contains($html, 'Wasap') && str_contains($html, 'obx-share--skin-framed') && str_contains($html, 'obx-share--shape-circle') && str_contains($html, rawurlencode('https://example.org/p')), 'share-buttons: claves y dirección personalizada.');

    $html = $render([$widget('blockquote', ['blockquote_content'=>'Frase <i>', 'author_name'=>'Ana','tweet_button'=>'yes','tweet_button_skin'=>'bubble','user_name'=>'@ana'])]);
    $assert(str_contains($html, 'Frase &lt;i&gt;') && str_contains($html, 'obx-quote__tweet--bubble') && str_contains($html, 'via=ana'), 'blockquote: claves de Pro.');

    $html = $render([$widget('call-to-action', ['skin'=>'cover','title'=>'Título CTA','description'=>'Desc','button'=>'Ir','link'=>['url'=>'https://example.org/c'],'link_click'=>'box','ribbon_title'=>'Nuevo','content_animation'=>'enter-from-left','transformation'=>'move-up','graphic_element'=>'icon'])]);
    $assert(str_contains($html, '<a ') && str_contains($html, 'obx-cta--cover') && str_contains($html, 'obx-cta--anim-enter-from-left') && str_contains($html, 'obx-cta--bg-move-up') && str_contains($html, 'obx-cta__icon') && str_contains($html, '>Nuevo<'), 'call-to-action: claves de Pro.');

    $html = $render([$widget('flip-box', ['title_text_a'=>'Delante','description_text_b'=>'Detrás','button_text'=>'Más','flip_effect'=>'zoom-out','title_tag'=>'h5'])]);
    $assert(str_contains($html, 'obx-flip--zoom-out') && str_contains($html, '<h5 class="obx-flip__title">Delante</h5>') && str_contains($html, 'Detrás') && str_contains($html, '>Más<'), 'flip-box: claves de Pro.');

    $html = $render([$widget('animated-headline', ['headline_style'=>'rotate','animation_type'=>'wave','rotating_text'=>"uno\ndos",'before_text'=>'Antes','tag'=>'h2'])]);
    $assert(str_contains($html, 'obx-headline--wave') && str_contains($html, '&quot;dos&quot;') && str_contains($html, '<h2 '), 'animated-headline: rotación.');
    $html = $render([$widget('animated-headline', [])]);
    $assert(str_contains($html, 'obx-headline--circle') && str_contains($html, 'data-delay="8000"'), 'animated-headline: valores por defecto (círculo).');

    $html = $render([$widget('table-of-contents', ['headings_by_tags'=>['h2'],'marker_view'=>'bullets','minimized_on'=>'mobile','no_headings_message'=>'Vacío','html_tag'=>'h5'])]);
    $assert(str_contains($html, 'data-headings="h2"') && str_contains($html, 'obx-toc--bullets') && str_contains($html, 'data-minimized-on="mobile"') && str_contains($html, '>Vacío<') && str_contains($html, '<h5 class="obx-toc__title"'), 'table-of-contents: claves de Pro.');

    $html = $render([$widget('progress-tracker', ['type'=>'circular','relative_to'=>'post_content','percentage'=>'yes','direction'=>'rtl'])]);
    $assert(str_contains($html, 'obx-progress--circular') && str_contains($html, 'obx-progress--rtl') && str_contains($html, 'theme-post-content') && str_contains($html, 'obx-progress__value'), 'progress-tracker: claves de Pro.');

    $html = $render([$widget('login', ['custom_labels'=>'yes','user_placeholder'=>'Tu correo','button_text'=>'Entrar','show_remember_me'=>'','redirect_after_login'=>'yes','redirect_url'=>['url'=>'https://evil.example/']])], true);
    $assert(str_contains($html, 'placeholder="Tu correo"') && str_contains($html, '>Entrar</button>') && !str_contains($html, 'rememberme') && !str_contains($html, 'evil.example'), 'login: claves de Pro y redirección solo local.');

    // v0.3 data saved under Pro names is translated.
    $html = $render([$widget('price-table', ['currency'=>'€','currency_position'=>'after','price'=>'29,99','items'=>[['text'=>'Antigua','included'=>'yes']],'button'=>'Viejo'])]);
    $assert(str_contains($html, 'obx-price-table__currency--after') && str_contains($html, '>Antigua<') && str_contains($html, '>Viejo<') && !str_contains($html, 'obx-ribbon'), 'price-table: datos de la v0.3.');
    $html = $render([$widget('countdown', ['type'=>'evergreen','evergreen_hours'=>2,'evergreen_minutes'=>0,'expire'=>'hide'])]);
    $assert(str_contains($html, 'data-evergreen="7200"') && str_contains($html, 'data-expire="hide"'), 'countdown: datos de la v0.3.');
    $html = $render([$widget('share-buttons', ['buttons'=>[['network'=>'x'],['network'=>'telegram']],'skin'=>'brand'])]);
    $assert(str_contains($html, 'obx-share__button--x-twitter') && str_contains($html, 'obx-share--color-official'), 'share-buttons: datos de la v0.3.');
    $html = $render([$widget('code-highlight', ['code'=>'x','line_numbers'=>'','copy'=>'','theme'=>'light'])]);
    $assert(!str_contains($html, 'obx-code__copy') && str_contains($html, 'obx-code--theme-default') && !str_contains($html, 'obx-code--numbers'), 'code-highlight: datos de la v0.3.');

    // Theme widgets with Pro keys.
    $html = $render([$widget('author-box', ['author_name_tag'=>'h3','show_biography'=>'','show_link'=>'yes','link_text'=>'Ver todo','layout'=>'above','link_to'=>'posts_archive'])]);
    $assert(str_contains($html, 'obx-author--above') && str_contains($html, '<h3 class="obx-author__name"><a href=') && str_contains($html, '>Ver todo</a>') && !str_contains($html, 'obx-author__bio'), 'author-box: claves de Pro.');
    $html = $render([$widget('author-box', ['source'=>'custom','author_name'=>'Persona Ficticia','author_bio'=>'Bio inventada','posts_url'=>['url'=>'https://example.com/a'],'link_text'=>'Más'])]);
    $assert(str_contains($html, 'Persona Ficticia') && str_contains($html, 'Bio inventada') && str_contains($html, 'href="https://example.com/a"'), 'author-box: autor personalizado.');
    $html = $render([$widget('author-box', ['show_bio'=>'', 'name_tag'=>'h5', 'button'=>'Entradas', 'layout'=>'top'])]);
    $assert(str_contains($html, '<h5 class="obx-author__name">') && str_contains($html, 'obx-author--above') && str_contains($html, '>Entradas</a>'), 'author-box: datos de la v0.3.');
    $wp_the_query = $wp_query = new WP_Query(['p'=>$posts['Beta']]);
    $wp_query->the_post();
    $html = $render([$widget('post-navigation', ['prev_label'=>'Atrás','next_label'=>'Adelante','show_borders'=>'','show_arrow'=>''])]);
    $assert(str_contains($html, 'obx-postnav') && !str_contains($html, 'obx-postnav--borders') && str_contains($html, '>Atrás<') && str_contains($html, '>Adelante<') && !str_contains($html, 'obx-postnav__arrow') && str_contains($html, 'obx-interop Alfa') && str_contains($html, 'obx-interop Gamma'), 'post-navigation: claves de Pro.');
    $html = $render([$widget('post-navigation', ['show_label'=>'','in_same_term'=>'yes','post_taxonomy'=>'category'])]);
    $assert(!str_contains($html, 'obx-postnav__label') && str_contains($html, 'obx-postnav__title'), 'post-navigation: sin etiquetas y mismo término.');
    $wp_the_query = $wp_query = new WP_Query(['p'=>$posts['Alfa']]);
    $wp_query->the_post();
    $html = $render([$widget('post-comments', ['_skin'=>'theme_comments'])], true);
    $assert(str_contains($html, 'obx-comments') || str_contains($html, 'comentarios'), 'post-comments: skin theme_comments.');

    // Form with Pro keys: markup and a real submission through the REST endpoint.
    $form_el = $widget('form', [
        'form_name'=>'Contacto interop','button_text'=>'Mandar','mark_required'=>'yes','submit_actions'=>['email','save-to-database','redirect'],
        'redirect_to'=>'https://example.com/gracias?n=[field id="name"]','email_to'=>'destino@example.com','email_subject'=>'Hola de [field id="name"]','email_reply_to'=>'[field id="email"]',
        'form_fields'=>[
            ['_id'=>'a1','custom_id'=>'name','field_type'=>'text','field_label'=>'Nombre','required'=>'true','width'=>'50'],
            ['_id'=>'a2','custom_id'=>'email','field_type'=>'email','field_label'=>'Correo','required'=>'true','width'=>'50','width_mobile'=>'100'],
            ['_id'=>'a3','custom_id'=>'topic','field_type'=>'select','field_label'=>'Tema','field_options'=>"Ventas|sales\nSoporte|support"],
            ['_id'=>'a4','custom_id'=>'','field_type'=>'checkbox','field_label'=>'Extras','field_options'=>"Uno\nDos",'inline_list'=>'elementor-subgroup-inline'],
            ['_id'=>'a5','custom_id'=>'ok','field_type'=>'acceptance','acceptance_text'=>'Acepto','required'=>'true'],
            ['_id'=>'a6','custom_id'=>'secret','field_type'=>'hidden','field_value'=>'oculto'],
        ],
    ]);
    $html = $render([$form_el], true);
    $form_page = end($created);
    $assert(str_contains($html, 'data-obx-form') && str_contains($html, 'name="form_fields[name]"') && str_contains($html, 'obx-col-50') && str_contains($html, 'obx-col-sm-100') && str_contains($html, 'obx-form--mark-required') && str_contains($html, '>Mandar</span>'), 'form: marcado con claves de Pro.');
    $assert(str_contains($html, 'name="form_fields[field_a4][]"') && str_contains($html, 'obx-form__group--inline') && str_contains($html, '<option value="support"') && str_contains($html, 'value="oculto"') && str_contains($html, 'name="form_id" value="' . $form_el['id'] . '"'), 'form: tipos de campo y id por defecto.');
    $mails = [];
    $catch = static function ($return, $atts) use (&$mails) { $mails[] = $atts; return true; };
    add_filter('pre_wp_mail', $catch, 10, 2);
    $send = static function (array $fields, array $extra = []) use ($form_page, $form_el) {
        $request = new WP_REST_Request('POST', '/obx/v1/form');
        $request->set_body_params(['post_id'=>$form_page,'form_id'=>$form_el['id'],'form_fields'=>$fields] + $extra);
        return rest_do_request($request);
    };
    $admin_id = get_current_user_id();
    wp_set_current_user(0);
    $bad = $send(['name'=>'','email'=>'no-es-correo','ok'=>'on']);
    $assert($bad->get_status() === 400 && isset($bad->get_data()['data']['errors']['name'], $bad->get_data()['data']['errors']['email']) && !$mails, 'form: validación en el servidor.');
    $tampered = $send(['name'=>'Ana','email'=>'ana@example.com','ok'=>'on','topic'=>'hack']);
    $assert($tampered->get_status() === 400 && isset($tampered->get_data()['data']['errors']['topic']), 'form: solo opciones definidas.');
    $ok = $send(['name'=>"Ana\r\nBcc: x@evil.example",'email'=>'ana@example.com','ok'=>'on','topic'=>'support','field_a4'=>['Dos','Inventada'],'secret'=>'cambiado']);
    $data = $ok->get_data();
    $assert($ok->get_status() === 200 && $data['success'] && str_starts_with($data['data']['redirect_url'] ?? '', 'https://example.com/gracias?n=Ana'), 'form: envío correcto y redirección.');
    $mail = $mails[0] ?? [];
    $headers = implode("\n", (array) ($mail['headers'] ?? []));
    $assert(($mail['to'] ?? []) === ['destino@example.com'] && !str_contains((string) $mail['subject'], "\n") && str_contains($headers, 'Reply-To: ana@example.com') && !str_contains($headers, 'evil.example') && str_contains((string) $mail['message'], 'Tema: support') && str_contains((string) $mail['message'], 'Extras: Dos') && !str_contains((string) $mail['message'], 'Inventada'), 'form: correo sin inyección de cabeceras.');
    $saved = get_posts(['post_type'=>'obx_submission','post_status'=>'private','numberposts'=>1,'meta_key'=>'_obx_form_id','meta_value'=>$form_el['id']]);
    $assert($saved && get_post_meta($saved[0]->ID, '_obx_form_name', true) === 'Contacto interop', 'form: envío guardado.');
    foreach ($saved as $item) { $created[] = $item->ID; }
    $honeypot = $send(['name'=>'Bot','email'=>'bot@example.com','ok'=>'on'], ['obx_hp'=>'spam']);
    $assert($honeypot->get_status() === 200 && count($mails) === 1, 'form: el campo trampa descarta el envío.');
    $missing = rest_do_request((static function () use ($form_page) { $r = new WP_REST_Request('POST', '/obx/v1/form'); $r->set_body_params(['post_id'=>$form_page,'form_id'=>'nope123']); return $r; })());
    $assert($missing->get_status() === 404, 'form: formulario inexistente.');
    wp_set_current_user($admin_id);
    remove_filter('pre_wp_mail', $catch, 10);
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_obx\_form\_%' OR option_name LIKE '\_transient\_timeout\_obx\_form\_%'");


    // Carousels and gallery with Pro keys.
    $html = $render([$widget('testimonial-carousel', ['slides'=>[['_id'=>'t1','content'=>'Opinión ficticia uno','name'=>'Cliente A','title'=>'Ciudad'],['_id'=>'t2','content'=>'Opinión ficticia dos','name'=>'Cliente B','title'=>'Pueblo']],'skin'=>'bubble','layout'=>'image_stacked','alignment'=>'left','show_arrows'=>'','pagination'=>'progressbar','slides_per_view'=>'3','autoplay'=>'','loop'=>''])]);
    $assert(str_contains($html, 'obx-testimonials--skin-bubble') && str_contains($html, 'obx-testimonials--layout-image_stacked') && str_contains($html, 'obx-testimonials--align-left') && !str_contains($html, 'obx-carousel__arrow') && str_contains($html, 'obx-carousel__pagination--progressbar') && substr_count($html, 'obx-carousel__slide"') === 2 && str_contains($html, 'Cliente B'), 'testimonial-carousel: claves de Pro.');
    $assert(str_contains($html, '&quot;autoplay&quot;:false') && str_contains($html, '&quot;loop&quot;:false'), 'testimonial-carousel: opciones del carrusel.');
    if (\OldBearExpansion\Widgets\Nested_Carousel::available()) {
        $nested = $widget('nested-carousel', ['carousel_items'=>[['_id'=>'s1','slide_title'=>'Uno'],['_id'=>'s2','slide_title'=>'Dos']],'slides_to_show'=>'2','pagination'=>'fraction']);
        $nested['elements'] = [
            ['id'=>$uid(),'elType'=>'container','isInner'=>true,'settings'=>[],'elements'=>[$widget('heading', ['title'=>'Contenido de la diapositiva uno'])]],
            ['id'=>$uid(),'elType'=>'container','isInner'=>true,'settings'=>[],'elements'=>[$widget('heading', ['title'=>'Contenido de la diapositiva dos'])]],
        ];
        $html = $render([$nested]);
        $assert(str_contains($html, 'obx-ncarousel') && str_contains($html, 'Contenido de la diapositiva uno') && str_contains($html, 'Contenido de la diapositiva dos') && preg_match_all('~<div class="obx-carousel__track">\\s*<div class="elementor-element[^"]*e-con~', $html) === 1 && str_contains($html, 'obx-carousel__pagination--fraction') && str_contains($html, 'obx-carousel__arrow--next'), 'nested-carousel: diapositivas con contenedores hijos.');
    }
    $img = wp_insert_attachment(['post_title'=>'obx-interop-img','post_mime_type'=>'image/png','post_status'=>'inherit','post_excerpt'=>'Leyenda ficticia'], false);
    $created[] = $img;
    update_post_meta($img, '_wp_attachment_metadata', ['width'=>800,'height'=>400,'file'=>'obx-interop.png']);
    update_post_meta($img, '_wp_attached_file', 'obx-interop.png');
    $html = $render([$widget('gallery', ['gallery'=>[['id'=>$img,'url'=>'https://example.com/a.png']],'aspect_ratio'=>'1:1','columns'=>3,'overlay_title'=>'caption','image_hover_animation'=>'grow'])]);
    $assert(str_contains($html, 'obx-gallery--grid') && str_contains($html, '--obx-ratio: 1 / 1') && str_contains($html, 'data-elementor-open-lightbox="default"') && str_contains($html, 'Leyenda ficticia') && str_contains($html, '--w: 800; --h: 400') && str_contains($html, 'obx-gallery--hover-grow'), 'gallery: cuadrícula con claves de Pro.');
    $html = $render([$widget('gallery', ['gallery_type'=>'multiple','galleries'=>[['_id'=>'g1','gallery_title'=>'Primera','multiple_gallery'=>[['id'=>$img,'url'=>'']]],['_id'=>'g2','gallery_title'=>'Segunda','multiple_gallery'=>[['id'=>$img,'url'=>'']]]],'show_all_galleries_label'=>'Todo','gallery_layout'=>'justified','link_to'=>''])]);
    $assert(str_contains($html, 'obx-gallery--justified') && str_contains($html, '>Todo</button>') && str_contains($html, '>Segunda</button>') && str_contains($html, 'data-galleries="0,1"') && !str_contains($html, '<a class="obx-gallery__item'), 'gallery: varias galerías y filtros.');
    // Conversion of stored data to Elementor Pro's format.
    $tree = [['id'=>'c1','elType'=>'container','settings'=>[],'elements'=>[
        ['id'=>'w1','elType'=>'widget','widgetType'=>'obx-nav-menu','settings'=>['menu'=>(string) $menu_id,'breakpoint'=>'none','align'=>'center'],'elements'=>[]],
        ['id'=>'w2','elType'=>'widget','widgetType'=>'author-box','settings'=>['show_bio'=>'','name_tag'=>'h5'],'elements'=>[]],
        ['id'=>'w3','elType'=>'widget','widgetType'=>'nav-menu','settings'=>['menu'=>'x','dropdown'=>'tablet'],'elements'=>[]],
        ['id'=>'w4','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Sin tocar'],'elements'=>[]],
    ]]];
    $changed = \OldBearExpansion\Converter::convert_elements($tree);
    $els = $tree[0]['elements'];
    $assert($changed === 2 && $els[0]['widgetType'] === 'nav-menu' && ($els[0]['settings']['dropdown'] ?? '') === 'none' && $els[1]['settings']['author_name_tag'] === 'h5' && !isset($els[1]['settings']['name_tag']), 'converter: nombres y claves de Pro.');
    $assert($els[2]['settings'] === ['menu'=>'x','dropdown'=>'tablet'] && $els[3]['settings'] === ['title'=>'Sin tocar'], 'converter: no toca datos que ya están en formato de Pro.');
    $again = $tree;
    $assert(\OldBearExpansion\Converter::convert_elements($again) === 0, 'converter: es idempotente.');
} finally {
    foreach ($created as $id) { wp_delete_post($id, true); }
    wp_delete_nav_menu($menu_id);
    wp_delete_term($cat_a['term_id'], 'category');
    wp_delete_term($cat_b['term_id'], 'category');
    wp_reset_postdata();
}
WP_CLI::success("{$checks} pruebas de compatibilidad de datos superadas.");
