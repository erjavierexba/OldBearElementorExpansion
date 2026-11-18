<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
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
    /** Public post types with taxonomies, as offered by Pro's «In same term». */
    private static function term_post_types(): array {
        $types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) {
            $taxonomies = [];
            foreach (get_object_taxonomies($type->name, 'objects') as $taxonomy) { if ($taxonomy->public) { $taxonomies[$taxonomy->name] = $taxonomy->label; } }
            if ($taxonomies) { $types[$type->name] = ['label'=>$type->label,'taxonomies'=>$taxonomies]; }
        }
        return $types;
    }

    /** Earlier versions: «arrow» select (one class for both sides) and «in_same_term» as a switcher. */
    public static function wants_upgrade(string $name, array $settings): bool {
        return $name === static::PRO && (isset($settings['arrow']) || ($settings['in_same_term'] ?? null) === 'yes');
    }
    protected static function upgrade_settings(array $s): array {
        if (isset($s['arrow'])) {
            $icon = str_replace('fa fa-', 'fas fa-', (string) $s['arrow']);
            if ($icon !== 'fas fa-angle-left') {
                $s['arrow_previous_icon'] ??= ['value'=>$icon,'library'=>'fa-solid'];
                $s['arrow_next_icon'] ??= ['value'=>str_replace('left', 'right', $icon),'library'=>'fa-solid'];
            }
            unset($s['arrow']);
        }
        if (($s['in_same_term'] ?? null) === 'yes') { $s['in_same_term'] = array_keys(self::term_post_types()); }
        return $s;
    }

    /** Normal / hover tabs with the hover transition, as in Pro's three style sections. */
    private function state_tabs(string $tabs, string $color, string $hover, string $selector, string $hover_selector, array $condition): void {
        $this->start_controls_tabs($tabs, ['condition'=>$condition]);
        $this->start_controls_tab($tabs . '_normal', ['label'=>'Normal','condition'=>$condition]);
        $this->color($color, 'Color', $selector, 'color', ['condition'=>$condition]);
        $this->end_controls_tab();
        $this->start_controls_tab($tabs . '_hover', ['label'=>'Al pasar el ratón','condition'=>$condition]);
        $this->color($hover, 'Color', $hover_selector, 'color', ['condition'=>$condition]);
        $this->slider($hover . '_transition_duration', 'Duración de la transición', $selector, 'transition-duration: {{SIZE}}{{UNIT}};', ['size_units'=>['s','ms','custom'],'default'=>['unit'=>'ms'],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>100]],'condition'=>$condition]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_post_navigation_content', ['label'=>'Navegación']);
        $this->add_control('show_label', ['label'=>'Etiqueta','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('prev_label', ['label'=>'Etiqueta anterior','type'=>Controls_Manager::TEXT,'default'=>'Anterior','dynamic'=>['active'=>true],'condition'=>['show_label'=>'yes']]);
        $this->add_control('next_label', ['label'=>'Etiqueta siguiente','type'=>Controls_Manager::TEXT,'default'=>'Siguiente','dynamic'=>['active'=>true],'condition'=>['show_label'=>'yes']]);
        $this->add_control('show_arrow', ['label'=>'Flechas','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('arrow_previous_icon', ['label'=>'Flecha anterior','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-angle-left','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'recommended'=>['fa-solid'=>['angle-left','angle-double-left','chevron-left','chevron-circle-left','caret-left','arrow-left','long-arrow-alt-left','arrow-circle-left','arrow-alt-circle-left']],'condition'=>['show_arrow'=>'yes']]);
        $this->add_control('arrow_next_icon', ['label'=>'Flecha siguiente','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-angle-right','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'recommended'=>['fa-solid'=>['angle-right','angle-double-right','chevron-right','chevron-circle-right','caret-right','arrow-right','long-arrow-alt-right','arrow-circle-right','arrow-alt-circle-right']],'condition'=>['show_arrow'=>'yes']]);
        $this->add_control('show_title', ['label'=>'Título de la entrada','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $this->add_control('show_borders', ['label'=>'Separadores','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before']);
        $types = self::term_post_types();
        $this->add_control('in_same_term', ['label'=>'Solo del mismo término en','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>array_map(static fn(array $t): string => $t['label'], $types),'separator'=>'before','description'=>'Muestra solo entradas que comparten un término de la taxonomía elegida.']);
        foreach ($types as $name => $type) {
            $this->add_control($name . '_taxonomy', ['label'=>'Taxonomía de ' . $type['label'],'type'=>Controls_Manager::SELECT,'default'=>(string) array_key_first($type['taxonomies']),'options'=>$type['taxonomies'],'condition'=>['in_same_term'=>$name]]);
        }
        $this->end_controls_section();

        $label = ['show_label'=>'yes'];
        $this->start_controls_section('label_style', ['label'=>'Etiqueta','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$label]);
        $this->state_tabs('tabs_label_style', 'label_color', 'label_hover_color', '.obx-postnav__label', '.obx-postnav a:hover .obx-postnav__label', $label);
        $this->typography('label_typography', '.obx-postnav__label', ['condition'=>$label,'exclude'=>['line_height'],'separator'=>'before']);
        $this->end_controls_section();

        $title = ['show_title'=>'yes'];
        $this->start_controls_section('title_style', ['label'=>'Título','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$title]);
        $this->state_tabs('tabs_post_navigation_style', 'text_color', 'hover_color', '.obx-postnav__title', '.obx-postnav a:hover .obx-postnav__title', $title);
        $this->typography('title_typography', '.obx-postnav__title', ['condition'=>$title,'exclude'=>['line_height'],'separator'=>'before']);
        $this->end_controls_section();

        $arrow = ['show_arrow'=>'yes'];
        $this->start_controls_section('arrow_style', ['label'=>'Flecha','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$arrow]);
        $this->state_tabs('tabs_post_navigation_arrow_style', 'arrow_color', 'arrow_hover_color', '.obx-postnav__arrow', '.obx-postnav a:hover .obx-postnav__arrow', $arrow);
        $this->slider('arrow_size', 'Tamaño', '.obx-postnav__arrow', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>6,'max'=>300]],'condition'=>$arrow,'separator'=>'before'], true);
        $this->slider('arrow_padding', 'Separación', '.obx-postnav', '--obx-arrow-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'condition'=>$arrow], true);
        $this->end_controls_section();

        $borders = ['show_borders!'=>''];
        $this->start_controls_section('borders_section_style', ['label'=>'Separadores','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$borders]);
        $this->color('sep_color', 'Color', '.obx-postnav', '--obx-postnav-border', ['condition'=>$borders]);
        $this->slider('borders_width', 'Grosor', '.obx-postnav', '--obx-postnav-border-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'range'=>['px'=>['max'=>10]],'condition'=>$borders]);
        $this->slider('borders_spacing', 'Separación', '.obx-postnav', '--obx-postnav-border-spacing: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100]],'condition'=>$borders]);
        $this->end_controls_section();
    }

    private function link(?\WP_Post $post, string $dir, array $s): string {
        if (!$post) { return '<span class="obx-postnav__' . $dir . ' is-empty"></span>'; }
        $arrow = '';
        if (($s['show_arrow'] ?? 'yes') === 'yes') {
            $icon = $s[$dir === 'prev' ? 'arrow_previous_icon' : 'arrow_next_icon'] ?? [];
            $html = is_array($icon) && !empty($icon['value']) ? Icons_Manager::try_get_icon_html($icon, ['aria-hidden'=>'true']) : '';
            $arrow = '<span class="obx-postnav__arrow" aria-hidden="true">' . ($html ?: ($dir === 'prev' ? '‹' : '›')) . '</span>';
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
        $same_in = $s['in_same_term'] ?? [];
        $same = $same_in === 'yes' || in_array($post->post_type, (array) $same_in, true);
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
    private const TAGS = ['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span','p'=>'p'];
    public function get_title(): string { return 'Mapa del sitio'; }
    public function get_icon(): string { return 'eicon-sitemap'; }
    public function get_keywords(): array { return ['sitemap','mapa','indice','índice','paginas','páginas']; }

    public static function wants_upgrade(string $name, array $settings): bool { return $name === self::PRO && (bool) array_intersect_key($settings, array_flip(['sources','columns','show_titles','limit'])); }

    protected static function upgrade_settings(array $s): array {
        if (array_key_exists('sources', $s) && !isset($s['sitemap_items'])) {
            $rows = [];
            foreach ((array) $s['sources'] as $source) {
                $source = (string) $source;
                $tax = str_starts_with($source, 'tax:') ? substr($source, 4) : '';
                $object = $tax !== '' ? get_taxonomy($tax) : get_post_type_object($source);
                $title = ($s['show_titles'] ?? 'yes') === 'yes' && $object ? (string) $object->labels->name : '';
                $rows[] = ['_id'=>substr(md5($source), 0, 7),'sitemap_type_selector'=>$tax !== '' ? 'taxonomy' : 'post_type','sitemap_title'=>$title] + ($tax !== '' ? ['sitemap_source_taxonomy'=>$tax] : ['sitemap_source_post_type'=>$source]);
            }
            $s['sitemap_items'] = $rows;
        } elseif (!array_key_exists('sources', $s) && ($s['show_titles'] ?? 'yes') !== 'yes') {
            $s['sitemap_items'] = [['_id'=>'pages01','sitemap_type_selector'=>'post_type','sitemap_source_post_type'=>'page','sitemap_title'=>''],['_id'=>'posts01','sitemap_type_selector'=>'post_type','sitemap_source_post_type'=>'post','sitemap_title'=>'']];
        } elseif (!array_key_exists('sources', $s)) {
            // Before 0.11.0 the default listed pages and posts.
            $s['sitemap_items'] = [['_id'=>'pages01','sitemap_type_selector'=>'post_type','sitemap_source_post_type'=>'page','sitemap_title'=>'Páginas'],['_id'=>'posts01','sitemap_type_selector'=>'post_type','sitemap_source_post_type'=>'post','sitemap_title'=>'Entradas']];
        }
        $s = self::rename($s, ['columns'=>'sitemap_columns','columns_tablet'=>'sitemap_columns_tablet','columns_mobile'=>'sitemap_columns_mobile']);
        unset($s['sources'], $s['show_titles'], $s['limit']);
        return $s;
    }

    protected function register_controls(): void {
        $post_types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) { if ($type->name !== 'attachment') { $post_types[$type->name] = $type->labels->name; } }
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true], 'objects') as $tax) { $taxonomies[$tax->name] = $tax->labels->name; }

        $this->start_controls_section('sitemap_section', ['label'=>'Mapa del sitio']);
        $r = new Repeater();
        $r->add_control('sitemap_type_selector', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'post_type','options'=>['post_type'=>'Tipo de contenido','taxonomy'=>'Taxonomía']]);
        $r->add_control('sitemap_source_post_type', ['label'=>'Fuente','type'=>Controls_Manager::SELECT,'default'=>'page','options'=>$post_types,'condition'=>['sitemap_type_selector'=>'post_type']]);
        $r->add_control('sitemap_source_taxonomy', ['label'=>'Fuente','type'=>Controls_Manager::SELECT,'default'=>'category','options'=>$taxonomies,'condition'=>['sitemap_type_selector'=>'taxonomy']]);
        $r->add_control('sitemap_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true]]);
        $r->add_control('sitemap_orderby_post_type', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'post_date','options'=>['post_date'=>'Fecha','post_title'=>'Título','menu_order'=>'Orden del menú','rand'=>'Aleatorio'],'condition'=>['sitemap_type_selector'=>'post_type']]);
        $r->add_control('sitemap_orderby_taxonomy', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'name','options'=>['id'=>'ID','name'=>'Nombre'],'condition'=>['sitemap_type_selector'=>'taxonomy']]);
        $r->add_control('sitemap_order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente']]);
        $r->add_control('sitemap_hide_empty', ['label'=>'Ocultar vacías','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['sitemap_type_selector'=>'taxonomy']]);
        $r->add_control('sitemap_hierarchical', ['label'=>'Jerárquico','type'=>Controls_Manager::SWITCHER,'default'=>'no']);
        $r->add_control('sitemap_depth', ['label'=>'Profundidad','type'=>Controls_Manager::SELECT,'default'=>'0','options'=>['0'=>'Todos','1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6'],'condition'=>['sitemap_hierarchical'=>'yes']]);
        $this->add_control('sitemap_items', ['label'=>'Elementos del mapa','type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[
            ['sitemap_type_selector'=>'post_type','sitemap_title'=>'Páginas','sitemap_source_post_type'=>'page'],
            ['sitemap_type_selector'=>'taxonomy','sitemap_title'=>'Categorías','sitemap_source_taxonomy'=>'category'],
        ],'title_field'=>'{{{ sitemap_title }}}']);
        $columns = array_combine(array_map('strval', range(1, 6)), array_map('strval', range(1, 6)));
        $this->add_responsive_control('sitemap_columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'default'=>'4','tablet_default'=>'2','mobile_default'=>'1','options'=>$columns,'selectors'=>['{{WRAPPER}} .obx-sitemap__section'=>'flex-basis: calc(100% / {{VALUE}});'],'separator'=>'before']);
        $this->add_control('sitemap_title_tag', ['label'=>'Etiqueta HTML del título','type'=>Controls_Manager::SELECT,'default'=>'h2','options'=>self::TAGS]);
        $this->add_control('sitemap_add_nofollow', ['label'=>'Añadir nofollow','type'=>Controls_Manager::SWITCHER]);
        $this->end_controls_section();

        $posts = [];
        foreach (get_posts(['post_type'=>array_keys($post_types),'numberposts'=>200,'post_status'=>'publish']) as $post) { $posts[(string) $post->ID] = get_the_title($post) ?: '#' . $post->ID; }
        $this->start_controls_section('sitemap_query_section', ['label'=>'Consulta']);
        $this->add_control('sitemap_exclude', ['label'=>'Excluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['current_post'=>'Entrada actual','manual_selection'=>'Selección manual']]);
        $this->add_control('sitemap_exclude_ids', ['label'=>'Buscar y seleccionar','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>['sitemap_exclude'=>'manual_selection']]);
        $this->add_control('sitemap_password_protected', ['label'=>'Incluir protegidas con contraseña','type'=>Controls_Manager::SWITCHER]);
        $this->end_controls_section();

        $w = '{{WRAPPER}} ';
        $this->start_controls_section('section_sitemap_style', ['label'=>'Lista','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('sitemap_list_indent', ['label'=>'Sangría','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>[$w . '.obx-sitemap__list, ' . $w . '.obx-sitemap__list .children'=>'padding-inline-start: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('sitemap_section_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>[$w . '.obx-sitemap__section'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('heading_sitemap_title', ['label'=>'Título','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('sitemap_title_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$w . '.obx-sitemap__title'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'sitemap_title_typography','selector'=>$w . '.obx-sitemap__title']);
        $this->add_control('heading_sitemap_list_item', ['label'=>'Elementos','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('sitemap_list_item_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$w . '.obx-sitemap__item > a'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'sitemap_list_item_typography','selector'=>$w . '.obx-sitemap__item, ' . $w . '.obx-sitemap__item > a']);
        $this->add_control('heading_sitemap_bullet', ['label'=>'Viñeta','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('sitemap_bullet_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$w . '.obx-sitemap__item'=>'color: {{VALUE}};']]);
        $this->add_control('sitemap_list_item_bullet_style', ['label'=>'Estilo','type'=>Controls_Manager::CHOOSE,'default'=>'disc','options'=>['disc'=>['title'=>'Disco','icon'=>'eicon-circle'],'circle'=>['title'=>'Círculo','icon'=>'eicon-circle-o'],'square'=>['title'=>'Cuadrado','icon'=>'eicon-square'],'none'=>['title'=>'Ninguno','icon'=>'eicon-ban']],'toggle'=>false,'selectors'=>[$w . '.obx-sitemap__list, ' . $w . '.obx-sitemap__list .children'=>'list-style-type: {{VALUE}};']]);
        $this->end_controls_section();
    }

    /** IDs left out of every list: the current post and/or a manual selection. */
    private static function excluded(array $s): array {
        $exclude = (array) ($s['sitemap_exclude'] ?? []);
        $ids = in_array('manual_selection', $exclude, true) ? array_map('intval', (array) ($s['sitemap_exclude_ids'] ?? [])) : [];
        if (in_array('current_post', $exclude, true) && get_queried_object_id()) { $ids[] = (int) get_queried_object_id(); }
        return array_values(array_filter(array_unique($ids)));
    }

    /** <li> items for a branch of $items (objects with ID/term_id and a parent), down to $depth (0 = no limit). */
    private function branch(array $by_parent, int $parent, string $kind, int $level, int $depth, string $rel): string {
        $html = '';
        foreach ($by_parent[$parent] ?? [] as $item) {
            $id = $kind === 'post' ? (int) $item->ID : (int) $item->term_id;
            $url = $kind === 'post' ? get_permalink($item) : get_term_link($item);
            if (is_wp_error($url) || !$url) { continue; }
            $label = $kind === 'post' ? (get_the_title($item) ?: '#' . $id) : $item->name;
            $html .= '<li class="obx-sitemap__item obx-sitemap__item--' . esc_attr($kind === 'post' ? $item->post_type : $item->taxonomy) . '"><a href="' . esc_url($url) . '"' . $rel . '>' . esc_html($label) . '</a>';
            if (($depth === 0 || $level < $depth) && !empty($by_parent[$id])) { $html .= '<ul class="children">' . $this->branch($by_parent, $id, $kind, $level + 1, $depth, $rel) . '</ul>'; }
            $html .= '</li>';
        }
        return $html;
    }

    private function section(array $row, array $s, array $exclude): string {
        $rel = ($s['sitemap_add_nofollow'] ?? '') === 'yes' ? ' rel="nofollow"' : '';
        $hierarchical = ($row['sitemap_hierarchical'] ?? 'no') === 'yes';
        $depth = max(0, (int) ($row['sitemap_depth'] ?? 0));
        $order = strtolower((string) ($row['sitemap_order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        if (($row['sitemap_type_selector'] ?? 'post_type') === 'taxonomy') {
            $tax = get_taxonomy((string) ($row['sitemap_source_taxonomy'] ?? 'category'));
            if (!$tax || !$tax->public) { return ''; }
            $terms = get_terms(['taxonomy'=>$tax->name,'hide_empty'=>($row['sitemap_hide_empty'] ?? 'yes') === 'yes','orderby'=>($row['sitemap_orderby_taxonomy'] ?? 'name') === 'id' ? 'term_id' : 'name','order'=>$order]);
            if (is_wp_error($terms) || !$terms) { return ''; }
            $items = $terms; $kind = 'term'; $class = $tax->name; $parent_of = static fn($t) => $hierarchical ? (int) $t->parent : 0;
        } else {
            $type = get_post_type_object((string) ($row['sitemap_source_post_type'] ?? 'page'));
            if (!$type || !$type->public) { return ''; }
            $orderby = in_array($row['sitemap_orderby_post_type'] ?? 'post_date', ['post_date','post_title','menu_order','rand'], true) ? (string) ($row['sitemap_orderby_post_type'] ?? 'post_date') : 'post_date';
            $args = ['post_type'=>$type->name,'post_status'=>'publish','posts_per_page'=>-1,'orderby'=>$orderby,'order'=>$order,'post__not_in'=>$exclude,'ignore_sticky_posts'=>true,'no_found_rows'=>true];
            if (($s['sitemap_password_protected'] ?? '') !== 'yes') { $args['has_password'] = false; }
            $items = get_posts($args);
            if (!$items) { return ''; }
            $kind = 'post'; $class = $type->name;
            $present = array_flip(wp_list_pluck($items, 'ID'));
            // A child whose parent is not listed hangs from the top level.
            $parent_of = static fn($p) => $hierarchical && $type->hierarchical && isset($present[(int) $p->post_parent]) ? (int) $p->post_parent : 0;
        }
        $by_parent = [];
        foreach ($items as $item) { $by_parent[$parent_of($item)][] = $item; }
        $list = $this->branch($by_parent, 0, $kind, 1, $hierarchical ? $depth : 1, $rel);
        if ($list === '') { return ''; }
        $tag = array_key_exists((string) ($s['sitemap_title_tag'] ?? 'h2'), self::TAGS) ? (string) ($s['sitemap_title_tag'] ?? 'h2') : 'h2';
        $title = trim((string) ($row['sitemap_title'] ?? ''));
        return '<div class="obx-sitemap__section obx-sitemap__section--' . esc_attr($class) . '">' . ($title !== '' ? '<' . $tag . ' class="obx-sitemap__title">' . esc_html($title) . '</' . $tag . '>' : '') . '<ul class="obx-sitemap__list">' . $list . '</ul></div>';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $exclude = self::excluded($s);
        $html = '';
        foreach ((array) ($s['sitemap_items'] ?? []) as $row) { if (is_array($row)) { $html .= $this->section($row, $s, $exclude); } }
        if ($html === '') { if (Context::is_editor()) { echo '<div class="obx-placeholder">El mapa del sitio no tiene elementos.</div>'; } return; }
        echo '<div class="obx-sitemap">' . $html . '</div>';
    }
}
