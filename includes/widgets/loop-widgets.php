<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Icons_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/**
 * Loop Grid and Loop Carousel: every post of the query is rendered with a «loop-item» template
 * (Elementor Pro's template type), so theme widgets and dynamic tags inside it show that post.
 */
trait Loop_Renderer {
    /** Loop item templates currently being printed, to stop a template from containing itself. */
    private static array $printing = [];

    /** Taxonomy filters (taxonomy-filter widget) selected in the URL for this widget: taxonomy => slugs. */
    protected function url_filters(): array {
        $filters = [];
        $prefix = 'e-filter-' . $this->get_id() . '-';
        foreach ($_GET as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only filter.
            if (!is_string($key) || !str_starts_with($key, $prefix) || !is_string($value)) { continue; }
            $taxonomy = sanitize_key(substr($key, strlen($prefix)));
            $slugs = array_values(array_filter(array_map('sanitize_title', explode(',', wp_unslash($value)))));
            if ($slugs && taxonomy_exists($taxonomy)) { $filters[$taxonomy] = $slugs; }
        }
        return $filters;
    }

    /** Loop item templates for the template_id select. */
    protected static function loop_templates(): array {
        $options = [''=>'Elige una plantilla'];
        foreach (get_posts(['post_type'=>'elementor_library','posts_per_page'=>200,'post_status'=>['publish','draft','private'],'meta_key'=>'_elementor_template_type','meta_value'=>'loop-item']) as $post) {
            $options[(string) $post->ID] = $post->post_title ?: '#' . $post->ID;
        }
        return $options;
    }

    protected function template_control(): void {
        $this->add_control('template_id', ['label'=>'Plantilla del elemento','type'=>Controls_Manager::SELECT,'options'=>self::loop_templates(),'default'=>'','label_block'=>true,
            'description'=>'Crea plantillas de tipo «Elemento de bucle» en Plantillas → Theme Builder.','frontend_available'=>true]);
    }

    /** HTML of one loop item for $post. */
    protected function loop_item(int $template_id, \WP_Post $post, bool $with_css): string {
        if (!$template_id || isset(self::$printing[$template_id]) || get_post_type($template_id) !== 'elementor_library') { return ''; }
        global $wp_query;
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- restored below.
        setup_postdata($post);
        self::$printing[$template_id] = true;
        // The template shows the looped post, also inside the editor preview.
        $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($template_id, $with_css);
        unset(self::$printing[$template_id]);
        $GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        if ($previous instanceof \WP_Post) { setup_postdata($previous); } elseif ($wp_query instanceof \WP_Query) { $wp_query->reset_postdata(); }
        return '<div class="e-loop-item obx-loop__item ' . esc_attr(implode(' ', get_post_class('', $post))) . '" data-elementor-type="loop-item" data-post-id="' . esc_attr((string) $post->ID) . '">' . $html . '</div>';
    }

    /** HTML of one loop item for a term (term loops): theme widgets and archive tags show $term. */
    protected function loop_term_item(int $template_id, \WP_Term $term, bool $with_css): string {
        if (!$template_id || isset(self::$printing[$template_id]) || get_post_type($template_id) !== 'elementor_library') { return ''; }
        self::$printing[$template_id] = true;
        Context::push_term($term);
        try { $html = (string) Elementor::$instance->frontend->get_builder_content_for_display($template_id, $with_css); }
        finally { Context::pop_term(); unset(self::$printing[$template_id]); }
        return '<div class="e-loop-item obx-loop__item e-loop-item-' . esc_attr((string) $term->term_id) . ' ' . esc_attr(sanitize_html_class($term->taxonomy . '-' . $term->slug)) . '" data-elementor-type="loop-item" data-term-id="' . esc_attr((string) $term->term_id) . '">' . $html . '</div>';
    }

    /**
     * Settings + query for this widget, with taxonomy-filter selections applied.
     * $p is the query prefix (post_query_ or product_query_); products use Query::products().
     */
    protected function loop_query(array $s, int $per_page, int $paged, string $p = 'post_query_'): \WP_Query {
        $build = static fn() => $p === 'product_query_' ? Query::products($s, $p, $per_page, $paged, null) : Query::build($s, $p, $per_page, $paged, null);
        $filters = $this->url_filters();
        if ($filters && ($s[$p . 'post_type'] ?? '') !== 'current_query') {
            $hook = static function (\WP_Query $q) use ($filters, &$hook) {
                $tax = (array) $q->get('tax_query');
                foreach ($filters as $taxonomy => $slugs) { $tax[] = ['taxonomy'=>$taxonomy,'field'=>'slug','terms'=>$slugs,'operator'=>'IN']; }
                $q->set('tax_query', ['relation'=>'AND'] + $tax);
                remove_action('pre_get_posts', $hook);
            };
            add_action('pre_get_posts', $hook);
            $query = $build();
            remove_action('pre_get_posts', $hook);
            return $query;
        }
        if ($filters) {
            $tax = [];
            foreach ($filters as $taxonomy => $slugs) { $tax[] = ['taxonomy'=>$taxonomy,'field'=>'slug','terms'=>$slugs]; }
            $current = Context::query();
            return new \WP_Query(array_merge($current->query_vars, ['tax_query'=>['relation'=>'AND'] + $tax,'paged'=>$paged]));
        }
        return $build();
    }

