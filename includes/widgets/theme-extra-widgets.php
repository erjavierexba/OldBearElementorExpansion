<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/** Title of the current page, post, archive or search, whatever the view. */
final class Page_Title extends Base {
    public const PRO = 'theme-page-title';
    public function get_title(): string { return 'Título de la página'; }
    public function get_icon(): string { return 'eicon-archive-title'; }
    public function get_keywords(): array { return ['title','titulo','page','pagina','heading']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Título']);
        $this->tag_control('h1');
        $this->end_controls_section();
        $this->text_style('.obx-page-title', 'Título');
    }
    protected function render(): void {
        if (Context::template() || is_singular()) {
            $post = Context::post();
            $title = $post ? get_the_title($post) : '';
        } elseif (is_404()) {
            $title = 'Página no encontrada';
        } else {
            $title = Context::archive_title();
        }
        if ($title === '') { $this->placeholder('Título de la página'); return; }
        printf('<%1$s class="obx-page-title">%2$s</%1$s>', $this->tag(), esc_html($title));
    }
}

final class Author_Box extends Base {
    public const PRO = 'author-box';
    public function get_title(): string { return 'Caja de autor'; }
    public function get_icon(): string { return 'eicon-person'; }
    public function get_keywords(): array { return ['author','autor','bio','biografia','perfil']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Autor']);
        $this->add_control('show_avatar', ['label'=>'Avatar','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('layout', ['label'=>'Posición del avatar','type'=>Controls_Manager::SELECT,'options'=>['left'=>'Izquierda','top'=>'Arriba','right'=>'Derecha'],'default'=>'left','condition'=>['show_avatar'=>'yes']]);
        $this->add_control('show_name', ['label'=>'Nombre','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('name_tag', ['label'=>'Etiqueta del nombre','type'=>Controls_Manager::SELECT,'options'=>['h3'=>'H3','h4'=>'H4','h5'=>'H5','p'=>'p','div'=>'div'],'default'=>'h4','condition'=>['show_name'=>'yes']]);
        $this->add_control('show_bio', ['label'=>'Biografía','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('link_to', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'options'=>['posts'=>'Entradas del autor','website'=>'Web del autor','none'=>'Ninguno'],'default'=>'posts']);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'','placeholder'=>'Todas las entradas','condition'=>['link_to!'=>'none']]);
        $this->end_controls_section();
        $this->start_controls_section('avatar_style', ['label'=>'Avatar','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_avatar'=>'yes']]);
        $this->add_responsive_control('avatar_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>20,'max'=>300]],'default'=>['unit'=>'px','size'=>96],'selectors'=>['{{WRAPPER}} .obx-author__avatar img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('avatar_radius', ['label'=>'Radio','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['unit'=>'%','size'=>50],'selectors'=>['{{WRAPPER}} .obx-author__avatar img'=>'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
        $this->text_style('.obx-author__name', 'Nombre', 'name', false);
        $this->text_style('.obx-author__bio', 'Biografía', 'bio', false);
        $this->button_style('.obx-author__button', 'Botón', 'button', ['button!'=>'']);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        $author = $post ? (int) $post->post_author : (is_author() ? (int) get_queried_object_id() : 0);
        if (!$author || !get_userdata($author)) { $this->placeholder('No hay autor que mostrar.'); return; }
        $url = $s['link_to'] === 'posts' ? get_author_posts_url($author) : ($s['link_to'] === 'website' ? (string) get_the_author_meta('url', $author) : '');
        $name = esc_html(get_the_author_meta('display_name', $author));
        $layout = in_array($s['layout'], ['left','top','right'], true) ? $s['layout'] : 'left';
        echo '<div class="obx-author obx-author--' . esc_attr($layout) . '">';
        if ($s['show_avatar'] === 'yes') {
            $avatar = get_avatar($author, 300, '', $name, ['loading'=>'lazy']);
            echo '<div class="obx-author__avatar">' . ($url ? '<a href="' . esc_url($url) . '" tabindex="-1" aria-hidden="true">' . $avatar . '</a>' : $avatar) . '</div>';
        }
        echo '<div class="obx-author__text">';
        if ($s['show_name'] === 'yes') {
            $tag = in_array($s['name_tag'], ['h3','h4','h5','p','div'], true) ? $s['name_tag'] : 'h4';
            printf('<%1$s class="obx-author__name">%2$s</%1$s>', $tag, $url ? '<a href="' . esc_url($url) . '">' . $name . '</a>' : $name);
        }
        if ($s['show_bio'] === 'yes' && get_the_author_meta('description', $author)) {
            echo '<div class="obx-author__bio">' . wp_kses_post(wpautop(get_the_author_meta('description', $author))) . '</div>';
        }
        if ($url && $s['button'] !== '') { echo '<a class="obx-author__button" href="' . esc_url($url) . '">' . esc_html($s['button']) . '</a>'; }
        echo '</div></div>';
    }
}

final class Post_Navigation extends Base {
    public const PRO = 'post-navigation';
    public function get_title(): string { return 'Navegación de entradas'; }
    public function get_icon(): string { return 'eicon-post-navigation'; }
    public function get_keywords(): array { return ['navigation','navegacion','anterior','siguiente','previous','next']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Navegación']);
        $this->add_control('show_label', ['label'=>'Etiqueta','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('prev_label', ['label'=>'Etiqueta anterior','type'=>Controls_Manager::TEXT,'default'=>'Anterior','condition'=>['show_label'=>'yes']]);
        $this->add_control('next_label', ['label'=>'Etiqueta siguiente','type'=>Controls_Manager::TEXT,'default'=>'Siguiente','condition'=>['show_label'=>'yes']]);
        $this->add_control('show_title', ['label'=>'Título de la entrada','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('show_arrow', ['label'=>'Flechas','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('in_same_term', ['label'=>'Solo de la misma categoría','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->end_controls_section();
        $this->text_style('.obx-postnav__label', 'Etiqueta', 'label', false);
        $this->text_style('.obx-postnav__title', 'Título', 'title', false);
    }
    private function link(?\WP_Post $post, string $dir, array $s): string {
        if (!$post) { return '<span class="obx-postnav__' . $dir . '"></span>'; }
        $arrow = $s['show_arrow'] === 'yes' ? '<span class="obx-postnav__arrow" aria-hidden="true">' . ($dir === 'prev' ? '←' : '→') . '</span>' : '';
        $text = '';
        if ($s['show_label'] === 'yes') { $text .= '<span class="obx-postnav__label">' . esc_html($s[$dir . '_label']) . '</span>'; }
        if ($s['show_title'] === 'yes') { $text .= '<span class="obx-postnav__title">' . esc_html(get_the_title($post)) . '</span>'; }
        $body = '<span class="obx-postnav__text">' . $text . '</span>';
        return '<a class="obx-postnav__' . $dir . '" href="' . esc_url(get_permalink($post)) . '" rel="' . $dir . '">' . ($dir === 'prev' ? $arrow . $body : $body . $arrow) . '</a>';
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        if (!$post) { $this->placeholder('No hay ninguna entrada que mostrar.'); return; }
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;
        $same = $s['in_same_term'] === 'yes';
        $prev = get_adjacent_post($same, '', true);
        $next = get_adjacent_post($same, '', false);
        $GLOBALS['post'] = $previous;
        if (!$prev && !$next) { $this->placeholder('Esta entrada no tiene anterior ni siguiente.'); return; }
        echo '<nav class="obx-postnav" aria-label="Entradas">' . $this->link($prev ?: null, 'prev', $s) . $this->link($next ?: null, 'next', $s) . '</nav>';
    }
}

final class Post_Comments extends Base {
    public const PRO = 'post-comments';
    public function get_title(): string { return 'Comentarios de la entrada'; }
    public function get_icon(): string { return 'eicon-comments'; }
    public function get_keywords(): array { return ['comments','comentarios','respuestas']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Comentarios']);
        $this->add_control('info', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Usa la plantilla de comentarios del tema y respeta los ajustes de Ajustes → Comentarios.','content_classes'=>'elementor-panel-alert elementor-panel-alert-info']);
        $this->end_controls_section();
    }
    protected function render(): void {
        $post = Context::post();
        if (!$post) { $this->placeholder('No hay ninguna entrada que mostrar.'); return; }
        if (!comments_open($post) && !get_comments_number($post)) { $this->placeholder('Los comentarios están cerrados en esta entrada.'); return; }
        if ($this->editing()) { $this->placeholder(sprintf('Aquí aparecerán los comentarios (%d) y el formulario.', get_comments_number($post))); return; }
        global $withcomments;
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;
        setup_postdata($post);
        $withcomments = true;
        echo '<div class="obx-comments">';
        comments_template();
        echo '</div>';
        $GLOBALS['post'] = $previous;
        if ($previous) { setup_postdata($previous); }
    }
}

final class Breadcrumbs extends Base {
    public const PRO = 'breadcrumbs';
    public function get_title(): string { return 'Migas de pan'; }
    public function get_icon(): string { return 'eicon-yoast'; }
    public function get_keywords(): array { return ['breadcrumbs','migas','ruta','navegacion']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Migas de pan']);
        $this->add_control('home', ['label'=>'Texto de inicio','type'=>Controls_Manager::TEXT,'default'=>'Inicio']);
        $this->add_control('separator', ['label'=>'Separador','type'=>Controls_Manager::TEXT,'default'=>'›']);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>['{{WRAPPER}} .obx-breadcrumbs ol'=>'justify-content: {{VALUE}};'],'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end']]);
        $this->end_controls_section();
        $this->text_style('.obx-breadcrumbs', 'Texto', 'text', false);
        $this->start_controls_section('links_style', ['label'=>'Enlaces','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('link_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-breadcrumbs a'=>'color: {{VALUE}};']]);
        $this->end_controls_section();
    }
    /** @return array<int,array{0:string,1:string}> label => url pairs, the last one without url. */
    public static function trail(string $home): array {
        $items = [[$home, home_url('/')]];
        $post = Context::template() ? Context::post() : null;
        if ($post || is_singular()) {
            $post = $post ?: get_queried_object();
            if (!$post instanceof \WP_Post) { return $items; }
            if ($post->post_type === 'post') {
                $blog = (int) get_option('page_for_posts');
                if ($blog) { $items[] = [get_the_title($blog), get_permalink($blog)]; }
                $terms = get_the_category($post->ID);
                if ($terms) {
                    foreach (array_reverse(get_ancestors($terms[0]->term_id, 'category')) as $ancestor) { $items[] = [get_cat_name($ancestor), get_category_link($ancestor)]; }
                    $items[] = [$terms[0]->name, get_category_link($terms[0])];
                }
            } elseif ($post->post_type !== 'page' && ($type = get_post_type_object($post->post_type)) && $type->has_archive) {
                $items[] = [$type->labels->name, get_post_type_archive_link($post->post_type)];
            }
            foreach (array_reverse(get_post_ancestors($post)) as $ancestor) { $items[] = [get_the_title($ancestor), get_permalink($ancestor)]; }
            $items[] = [get_the_title($post), ''];
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                foreach (array_reverse(get_ancestors($term->term_id, $term->taxonomy)) as $ancestor) {
                    $parent = get_term($ancestor, $term->taxonomy);
                    if ($parent instanceof \WP_Term) { $items[] = [$parent->name, get_term_link($parent)]; }
                }
                $items[] = [$term->name, ''];
            }
        } elseif (is_404()) {
            $items[] = ['Página no encontrada', ''];
        } elseif (!is_front_page()) {
            $items[] = [Context::archive_title(), ''];
        }
        return $items;
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $items = self::trail($s['home'] !== '' ? $s['home'] : 'Inicio');
        echo '<nav class="obx-breadcrumbs" aria-label="Migas de pan"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
        $last = count($items) - 1;
        foreach ($items as $i => [$label, $url]) {
            if ($i > 0) { echo '<li class="obx-breadcrumbs__sep" aria-hidden="true">' . esc_html($s['separator']) . '</li>'; }
            echo '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            if ($url && $i !== $last) {
                echo '<a itemprop="item" href="' . esc_url($url) . '"><span itemprop="name">' . esc_html($label) . '</span></a>';
            } else {
                echo '<span itemprop="name"' . ($i === $last ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</span>';
            }
            echo '<meta itemprop="position" content="' . ($i + 1) . '"></li>';
        }
        echo '</ol></nav>';
    }
}

final class Sitemap extends Base {
    public const PRO = 'sitemap';
    public function get_title(): string { return 'Mapa del sitio'; }
    public function get_icon(): string { return 'eicon-sitemap'; }
    public function get_keywords(): array { return ['sitemap','mapa','indice','paginas']; }
    protected function register_controls(): void {
        $types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) { if ($type->name !== 'attachment') { $types[$type->name] = $type->labels->name; } }
        foreach (get_taxonomies(['public'=>true], 'objects') as $tax) { $types['tax:' . $tax->name] = $tax->labels->name; }
        $this->start_controls_section('content', ['label'=>'Mapa del sitio']);
        $this->add_control('sources', ['label'=>'Contenido','type'=>Controls_Manager::SELECT2,'multiple'=>true,'options'=>$types,'default'=>['page','post'],'label_block'=>true]);
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'options'=>['1'=>'1','2'=>'2','3'=>'3','4'=>'4'],'default'=>'2','tablet_default'=>'2','mobile_default'=>'1','selectors'=>['{{WRAPPER}} .obx-sitemap'=>'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));']]);
        $this->add_control('show_titles', ['label'=>'Títulos de sección','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('limit', ['label'=>'Máximo por sección','type'=>Controls_Manager::NUMBER,'min'=>1,'max'=>500,'default'=>50]);
        $this->end_controls_section();
        $this->text_style('.obx-sitemap__title', 'Títulos de sección', 'section', false);
        $this->text_style('.obx-sitemap__list', 'Enlaces', 'links', false);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $limit = max(1, min(500, (int) $s['limit']));
        echo '<div class="obx-sitemap">';
        foreach ((array) $s['sources'] as $source) {
            $source = (string) $source;
            if (str_starts_with($source, 'tax:')) {
                $tax = get_taxonomy(substr($source, 4));
                if (!$tax || !$tax->public) { continue; }
                $list = wp_list_categories(['taxonomy'=>$tax->name,'title_li'=>'','echo'=>false,'number'=>$limit,'show_option_none'=>'']);
                $label = $tax->labels->name;
            } else {
                $type = get_post_type_object($source);
                if (!$type || !$type->public) { continue; }
                $label = $type->labels->name;
                if ($type->hierarchical) {
                    $list = wp_list_pages(['post_type'=>$source,'title_li'=>'','echo'=>false,'number'=>$limit]);
                } else {
                    $list = '';
                    foreach (get_posts(['post_type'=>$source,'posts_per_page'=>$limit,'post_status'=>'publish']) as $item) {
                        $list .= '<li><a href="' . esc_url(get_permalink($item)) . '">' . esc_html(get_the_title($item)) . '</a></li>';
                    }
                }
            }
            if (!$list) { continue; }
            echo '<section class="obx-sitemap__section">';
            if ($s['show_titles'] === 'yes') { echo '<h3 class="obx-sitemap__title">' . esc_html($label) . '</h3>'; }
            echo '<ul class="obx-sitemap__list">' . $list . '</ul></section>'; // Core list markup.
        }
        echo '</div>';
    }
}

/** Posts from any query, with the same cards as the archive widget. */
final class Posts extends Archive_Posts {
    public const NAME = '';
    public const PRO = 'posts';
    public const PRO_CATEGORY = 'pro-elements';
    public function get_title(): string { return 'Entradas'; }
    public function get_icon(): string { return 'eicon-post-list'; }
    public function get_keywords(): array { return ['posts','entradas','blog','grid','rejilla','query','consulta']; }
    protected function query_controls(): void {
        $types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) { if ($type->name !== 'attachment') { $types[$type->name] = $type->labels->name; } }
        $terms = [];
        foreach (get_categories(['hide_empty'=>false]) as $term) { $terms[(string) $term->term_id] = $term->name; }
        $this->start_controls_section('query', ['label'=>'Consulta']);
        $this->add_control('post_type', ['label'=>'Tipo de contenido','type'=>Controls_Manager::SELECT,'options'=>$types,'default'=>'post']);
        $this->add_control('categories', ['label'=>'Categorías','type'=>Controls_Manager::SELECT2,'multiple'=>true,'options'=>$terms,'label_block'=>true,'condition'=>['post_type'=>'post']]);
        $this->add_control('posts_per_page', ['label'=>'Número de entradas','type'=>Controls_Manager::NUMBER,'min'=>1,'max'=>100,'default'=>6]);
        $this->add_control('orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'options'=>['date'=>'Fecha','title'=>'Título','modified'=>'Última modificación','menu_order'=>'Orden','comment_count'=>'Comentarios','rand'=>'Aleatorio'],'default'=>'date']);
        $this->add_control('order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'options'=>['DESC'=>'Descendente','ASC'=>'Ascendente'],'default'=>'DESC']);
        $this->add_control('offset', ['label'=>'Saltar','type'=>Controls_Manager::NUMBER,'min'=>0,'max'=>100,'default'=>0]);
        $this->add_control('exclude_current', ['label'=>'Excluir la entrada actual','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->end_controls_section();
    }
    protected function query(): \WP_Query {
        $s = $this->get_settings_for_display();
        $type = post_type_exists((string) $s['post_type']) ? (string) $s['post_type'] : 'post';
        $per_page = max(1, min(100, (int) $s['posts_per_page']));
        $paged = $s['pagination'] === 'yes' ? max(1, (int) get_query_var('paged'), (int) get_query_var('page')) : 1;
        $args = ['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>$per_page,'ignore_sticky_posts'=>true,
            'orderby'=>in_array($s['orderby'], ['date','title','modified','menu_order','comment_count','rand'], true) ? $s['orderby'] : 'date',
            'order'=>$s['order'] === 'ASC' ? 'ASC' : 'DESC','offset'=>max(0, (int) $s['offset']) + ($paged - 1) * $per_page];
        if ($type === 'post' && !empty($s['categories'])) { $args['category__in'] = array_map('absint', (array) $s['categories']); }
        $current = Context::template() ? null : get_queried_object();
        if ($s['exclude_current'] === 'yes' && $current instanceof \WP_Post) { $args['post__not_in'] = [$current->ID]; }
        return new \WP_Query($args);
    }
}
