<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Utils;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

final class Author_Box extends Element {
    public const PRO = 'author-box';
    protected const V03 = ['show_bio'=>'show_biography','name_tag'=>'author_name_tag','button'=>'link_text','avatar_size'=>'image_size','avatar_radius'=>'image_border_radius'];
    public function get_title(): string { return 'Caja de autor'; }
    public function get_icon(): string { return 'eicon-person'; }
    public function get_keywords(): array { return ['author','autor','bio','biografia','perfil']; }
    protected function is_dynamic_content(): bool { return true; }
    public function get_script_depends(): array { return []; }

    public static function wants_upgrade(string $name, array $settings): bool { return $name === self::PRO && (isset($settings['show_bio']) || isset($settings['name_tag']) || isset($settings['button']) || isset($settings['avatar_size'])); }
    protected static function upgrade_settings(array $s): array {
        if (isset($s['layout'])) { $s['layout'] = ['top'=>'above','left'=>'left','right'=>'right'][$s['layout']] ?? 'left'; }
        if (isset($s['link_to'])) { $s['link_to'] = ['posts'=>'posts_archive','website'=>'website','none'=>''][$s['link_to']] ?? ''; }
        $s['show_link'] = ($s['button'] ?? '') !== '' ? 'yes' : '';
        return parent::upgrade_settings($s);
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_author_info', ['label'=>'Autor']);
        $this->add_control('source', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'current','options'=>['current'=>'Autor actual','custom'=>'Personalizado']]);
        $this->add_control('show_avatar', ['label'=>'Foto de perfil','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['source'=>'current'],'separator'=>'before']);
        $this->add_control('author_avatar', ['label'=>'Foto de perfil','type'=>Controls_Manager::MEDIA,'default'=>['url'=>Utils::get_placeholder_image_src()],'condition'=>['source'=>'custom'],'separator'=>'before']);
        $this->add_control('show_name', ['label'=>'Nombre visible','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['source'=>'current'],'separator'=>'before']);
        $this->add_control('author_name', ['label'=>'Nombre','type'=>Controls_Manager::TEXT,'default'=>'Nombre del autor','condition'=>['source'=>'custom'],'separator'=>'before']);
        $this->add_control('author_name_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'h4','options'=>self::heading_tags()]);
        $this->add_control('link_to', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguno','website'=>'Web','posts_archive'=>'Entradas del autor'],'condition'=>['source'=>'current'],'description'=>'Se aplica al nombre y a la foto.']);
        $this->add_control('author_website', ['label'=>'Enlace','type'=>Controls_Manager::URL,'condition'=>['source'=>'custom'],'description'=>'Se aplica al nombre y a la foto.']);
        $this->add_control('show_biography', ['label'=>'Biografía','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['source'=>'current'],'separator'=>'before']);
        $this->add_control('author_bio', ['label'=>'Biografía','type'=>Controls_Manager::TEXTAREA,'default'=>'Escribe aquí una presentación breve del autor.','rows'=>3,'condition'=>['source'=>'custom'],'separator'=>'before']);
        $this->add_control('show_link', ['label'=>'Botón «Todas las entradas»','type'=>Controls_Manager::SWITCHER,'default'=>'','condition'=>['source'=>'current']]);
        $this->add_control('posts_url', ['label'=>'Enlace del archivo','type'=>Controls_Manager::URL,'condition'=>['source'=>'custom']]);
        $this->add_control('link_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Todas las entradas','conditions'=>['relation'=>'or','terms'=>[['name'=>'show_link','operator'=>'===','value'=>'yes'],['name'=>'source','operator'=>'===','value'=>'custom']]]]);
        $this->add_control('layout', ['label'=>'Posición de la foto','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'above'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'separator'=>'before']);
        $this->add_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>self::css('.obx-author__text', 'text-align: {{VALUE}};')]);
        $this->end_controls_section();

        $this->start_controls_section('section_image_style', ['label'=>'Foto','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('image_vertical_align', ['label'=>'Alineación vertical','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center'],'selectors'=>self::css('.obx-author', 'align-items: {{VALUE}};'),'condition'=>['layout!'=>'above']]);
        $this->slider('image_size', 'Tamaño', '.obx-author__avatar img', 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>200]]], true);
        $this->slider('image_gap', 'Separación', '.obx-author', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]], true);
        $this->add_control('image_border', ['label'=>'Borde','type'=>Controls_Manager::SWITCHER,'selectors'=>self::css('.obx-author__avatar img', 'border-style: solid;'),'separator'=>'before']);
        $this->color('image_border_color', 'Color del borde', '.obx-author__avatar img', 'border-color', ['condition'=>['image_border'=>'yes']]);
        $this->slider('image_border_width', 'Grosor del borde', '.obx-author__avatar img', 'border-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>20]],'condition'=>['image_border'=>'yes']], true);
        $this->slider('image_border_radius', 'Radio del borde', '.obx-author__avatar img', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['%','px','em','rem']], true);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'input_box_shadow','selector'=>'{{WRAPPER}} .obx-author__avatar img']);
        $this->end_controls_section();

        $this->start_controls_section('section_text_style', ['label'=>'Texto','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading_name_style', ['label'=>'Nombre','type'=>Controls_Manager::HEADING]);
        $this->color('name_color', 'Color', '.obx-author__name, .obx-author__name a');
        $this->typography('name_typography', '.obx-author__name');
        $this->slider('name_gap', 'Separación', '.obx-author__name', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]], true);
        $this->add_control('heading_bio_style', ['label'=>'Biografía','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('bio_color', 'Color', '.obx-author__bio');
        $this->typography('bio_typography', '.obx-author__bio');
        $this->slider('bio_gap', 'Separación', '.obx-author__bio', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]], true);
        $this->end_controls_section();

        $this->start_controls_section('section_style_button', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('button_text_color', 'Color del texto', '.obx-author__button');
        $this->color('button_background_color', 'Fondo', '.obx-author__button', 'background-color');
        $this->color('button_hover_color', 'Color al pasar el ratón', '.obx-author__button:hover', 'color');
        $this->color('button_hover_background_color', 'Fondo al pasar el ratón', '.obx-author__button:hover', 'background-color');
        $this->color('button_hover_border_color', 'Borde al pasar el ratón', '.obx-author__button:hover', 'border-color');
        $this->typography('button_typography', '.obx-author__button', ['separator'=>'before']);
        $this->slider('button_border_width', 'Grosor del borde', '.obx-author__button', 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>20]]]);
        $this->slider('button_border_radius', 'Radio del borde', '.obx-author__button', 'border-radius: {{SIZE}}{{UNIT}};', ['range'=>['px'=>['max'=>100]]]);
        $this->dimensions('button_text_padding', 'Relleno', '.obx-author__button', 'padding');
        $this->add_control('button_hover_animation', ['label'=>'Animación','type'=>Controls_Manager::HOVER_ANIMATION]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $custom = ($s['source'] ?? 'current') === 'custom';
        if ($custom) {
            $name = (string) ($s['author_name'] ?? '');
            $bio = (string) ($s['author_bio'] ?? '');
            $avatar = !empty($s['author_avatar']['url']) ? '<img src="' . esc_url($s['author_avatar']['url']) . '" alt="' . esc_attr($name) . '" loading="lazy">' : '';
            $url = (string) ($s['author_website']['url'] ?? '');
            $button_url = (string) ($s['posts_url']['url'] ?? '');
            $show_button = $button_url !== '';
        } else {
            $post = Context::post();
            $author = is_author() && !Context::template() ? (int) get_queried_object_id() : ($post ? (int) $post->post_author : 0);
            if (!$author || !get_userdata($author)) { $this->placeholder('No hay autor que mostrar.'); return; }
            $name = ($s['show_name'] ?? 'yes') === 'yes' ? (string) get_the_author_meta('display_name', $author) : '';
            $bio = ($s['show_biography'] ?? 'yes') === 'yes' ? (string) get_the_author_meta('description', $author) : '';
            $avatar = ($s['show_avatar'] ?? 'yes') === 'yes' ? (string) get_avatar($author, 300, '', get_the_author_meta('display_name', $author), ['loading'=>'lazy']) : '';
            $link = (string) ($s['link_to'] ?? '');
            $url = $link === 'posts_archive' ? get_author_posts_url($author) : ($link === 'website' ? (string) get_the_author_meta('url', $author) : '');
            $button_url = get_author_posts_url($author);
            $show_button = ($s['show_link'] ?? '') === 'yes';
        }
        $layout = in_array($s['layout'] ?? '', ['left','above','right'], true) ? $s['layout'] : 'left';
        echo '<div class="obx-author obx-author--' . esc_attr($layout) . '">';
        if ($avatar !== '') { echo '<div class="obx-author__avatar">' . ($url ? '<a href="' . esc_url($url) . '" tabindex="-1" aria-hidden="true">' . $avatar . '</a>' : $avatar) . '</div>'; }
        echo '<div class="obx-author__text">';
        if ($name !== '') {
            $tag = self::pick_tag($s['author_name_tag'] ?? '', 'h4');
            printf('<%1$s class="obx-author__name">%2$s</%1$s>', $tag, $url ? '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a>' : esc_html($name));
        }
        if ($bio !== '') { echo '<div class="obx-author__bio">' . wp_kses_post(wpautop($bio)) . '</div>'; }
        if ($show_button && (string) ($s['link_text'] ?? '') !== '') {
            $animation = ($s['button_hover_animation'] ?? '') ? ' elementor-animation-' . sanitize_html_class($s['button_hover_animation']) : '';
            echo '<a class="obx-author__button elementor-button elementor-size-xs' . esc_attr($animation) . '" href="' . esc_url($button_url) . '">' . esc_html($s['link_text']) . '</a>';
        }
        echo '</div></div>';
    }
}