    protected function no_template_notice(): void {
        $this->placeholder('Elige una plantilla de «Elemento de bucle» en los ajustes del widget.');
    }
}

final class Loop_Grid extends Element {
    use Loop_Renderer;
    use Paginates;
    use \Elementor\Includes\Widgets\Traits\Button_Trait;
    public const PRO = 'loop-grid';
    /** Pro's `_skin`: what the grid loops over. */
    public const SKINS = ['post'=>'Entradas','product'=>'Productos','post_taxonomy'=>'Términos de entradas','product_taxonomy'=>'Términos de productos'];
    private const TERM_SKINS = ['post_taxonomy','product_taxonomy'];

    public function get_title(): string { return 'Rejilla de bucle'; }
    public function get_icon(): string { return 'eicon-loop-builder'; }
    public function get_keywords(): array { return ['loop','bucle','grid','rejilla','query','consulta','posts','entradas','listing','productos','términos']; }
    protected function is_dynamic_content(): bool { return true; }
    /** Rendered on the server: Button_Trait brings a JS template meant for the Button widget. */
    protected function content_template() {}

    public function skin(): string {
        $skin = (string) $this->get_settings('_skin');
        return isset(self::SKINS[$skin]) ? $skin : 'post';
    }

    /** Query prefix of the post skins. */
    private function prefix(): string { return $this->skin() === 'product' ? 'product_query_' : 'post_query_'; }

    /** Own page parameter (e-page-ID) for AJAX pages, «Load more» and «Individual pagination»; else WordPress's. */
    protected function page_arg(): ?string {
        $s = $this->get_settings();
        if (($s[$this->prefix() . 'post_type'] ?? '') === 'current_query') { return null; }
        $type = (string) ($s['pagination_type'] ?? '');
        if (str_starts_with($type, 'load_more') || ($s['pagination_load_type'] ?? '') === 'ajax' || ($s['pagination_individual_handle'] ?? '') === 'yes') { return 'e-page-' . $this->get_id(); }
        return null;
    }

