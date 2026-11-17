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

    /** Settings + query for this widget, with taxonomy-filter selections applied. */
    protected function loop_query(array $s, int $per_page, int $paged): \WP_Query {
        $filters = $this->url_filters();
        if ($filters && ($s['post_query_post_type'] ?? 'post') !== 'current_query') {
            $hook = static function (\WP_Query $q) use ($filters, &$hook) {
                $tax = (array) $q->get('tax_query');
                foreach ($filters as $taxonomy => $slugs) { $tax[] = ['taxonomy'=>$taxonomy,'field'=>'slug','terms'=>$slugs,'operator'=>'IN']; }
                $q->set('tax_query', ['relation'=>'AND'] + $tax);
                remove_action('pre_get_posts', $hook);
            };
            add_action('pre_get_posts', $hook);
            $query = Query::build($s, 'post_query_', $per_page, $paged, $this);
            remove_action('pre_get_posts', $hook);
            return $query;
        }
        if ($filters) {
            $tax = [];
            foreach ($filters as $taxonomy => $slugs) { $tax[] = ['taxonomy'=>$taxonomy,'field'=>'slug','terms'=>$slugs]; }
            $current = Context::query();
            return new \WP_Query(array_merge($current->query_vars, ['tax_query'=>['relation'=>'AND'] + $tax,'paged'=>$paged]));
        }
        return Query::build($s, 'post_query_', $per_page, $paged, $this);
    }

    protected function no_template_notice(): void {
        $this->placeholder('Elige una plantilla de «Elemento de bucle» en los ajustes del widget.');
    }
}

final class Loop_Grid extends Element {
    use Loop_Renderer;
    use Paginates;
    public const PRO = 'loop-grid';

    public function get_title(): string { return 'Rejilla de bucle'; }
    public function get_icon(): string { return 'eicon-loop-builder'; }
    public function get_keywords(): array { return ['loop','bucle','grid','rejilla','query','consulta','posts','entradas','listing']; }
    protected function is_dynamic_content(): bool { return true; }
    protected function page_arg(): ?string { return ($this->get_settings('post_query_post_type') === 'current_query') ? null : 'e-page-' . $this->get_id(); }