final class Post_Navigation extends Element {
    public const PRO = 'post-navigation';
    public function get_title(): string { return 'Navegación de entradas'; }
    public function get_icon(): string { return 'eicon-post-navigation'; }
    public function get_keywords(): array { return ['navigation','navegacion','anterior','siguiente','previous','next']; }
    protected function is_dynamic_content(): bool { return true; }
    public function get_script_depends(): array { return []; }
    public static function wants_upgrade(string $name, array $settings): bool { return false; }

    protected function register_controls(): void {
        $this->start_controls_section('section_post_navigation_content', ['label'=>'Navegación']);
        $this->add_control('show_label', ['label'=>'Etiqueta','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('prev_label', ['label'=>'Etiqueta anterior','type'=>Controls_Manager::TEXT,'default'=>'Anterior','condition'=>['show_label'=>'yes']]);
        $this->add_control('next_label', ['label'=>'Etiqueta siguiente','type'=>Controls_Manager::TEXT,'default'=>'Siguiente','condition'=>['show_label'=>'yes']]);
        $this->add_control('show_arrow', ['label'=>'Flechas','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('arrow', ['label'=>'Tipo de flecha','type'=>Controls_Manager::SELECT,'default'=>'fa fa-angle-left','options'=>['fa fa-angle-left'=>'Ángulo','fa fa-angle-double-left'=>'Doble ángulo','fa fa-chevron-left'=>'Galón','fa fa-chevron-circle-left'=>'Galón en círculo','fa fa-caret-left'=>'Triángulo','fa fa-arrow-left'=>'Flecha','fa fa-long-arrow-left'=>'Flecha larga','fa fa-arrow-circle-left'=>'Flecha en círculo','fa fa-arrow-circle-o-left'=>'Flecha en círculo (contorno)'],'condition'=>['show_arrow'=>'yes']]);
        $this->add_control('show_title', ['label'=>'Título de la entrada','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('show_borders', ['label'=>'Separadores','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('in_same_term', ['label'=>'Solo del mismo término','type'=>Controls_Manager::SWITCHER,'default'=>'','separator'=>'before']);
        foreach (get_post_types(['public'=>true], 'objects') as $type) {
            $taxonomies = [];
            foreach (get_object_taxonomies($type->name, 'objects') as $taxonomy) { if ($taxonomy->public && $taxonomy->hierarchical !== null) { $taxonomies[$taxonomy->name] = $taxonomy->label; } }
            if (!$taxonomies) { continue; }
            $this->add_control($type->name . '_taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT,'default'=>(string) array_key_first($taxonomies),'options'=>$taxonomies,'condition'=>['in_same_term'=>'yes']]);
        }
        $this->end_controls_section();

        $this->start_controls_section('label_style', ['label'=>'Etiqueta','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_label'=>'yes']]);
        $this->color('label_color', 'Color', '.obx-postnav__label');
        $this->color('label_hover_color', 'Color al pasar el ratón', '.obx-postnav a:hover .obx-postnav__label');
        $this->typography('label_typography', '.obx-postnav__label');
        $this->end_controls_section();

        $this->start_controls_section('title_style', ['label'=>'Título','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_title'=>'yes']]);
        $this->color('text_color', 'Color', '.obx-postnav__title');
        $this->color('hover_color', 'Color al pasar el ratón', '.obx-postnav a:hover .obx-postnav__title');
        $this->typography('title_typography', '.obx-postnav__title');
        $this->end_controls_section();

        $this->start_controls_section('arrow_style', ['label'=>'Flecha','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_arrow'=>'yes']]);
        $this->color('arrow_color', 'Color', '.obx-postnav__arrow');
        $this->color('arrow_hover_color', 'Color al pasar el ratón', '.obx-postnav a:hover .obx-postnav__arrow');
        $this->slider('arrow_size', 'Tamaño', '.obx-postnav__arrow', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['min'=>6,'max'=>300]]], true);
        $this->slider('arrow_padding', 'Separación', '.obx-postnav', '--obx-arrow-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>50]]], true);
        $this->end_controls_section();

        $this->start_controls_section('borders_section_style', ['label'=>'Separadores','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['show_borders!'=>'']]);
        $this->color('sep_color', 'Color', '.obx-postnav', '--obx-postnav-border');
        $this->slider('borders_width', 'Grosor', '.obx-postnav', '--obx-postnav-border-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'range'=>['px'=>['max'=>10]]]);
        $this->slider('borders_spacing', 'Separación', '.obx-postnav', '--obx-postnav-border-spacing: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]]);
        $this->end_controls_section();
    }

    private function link(?\WP_Post $post, string $dir, array $s): string {
        if (!$post) { return '<span class="obx-postnav__' . $dir . ' is-empty"></span>'; }
        $arrow = '';
        if (($s['show_arrow'] ?? 'yes') === 'yes') {
            $icon = (string) ($s['arrow'] ?? 'fa fa-angle-left');
            if ($dir === 'next') { $icon = str_replace('left', 'right', $icon); }
            $arrow = '<span class="obx-postnav__arrow" aria-hidden="true">' . (Icons_Manager::try_get_icon_html(['value'=>$icon,'library'=>'fa-solid'], ['aria-hidden'=>'true']) ?: ($dir === 'prev' ? '‹' : '›')) . '</span>';
        }
        $text = '';
        if (($s['show_label'] ?? 'yes') === 'yes') { $text .= '<span class="obx-postnav__label">' . esc_html((string) ($s[$dir . '_label'] ?? '')) . '</span>'; }
        if (($s['show_title'] ?? 'yes') === 'yes') { $text .= '<span class="obx-postnav__title">' . esc_html(get_the_title($post)) . '</span>'; }
        $body = '<span class="obx-postnav__text">' . $text . '</span>';
        return '<a class="obx-postnav__' . $dir . '" href="' . esc_url(get_permalink($post)) . '" rel="' . $dir . '">' . ($dir === 'prev' ? $arrow . $body : $body . $arrow) . '</a>';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        if (!$post) { $this->placeholder('No hay ninguna entrada que mostrar.'); return; }
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;
        $same = ($s['in_same_term'] ?? '') === 'yes';
        $taxonomy = (string) ($s[$post->post_type . '_taxonomy'] ?? 'category');
        $taxonomy = taxonomy_exists($taxonomy) ? $taxonomy : 'category';
        $prev = get_adjacent_post($same, '', true, $taxonomy);
        $next = get_adjacent_post($same, '', false, $taxonomy);
        $GLOBALS['post'] = $previous;
        if (!$prev && !$next) { $this->placeholder('Esta entrada no tiene anterior ni siguiente.'); return; }
        $borders = ($s['show_borders'] ?? 'yes') !== '';
        echo '<nav class="obx-postnav' . ($borders ? ' obx-postnav--borders' : '') . '" aria-label="Entradas">' . $this->link($prev ?: null, 'prev', $s) . ($borders ? '<span class="obx-postnav__separator" aria-hidden="true"></span>' : '') . $this->link($next ?: null, 'next', $s) . '</nav>';
    }
}

final class Post_Comments extends Base {
    public const PRO = 'post-comments';
    public const PRO_CATEGORY = 'theme-elements-single';
    public function get_title(): string { return 'Comentarios de la entrada'; }
    public function get_icon(): string { return 'eicon-comments'; }
    public function get_keywords(): array { return ['comments','comentarios','respuestas']; }
    protected function register_controls(): void {
        $this->start_controls_section('section_content', ['label'=>'Comentarios']);
        $this->add_control('_skin', ['label'=>'Apariencia','type'=>Controls_Manager::SELECT,'default'=>'theme_comments','options'=>['theme_comments'=>'Tema'],'description'=>'Usa la plantilla de comentarios del tema y los ajustes de Ajustes → Comentarios.']);
        $this->add_control('source_type', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'current_post','options'=>['current_post'=>'Entrada actual','custom'=>'Otra entrada']]);
        $this->add_control('source_custom', ['label'=>'ID de la entrada','type'=>Controls_Manager::NUMBER,'min'=>1,'condition'=>['source_type'=>'custom']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = ($s['source_type'] ?? 'current_post') === 'custom' && absint($s['source_custom'] ?? 0) ? get_post(absint($s['source_custom'])) : Context::post();
        if (!$post || ($post->post_status !== 'publish' && !current_user_can('read_post', $post->ID))) { $this->placeholder('No hay ninguna entrada que mostrar.'); return; }
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
        $this->add_control('html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Por defecto','p'=>'p','div'=>'div','nav'=>'nav','span'=>'span']]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>['{{WRAPPER}} .obx-breadcrumbs ol'=>'justify-content: {{VALUE}};'],'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end']]);
        $this->end_controls_section();
        $this->text_style('.obx-breadcrumbs', 'Texto', 'text', false);
        $this->start_controls_section('links_style', ['label'=>'Enlaces','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('link_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-breadcrumbs a'=>'color: {{VALUE}};']]);
        $this->add_control('link_hover_color', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-breadcrumbs a:hover'=>'color: {{VALUE}};']]);
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
        $tag = in_array($s['html_tag'] ?? '', ['p','div','nav','span'], true) ? $s['html_tag'] : '';
        // Same source as Elementor Pro's widget when Yoast SEO is installed.
        if (function_exists('yoast_breadcrumb') && apply_filters('obx/breadcrumbs/use_yoast', true)) {
            $tag = $tag ?: 'nav';
            echo '<' . $tag . ' class="obx-breadcrumbs obx-breadcrumbs--yoast">' . yoast_breadcrumb('', '', false) . '</' . $tag . '>';
            return;
        }
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