    /** @return array<string,string> taxonomies for a term skin. */
    public static function skin_taxonomies(string $skin): array {
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true], 'objects') as $taxonomy) {
            $product = in_array('product', (array) $taxonomy->object_type, true);
            if ($taxonomy->name === 'product_visibility' || $taxonomy->name === 'product_shipping_class') { continue; }
            if ($skin === 'product_taxonomy' ? $product : !$product) { $taxonomies[$taxonomy->name] = $taxonomy->labels->name; }
        }
        return $taxonomies;
    }

    protected function register_controls(): void {
        $has_template = ['template_id!'=>''];
        $not_terms = ['_skin!'=>self::TERM_SKINS];
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $skins = self::SKINS;
        if (!post_type_exists('product')) { unset($skins['product'], $skins['product_taxonomy']); }
        $this->add_control('_skin', ['label'=>'Mostrar','type'=>Controls_Manager::SELECT,'default'=>'post','options'=>$skins,'label_block'=>true]);
        $this->template_control();
        $this->add_control('edit_handle_selector', ['type'=>Controls_Manager::HIDDEN,'default'=>'[data-elementor-type="loop-item"]']);
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::NUMBER,'default'=>3,'tablet_default'=>2,'mobile_default'=>1,'min'=>1,'max'=>12,'selectors'=>self::css('.obx-loop', '--obx-columns: {{VALUE}};'),'separator'=>'before','condition'=>$has_template]);
        $this->add_control('posts_per_page', ['label'=>'Elementos por página','type'=>Controls_Manager::NUMBER,'default'=>6,'min'=>1,'max'=>100,'condition'=>$has_template]);
        $this->add_control('masonry', ['label'=>'Mampostería','type'=>Controls_Manager::SWITCHER,'condition'=>['columns!'=>1] + $has_template]);
        $this->add_control('equal_height', ['label'=>'Misma altura','type'=>Controls_Manager::SWITCHER,'condition'=>['columns!'=>1,'masonry'=>''] + $has_template,'selectors'=>self::css('.obx-loop > .e-loop-item > .elementor', 'height: 100%;')]);
        $r = new Repeater();
        $r->add_control('template_id', ['label'=>'Plantilla','type'=>Controls_Manager::SELECT,'options'=>self::loop_templates(),'default'=>'','label_block'=>true]);
        $r->add_control('repeat_template', ['label'=>'Posición en la rejilla','type'=>Controls_Manager::NUMBER,'min'=>1,'default'=>2]);
        $r->add_control('repeat_template_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Se usa cada N elementos, o solo en esa posición si se muestra una vez.','content_classes'=>'elementor-descriptor','condition'=>['template_id!'=>'']]);
        $r->add_control('show_once', ['label'=>'Mostrar una sola vez','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $r->add_control('column_span', ['label'=>'Columnas que ocupa','type'=>Controls_Manager::SELECT,'default'=>'1','options'=>array_combine(array_map('strval', range(1, 12)), array_map('strval', range(1, 12)))]);
        $r->add_control('column_span_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Si ocupa más columnas de las que tiene la rejilla, se ajusta al ancho disponible.','content_classes'=>'elementor-descriptor','condition'=>['template_id!'=>'']]);
        $r->add_control('column_span_masonry_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Con mampostería, ocupar varias columnas puede dejar huecos.','content_classes'=>'elementor-panel-alert elementor-panel-alert-warning','condition'=>['column_span!'=>'1']]);
        $r->add_control('static_position', ['label'=>'Posición fija','type'=>Controls_Manager::SWITCHER,'condition'=>['template_id!'=>'']]);
        $r->add_control('static_position_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Se inserta en esa posición sin ocupar el lugar de ninguna entrada: sirve para anuncios o llamadas a la acción.','content_classes'=>'elementor-descriptor','condition'=>['static_position!'=>'','template_id!'=>'']]);
        $this->add_control('alternate_template', ['label'=>'Plantillas alternativas','type'=>Controls_Manager::SWITCHER,'separator'=>'before','condition'=>['posts_per_page!'=>1] + $has_template + $not_terms]);
        $this->add_control('alternate_templates', ['label'=>'Plantillas','type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[],'title_field'=>'#{{{ repeat_template }}}','condition'=>['alternate_template'=>'yes'] + $not_terms]);
        $this->end_controls_section();

        Query::controls($this, 'post_query_', 'section_query', ['_skin'=>'post'], false);
        Query::product_controls($this, 'product_query_', ['_skin'=>'product']);
        Query::term_controls($this, 'post_taxonomy_', self::skin_taxonomies('post_taxonomy'), 'category', ['_skin'=>'post_taxonomy']);
        Query::term_controls($this, 'product_taxonomy_', self::skin_taxonomies('product_taxonomy'), 'product_cat', ['_skin'=>'product_taxonomy']);
        $this->end_controls_section();

        $this->pagination_controls(false, $not_terms, true);

        $this->start_controls_section('section_additional_options', ['label'=>'Sin resultados','condition'=>$has_template]);
        // Pro data key; every control must live inside a section or Elementor rejects the widgets config.
        $this->add_control('pagination_individual_divider', ['type'=>Controls_Manager::HIDDEN]);
        $this->add_control('enable_nothing_found_message', ['label'=>'Mostrar un mensaje si no hay resultados','type'=>Controls_Manager::SWITCHER]);
        $on = ['enable_nothing_found_message'=>'yes'];
        $this->add_control('nothing_found_message_text', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>'Parece que no encontramos lo que buscas.','condition'=>$on]);
        $this->add_responsive_control('nothing_found_message_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::flex_align_options(),'selectors_dictionary'=>['start'=>'start','center'=>'center','end'=>'end'],'selectors'=>['{{WRAPPER}} .e-loop-nothing-found-message'=>'text-align: {{VALUE}};'],'condition'=>$on]);
        $this->add_control('nothing_found_message_html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>array_combine(['h1','h2','h3','h4','h5','h6','div','span'], ['H1','H2','H3','H4','H5','H6','div','span']),'condition'=>$on]);
        $this->end_controls_section();

        $this->start_controls_section('section_design_layout', ['label'=>'Diseño','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('column_gap', 'Separación entre columnas', '.obx-loop', '--obx-column-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','%','vw'],'default'=>['size'=>20,'unit'=>'px'],'range'=>['px'=>['max'=>100]]], true);
        $this->slider('row_gap', 'Separación entre filas', '.obx-loop', '--obx-row-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vh'],'default'=>['size'=>20,'unit'=>'px'],'range'=>['px'=>['max'=>100]]], true);
        $this->end_controls_section();

        $this->pagination_style();
        $this->load_more_style(true);

        $this->start_controls_section('section_nothing_found_message_design', ['label'=>'Sin resultados','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$has_template + $on]);
        $this->slider('nothing_found_message_space_from_top', 'Espacio superior', '.e-loop-nothing-found-message', 'margin-top: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vh'],'range'=>['px'=>['max'=>200]]], true);
        $this->slider('nothing_found_message_space_from_bottom', 'Espacio inferior', '.e-loop-nothing-found-message', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vh'],'range'=>['px'=>['max'=>200]]], true);
        $this->typography('nothing_found_message_typography', '.e-loop-nothing-found-message');
        $this->color('nothing_found_message_color', 'Color', '.e-loop-nothing-found-message');
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'nothing_found_message_text_shadow','selector'=>'{{WRAPPER}} .e-loop-nothing-found-message']);
        $this->add_group_control(\Elementor\Group_Control_Text_Stroke::get_type(), ['name'=>'nothing_found_message_text_stroke','selector'=>'{{WRAPPER}} .e-loop-nothing-found-message']);
        $this->end_controls_section();
    }

    /** Alternate template rows that apply: [template, column span, static]. */
    private static function alternates(array $s): array {
        if (($s['alternate_template'] ?? '') !== 'yes') { return []; }
        $rows = [];
        foreach ((array) ($s['alternate_templates'] ?? []) as $row) {
            $id = (int) ($row['template_id'] ?? 0);
            if ($id) { $rows[] = ['id'=>$id,'position'=>max(1, (int) ($row['repeat_template'] ?? 0)),'once'=>($row['show_once'] ?? 'yes') === 'yes','span'=>max(1, (int) ($row['column_span'] ?? 1)),'static'=>($row['static_position'] ?? '') === 'yes']; }
        }
        return $rows;
    }

    /** Template for the item at 1-based position $n. */
    private static function template_for(array $rows, int $n, int $default, bool $static): array {
        foreach ($rows as $row) {
            if ($row['static'] !== $static) { continue; }
            if ($row['once'] ? $n === $row['position'] : $n % $row['position'] === 0) { return [$row['id'], $row['span']]; }
        }
        return [$static ? 0 : $default, 1];
    }

    private function nothing_found(array $s): void {
        $raw = $this->get_data('settings');
        // Before 0.9.0 the message had no switch: an explicit message still shows.
        $enabled = ($s['enable_nothing_found_message'] ?? '') === 'yes' || (!isset($raw['enable_nothing_found_message']) && !empty($raw['nothing_found_message_text']));
        $message = (string) ($s['nothing_found_message_text'] ?? '');
        $tag = (string) ($s['nothing_found_message_html_tag'] ?? 'div');
        $tag = in_array($tag, ['h1','h2','h3','h4','h5','h6','div','span'], true) ? $tag : 'div';
        echo '<div class="obx-loop-container">' . ($enabled && $message !== '' ? '<' . $tag . ' class="e-loop-nothing-found-message">' . wp_kses_post($message) . '</' . $tag . '>' : '') . '</div>';
    }

    private static function with_span(string $item, int $span): string {
        return $span > 1 ? preg_replace('/^<div class="e-loop-item /', '<div style="grid-column: span ' . $span . '" class="e-loop-item ', $item, 1) : $item;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $template = (int) ($s['template_id'] ?? 0);
        if (!$template) { $this->no_template_notice(); return; }
        $per_page = max(1, min(100, (int) ($s['posts_per_page'] ?? 6) ?: 6));
        $skin = $this->skin();
        $classes = 'obx-loop elementor-loop-container elementor-grid' . (($s['masonry'] ?? '') === 'yes' ? ' obx-loop--masonry' : '');

        if (in_array($skin, self::TERM_SKINS, true)) {
            $terms = Query::terms($s, $skin . '_', $skin === 'product_taxonomy' ? 'product_cat' : 'category', $per_page);
            if (!$terms) { $this->nothing_found($s); return; }
            echo '<div class="obx-loop-container"><div class="' . esc_attr($classes) . '">';
            foreach ($terms as $i => $term) { echo $this->loop_term_item($template, $term, $i === 0); Query::$shown_terms[] = $term->term_id; } // Elementor output.
            echo '</div></div>';
            return;
        }

        $paged = ($s['pagination_type'] ?? '') !== '' ? $this->current_page() : 1;
        $query = $this->loop_query($s, $per_page, $paged, $this->prefix());
        if (!$query->have_posts()) { $this->nothing_found($s); return; }
        $offset = ($paged - 1) * $per_page;
        $rows = self::alternates($s);
        echo '<div class="obx-loop-container"><div class="' . esc_attr($classes) . '">';
        $css = [];
        $slot = 0;
        foreach ($query->posts as $i => $post) {
            // Static items take their slot on every page without using up a post.
            while (true) {
                [$static, $span] = self::template_for($rows, $slot + 1, 0, true);
                if (!$static) { break; }
                $slot++;
                echo self::with_span($this->loop_item($static, $post, !isset($css[$static])), $span); // Elementor output.
                $css[$static] = true;
            }
            $slot++;
            [$id, $span] = self::template_for($rows, $offset + $i + 1, $template, false);
            echo self::with_span($this->loop_item($id, $post, !isset($css[$id])), $span); // Elementor output.
            $css[$id] = true;
            Query::$shown[] = $post->ID;
        }
        echo '</div>';
        $this->pagination($s, $query);
        echo '</div>';
    }
}

