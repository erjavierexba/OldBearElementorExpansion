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
        $this->add_control('taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT,'options'=>[''=>'Elige una taxonomía'] + $taxonomies,'default'=>'category','condition'=>$active]);
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
        $this->start_controls_tabs('taxonomy_filter_tabs_section');
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

/**
 * Elementor Pro's current «Search» widget: field with icon, clear button and submit, plus live results
 * printed with a «loop-item» template and loaded from the REST route obx/v1/search.
 */
final class Search extends Base {
    use Loop_Renderer;
    public const PRO = 'search';
    public const PRO_CATEGORY = 'pro-elements';
    public const ROUTE = '/search';
    private const MAX_ITEMS = 50;
    private const FIELD = '{{WRAPPER}} .obx-search__field';
    private const SUBMIT = '{{WRAPPER}} .obx-search__submit';
    private const RESULTS = '{{WRAPPER}} .obx-search__results';

    public function get_title(): string { return 'Buscador'; }
    public function get_icon(): string { return 'eicon-site-search'; }
    public function get_keywords(): array { return ['search','buscar','buscador','busqueda','live','resultados','autocompletar']; }
    public function get_script_depends(): array { return ['obx-widgets']; }
    protected function content_template() {}

    public static function routes(): void {
        register_rest_route('obx/v1', self::ROUTE, ['methods'=>'GET','callback'=>[self::class, 'respond'],'permission_callback'=>'__return_true',
            'args'=>['post'=>['type'=>'integer','required'=>true],'widget'=>['type'=>'string','required'=>true],'q'=>['type'=>'string','default'=>''],'page'=>['type'=>'integer','default'=>1]]]);
    }

    public static function respond(\WP_REST_Request $request): \WP_REST_Response {
        $widget = self::saved((int) $request['post'], sanitize_key((string) $request['widget']));
        if (!$widget) { return new \WP_REST_Response(['html'=>'','total'=>0,'pages'=>0], 404); }
        $term = mb_substr(trim(sanitize_text_field((string) $request['q'])), 0, 200);
        return new \WP_REST_Response($widget->results($term, max(1, (int) $request['page'])));
    }

    /** The Search widget $id saved in document $post_id (published, or editable by the current user). */
    public static function saved(int $post_id, string $id): ?self {
        $post = get_post($post_id);
        if (!$post || $id === '' || post_password_required($post) || ($post->post_status !== 'publish' && !current_user_can('edit_post', $post_id))) { return null; }
        $document = Elementor::$instance->documents->get($post_id);
        $element = $document ? \OldBearExpansion\Forms\Handler::find((array) $document->get_elements_data(), $id) : null;
        if (!$element || ($element['elType'] ?? '') !== 'widget' || ($element['widgetType'] ?? '') !== self::PRO) { return null; }
        $widget = Elementor::$instance->elements_manager->create_element_instance($element);
        return $widget instanceof self ? $widget : null;
    }

    private static function post_types(): array {
        $types = ['any'=>'Todos'];
        foreach (get_post_types(['public'=>true], 'objects') as $type) {
            if (!in_array($type->name, ['attachment','elementor_library','e-landing-page'], true)) { $types[$type->name] = $type->labels->name; }
        }
        return $types;
    }

    protected function register_controls(): void {
        $live = ['live_results'=>'yes'];
        $has_template = $live + ['template_id!'=>''];
        $nothing = $has_template + ['enable_nothing_found_message'=>'yes'];
        $submit = ['submit_trigger!'=>'key_enter'];
        $submit_icon = $submit + ['icon_submit[value]!'=>''];
        $paged = ['pagination_type_options'=>['numbers_previous_next','previous_next','numbers']];
        $dims = static fn(string $selector, string $property) => [$selector=>$property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'];
        $seconds = ['type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms'],'default'=>['unit'=>'s'],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>50]]];

        $this->start_controls_section('content_section_search_field', ['label'=>'Campo de búsqueda']);
        $this->add_control('search_input_placeholder_text', ['label'=>'Texto de ejemplo','type'=>Controls_Manager::TEXT,'default'=>'Escribe para buscar…','dynamic'=>['active'=>true]]);
        $this->add_control('icon_search', ['label'=>'Icono del campo','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false]);
        $this->add_control('autocomplete', ['label'=>'Autocompletado del navegador','type'=>Controls_Manager::SWITCHER,'description'=>'Deja que el navegador sugiera búsquedas anteriores.']);
        $this->add_control('icon_clear', ['label'=>'Icono para borrar','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-times','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false]);
        $this->add_control('submit_trigger', ['label'=>'Enviar con','type'=>Controls_Manager::SELECT,'default'=>'click_submit','options'=>['click_submit'=>'Botón','key_enter'=>'Tecla Intro','both'=>'Ambos'],'separator'=>'before']);
        $this->add_control('submit_button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Buscar','dynamic'=>['active'=>true],'condition'=>$submit]);
        $this->add_control('icon_submit', ['label'=>'Icono del botón','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'condition'=>$submit]);
        $this->end_controls_section();

        $this->start_controls_section('content_section_results', ['label'=>'Resultados en vivo']);
        $this->add_control('live_results', ['label'=>'Mostrar resultados al escribir','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('template_id', ['label'=>'Plantilla del elemento','type'=>Controls_Manager::SELECT,'options'=>self::loop_templates(),'default'=>'','label_block'=>true,'condition'=>$live,
            'description'=>'Cada resultado se muestra con una plantilla de tipo «Elemento de bucle».']);
        $this->add_control('minimum_search_characters', ['label'=>'Caracteres mínimos','type'=>Controls_Manager::NUMBER,'default'=>3,'min'=>1,'max'=>10,'condition'=>$has_template]);
        $this->add_responsive_control('number_of_columns', ['label'=>'Columnas','type'=>Controls_Manager::NUMBER,'default'=>1,'min'=>1,'max'=>6,'condition'=>$has_template,'selectors'=>['{{WRAPPER}} .obx-search__items'=>'--obx-search-columns: {{VALUE}};']]);
        $this->add_control('number_of_items', ['label'=>'Resultados por página','type'=>Controls_Manager::NUMBER,'default'=>6,'min'=>1,'max'=>self::MAX_ITEMS,'condition'=>$has_template]);
        $this->add_control('equal_height', ['label'=>'Misma altura','type'=>Controls_Manager::SWITCHER,'condition'=>$has_template,'selectors'=>['{{WRAPPER}} .obx-search__items > .e-loop-item > .elementor'=>'height: 100%;']]);
        $this->add_control('enable_loader', ['label'=>'Indicador de carga','type'=>Controls_Manager::SWITCHER,'condition'=>$has_template]);
        $this->add_control('enable_nothing_found_message', ['label'=>'Mensaje sin resultados','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>$has_template,'separator'=>'before']);
        $this->add_control('nothing_found_message_text', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>'No hemos encontrado nada con esa búsqueda.','dynamic'=>['active'=>true],'condition'=>$nothing]);
        $this->add_control('nothing_found_message_html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span'],'condition'=>$nothing]);
        $this->end_controls_section();

        $this->start_controls_section('content_section_query', ['label'=>'Consulta','condition'=>$live]);
        Query::controls($this, 'search_query_', '', [], false);
        foreach (['archive_query_note','search_query_related_taxonomies','search_query_avoid_duplicates','search_query_offset','search_query_related_fallback','search_query_fallback_ids'] as $key) { $this->remove_control($key); }
        $this->update_control('search_query_post_type', ['label'=>'Buscar en','default'=>'any','options'=>self::post_types()]);
        $this->end_controls_section();

        $this->start_controls_section('content_section_additional_settings', ['label'=>'Paginación de resultados','condition'=>$has_template]);
        $this->add_control('pagination_type_options', ['label'=>'Paginación','type'=>Controls_Manager::SELECT,'default'=>'none','options'=>['none'=>'Ninguna','numbers'=>'Números','previous_next'=>'Anterior / Siguiente','numbers_previous_next'=>'Números + Anterior / Siguiente']]);
        $this->add_control('pagination_prev_label', ['label'=>'Texto «Anterior»','type'=>Controls_Manager::TEXT,'default'=>'Anterior','condition'=>['pagination_type_options'=>['previous_next','numbers_previous_next']]]);
        $this->add_control('pagination_next_label', ['label'=>'Texto «Siguiente»','type'=>Controls_Manager::TEXT,'default'=>'Siguiente','condition'=>['pagination_type_options'=>['previous_next','numbers_previous_next']]]);
        $this->add_control('page_limit_settings', ['label'=>'Límite de páginas','type'=>Controls_Manager::NUMBER,'default'=>5,'min'=>1,'condition'=>$paged]);
        $this->add_control('pagination_shorten_settings', ['label'=>'Acortar','type'=>Controls_Manager::SWITCHER,'description'=>'Muestra solo la primera, la última y las páginas cercanas a la actual.','condition'=>['pagination_type_options'=>['numbers_previous_next','numbers']]]);
        $this->end_controls_section();

        // Style: field.
        $this->start_controls_section('section_search_field_style', ['label'=>'Campo de búsqueda','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name'=>'search_field_typography','selector'=>self::FIELD]);
        $this->add_control('placeholder_color', ['label'=>'Color del texto de ejemplo','type'=>Controls_Manager::COLOR,'selectors'=>[self::FIELD . '::placeholder'=>'color: {{VALUE}};']]);
        $this->start_controls_tabs('search_field_tabs');
        foreach (['normal'=>['Normal', '', ''], 'focus'=>['Al escribir', ':focus', ':focus-within']] as $state => [$label, $pseudo, $within]) {
            $this->start_controls_tab('search_field_tab_' . $state, ['label'=>$label]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'search_field_background_' . $state,'types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>self::FIELD . $pseudo]);
            $this->add_control('search_field_input_text_color_' . $state, ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>[self::FIELD . $pseudo=>'color: {{VALUE}};']]);
            $this->add_control('search_field_icon_search_normal_color_' . $state, ['label'=>'Color del icono','type'=>Controls_Manager::COLOR,'condition'=>['icon_search[value]!'=>''],
                'selectors'=>['{{WRAPPER}} .obx-search__wrap' . $within . ' .obx-search__icon'=>'color: {{VALUE}}; fill: {{VALUE}};']]);
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'search_field_border_' . $state,'selector'=>self::FIELD . $pseudo]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'search_field_box_shadow__' . $state,'selector'=>self::FIELD . $pseudo]);
            if ($state === 'focus') {
                $this->add_control('search_field_search_input_transition', ['label'=>'Duración de la transición'] + $seconds + ['selectors'=>[self::FIELD . ', {{WRAPPER}} .obx-search__icon'=>'transition-duration: {{SIZE}}{{UNIT}};']]);
            }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_responsive_control('search_field_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'separator'=>'before','selectors'=>$dims(self::FIELD, 'border-radius')]);
        $this->add_responsive_control('search_field_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'selectors'=>$dims(self::FIELD, 'padding')]);
        $this->add_responsive_control('search_field_icon_label_size', ['label'=>'Tamaño del icono','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>['icon_search[value]!'=>''],'selectors'=>['{{WRAPPER}} .obx-search'=>'--obx-search-icon-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('search_field_icon_gap', ['label'=>'Separación del icono','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>['icon_search[value]!'=>''],'selectors'=>['{{WRAPPER}} .obx-search'=>'--obx-search-icon-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('search_field_submit_gap', ['label'=>'Separación con el botón','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'selectors'=>['{{WRAPPER}} .obx-search'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        // Style: clear icon.
        $clear = ['icon_clear[value]!'=>''];
        $this->start_controls_section('style_section_clear', ['label'=>'Icono para borrar','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$clear]);
        $this->add_responsive_control('icon_clear_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$clear,'selectors'=>['{{WRAPPER}} .obx-search__clear'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->start_controls_tabs('icon_clear_color_tabs', ['condition'=>$clear]);
        foreach (['normal'=>['Normal', ''], 'hover'=>['Al pasar el ratón', ':hover']] as $state => [$label, $pseudo]) {
            $this->start_controls_tab('icon_clear_tab_' . $state, ['label'=>$label]);
            $this->add_control('icon_clear_' . $state . '_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>$clear,'selectors'=>['{{WRAPPER}} .obx-search__clear' . $pseudo=>'color: {{VALUE}}; fill: {{VALUE}}; opacity: 1;']]);
            if ($state === 'hover') { $this->add_control('icon_clear_hover_transition', ['label'=>'Duración de la transición'] + $seconds + ['condition'=>$clear,'selectors'=>['{{WRAPPER}} .obx-search__clear'=>'transition-duration: {{SIZE}}{{UNIT}};']]); }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        // Style: submit button.
        $this->start_controls_section('style_section_submit', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$submit]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name'=>'submit_typography','selector'=>self::SUBMIT,'condition'=>$submit]);
        $this->add_control('icon_submit_position', ['label'=>'Posición del icono','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Antes','icon'=>'eicon-h-align-left'],'end'=>['title'=>'Después','icon'=>'eicon-h-align-right']],
            'condition'=>$submit_icon,'selectors_dictionary'=>['start'=>'row','end'=>'row-reverse'],'selectors'=>[self::SUBMIT=>'flex-direction: {{VALUE}};']]);
        $this->add_responsive_control('icon_submit_size', ['label'=>'Tamaño del icono','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$submit_icon,'selectors'=>[self::SUBMIT . ' .obx-search__submit-icon'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('icon_submit_gap', ['label'=>'Separación del icono','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$submit_icon,'selectors'=>[self::SUBMIT=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->start_controls_tabs('submit_style_tabs', ['condition'=>$submit]);
        foreach (['normal'=>['Normal', ''], 'hover'=>['Al pasar el ratón', ':hover, ' . self::SUBMIT . ':focus-visible']] as $state => [$label, $pseudo]) {
            $selector = self::SUBMIT . $pseudo;
            $this->start_controls_tab('submit_tab_' . $state, ['label'=>$label]);
            $this->add_control('submit_text_color_' . $state, ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'condition'=>$submit,'selectors'=>[$selector=>'color: {{VALUE}};']]);
            $this->add_control('submit_icon_color_' . $state, ['label'=>'Color del icono','type'=>Controls_Manager::COLOR,'condition'=>$submit_icon,
                'selectors'=>[$state === 'hover' ? self::SUBMIT . ':hover .obx-search__submit-icon, ' . self::SUBMIT . ':focus-visible .obx-search__submit-icon' : self::SUBMIT . ' .obx-search__submit-icon'=>'color: {{VALUE}}; fill: {{VALUE}};']]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'submit_background_' . $state,'types'=>['classic','gradient'],'exclude'=>['image'],'condition'=>$submit,'selector'=>$selector]);
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'submit_border_' . $state,'condition'=>$submit,'selector'=>$selector]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'submit_box_shadow_' . $state,'condition'=>$submit,'selector'=>$selector]);
            if ($state === 'hover') {
                $this->add_control('submit_hover_animation', ['label'=>'Animación','type'=>Controls_Manager::HOVER_ANIMATION,'condition'=>$submit]);
                $this->add_control('submit_hover_transition', ['label'=>'Duración de la transición'] + $seconds + ['condition'=>$submit,'selectors'=>[self::SUBMIT=>'transition-duration: {{SIZE}}{{UNIT}};']]);
            }
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_responsive_control('submit_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'condition'=>$submit,'separator'=>'before','selectors'=>$dims(self::SUBMIT, 'border-radius')]);
        $this->add_responsive_control('submit_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'condition'=>$submit,'selectors'=>$dims(self::SUBMIT, 'padding')]);
        $this->end_controls_section();

        // Style: live results box.
        $this->start_controls_section('style_section_results', ['label'=>'Resultados','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$live]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'results_background','types'=>['classic','gradient'],'exclude'=>['image'],'condition'=>$live,'selector'=>self::RESULTS]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'results_border_type','condition'=>$live,'selector'=>self::RESULTS]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'results_box_shadow','condition'=>$live,'selector'=>self::RESULTS]);
        $this->add_responsive_control('results_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'condition'=>$live,'selectors'=>$dims(self::RESULTS, 'border-radius')]);
        $this->add_responsive_control('results_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'condition'=>$live,'selectors'=>$dims(self::RESULTS, 'padding')]);
        $this->add_responsive_control('results_distance_from_search_field', ['label'=>'Distancia al campo','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$live,'selectors'=>[self::RESULTS=>'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->add_control('results_is_dropdown_width', ['label'=>'Ancho del desplegable','type'=>Controls_Manager::SELECT,'default'=>'search_field','options'=>['search_field'=>'El del campo','widget_width'=>'El del widget'],'condition'=>$live]);
        $this->add_control('results_is_custom_width', ['label'=>'Ancho personalizado','type'=>Controls_Manager::SWITCHER,'condition'=>$live]);
        $this->add_responsive_control('results_custom_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw'],'range'=>['px'=>['min'=>100,'max'=>1600]],'condition'=>$live + ['results_is_custom_width'=>'yes'],
            'selectors'=>[self::RESULTS=>'width: {{SIZE}}{{UNIT}}; right: auto;']]);
        $this->add_responsive_control('results_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'condition'=>$live + ['results_is_custom_width'=>'yes'],
            'selectors_dictionary'=>['start'=>'left: 0; right: auto; transform: none;','center'=>'left: 50%; right: auto; transform: translateX(-50%);','end'=>'left: auto; right: 0; transform: none;'],'selectors'=>[self::RESULTS=>'{{VALUE}}']]);
        $this->add_responsive_control('results_max_height', ['label'=>'Altura máxima','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh'],'range'=>['px'=>['min'=>100,'max'=>1200]],'condition'=>$live,'selectors'=>[self::RESULTS=>'max-height: {{SIZE}}{{UNIT}}; overflow-y: auto;']]);
        $this->add_responsive_control('search_result_column_gap', ['label'=>'Separación entre columnas','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$live,'selectors'=>['{{WRAPPER}} .obx-search__items'=>'column-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('search_result_row_gap', ['label'=>'Separación entre filas','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$live,'selectors'=>['{{WRAPPER}} .obx-search__items'=>'row-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('search_result_loader_icon_size', ['label'=>'Tamaño del indicador de carga','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw'],'condition'=>$has_template + ['enable_loader'=>'yes'],'selectors'=>['{{WRAPPER}} .obx-search__loader'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('search_result_loader_icon_color', ['label'=>'Color del indicador de carga','type'=>Controls_Manager::COLOR,'condition'=>$has_template + ['enable_loader'=>'yes'],'selectors'=>['{{WRAPPER}} .obx-search__loader'=>'color: {{VALUE}};']]);
        $this->end_controls_section();

        // Style: results pagination.
        $page = '{{WRAPPER}} .obx-search__page';
        $this->start_controls_section('style_additional_settings', ['label'=>'Paginación de resultados','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$has_template + $paged]);
        $this->add_responsive_control('style_additional_settings_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'condition'=>$paged,
            'options'=>['flex-start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'flex-end'=>['title'=>'Final','icon'=>'eicon-h-align-right']],'selectors'=>['{{WRAPPER}} .obx-search__pages'=>'justify-content: {{VALUE}};']]);
        $this->add_control('style_additional_settings_vertical_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'condition'=>$paged,
            'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],
            'selectors_dictionary'=>['top'=>'order: -1; margin: 0 0 12px;','bottom'=>'order: 1;'],'selectors'=>['{{WRAPPER}} .obx-search__pages'=>'{{VALUE}}']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name'=>'typography_title','condition'=>$paged,'selector'=>$page]);
        $this->start_controls_tabs('style_additional_settings_color_tabs', ['condition'=>$paged]);
        foreach (['normal'=>['Normal', ''], 'hover'=>['Al pasar el ratón', ':hover']] as $state => [$label, $pseudo]) {
            $this->start_controls_tab('style_additional_settings_tab_' . $state, ['label'=>$label]);
            $this->add_control('style_additional_settings_' . $state . '_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>$paged,'selectors'=>[$page . $pseudo=>'color: {{VALUE}};']]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_control('style_button_color_icon_active', ['label'=>'Color de la página actual','type'=>Controls_Manager::COLOR,'condition'=>$paged,'selectors'=>[$page . '.is-current'=>'color: {{VALUE}};']]);
        $this->add_responsive_control('page_numbers_space_between', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$paged,'separator'=>'before','selectors'=>['{{WRAPPER}} .obx-search__pages'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('top_spacing', ['label'=>'Espacio superior','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$paged,'selectors'=>['{{WRAPPER}} .obx-search__pages'=>'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('bottom_spacing', ['label'=>'Espacio inferior','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$paged,'selectors'=>['{{WRAPPER}} .obx-search__pages'=>'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        // Style: nothing found message.
        $message = '{{WRAPPER}} .obx-search__nothing';
        $this->start_controls_section('style_section_nothing_found_message', ['label'=>'Mensaje sin resultados','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$nothing]);
        $this->add_responsive_control('nothing_found_message_space_from_top', ['label'=>'Espacio superior','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$nothing,'selectors'=>[$message=>'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('nothing_found_message_space_from_bottom', ['label'=>'Espacio inferior','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'condition'=>$nothing,'selectors'=>[$message=>'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('nothing_found_message_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'condition'=>$nothing,'selectors'=>[$message=>'text-align: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name'=>'nothing_found_message_typography','condition'=>$nothing,'selector'=>$message]);
        $this->add_control('nothing_found_message_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>$nothing,'selectors'=>[$message=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'nothing_found_message_text_shadow','condition'=>$nothing,'selector'=>$message]);
        $this->add_group_control(\Elementor\Group_Control_Text_Stroke::get_type(), ['name'=>'nothing_found_message_text_stroke','condition'=>$nothing,'selector'=>$message]);
        $this->end_controls_section();
    }

    private function template(array $s): int {
        $template = (int) ($s['template_id'] ?? 0);
        return ($s['live_results'] ?? '') === 'yes' && $template && get_post_type($template) === 'elementor_library' ? $template : 0;
    }

    /**
     * Results for $term: loop items, pagination and the «nothing found» message.
     * @return array{html:string,total:int,pages:int}
     */
    public function results(string $term, int $page): array {
        $s = $this->get_settings_for_display();
        $template = $this->template($s);
        if (!$template) { return ['html'=>'','total'=>0,'pages'=>0]; }
        $per_page = min(self::MAX_ITEMS, max(1, (int) ($s['number_of_items'] ?? 6)));
        $paginated = ($s['pagination_type_options'] ?? 'none') !== 'none';
        $limit = $paginated ? max(1, (int) ($s['page_limit_settings'] ?? 5)) : 1;
        $page = min($page, $limit);
        $type = (string) ($s['search_query_post_type'] ?? 'any');
        $extra = ['s'=>$term] + ($type === 'any' || !post_type_exists($type) ? ['post_type'=>'any'] : []);
        $query = Query::build($s, 'search_query_', $per_page, $page, $this, $extra);
        $pages = min((int) $query->max_num_pages, $limit);
        $html = '';
        if ($query->have_posts()) {
            $items = '';
            foreach ($query->posts as $i => $post) { if ($post instanceof \WP_Post) { $items .= $this->loop_item($template, $post, $i === 0); } }
            $html = '<div class="obx-search__items">' . $items . '</div>' . $this->page_links($s, $page, $pages);
        } elseif (($s['enable_nothing_found_message'] ?? 'yes') === 'yes') {
            $tag = \Elementor\Utils::validate_html_tag((string) ($s['nothing_found_message_html_tag'] ?? 'div'));
            $html = '<' . $tag . ' class="obx-search__nothing">' . wp_kses_post((string) ($s['nothing_found_message_text'] ?? '')) . '</' . $tag . '>';
        }
        return ['html'=>$html,'total'=>(int) $query->found_posts,'pages'=>$pages];
    }

    private function page_links(array $s, int $page, int $pages): string {
        $type = (string) ($s['pagination_type_options'] ?? 'none');
        if ($type === 'none' || $pages < 2) { return ''; }
        $button = static fn(int $n, string $label, string $class, bool $current = false): string => '<button type="button" class="obx-search__page ' . $class . ($current ? ' is-current' : '') . '" data-page="' . $n . '"' . ($current ? ' aria-current="page"' : '') . '>' . $label . '</button>';
        $arrows = in_array($type, ['previous_next','numbers_previous_next'], true);
        $out = '';
        if ($arrows && $page > 1) { $out .= $button($page - 1, esc_html((string) ($s['pagination_prev_label'] ?? 'Anterior')), 'obx-search__page--prev'); }
        if (in_array($type, ['numbers','numbers_previous_next'], true)) {
            $short = ($s['pagination_shorten_settings'] ?? '') === 'yes';
            $gap = false;
            for ($n = 1; $n <= $pages; $n++) {
                if ($short && $n !== 1 && $n !== $pages && abs($n - $page) > 1) {
                    if (!$gap) { $out .= '<span class="obx-search__page obx-search__page--dots" aria-hidden="true">…</span>'; }
                    $gap = true;
                    continue;
                }
                $gap = false;
                $out .= $button($n, (string) $n, 'obx-search__page--number', $n === $page);
            }
        }
        if ($arrows && $page < $pages) { $out .= $button($page + 1, esc_html((string) ($s['pagination_next_label'] ?? 'Siguiente')), 'obx-search__page--next'); }
        return '<nav class="obx-search__pages" aria-label="Páginas de resultados">' . $out . '</nav>';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $id = 'obx-search-' . $this->get_id();
        $template = $this->template($s);
        $editing = $this->editing();
        $attrs = ' class="obx-search obx-search--classic obx-search--new" role="search" method="get" action="' . esc_url(home_url('/')) . '"';
        $results = '';
        if ($template) {
            $config = ['url'=>rest_url('obx/v1' . self::ROUTE),'widget'=>$this->get_id(),'min'=>max(1, (int) ($s['minimum_search_characters'] ?? 3))];
            if (is_user_logged_in()) { $config['nonce'] = wp_create_nonce('wp_rest'); }
            $attrs .= " data-live='" . esc_attr((string) wp_json_encode($config)) . "'";
            if (!$editing) { \Elementor\Core\Files\CSS\Post::create($template)->enqueue(); }
            // In the editor the box shows the first results, so it can be designed.
            $preview = $editing ? $this->results('', 1)['html'] : '';
            $results = '<div class="obx-search__results" id="' . esc_attr($id) . '-results" role="region" aria-label="Resultados de búsqueda"' . ($preview === '' ? ' hidden' : '') . '>'
                . (($s['enable_loader'] ?? '') === 'yes' ? '<span class="obx-search__loader" aria-hidden="true"></span>' : '')
                . '<div class="obx-search__content" aria-live="polite">' . $preview . '</div></div>';
        }
        $in_field = ($s['results_is_dropdown_width'] ?? 'search_field') !== 'widget_width';
        echo '<form' . $attrs . '><label class="screen-reader-text" for="' . esc_attr($id) . '">Buscar</label><span class="obx-search__wrap">';
        if (!empty($s['icon_search']['value'])) { echo '<span class="obx-search__icon">'; Icons_Manager::render_icon($s['icon_search'], ['aria-hidden'=>'true']); echo '</span>'; }
        echo '<input id="' . esc_attr($id) . '" class="obx-search__field" type="search" name="s" value="' . esc_attr(get_search_query()) . '" placeholder="' . esc_attr((string) ($s['search_input_placeholder_text'] ?? '')) . '" autocomplete="' . (($s['autocomplete'] ?? '') === 'yes' ? 'on' : 'off') . '"'
            . ($template ? ' aria-controls="' . esc_attr($id) . '-results" aria-expanded="false"' : '') . '>';
        if (!empty($s['icon_clear']['value'])) { echo '<button type="reset" class="obx-search__clear"><span class="screen-reader-text">Borrar</span>'; Icons_Manager::render_icon($s['icon_clear'], ['aria-hidden'=>'true']); echo '</button>'; }
        if ($in_field) { echo $results; } // phpcs:ignore WordPress.Security.EscapeOutput -- built above.
        echo '</span>';
        if (($s['submit_trigger'] ?? 'click_submit') !== 'key_enter') {
            $animation = (string) ($s['submit_hover_animation'] ?? '');
            echo '<button class="obx-search__submit' . ($animation !== '' ? ' elementor-animation-' . esc_attr($animation) : '') . '" type="submit">';
            if (!empty($s['icon_submit']['value'])) { echo '<span class="obx-search__submit-icon">'; Icons_Manager::render_icon($s['icon_submit'], ['aria-hidden'=>'true']); echo '</span>'; }
            if (($s['submit_button_text'] ?? '') !== '') { echo '<span>' . esc_html($s['submit_button_text']) . '</span>'; }
            if (($s['submit_button_text'] ?? '') === '' && empty($s['icon_submit']['value'])) { echo '<span class="screen-reader-text">Buscar</span>'; }
            echo '</button>';
        }
        if (!$in_field) { echo $results; } // phpcs:ignore WordPress.Security.EscapeOutput -- built above.
        echo '</form>';
    }
}