    protected function register_controls(): void {
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->template_control();
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::NUMBER,'default'=>3,'tablet_default'=>2,'mobile_default'=>1,'min'=>1,'max'=>12,'selectors'=>self::css('.obx-loop', '--obx-columns: {{VALUE}};'),'separator'=>'before']);
        $this->add_control('posts_per_page', ['label'=>'Elementos por página','type'=>Controls_Manager::NUMBER,'default'=>6,'min'=>1,'max'=>100]);
        $this->add_control('masonry', ['label'=>'Mampostería','type'=>Controls_Manager::SWITCHER,'condition'=>['columns!'=>'1']]);
        $this->add_control('equal_height', ['label'=>'Misma altura','type'=>Controls_Manager::SWITCHER,'condition'=>['masonry!'=>'yes'],'selectors'=>self::css('.obx-loop > .e-loop-item > .elementor', 'height: 100%;')]);
        $r = new Repeater();
        $r->add_control('template_id', ['label'=>'Plantilla','type'=>Controls_Manager::SELECT,'options'=>self::loop_templates(),'default'=>'','label_block'=>true]);
        $r->add_control('repeat_template', ['label'=>'Posición en la rejilla','type'=>Controls_Manager::NUMBER,'min'=>1,'default'=>2,'description'=>'Se usa cada N elementos (o solo en esa posición si se muestra una vez).']);
        $r->add_control('show_once', ['label'=>'Mostrar una sola vez','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $r->add_control('column_span', ['label'=>'Columnas que ocupa','type'=>Controls_Manager::SELECT,'default'=>'1','options'=>array_combine(array_map('strval', range(1, 12)), array_map('strval', range(1, 12)))]);
        $this->add_control('alternate_template', ['label'=>'Plantillas alternativas','type'=>Controls_Manager::SWITCHER,'separator'=>'before']);
        $this->add_control('alternate_templates', ['label'=>'Plantillas','type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[],'title_field'=>'#{{{ repeat_template }}}','condition'=>['alternate_template'=>'yes']]);
        $this->end_controls_section();

        Query::controls($this, 'post_query_');
        $this->pagination_controls(false);

        $this->start_controls_section('section_nothing_found', ['label'=>'Sin resultados']);
        // Pro data key; every control must live inside a section or Elementor rejects the widgets config.
        $this->add_control('pagination_individual_divider', ['type'=>Controls_Manager::HIDDEN]);
        $this->add_control('nothing_found_message_text', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>'Parece que no hay nada aquí.']);
        $this->end_controls_section();

        $this->start_controls_section('section_design_layout', ['label'=>'Diseño','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('column_gap', 'Separación entre columnas', '.obx-loop', '--obx-column-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','%','vw'],'default'=>['size'=>20,'unit'=>'px'],'range'=>['px'=>['max'=>100]]], true);
        $this->slider('row_gap', 'Separación entre filas', '.obx-loop', '--obx-row-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','vh'],'default'=>['size'=>20,'unit'=>'px'],'range'=>['px'=>['max'=>100]]], true);
        $this->end_controls_section();

        $this->pagination_style();
        $this->start_controls_section('section_nothing_found_style', ['label'=>'Sin resultados','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('nothing_found_color', 'Color', '.e-loop-nothing-found-message');
        $this->typography('nothing_found_typography', '.e-loop-nothing-found-message');
        $this->end_controls_section();
    }

    /** Template for the item at 1-based position $n. */
    private static function template_for(array $s, int $n, int $default): array {
        if (($s['alternate_template'] ?? '') === 'yes') {
            foreach ((array) ($s['alternate_templates'] ?? []) as $row) {
                $position = max(1, (int) ($row['repeat_template'] ?? 0));
                $id = (int) ($row['template_id'] ?? 0);
                if (!$id) { continue; }
                $match = ($row['show_once'] ?? 'yes') === 'yes' ? $n === $position : $n % $position === 0;
                if ($match) { return [$id, max(1, (int) ($row['column_span'] ?? 1))]; }
            }
        }
        return [$default, 1];
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $template = (int) ($s['template_id'] ?? 0);
        if (!$template) { $this->no_template_notice(); return; }
        $per_page = max(1, min(100, (int) ($s['posts_per_page'] ?? 6) ?: 6));
        $paged = ($s['pagination_type'] ?? '') !== '' ? $this->current_page() : 1;
        $query = $this->loop_query($s, $per_page, $paged);
        if (!$query->have_posts()) {
            $message = (string) ($s['nothing_found_message_text'] ?? '');
            echo '<div class="obx-loop-container">' . ($message !== '' ? '<div class="e-loop-nothing-found-message">' . wp_kses_post($message) . '</div>' : '') . '</div>';
            return;
        }
        $offset = ($paged - 1) * $per_page;
        $classes = 'obx-loop elementor-loop-container elementor-grid' . (($s['masonry'] ?? '') === 'yes' ? ' obx-loop--masonry' : '');
        echo '<div class="obx-loop-container"><div class="' . esc_attr($classes) . '">';
        $css = [];
        foreach ($query->posts as $i => $post) {
            [$id, $span] = self::template_for($s, $offset + $i + 1, $template);
            $item = $this->loop_item($id, $post, !isset($css[$id]));
            $css[$id] = true;
            Query::$shown[] = $post->ID;
            echo $span > 1 ? str_replace('class="e-loop-item ', 'style="grid-column: span ' . $span . '" class="e-loop-item ', $item) : $item; // Elementor output.
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

    public function get_title(): string { return 'Carrusel de bucle'; }
    public function get_icon(): string { return 'eicon-carousel-loop'; }
    public function get_keywords(): array { return ['loop','bucle','carousel','carrusel','slider','query','posts','entradas']; }
    protected function is_dynamic_content(): bool { return true; }

    protected function register_controls(): void {
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->template_control();
        $this->add_control('posts_per_page', ['label'=>'Elementos','type'=>Controls_Manager::NUMBER,'default'=>6,'min'=>1,'max'=>50,'separator'=>'before']);
        $this->add_control('equal_height', ['label'=>'Misma altura','type'=>Controls_Manager::SWITCHER,'selectors'=>self::css('.obx-carousel__slide > .e-loop-item, .obx-carousel__slide > .e-loop-item > .elementor', 'height: 100%;')]);
        $this->end_controls_section();

        Query::controls($this, 'post_query_');

        $this->start_controls_section('section_carousel_options', ['label'=>'Carrusel']);
        $this->carousel_controls(['show'=>'slides_to_show','scroll'=>'slides_to_scroll','gap'=>'image_spacing_custom','arrows'=>'arrows','loop'=>'infinite'], ['show'=>'3','show_tablet'=>'2','show_mobile'=>'1']);
        $this->add_control('navigation_previous_icon', ['label'=>'Icono anterior','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-chevron-left','library'=>'fa-solid'],'condition'=>['arrows'=>'yes']]);
        $this->add_control('navigation_next_icon', ['label'=>'Icono siguiente','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-chevron-right','library'=>'fa-solid'],'condition'=>['arrows'=>'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('section_design_layout', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('image_spacing_custom', 'Separación', '.obx-carousel', '--obx-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $this->end_controls_section();

        $this->start_controls_section('section_navigation_style', ['label'=>'Navegación','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('arrows_size', 'Tamaño de las flechas', '.obx-carousel__arrow', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['min'=>6,'max'=>100]],'condition'=>['arrows'=>'yes']], true);
        $this->color('arrows_normal_color', 'Color', '.obx-carousel__arrow', 'color', ['condition'=>['arrows'=>'yes']]);
        $this->color('arrows_hover_color', 'Color al pasar el ratón', '.obx-carousel__arrow:hover', 'color', ['condition'=>['arrows'=>'yes']]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'arrows_normal_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-carousel__arrow','condition'=>['arrows'=>'yes']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'arrows_normal_border_type','selector'=>'{{WRAPPER}} .obx-carousel__arrow','condition'=>['arrows'=>'yes']]);
        $this->dimensions('arrows_border_radius', 'Radio del borde', '.obx-carousel__arrow', 'border-radius', ['condition'=>['arrows'=>'yes']]);
        $this->dimensions('arrows_padding', 'Relleno', '.obx-carousel__arrow', 'padding', ['condition'=>['arrows'=>'yes']]);
        $this->slider('dots_gap', 'Separación entre puntos', '.obx-carousel__pagination', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>40]],'condition'=>['pagination!'=>''],'separator'=>'before'], true);
        $this->slider('dots_size', 'Tamaño de los puntos', '.obx-carousel__pagination', '--obx-dot: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>30]],'condition'=>['pagination!'=>'']], true);
        $this->slider('dots_pagination_spacing', 'Distancia al contenido', '.obx-carousel__pagination', 'margin-top: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]],'condition'=>['pagination!'=>'']], true);
        $this->color('dots_color_inactive', 'Color de los puntos', '.obx-carousel__pagination', '--obx-dot-inactive', ['condition'=>['pagination!'=>'']]);
        $this->color('dots_color_active', 'Color del punto activo', '.obx-carousel__pagination', '--obx-dot-active', ['condition'=>['pagination!'=>'']]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $template = (int) ($s['template_id'] ?? 0);
        if (!$template) { $this->no_template_notice(); return; }
        $query = $this->loop_query($s, max(1, min(50, (int) ($s['posts_per_page'] ?? 6) ?: 6)), 1);
        if (!$query->have_posts()) { $this->placeholder('No hay entradas que coincidan con la consulta.'); return; }
        $prev = !empty($s['navigation_previous_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($s['navigation_previous_icon'], ['aria-hidden'=>'true']) : '';
        $next = !empty($s['navigation_next_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($s['navigation_next_icon'], ['aria-hidden'=>'true']) : '';
        $count = count($query->posts);
        echo '<div class="obx-carousel obx-loop-carousel" data-carousel="' . esc_attr($this->carousel_options($s, 'arrows', 'infinite')) . '" role="region" aria-roledescription="carrusel" aria-label="Carrusel"><div class="obx-carousel__track">';
        foreach ($query->posts as $i => $post) {
            echo '<div class="obx-carousel__slide" role="group" aria-roledescription="diapositiva" aria-label="' . esc_attr(($i + 1) . ' / ' . $count) . '">' . $this->loop_item($template, $post, $i === 0) . '</div>'; // Elementor output.
            Query::$shown[] = $post->ID;
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