final class Loop_Carousel extends Element {
    use Loop_Renderer;
    use Carousel;
    public const PRO = 'loop-carousel';
    private const TERM_SKINS = ['post_taxonomy','product_taxonomy'];
    /** Keys of OBX 0.5–0.8 → Pro's. */
    protected const V03 = ['arrows_normal_color'=>'arrow_normal_color','arrows_hover_color'=>'arrow_hover_color','dots_color_inactive'=>'dots_normal_color','dots_color_active'=>'dots_hover_color'];

    public function get_title(): string { return 'Carrusel de bucle'; }
    public function get_icon(): string { return 'eicon-carousel-loop'; }
    public function get_keywords(): array { return ['loop','bucle','carousel','carrusel','slider','query','posts','entradas','productos','términos']; }
    protected function is_dynamic_content(): bool { return true; }

    private function skin(): string {
        $skin = (string) $this->get_settings('_skin');
        return isset(Loop_Grid::SKINS[$skin]) ? $skin : 'post';
    }

    protected function register_controls(): void {
        $has_template = ['template_id!'=>''];
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $skins = Loop_Grid::SKINS;
        if (!post_type_exists('product')) { unset($skins['product'], $skins['product_taxonomy']); }
        $this->add_control('_skin', ['label'=>'Mostrar','type'=>Controls_Manager::SELECT,'default'=>'post','options'=>$skins,'label_block'=>true]);
        $this->template_control();
        $this->add_control('edit_handle_selector', ['type'=>Controls_Manager::HIDDEN,'default'=>'.elementor-loop-container']);
        $this->add_control('posts_per_page', ['label'=>'Elementos','type'=>Controls_Manager::NUMBER,'default'=>6,'min'=>1,'max'=>50,'separator'=>'before']);
        $this->add_control('equal_height', ['label'=>'Misma altura','type'=>Controls_Manager::SWITCHER,'selectors'=>self::css('.obx-carousel__slide > .e-loop-item, .obx-carousel__slide > .e-loop-item > .elementor', 'height: 100%;')]);
        $numbers = [''=>'Por defecto'] + array_combine(array_map('strval', range(1, 10)), array_map('strval', range(1, 10)));
        $this->add_responsive_control('slides_to_show', ['label'=>'Diapositivas visibles','type'=>Controls_Manager::SELECT,'options'=>$numbers,'default'=>'3','tablet_default'=>'2','mobile_default'=>'1','selectors'=>['{{WRAPPER}} .obx-carousel'=>'--obx-slides: {{VALUE}};'],'frontend_available'=>true,'separator'=>'before']);
        $this->add_responsive_control('slides_to_scroll', ['label'=>'Diapositivas por avance','type'=>Controls_Manager::SELECT,'options'=>$numbers,'default'=>'1','selectors'=>['{{WRAPPER}} .obx-carousel'=>'--obx-scroll: {{VALUE}};'],'frontend_available'=>true]);
        $this->end_controls_section();

        Query::controls($this, 'post_query_', 'section_query', ['_skin'=>'post'], false);
        Query::product_controls($this, 'product_query_', ['_skin'=>'product']);
        Query::term_controls($this, 'post_taxonomy_', Loop_Grid::skin_taxonomies('post_taxonomy'), 'category', ['_skin'=>'post_taxonomy']);
        Query::term_controls($this, 'product_taxonomy_', Loop_Grid::skin_taxonomies('product_taxonomy'), 'product_cat', ['_skin'=>'product_taxonomy']);
        $this->end_controls_section();

        $this->start_controls_section('section_carousel_settings', ['label'=>'Carrusel']);
        $this->add_control('autoplay', ['label'=>'Reproducción automática','type'=>Controls_Manager::SWITCHER,'default'=>'yes','frontend_available'=>true]);
        $this->add_control('autoplay_speed', ['label'=>'Intervalo (ms)','type'=>Controls_Manager::NUMBER,'default'=>5000,'condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control('pause_on_hover', ['label'=>'Pausar al pasar el ratón','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control('pause_on_interaction', ['label'=>'Pausar al interactuar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control('infinite', ['label'=>'Bucle infinito','type'=>Controls_Manager::SWITCHER,'default'=>'yes','frontend_available'=>true]);
        $this->add_control('speed', ['label'=>'Duración de la transición (ms)','type'=>Controls_Manager::NUMBER,'default'=>500,'frontend_available'=>true]);
        $this->carousel_offset_controls('infinite');
        $this->end_controls_section();

        $this->start_controls_section('section_navigation_settings', ['label'=>'Flechas','condition'=>$has_template]);
        $this->add_control('arrows', ['label'=>'Flechas','type'=>Controls_Manager::SWITCHER,'default'=>'yes','frontend_available'=>true]);
        $this->add_control('navigation_previous_icon', ['label'=>'Icono anterior','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'eicon-chevron-left','library'=>'eicons'],'condition'=>['arrows'=>'yes']]);
        $this->add_control('navigation_next_icon', ['label'=>'Icono siguiente','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'eicon-chevron-right','library'=>'eicons'],'condition'=>['arrows'=>'yes']]);
        $this->carousel_arrow_position_controls('arrows', $has_template);
        $this->end_controls_section();

        $this->start_controls_section('section_carousel_pagination', ['label'=>'Paginación']);
        $this->add_control('pagination', ['label'=>'Paginación','type'=>Controls_Manager::SELECT,'default'=>'bullets','options'=>[''=>'Ninguna','bullets'=>'Puntos','fraction'=>'Fracción','progressbar'=>'Barra de progreso'],'frontend_available'=>true]);
        $this->end_controls_section();

        $this->start_controls_section('section_layout_style', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('image_spacing_custom', 'Separación', '.obx-carousel', '--obx-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['unit'=>'px','size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $this->end_controls_section();

        $this->carousel_pro_navigation_style('arrows', $has_template);
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $template = (int) ($s['template_id'] ?? 0);
        if (!$template) { $this->no_template_notice(); return; }
        $count = max(1, min(50, (int) ($s['posts_per_page'] ?? 6) ?: 6));
        $skin = $this->skin();
        $items = [];
        if (in_array($skin, self::TERM_SKINS, true)) {
            foreach (Query::terms($s, $skin . '_', $skin === 'product_taxonomy' ? 'product_cat' : 'category', $count) as $i => $term) { $items[] = $this->loop_term_item($template, $term, $i === 0); Query::$shown_terms[] = $term->term_id; }
        } else {
            $query = $this->loop_query($s, $count, 1, $skin === 'product' ? 'product_query_' : 'post_query_');
            foreach ($query->posts as $i => $post) { $items[] = $this->loop_item($template, $post, $i === 0); Query::$shown[] = $post->ID; }
        }
        if (!$items) { $this->placeholder('No hay contenido que coincida con la consulta.'); return; }
        $prev = !empty($s['navigation_previous_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($s['navigation_previous_icon'], ['aria-hidden'=>'true']) : '';
        $next = !empty($s['navigation_next_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($s['navigation_next_icon'], ['aria-hidden'=>'true']) : '';
        $total = count($items);
        $dir = ($s['direction'] ?? 'ltr') === 'rtl' ? ' dir="rtl"' : '';
        echo '<div class="obx-carousel obx-loop-carousel elementor-loop-container" data-carousel="' . esc_attr($this->carousel_options($s, 'arrows', 'infinite')) . '"' . $dir . ' role="region" aria-roledescription="carrusel" aria-label="Carrusel"><div class="obx-carousel__track">';
        foreach ($items as $i => $item) {
            echo '<div class="obx-carousel__slide" role="group" aria-roledescription="diapositiva" aria-label="' . esc_attr(($i + 1) . ' / ' . $total) . '">' . $item . '</div>'; // Elementor output.
        }
        echo '</div>' . $this->carousel_controls_html($s, 'arrows', $prev, $next) . '</div>';
    }
}

/** Filters a Loop Grid on the same page by the terms of a taxonomy. */
final class Taxonomy_Filter extends Element {
    public const PRO = 'taxonomy-filter';

    public function get_title(): string { return 'Filtro por taxonomía'; }
    public function get_icon(): string { return 'eicon-taxonomy-filter'; }
    public function get_keywords(): array { return ['filter','filtro','taxonomy','taxonomía','categorías','loop','bucle']; }
    protected function is_dynamic_content(): bool { return true; }

    protected function register_controls(): void {
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) { if ($taxonomy->name !== 'post_format') { $taxonomies[$taxonomy->name] = $taxonomy->labels->name; } }
        $active = ['selected_element!'=>''];
        $this->start_controls_section('section_taxonomy_filter', ['label'=>'Filtro']);
        $this->add_control('selected_element', ['label'=>'Rejilla de bucle','type'=>Controls_Manager::TEXT,'default'=>'','placeholder'=>'ID del elemento (p. ej. 3f2a9c1)','description'=>'ID del widget «Rejilla de bucle» de esta página (clic derecho → Copiar ID, o en Avanzado).','label_block'=>true]);
        $this->add_control('taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT,'options'=>$taxonomies,'default'=>'category','condition'=>$active]);
        $this->add_responsive_control('direction', ['label'=>'Dirección','type'=>Controls_Manager::SELECT,'default'=>'horizontal','options'=>['horizontal'=>'Horizontal','vertical'=>'Vertical'],'prefix_class'=>'obx-tfilter%s--dir-','condition'=>$active]);
        $stretch = self::logical_align() + ['stretch'=>['title'=>'Estirar','icon'=>'eicon-h-align-stretch']];
        $this->add_responsive_control('item_alignment_horizontal', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>$stretch,'selectors_dictionary'=>['start'=>'flex-start','end'=>'flex-end','center'=>'center','stretch'=>'stretch'],'selectors'=>self::css('.obx-tfilter', 'justify-content: {{VALUE}}; --obx-tfilter-grow: {{VALUE}};'),'condition'=>$active + ['direction'=>'horizontal']]);
        $this->add_responsive_control('title_alignment_horizontal', ['label'=>'Alineación del texto','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors'=>self::css('.obx-tfilter__item', 'text-align: {{VALUE}};'),'condition'=>$active + ['direction'=>'horizontal','item_alignment_horizontal'=>'stretch']]);
        $this->add_responsive_control('item_alignment_vertical', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>$stretch,'selectors_dictionary'=>['start'=>'flex-start','end'=>'flex-end','center'=>'center','stretch'=>'stretch'],'selectors'=>self::css('.obx-tfilter', 'align-items: {{VALUE}};'),'condition'=>$active + ['direction'=>'vertical']]);
        $this->add_responsive_control('title_alignment_vertical', ['label'=>'Alineación del texto','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors'=>self::css('.obx-tfilter__item', 'text-align: {{VALUE}};'),'condition'=>$active + ['direction'=>'vertical','item_alignment_vertical'=>'stretch']]);
        $this->end_controls_section();

        $this->start_controls_section('section_settings', ['label'=>'Ajustes','condition'=>$active]);
        $this->add_control('multiple_selection', ['label'=>'Selección múltiple','type'=>Controls_Manager::SWITCHER,'default'=>'no']);
        $this->add_control('logical_combination', ['label'=>'Combinación','type'=>Controls_Manager::SELECT,'default'=>'AND','options'=>['AND'=>'Todos (Y)','OR'=>'Cualquiera (O)'],'condition'=>['multiple_selection'=>'yes']]);
        $this->add_control('show_empty_items', ['label'=>'Mostrar términos vacíos','type'=>Controls_Manager::SWITCHER,'default'=>'no','separator'=>'before']);
        $this->add_control('show_child_taxonomy', ['label'=>'Mostrar términos hijos','type'=>Controls_Manager::SWITCHER,'default'=>'no']);
        $this->add_control('child_taxonomy_depth', ['label'=>'Profundidad','type'=>Controls_Manager::SELECT,'default'=>'1','options'=>array_combine(array_map('strval', range(1, 6)), array_map('strval', range(1, 6))),'condition'=>['show_child_taxonomy'=>'yes']]);
        $this->add_control('show_first_item', ['label'=>'Mostrar «Todo»','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('first_item_title', ['label'=>'Texto de «Todo»','type'=>Controls_Manager::TEXT,'default'=>'Todo','condition'=>['show_first_item'=>'yes']]);
        $this->add_control('number_of_taxonomies', ['label'=>'Número máximo de términos','type'=>Controls_Manager::NUMBER,'min'=>1]);
        $this->add_responsive_control('horizontal_scroll', ['label'=>'Desplazamiento horizontal','type'=>Controls_Manager::SELECT,'default'=>'disable','options'=>['disable'=>'No','enable'=>'Sí'],'prefix_class'=>'obx-tfilter%s--scroll-','condition'=>['direction'=>'horizontal']]);
        $this->end_controls_section();

        $this->start_controls_section('section_design_layout', ['label'=>'Filtro','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('taxonomy_filter_items_space_between', 'Separación', '.obx-tfilter', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]], true);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name'=>'taxonomy_filter_typography','selector'=>'{{WRAPPER}} .obx-tfilter__item']);
        $this->start_controls_tabs('taxonomy_filter_tabs');
        foreach (['normal'=>['Normal', '.obx-tfilter__item'], 'hover'=>['Al pasar el ratón', '.obx-tfilter__item:hover'], 'active'=>['Activo', '.obx-tfilter__item[aria-pressed="true"]']] as $state => [$label, $selector]) {
            $this->start_controls_tab('taxonomy_filter_tab_' . $state, ['label'=>$label]);
            $this->color('taxonomy_filter_' . $state . '_text_color', 'Color del texto', $selector);
            $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'taxonomy_filter_' . $state . '_text_shadow','selector'=>'{{WRAPPER}} ' . $selector]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'taxonomy_filter_' . $state . '_background','types'=>['classic','gradient'],'exclude'=>['image','video'],'selector'=>'{{WRAPPER}} ' . $selector]);
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'taxonomy_filter_' . $state . '_border','selector'=>'{{WRAPPER}} ' . $selector]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'taxonomy_filter_' . $state . '_box_shadow','selector'=>'{{WRAPPER}} ' . $selector]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->dimensions('taxonomy_filter_border_radius', 'Radio del borde', '.obx-tfilter__item', 'border-radius', ['separator'=>'before']);
        $this->dimensions('taxonomy_filter_padding', 'Relleno', '.obx-tfilter__item', 'padding');
        $this->end_controls_section();
    }

    /** @return \WP_Term[] terms in display order, with depth */
    private static function terms(string $taxonomy, array $s): array {
        $args = ['taxonomy'=>$taxonomy,'hide_empty'=>($s['show_empty_items'] ?? 'no') !== 'yes','orderby'=>'name'];
        $children = ($s['show_child_taxonomy'] ?? 'no') === 'yes';
        if (!$children) { $args['parent'] = 0; }
        $terms = get_terms($args);
        if (!is_array($terms)) { return []; }
        if ($children) {
            $depth = max(1, (int) ($s['child_taxonomy_depth'] ?? 1));
            $terms = array_values(array_filter($terms, static fn(\WP_Term $t) => count(get_ancestors($t->term_id, $taxonomy, 'taxonomy')) <= $depth));
        }
        $max = (int) ($s['number_of_taxonomies'] ?? 0);
        return $max > 0 ? array_slice($terms, 0, $max) : $terms;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $target = sanitize_key((string) ($s['selected_element'] ?? ''));
        $taxonomy = (string) ($s['taxonomy'] ?? 'category');
        if ($target === '' || !taxonomy_exists($taxonomy)) { $this->placeholder('Indica el ID de la rejilla de bucle que quieres filtrar.'); return; }
        $param = 'e-filter-' . $target . '-' . $taxonomy;
        $selected = array_filter(array_map('sanitize_title', explode(',', (string) wp_unslash($_GET[$param] ?? '')))); // phpcs:ignore WordPress.Security.NonceVerification
        $multiple = ($s['multiple_selection'] ?? 'no') === 'yes';
        $base = remove_query_arg([$param, 'e-page-' . $target]);
        echo '<div class="obx-tfilter" role="toolbar" aria-label="Filtro" data-target="' . esc_attr($target) . '" data-param="' . esc_attr($param) . '" data-multiple="' . ($multiple ? '1' : '') . '">';
        if (($s['show_first_item'] ?? 'yes') === 'yes') {
            echo '<a class="obx-tfilter__item" href="' . esc_url($base) . '" data-filter="" aria-pressed="' . ($selected ? 'false' : 'true') . '">' . esc_html((string) ($s['first_item_title'] ?? 'Todo')) . '</a>';
        }
        foreach (self::terms($taxonomy, $s) as $term) {
            $on = in_array($term->slug, $selected, true);
            $next = $multiple ? ($on ? array_diff($selected, [$term->slug]) : array_merge($selected, [$term->slug])) : ($on ? [] : [$term->slug]);
            $url = $next ? add_query_arg($param, implode(',', $next), $base) : $base;
            echo '<a class="obx-tfilter__item" href="' . esc_url($url) . '" data-filter="' . esc_attr($term->slug) . '" aria-pressed="' . ($on ? 'true' : 'false') . '">' . esc_html($term->name) . '</a>';
        }
        echo '</div>';
    }
}
