<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/** Pagination controls and markup shared by post grids and loop grids (Elementor Pro's keys). */
trait Paginates {
    /**
     * Pagination with Elementor Pro's keys. $condition hides the whole section (the Loop Grid's term skins);
     * $loop adds what only the Loop Grid has: AJAX page loads with auto scroll and the full «Load more» button.
     */
    protected function pagination_controls(bool $nothing_found = true, array $condition = [], bool $loop = false): void {
        $this->start_controls_section('section_pagination', ['label'=>'Paginación'] + ($condition ? ['condition'=>$condition] : []));
        $this->add_control('pagination_type', ['label'=>'Paginación','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguna','numbers'=>'Números','prev_next'=>'Anterior/Siguiente','numbers_and_prev_next'=>'Números + Anterior/Siguiente','load_more_on_click'=>'Botón «Cargar más»','load_more_infinite_scroll'=>'Desplazamiento infinito']]);
        $numbers = ['pagination_type'=>['numbers','numbers_and_prev_next','prev_next']];
        $more = ['pagination_type'=>['load_more_on_click','load_more_infinite_scroll']];
        $click = ['pagination_type'=>'load_more_on_click'];
        $this->add_control('pagination_page_limit', ['label'=>'Límite de páginas','type'=>Controls_Manager::NUMBER,'default'=>'5','condition'=>['pagination_type!'=>'']]);
        $this->add_control('pagination_numbers_shorten', ['label'=>'Acortar','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','condition'=>['pagination_type'=>['numbers','numbers_and_prev_next']]]);
        $this->add_control('pagination_prev_label', ['label'=>'Texto «Anterior»','type'=>Controls_Manager::TEXT,'default'=>'&laquo; Anterior','condition'=>['pagination_type'=>['prev_next','numbers_and_prev_next']]]);
        $this->add_control('pagination_next_label', ['label'=>'Texto «Siguiente»','type'=>Controls_Manager::TEXT,'default'=>'Siguiente &raquo;','condition'=>['pagination_type'=>['prev_next','numbers_and_prev_next']]]);
        $this->add_control('pagination_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::flex_align_options(),'selectors_dictionary'=>['left'=>'flex-start','start'=>'flex-start','right'=>'flex-end','end'=>'flex-end','center'=>'center'],'selectors'=>['{{WRAPPER}} .obx-pagination'=>'justify-content: {{VALUE}};'],'condition'=>$numbers]);
        if ($loop) {
            $this->add_control('pagination_load_type', ['label'=>'Carga','type'=>Controls_Manager::SELECT,'default'=>'page_reload','options'=>['page_reload'=>'Recargar la página','ajax'=>'Sin recargar (AJAX)'],'condition'=>$numbers,'frontend_available'=>true]);
            $this->add_control('auto_scroll', ['label'=>'Desplazar al inicio del widget','type'=>Controls_Manager::SWITCHER,'condition'=>['pagination_load_type'=>['ajax']] + $numbers,'frontend_available'=>true]);
            $this->add_control('auto_scroll_offset', ['label'=>'Margen superior (px)','type'=>Controls_Manager::NUMBER,'default'=>0,'condition'=>['pagination_load_type'=>['ajax'],'auto_scroll'=>'yes'] + $numbers,'frontend_available'=>true]);
        }
        $this->add_control('pagination_individual_handle', ['label'=>'Paginación independiente','type'=>Controls_Manager::SWITCHER,'description'=>'Cada widget pasa de página por su cuenta (parámetro e-page-ID en la dirección).','condition'=>$numbers + ($loop ? ['pagination_load_type'=>['page_reload']] : [])]);
        $this->add_control('load_more_spinner', ['label'=>'Icono de carga','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-spinner','library'=>'fa-solid'],'exclude_inline_options'=>['svg'],'condition'=>$more,'separator'=>'before']);
        $this->add_control('text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Cargar más','condition'=>$click]);
        $this->add_control('selected_icon', ['label'=>'Icono del botón','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'condition'=>$click]);
        $this->add_control('icon_align', ['label'=>'Posición del icono','type'=>Controls_Manager::CHOOSE,'default'=>'row','options'=>['row'=>['title'=>'Antes','icon'=>'eicon-h-align-left'],'row-reverse'=>['title'=>'Después','icon'=>'eicon-h-align-right']],'selectors'=>['{{WRAPPER}} .obx-load-more .elementor-button-content-wrapper'=>'flex-direction: {{VALUE}};'],'condition'=>$click + ['text!'=>'','selected_icon[value]!'=>'']]);
        $this->add_control('icon_indent', ['label'=>'Separación del icono','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-load-more .elementor-button-content-wrapper'=>'gap: {{SIZE}}{{UNIT}};'],'condition'=>$click + ['text!'=>'','selected_icon[value]!'=>'']]);
        $this->add_control('button_css_id', ['label'=>'ID del botón','type'=>Controls_Manager::TEXT,'default'=>'','ai'=>['active'=>false],'description'=>'Sin espacios ni «#».','condition'=>$click]);
        if ($loop) {
            $this->add_control('load_more_button_align', ['label'=>'Alineación del botón','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::flex_align_options() + ['justify'=>['title'=>'Ancho completo','icon'=>'eicon-h-align-stretch']],'prefix_class'=>'obx-load-more-align-','condition'=>$click]);
        }
        $this->add_control('load_more_no_posts_message_switcher', ['label'=>'Mensaje al terminar','type'=>Controls_Manager::SWITCHER,'condition'=>$more,'separator'=>'before']);
        $this->add_control('load_more_no_posts_custom_message', ['label'=>'Mensaje','type'=>Controls_Manager::TEXT,'default'=>'No hay más entradas','label_block'=>true,'condition'=>$more + ['load_more_no_posts_message_switcher'=>'yes']]);
        $this->add_control('load_more_no_posts_message_align', ['label'=>'Alineación del mensaje','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options() + ['justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],'selectors'=>['{{WRAPPER}} .obx-load-more__message'=>'text-align: {{VALUE}};'],'condition'=>$more + ['load_more_no_posts_message_switcher'=>'yes']]);
        $this->end_controls_section();
        if (!$nothing_found) { return; }

        $this->start_controls_section('section_nothing_found', ['label'=>'Sin resultados']);
        $this->add_control('nothing_found_message', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>static::SKIN_PREFIX ? 'Parece que no encontramos lo que buscas.' : '']);
        $this->end_controls_section();
    }

    protected static function flex_align_options(): array {
        return ['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right']];
    }

    protected function pagination_style(): void {
        $this->start_controls_section('section_pagination_style', ['label'=>'Paginación','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['pagination_type!'=>'']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'pagination_typography','selector'=>'{{WRAPPER}} .obx-pagination']);
        $this->start_controls_tabs('pagination_colors', ['condition'=>['pagination_type!'=>['load_more_on_click','load_more_infinite_scroll','']]]);
        $this->start_controls_tab('pagination_color_normal', ['label'=>'Normal']);
        $this->add_control('pagination_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-pagination .page-numbers:not(.dots)'=>'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('pagination_color_hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('pagination_hover_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-pagination a.page-numbers:hover'=>'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('pagination_color_active', ['label'=>'Activo']);
        $this->add_control('pagination_active_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-pagination .page-numbers.current'=>'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_responsive_control('pagination_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'default'=>['size'=>10],'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-pagination'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('pagination_spacing_top', ['label'=>'Distancia','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-pagination, {{WRAPPER}} .obx-load-more'=>'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->add_control('load_more_color', ['label'=>'Color del botón «Cargar más»','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-load-more__button'=>'color: {{VALUE}};'],'condition'=>['pagination_type'=>'load_more_on_click'],'separator'=>'before']);
        $this->add_control('load_more_background_color', ['label'=>'Fondo del botón «Cargar más»','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-load-more__button'=>'background-color: {{VALUE}};'],'condition'=>['pagination_type'=>'load_more_on_click']]);
        $this->end_controls_section();
    }

    /**
     * «Load more» button and end message (Pro's «section_style»). With $button the button gets Elementor's own
     * button style controls (Button_Trait keys), scoped to the «Load more» box so buttons inside the items keep theirs.
     */
    protected function load_more_style(bool $button, bool $align = false): void {
        $more = ['pagination_type'=>['load_more_on_click','load_more_infinite_scroll']];
        $this->start_controls_section('section_style', ['label'=>'Cargar más','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$more]);
        if ($button && method_exists($this, 'register_button_style_controls')) {
            $before = array_keys($this->get_controls());
            // Elementor's «elementor-align-*» class would align the whole widget, its text included: Pro's «align»
            // gets our own class, which only moves the «Load more» button (frontend.css).
            $this->register_button_style_controls(['section_condition'=>['pagination_type'=>'load_more_on_click'],'alignment_default'=>'center','alignment_control_prefix_class'=>'obx-load-more%s-btn-']);
            if (!$align) { $this->remove_control('align'); }
            foreach (array_diff(array_keys($this->get_controls()), $before) as $id) {
                $control = $this->get_controls($id);
                if (!empty($control['selectors'])) {
                    $selectors = [];
                    foreach ($control['selectors'] as $selector => $css) { $selectors[str_replace('{{WRAPPER}} .elementor-button', '{{WRAPPER}} .obx-load-more .elementor-button', $selector)] = $css; }
                    $this->update_control($id, ['selectors'=>$selectors]);
                }
            }
            $this->update_control('button_hover_transition_duration', ['condition'=>$more]);
        }
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'load_more_no_posts_message','selector'=>'{{WRAPPER}} .obx-load-more__message','separator'=>'before']);
        $this->add_control('load_more_no_posts_message_color', ['label'=>'Color del mensaje','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-load-more__message'=>'color: {{VALUE}};']]);
        $this->add_control('load_more_spinner_color', ['label'=>'Color del icono de carga','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-load-more__spinner'=>'color: {{VALUE}}; fill: {{VALUE}};'],'condition'=>['load_more_spinner[value]!'=>'']]);
        $this->add_responsive_control('load_more_spacing', ['label'=>'Distancia a los elementos','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>200]],'selectors'=>['{{WRAPPER}} .obx-load-more'=>'margin-top: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    protected function current_page(): int {
        $arg = $this->page_arg();
        if ($arg !== null) { return max(1, absint($_GET[$arg] ?? 1)); } // phpcs:ignore WordPress.Security.NonceVerification -- read-only page number.
        return max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    }

    protected function pagination(array $s, \WP_Query $query): void {
        $type = (string) ($s['pagination_type'] ?? '');
        $limit = absint($s['pagination_page_limit'] ?? 5);
        $total = (int) $query->max_num_pages;
        if ($limit) { $total = min($total, $limit); }
        if ($type === '' || $total < 2) { return; }
        $current = min($this->current_page(), $total);
        $arg = $this->page_arg();
        $base = str_replace('999999999', '%#%', $arg !== null ? add_query_arg($arg, 999999999) : get_pagenum_link(999999999, false));
        $format = '';
        $link = static fn(int $page) => str_replace('%#%', (string) $page, $base);

        if (str_starts_with($type, 'load_more')) {
            if ($current >= $total) { return; }
            $infinite = $type === 'load_more_infinite_scroll';
            $raw = $this->get_data('settings');
            // Before 0.9.0 the message had no switch: an explicit message still shows.
            $show_message = ($s['load_more_no_posts_message_switcher'] ?? '') === 'yes' || (!isset($raw['load_more_no_posts_message_switcher']) && isset($raw['load_more_no_posts_custom_message']));
            $spinner = !empty($s['load_more_spinner']['value']) ? (string) \Elementor\Icons_Manager::try_get_icon_html($s['load_more_spinner'], ['aria-hidden'=>'true']) : '';
            echo '<div class="obx-load-more" data-next="' . esc_attr($link($current + 1)) . '" data-page="' . esc_attr((string) $current) . '" data-max="' . esc_attr((string) $total) . '" data-mode="' . ($infinite ? 'scroll' : 'click') . '" data-done="' . esc_attr($show_message ? (string) ($s['load_more_no_posts_custom_message'] ?? '') : '') . '">';
            if (!$infinite) {
                $icon = !empty($s['selected_icon']['value']) ? (string) \Elementor\Icons_Manager::try_get_icon_html($s['selected_icon'], ['aria-hidden'=>'true']) : '';
                $id = sanitize_html_class((string) ($s['button_css_id'] ?? ''));
                $animation = sanitize_html_class((string) ($s['hover_animation'] ?? ''));
                echo '<button type="button"' . ($id !== '' ? ' id="' . esc_attr($id) . '"' : '') . ' class="obx-load-more__button elementor-button elementor-size-sm' . ($animation !== '' ? ' elementor-animation-' . esc_attr($animation) : '') . '"><span class="elementor-button-content-wrapper">'
                    . ($icon !== '' ? '<span class="elementor-button-icon">' . $icon . '</span>' : '')
                    . '<span class="elementor-button-text">' . esc_html(($s['text'] ?? '') ?: 'Cargar más') . '</span></span></button>'; // Icon HTML from Elementor.
            }
            echo '<span class="obx-load-more__spinner" aria-hidden="true">' . $spinner . '</span>'; // Icon HTML from Elementor.
            echo '<p class="obx-load-more__message" role="status" hidden></p></div>';
            return;
        }
        $prev = wp_kses_post((string) ($s['pagination_prev_label'] ?? ''));
        $next = wp_kses_post((string) ($s['pagination_next_label'] ?? ''));
        $parts = [];
        if ($type !== 'numbers') {
            $parts[] = $current > 1 ? '<a class="page-numbers prev" href="' . esc_url($link($current - 1)) . '">' . $prev . '</a>' : '<span class="page-numbers prev" aria-disabled="true">' . $prev . '</span>';
        }
        if ($type !== 'prev_next') {
            $shorten = ($s['pagination_numbers_shorten'] ?? '') === 'yes';
            $numbers = paginate_links(['base'=>$base,'format'=>$format,'total'=>$total,'current'=>$current,'show_all'=>!$shorten,'end_size'=>1,'mid_size'=>1,'prev_next'=>false,'type'=>'array']);
            $parts = array_merge($parts, (array) $numbers);
        }
        if ($type !== 'numbers') {
            $parts[] = $current < $total ? '<a class="page-numbers next" href="' . esc_url($link($current + 1)) . '">' . $next . '</a>' : '<span class="page-numbers next" aria-disabled="true">' . $next . '</span>';
        }
        $ajax = ($s['pagination_load_type'] ?? '') === 'ajax' ? ' data-ajax="1" data-scroll="' . (($s['auto_scroll'] ?? '') === 'yes' ? '1' : '') . '" data-offset="' . esc_attr((string) (int) ($s['auto_scroll_offset'] ?? 0)) . '"' : '';
        echo '<nav class="obx-pagination" aria-label="Paginación"' . $ajax . '>' . implode('', $parts) . '</nav>'; // Escaped above.
    }
}

/**
 * Post grids with Elementor Pro's data layout: a `_skin` setting (classic, cards, full_content)
 * and every layout/style key prefixed with the skin id ("classic_columns", "archive_cards_title_color").
 */
abstract class Posts_Base extends Base {
    use Paginates;
    use \Elementor\Includes\Widgets\Traits\Button_Trait;
    /** Prefix of the skin ids: '' for the Posts widget, 'archive_' for Archive Posts. */
    protected const SKIN_PREFIX = '';
    public const SKINS = ['classic'=>'Clásico','cards'=>'Tarjetas','full_content'=>'Contenido completo'];

    /** IDs already shown on this page, for «Evitar duplicados». */
    protected static array $shown = [];
    /** Posts being rendered by the full content skin, to avoid recursion. */
    private static array $rendering = [];

    public function get_script_depends(): array { return ['obx-widgets']; }
    /** Rendered on the server: Button_Trait brings a JS template meant for the Button widget. */
    protected function content_template() {}

    public static function excerpt(\WP_Post $post, int $length, bool $trim_custom = false): string {
        $length = max(1, $length ?: 25);
        if (has_excerpt($post)) {
            $text = wp_strip_all_tags(get_the_excerpt($post));
            return $trim_custom ? wp_trim_words($text, $length) : $text;
        }
        return wp_trim_words(wp_strip_all_tags(strip_shortcodes(excerpt_remove_blocks($post->post_content))), $length);
    }

    /** Section(s) placed before the layout (the Posts widget adds its query). */
    protected function query_controls(): void {}

    abstract protected function query(): \WP_Query;

    /** Query arg used for pagination; null means the main query's `paged`. */
    protected function page_arg(): ?string { return null; }

    protected function skin(): string {
        $skin = (string) ($this->get_settings('_skin') ?: static::SKIN_PREFIX . 'classic');
        return array_key_exists(substr($skin, strlen(static::SKIN_PREFIX)), self::SKINS) && str_starts_with($skin, static::SKIN_PREFIX) ? $skin : static::SKIN_PREFIX . 'classic';
    }

    protected function register_controls(): void {
        $skins = [];
        foreach (self::SKINS as $id => $label) { $skins[static::SKIN_PREFIX . $id] = $label; }
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->add_control('_skin', ['label'=>'Apariencia','type'=>Controls_Manager::SELECT,'default'=>static::SKIN_PREFIX . 'classic','options'=>$skins,'label_block'=>true]);
        foreach (array_keys(self::SKINS) as $skin) { $this->layout_controls(static::SKIN_PREFIX . $skin, $skin); }
        $this->end_controls_section();

        $this->query_controls();
        $this->pagination_controls();

        foreach (array_keys(self::SKINS) as $skin) { $this->style_controls(static::SKIN_PREFIX . $skin, $skin); }
        $this->pagination_style();
        $this->load_more_style(true, true);
        $this->start_controls_section('section_nothing_found_style', ['label'=>'Sin resultados','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['nothing_found_message!'=>'']]);
        $this->add_control('nothing_found_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'condition'=>['nothing_found_message!'=>''],'selectors'=>['{{WRAPPER}} .obx-posts-nothing-found'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'nothing_found_typography','selector'=>'{{WRAPPER}} .obx-posts-nothing-found','condition'=>['nothing_found_message!'=>'']]);
        $this->end_controls_section();
    }

    private function layout_controls(string $id, string $skin): void {
        $c = ['_skin'=>$id];
        $p = $id . '_';
        if ($skin !== 'full_content') {
            $this->add_responsive_control($p . 'columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'default'=>'3','tablet_default'=>'2','mobile_default'=>'1','options'=>array_combine(range(1, 6), range(1, 6)),'selectors'=>['{{WRAPPER}} .obx-posts'=>'--obx-columns: {{VALUE}};'],'condition'=>$c]);
        }
        if (static::SKIN_PREFIX === '') {
            $this->add_control($p . 'posts_per_page', ['label'=>'Entradas por página','type'=>Controls_Manager::NUMBER,'default'=>6,'min'=>1,'max'=>100,'condition'=>$c]);
        }
        $positions = $skin === 'cards' ? ['top'=>'Arriba','none'=>'Ninguna'] : ['top'=>'Arriba','left'=>'Izquierda','right'=>'Derecha','none'=>'Ninguna'];
        $this->add_control($p . 'thumbnail', ['label'=>'Imagen','type'=>Controls_Manager::SELECT,'default'=>'top','options'=>$positions,'condition'=>$c]);
        $thumb = $c + [$p . 'thumbnail!'=>'none'];
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>$p . 'thumbnail_size','default'=>'medium','exclude'=>['custom'],'condition'=>$thumb]);
        $this->add_responsive_control($p . 'item_ratio', ['label'=>'Proporción de la imagen','type'=>Controls_Manager::SLIDER,'default'=>['size'=>0.66],'range'=>['px'=>['min'=>0.1,'max'=>2,'step'=>0.01]],'selectors'=>['{{WRAPPER}} .obx-post__thumb'=>'aspect-ratio: 1 / {{SIZE}};'],'condition'=>$skin === 'full_content' ? $c + [$p . 'thumbnail!'=>''] : $thumb]);
        if ($skin !== 'cards') {
            $this->add_responsive_control($p . 'image_width', ['label'=>'Ancho de la imagen','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['size'=>100,'unit'=>'%'],'range'=>['%'=>['min'=>10,'max'=>100],'px'=>['min'=>10,'max'=>600]],'selectors'=>['{{WRAPPER}} .obx-post__thumb-link'=>'width: {{SIZE}}{{UNIT}};'],'condition'=>$thumb]);
        }
        if ($skin !== 'full_content') {
            $this->add_control($p . 'masonry', ['label'=>'Mampostería','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','description'=>'Cada entrada sube hasta la de encima, sin huecos, manteniendo el orden.','condition'=>$c + [$p . 'columns!'=>'1',$p . 'thumbnail'=>'top']]);
        }
        $this->add_control($p . 'show_title', ['label'=>'Título','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','separator'=>'before','condition'=>$c]);
        $this->add_control($p . 'title_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'default'=>'h3','options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span','p'=>'p'],'condition'=>$c + [$p . 'show_title'=>'yes']]);
        if ($skin !== 'full_content') {
            $this->add_control($p . 'show_excerpt', ['label'=>'Extracto','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','condition'=>$c]);
            $this->add_control($p . 'excerpt_length', ['label'=>'Palabras del extracto','type'=>Controls_Manager::NUMBER,'default'=>25,'condition'=>$c + [$p . 'show_excerpt'=>'yes']]);
            $this->add_control($p . 'apply_to_custom_excerpt', ['label'=>'Recortar también los extractos manuales','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'no','condition'=>$c + [$p . 'show_excerpt'=>'yes']]);
        }
        $this->add_control($p . 'meta_data', ['label'=>'Metadatos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'default'=>['date','comments'],'options'=>['author'=>'Autor','date'=>'Fecha','time'=>'Hora','comments'=>'Comentarios','modified'=>'Fecha de modificación'],'separator'=>'before','condition'=>$c]);
        $this->add_control($p . 'meta_separator', ['label'=>'Separador','type'=>Controls_Manager::TEXT,'default'=>$skin === 'cards' ? '•' : ($skin === 'full_content' ? '///' : '|'),'selectors'=>['{{WRAPPER}} .obx-post__meta > span + span::before'=>'content: "{{VALUE}}";'],'condition'=>$c + [$p . 'meta_data!'=>[]]]);
        if ($skin !== 'full_content') {
            $this->add_control($p . 'show_read_more', ['label'=>'Leer más','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','separator'=>'before','condition'=>$c]);
            $this->add_control($p . 'read_more_text', ['label'=>'Texto de «Leer más»','type'=>Controls_Manager::TEXT,'default'=>'Leer más »','condition'=>$c + [$p . 'show_read_more'=>'yes']]);
            $this->add_control($p . 'read_more_alignment', ['label'=>'Alinear «Leer más» abajo','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','description'=>'En cada fila, los enlaces quedan a la misma altura.','condition'=>$c + [$p . 'masonry!'=>'yes',$p . 'show_read_more'=>'yes']]);
        }
        $this->add_control($p . 'open_new_tab', ['label'=>'Abrir en una pestaña nueva','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'no','condition'=>$c]);
        if ($skin === 'cards') {
            $this->add_control($p . 'show_badge', ['label'=>'Distintivo','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','separator'=>'before','condition'=>$c]);
            $taxonomies = [''=>'Ninguna'];
            foreach (get_taxonomies(['public'=>true,'show_in_nav_menus'=>true], 'objects') as $taxonomy) { $taxonomies[$taxonomy->name] = $taxonomy->label; }
            $this->add_control($p . 'badge_taxonomy', ['label'=>'Taxonomía del distintivo','type'=>Controls_Manager::SELECT2,'label_block'=>true,'default'=>'category','options'=>$taxonomies,'condition'=>$c + [$p . 'show_badge'=>'yes']]);
            $this->add_control($p . 'show_avatar', ['label'=>'Avatar del autor','type'=>Controls_Manager::SWITCHER,'return_value'=>'show-avatar','default'=>'show-avatar','condition'=>$c + [$p . 'thumbnail!'=>'none']]);
        }
    }

    private function style_controls(string $id, string $skin): void {
        $c = ['_skin'=>$id];
        $p = $id . '_';
        $this->start_controls_section($p . 'section_design_layout', ['label'=>'Diseño','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$c]);
        if ($skin !== 'full_content') {
            $this->add_responsive_control($p . 'column_gap', ['label'=>'Separación entre columnas','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'default'=>['size'=>30],'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-posts'=>'column-gap: {{SIZE}}{{UNIT}};']]);
        }
        $this->add_responsive_control($p . 'row_gap', ['label'=>'Separación entre filas','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'default'=>['size'=>35],'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-posts'=>'row-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control($p . 'alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors'=>['{{WRAPPER}} .obx-post__text'=>'text-align: {{VALUE}};']]);
        $this->end_controls_section();

        $box = $skin === 'cards' ? '.obx-post__card' : '.obx-post';
        $this->start_controls_section($p . 'section_design_box', ['label'=>$skin === 'cards' ? 'Tarjeta' : 'Caja','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$c]);
        if ($skin === 'cards') {
            $this->add_control($p . 'card_bg_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__card'=>'background-color: {{VALUE}};']]);
            $this->add_control($p . 'card_border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__card'=>'border-color: {{VALUE}};']]);
            $this->add_control($p . 'card_border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>15]],'selectors'=>['{{WRAPPER}} .obx-post__card'=>'border-width: {{SIZE}}{{UNIT}}; border-style: solid;']]);
            $this->add_control($p . 'card_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'range'=>['px'=>['max'=>200]],'selectors'=>['{{WRAPPER}} .obx-post__card'=>'border-radius: {{SIZE}}{{UNIT}};']]);
            $this->add_control($p . 'card_padding', ['label'=>'Relleno horizontal','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post__text, {{WRAPPER}} .obx-post__meta-footer'=>'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};']]);
            $this->add_control($p . 'card_vertical_padding', ['label'=>'Relleno vertical','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post__card'=>'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};']]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$p . 'box_shadow','selector'=>'{{WRAPPER}} .obx-post__card']);
            $this->add_control($p . 'hover_effect', ['label'=>'Efecto al pasar el ratón','type'=>Controls_Manager::SELECT,'default'=>'gradient','options'=>['none'=>'Ninguno','gradient'=>'Degradado']]);
            $this->add_control($p . 'meta_border_color', ['label'=>'Color del borde de los metadatos','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__meta-footer'=>'border-top-color: {{VALUE}};']]);
        } else {
            $this->add_control($p . 'box_border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em','rem','custom'],'selectors'=>['{{WRAPPER}} .obx-post'=>'border-style: solid; border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
            $this->add_control($p . 'box_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','custom'],'range'=>['px'=>['max'=>200]],'selectors'=>['{{WRAPPER}} .obx-post'=>'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;']]);
            $this->add_responsive_control($p . 'box_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em','rem','custom'],'selectors'=>['{{WRAPPER}} .obx-post'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
            $this->add_responsive_control($p . 'content_padding', ['label'=>'Relleno del contenido','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em','rem','custom'],'selectors'=>['{{WRAPPER}} .obx-post__text'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
            $this->start_controls_tabs($p . 'bg_effects_tabs');
            $this->start_controls_tab($p . 'style_normal', ['label'=>'Normal']);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$p . 'box_shadow','selector'=>'{{WRAPPER}} .obx-post']);
            $this->add_control($p . 'box_bg_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post'=>'background-color: {{VALUE}};']]);
            $this->add_control($p . 'box_border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post'=>'border-color: {{VALUE}};']]);
            $this->end_controls_tab();
            $this->start_controls_tab($p . 'style_hover', ['label'=>'Al pasar el ratón']);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>$p . 'box_shadow_hover','selector'=>'{{WRAPPER}} .obx-post:hover']);
            $this->add_control($p . 'box_bg_color_hover', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post:hover'=>'background-color: {{VALUE}};']]);
            $this->add_control($p . 'box_border_color_hover', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post:hover'=>'border-color: {{VALUE}};']]);
            $this->end_controls_tab();
            $this->end_controls_tabs();
        }
        $this->end_controls_section();

        $this->start_controls_section($p . 'section_design_image', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$c + [$p . 'thumbnail!'=>'none']]);
        $this->add_control($p . 'img_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}} .obx-post__thumb'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control($p . 'image_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-post'=>'--obx-image-spacing: {{SIZE}}{{UNIT}};']]);
        if ($skin === 'cards') {
            $this->add_control($p . 'avatar_size', ['label'=>'Tamaño del avatar','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>20,'max'=>120]],'condition'=>[$p . 'show_avatar'=>'show-avatar'],
                'selectors'=>['{{WRAPPER}} .obx-post__avatar img'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};','{{WRAPPER}} .obx-post__avatar'=>'--obx-avatar-size: {{SIZE}}{{UNIT}};']]);
        }
        $this->start_controls_tabs($p . 'thumbnail_effects_tabs');
        $this->start_controls_tab($p . 'normal', ['label'=>'Normal']);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>$p . 'thumbnail_filters','selector'=>'{{WRAPPER}} .obx-post__thumb img']);
        $this->end_controls_tab();
        $this->start_controls_tab($p . 'hover', ['label'=>'Al pasar el ratón']);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>$p . 'thumbnail_hover_filters','selector'=>'{{WRAPPER}} .obx-post:hover .obx-post__thumb img']);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        if ($skin === 'cards') {
            $this->start_controls_section($p . 'section_design_badge', ['label'=>'Distintivo','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$c + [$p . 'show_badge'=>'yes']]);
            $this->add_control($p . 'badge_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'default'=>'right','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'selectors_dictionary'=>['left'=>'left: 0; right: auto','right'=>'right: 0; left: auto'],'selectors'=>['{{WRAPPER}} .obx-post__badge'=>'{{VALUE}};']]);
            $this->add_control($p . 'badge_bg_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__badge'=>'background-color: {{VALUE}};']]);
            $this->add_control($p . 'badge_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__badge'=>'color: {{VALUE}};']]);
            $this->add_control($p . 'badge_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post__badge'=>'border-radius: {{SIZE}}{{UNIT}};']]);
            $this->add_control($p . 'badge_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>5,'max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post__badge'=>'font-size: {{SIZE}}{{UNIT}};']]);
            $this->add_control($p . 'badge_margin', ['label'=>'Margen','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post__badge'=>'margin: {{SIZE}}{{UNIT}};']]);
            $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$p . 'badge_typography','selector'=>'{{WRAPPER}} .obx-post__badge']);
            $this->end_controls_section();
        }

        $this->start_controls_section($p . 'section_design_content', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$c]);
        foreach (['title'=>['Título','.obx-post__title, {{WRAPPER}} .obx-post__title a'],'meta'=>['Metadatos','.obx-post__meta'],'excerpt'=>['Extracto','.obx-post__excerpt'],'read_more'=>['Leer más','.obx-post__read-more']] as $part => [$label, $selector]) {
            $on = in_array($part, ['excerpt','read_more'], true) ? [$p . 'show_' . $part=>'yes'] : [];
            $this->add_control($p . 'heading_' . $part . '_style', ['label'=>$label,'type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>$on]);
            $this->add_control($p . $part . '_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector=>'color: {{VALUE}};'],'condition'=>$on]);
            if ($part === 'meta') { $this->add_control($p . 'meta_separator_color', ['label'=>'Color del separador','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__meta > span + span::before'=>'color: {{VALUE}};']]); }
            $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$p . $part . '_typography','selector'=>'{{WRAPPER}} ' . $selector,'condition'=>$on]);
            if ($part === 'title') { $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>$p . 'text_stroke','selector'=>'{{WRAPPER}} .obx-post__title']); }
            $this->add_responsive_control($p . $part . '_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-post__' . str_replace('_', '-', $part)=>'margin-bottom: {{SIZE}}{{UNIT}};'],'condition'=>$on]);
        }
        if ($skin === 'full_content') {
            $this->add_control($p . 'heading_content_style', ['label'=>'Texto','type'=>Controls_Manager::HEADING,'separator'=>'before']);
            $this->add_control($p . 'content_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post__content'=>'color: {{VALUE}};']]);
            $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$p . 'content_typography','selector'=>'{{WRAPPER}} .obx-post__content']);
        }
        $this->end_controls_section();
    }




    protected function render(): void {
        $s = $this->get_settings_for_display();
        $id = $this->skin();
        $skin = substr($id, strlen(static::SKIN_PREFIX));
        $v = static fn(string $key, $default = '') => $s[$id . '_' . $key] ?? $default;
        $query = $this->query();
        if (!$query->have_posts()) {
            $message = (string) ($s['nothing_found_message'] ?? '');
            if ($message !== '') { echo '<div class="obx-posts-nothing-found">' . wp_kses_post($message) . '</div>'; }
            elseif ($this->editing()) { $this->placeholder('No hay entradas que coincidan con la consulta.'); }
            return;
        }
        $thumbnail = (string) $v('thumbnail', 'top');
        $size = (string) $v('thumbnail_size_size', 'medium');
        $tag = in_array($v('title_tag', 'h3'), ['h1','h2','h3','h4','h5','h6','div','span','p'], true) ? $v('title_tag', 'h3') : 'h3';
        $meta = array_intersect((array) $v('meta_data', []), ['author','date','time','comments','modified']);
        $target = $v('open_new_tab') === 'yes' ? ' target="_blank" rel="noopener"' : '';

        $classes = ['obx-posts','obx-posts--' . $skin,'obx-posts--thumb-' . $thumbnail];
        if ($skin !== 'full_content' && $v('masonry') === 'yes' && $thumbnail === 'top') { $classes[] = 'obx-posts--masonry'; }
        elseif ($skin !== 'full_content' && $v('read_more_alignment') === 'yes' && $v('show_read_more', 'yes') === 'yes') { $classes[] = 'obx-posts--read-more-bottom'; }
        if ($skin === 'cards') {
            $classes[] = 'obx-posts--hover-' . ($v('hover_effect', 'gradient') === 'none' ? 'none' : 'gradient');
            if ($v('show_avatar', 'show-avatar') === 'show-avatar') { $classes[] = 'obx-posts--show-avatar'; }
        }
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        foreach ($query->posts as $post) {
            Query::$shown[] = $post->ID;
            $url = esc_url(get_permalink($post));
            echo '<article class="obx-post ' . esc_attr(implode(' ', get_post_class('', $post))) . '">';
            if ($skin === 'cards') { echo '<div class="obx-post__card">'; }
            if ($thumbnail !== 'none' && has_post_thumbnail($post)) {
                echo '<a class="obx-post__thumb-link" href="' . $url . '"' . $target . ' tabindex="-1" aria-hidden="true"><div class="obx-post__thumb">' . get_the_post_thumbnail($post, $size ?: 'medium', ['loading'=>'lazy','alt'=>'']) . '</div></a>';
                if ($skin === 'cards' && $v('show_badge') === 'yes') { $this->badge($post, (string) $v('badge_taxonomy', 'category')); }
                if ($skin === 'cards' && $v('show_avatar') === 'show-avatar') { echo '<div class="obx-post__avatar">' . get_avatar((int) $post->post_author, 128, '', '') . '</div>'; }
            } elseif ($skin === 'cards' && $v('show_badge') === 'yes') {
                $this->badge($post, (string) $v('badge_taxonomy', 'category'));
            }
            echo '<div class="obx-post__text">';
            if ($v('show_title', 'yes') === 'yes') { echo '<' . $tag . ' class="obx-post__title"><a href="' . $url . '"' . $target . '>' . esc_html(get_the_title($post)) . '</a></' . $tag . '>'; }
            if ($skin !== 'cards') { $this->meta($post, $meta); }
            if ($skin === 'full_content') {
                echo '<div class="obx-post__content">' . $this->full_content($post) . '</div>'; // Filtered post content.
            } else {
                if ($v('show_excerpt', 'yes') === 'yes') { echo '<div class="obx-post__excerpt"><p>' . esc_html(self::excerpt($post, (int) $v('excerpt_length', 25), $v('apply_to_custom_excerpt') === 'yes')) . '</p></div>'; }
                if ($v('show_read_more', 'yes') === 'yes' && $v('read_more_text') !== '') { echo '<a class="obx-post__read-more" href="' . $url . '"' . $target . ' aria-label="' . esc_attr(sprintf('Leer más sobre %s', get_the_title($post))) . '">' . esc_html($v('read_more_text')) . '</a>'; }
            }
            echo '</div>';
            if ($skin === 'cards' && $meta) { echo '<div class="obx-post__meta-footer">'; $this->meta($post, $meta); echo '</div>'; }
            if ($skin === 'cards') { echo '</div>'; }
            echo '</article>';
        }
        echo '</div>';
        $this->pagination($s, $query);
    }

    private function badge(\WP_Post $post, string $taxonomy): void {
        $terms = taxonomy_exists($taxonomy) ? get_the_terms($post, $taxonomy) : false;
        if (is_array($terms) && $terms) { echo '<div class="obx-post__badge">' . esc_html($terms[0]->name) . '</div>'; }
    }

    private function meta(\WP_Post $post, array $meta): void {
        if (!$meta) { return; }
        echo '<div class="obx-post__meta">';
        foreach ($meta as $item) {
            echo match ($item) {
                'author' => '<span class="obx-post__author">' . esc_html(get_the_author_meta('display_name', (int) $post->post_author)) . '</span>',
                'date' => '<span class="obx-post__date"><time datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(get_the_date('', $post)) . '</time></span>',
                'time' => '<span class="obx-post__time">' . esc_html(get_the_time('', $post)) . '</span>',
                'comments' => '<span class="obx-post__comments">' . esc_html(self::comments_label((int) get_comments_number($post))) . '</span>',
                'modified' => '<span class="obx-post__modified"><time datetime="' . esc_attr(get_the_modified_date('c', $post)) . '">' . esc_html(get_the_modified_date('', $post)) . '</time></span>',
                default => '',
            };
        }
        echo '</div>';
    }

    public static function comments_label(int $count): string {
        return $count === 0 ? 'Sin comentarios' : sprintf(_n('%d comentario', '%d comentarios', $count, 'oldbear-expansion'), $count);
    }

    private function full_content(\WP_Post $post): string {
        if (in_array($post->ID, self::$rendering, true) || (int) get_queried_object_id() === $post->ID || post_password_required($post)) {
            return '<p>' . esc_html(self::excerpt($post, 55)) . '</p>';
        }
        self::$rendering[] = $post->ID;
        $elementor = \Elementor\Plugin::$instance;
        $document = $elementor->documents->get($post->ID);
        if ($document && $document->is_built_with_elementor()) {
            $html = $elementor->frontend->get_builder_content_for_display($post->ID, true);
        } else {
            $GLOBALS['post'] = $post;
            setup_postdata($post);
            $html = apply_filters('the_content', $post->post_content);
            wp_reset_postdata();
        }
        array_pop(self::$rendering);
        return (string) $html;
    }

}

/** Posts of the current archive, search or blog page. */
final class Archive_Posts extends Posts_Base {
    public const CATEGORY = 'obx-archive';
    public const NAME = 'obx-archive-posts';
    public const PRO = 'archive-posts';
    protected const SKIN_PREFIX = 'archive_';
    public function get_title(): string { return 'Entradas del archivo'; }
    public function get_icon(): string { return 'eicon-archive-posts'; }
    public function get_keywords(): array { return ['posts','entradas','archive','archivo','loop','grid']; }
    protected function query(): \WP_Query { return Context::query(); }

    protected static function upgrade_settings(array $s): array { return self::upgrade_cards($s, 'archive_classic_'); }

    /** Settings of the original obx-archive-posts widget → classic skin keys. */
    public static function upgrade_cards(array $s, string $p): array {
        $map = ['columns'=>$p . 'columns','columns_tablet'=>$p . 'columns_tablet','columns_mobile'=>$p . 'columns_mobile','title_tag'=>$p . 'title_tag','show_excerpt'=>$p . 'show_excerpt','excerpt_length'=>$p . 'excerpt_length',
            'read_more'=>$p . 'read_more_text','empty'=>'nothing_found_message','card_bg'=>$p . 'box_bg_color','card_padding'=>$p . 'content_padding','title_color'=>$p . 'title_color','meta_color'=>$p . 'meta_color','excerpt_color'=>$p . 'excerpt_color','more_color'=>$p . 'read_more_color'];
        if (array_key_exists('show_image', $s)) { $s[$p . 'thumbnail'] = $s['show_image'] === 'yes' ? 'top' : 'none'; unset($s['show_image']); }
        if (isset($s['image_size'])) { $s[$p . 'thumbnail_size_size'] = $s['image_size']; unset($s['image_size']); }
        if (array_key_exists('show_date', $s)) { $s[$p . 'meta_data'] = $s['show_date'] === 'yes' ? ['date'] : []; unset($s['show_date']); }
        elseif (!isset($s[$p . 'meta_data'])) { $s[$p . 'meta_data'] = ['date']; }
        if (array_key_exists('pagination', $s) || !isset($s['pagination_type'])) { $s['pagination_type'] = ($s['pagination'] ?? 'yes') === 'yes' ? 'numbers' : ''; unset($s['pagination']); }
        if (isset($s['read_more']) && $s['read_more'] === '') { $s[$p . 'show_read_more'] = ''; }
        if (isset($s['gap'])) { $s[$p . 'column_gap'] = $s['gap']; $s[$p . 'row_gap'] = $s['gap']; unset($s['gap']); }
        if (isset($s['card_radius']) && is_array($s['card_radius'])) { $s[$p . 'box_border_radius'] = ['unit'=>$s['card_radius']['unit'] ?? 'px','size'=>(float) ($s['card_radius']['top'] ?? 0)]; unset($s['card_radius']); }
        $s = self::rename($s, $map);
        foreach (['title'=>'title','meta'=>'meta','excerpt'=>'excerpt','more'=>'read_more'] as $old => $new) { $s = self::rename_prefix($s, $old . '_typography_', $p . $new . '_typography_'); }
        $s = self::rename_prefix($s, 'card_border_', $p . 'box_border_');
        $s['_skin'] = rtrim(substr($p, 0, -1), '_');
        return $s;
    }
}

/** Posts from any query. */
final class Posts extends Posts_Base {
    public const PRO = 'posts';
    public const PRO_CATEGORY = 'pro-elements';
    public function get_title(): string { return 'Entradas'; }
    public function get_icon(): string { return 'eicon-post-list'; }
    public function get_keywords(): array { return ['posts','entradas','blog','grid','rejilla','query','consulta']; }

    /** v0.3 saved this widget with its own keys under the Pro name. */
    protected static function is_legacy(array $s): bool { return !isset($s['_skin']) && (isset($s['post_type']) || isset($s['posts_per_page']) || isset($s['show_image'])); }

    protected static function upgrade_settings(array $s): array {
        if (!self::is_legacy($s)) { return $s; }
        $s = Archive_Posts::upgrade_cards($s, 'classic_');
        $s = self::rename($s, ['post_type'=>'posts_post_type','posts_per_page'=>'classic_posts_per_page','offset'=>'posts_offset']);
        if (isset($s['orderby'])) { $s['posts_orderby'] = ['date'=>'post_date','title'=>'post_title','modified'=>'modified'][$s['orderby']] ?? $s['orderby']; unset($s['orderby']); }
        if (isset($s['order'])) { $s['posts_order'] = strtolower((string) $s['order']); unset($s['order']); }
        if (!empty($s['categories'])) { $s['posts_include'] = ['terms']; $s['posts_include_term_ids'] = array_map('strval', (array) $s['categories']); }
        unset($s['categories']);
        $s['posts_exclude'] = (($s['exclude_current'] ?? 'yes') === 'yes') ? ['current_post'] : [];
        unset($s['exclude_current']);
        return $s;
    }

    public static function wants_upgrade(string $name, array $settings): bool { return $name === self::PRO && self::is_legacy($settings); }

    protected function page_arg(): ?string { return 'e-page-' . $this->get_id(); }

    protected function query_controls(): void { Query::controls($this, 'posts_'); }

    protected function query(): \WP_Query {
        $s = $this->get_settings_for_display();
        $per_page = max(1, min(100, (int) ($s[$this->skin() . '_posts_per_page'] ?? 6) ?: 6));
        $paged = ($s['pagination_type'] ?? '') !== '' ? $this->current_page() : 1;
        return Query::build($s, 'posts_', $per_page, $paged, $this);
    }
}

/** Meta list of the current post (Elementor Pro «Post Info»). */
final class Post_Info extends Base {
    public const NAME = 'obx-post-info';
    public const PRO = 'post-info';
    public const PRO_CATEGORY = 'theme-elements-single';
    public function get_title(): string { return 'Información de la entrada'; }
    public function get_icon(): string { return 'eicon-post-info'; }
    public function get_keywords(): array { return ['meta','fecha','autor','categorias','info','post']; }
    public function get_style_depends(): array { return ['obx-frontend','elementor-icons-fa-regular','elementor-icons-fa-solid']; }

    public const DATE_FORMATS = ['default'=>'Predeterminado','0'=>'F j, Y','1'=>'Y-m-d','2'=>'m/d/Y','3'=>'d/m/Y','custom'=>'Personalizado'];
    public const TIME_FORMATS = ['default'=>'Predeterminado','0'=>'g:i a','1'=>'g:i A','2'=>'H:i','custom'=>'Personalizado'];
    private const ICONS = ['author'=>'far fa-user-circle','date'=>'fas fa-calendar','time'=>'far fa-clock','comments'=>'far fa-comment-dots','terms'=>'fas fa-tags','custom'=>'fas fa-info-circle'];

    protected function register_controls(): void {
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) { if ($taxonomy->name !== 'post_format') { $taxonomies[$taxonomy->name] = $taxonomy->label; } }
        $this->start_controls_section('section_icon', ['label'=>'Metadatos']);
        $this->add_control('view', ['label'=>'Disposición','type'=>Controls_Manager::CHOOSE,'default'=>'inline','options'=>['traditional'=>['title'=>'Predeterminada','icon'=>'eicon-editor-list-ul'],'inline'=>['title'=>'En línea','icon'=>'eicon-ellipsis-h']],'render_type'=>'template','label_block'=>false]);

        $r = new \Elementor\Repeater();
        $r->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'date','options'=>['author'=>'Autor','date'=>'Fecha','time'=>'Hora','comments'=>'Comentarios','terms'=>'Términos','custom'=>'Personalizado']]);
        $r->add_control('date_format', ['label'=>'Formato de fecha','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>self::DATE_FORMATS,'condition'=>['type'=>'date']]);
        $r->add_control('custom_date_format', ['label'=>'Formato personalizado','type'=>Controls_Manager::TEXT,'default'=>'F j, Y','condition'=>['type'=>'date','date_format'=>'custom']]);
        $r->add_control('time_format', ['label'=>'Formato de hora','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>self::TIME_FORMATS,'condition'=>['type'=>'time']]);
        $r->add_control('custom_time_format', ['label'=>'Formato personalizado','type'=>Controls_Manager::TEXT,'default'=>'g:i a','condition'=>['type'=>'time','time_format'=>'custom']]);
        $r->add_control('taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT2,'label_block'=>true,'default'=>[],'options'=>$taxonomies,'condition'=>['type'=>'terms']]);
        $r->add_control('text_prefix', ['label'=>'Texto previo','type'=>Controls_Manager::TEXT,'condition'=>['type!'=>'custom']]);
        $r->add_control('show_avatar', ['label'=>'Avatar','type'=>Controls_Manager::SWITCHER,'condition'=>['type'=>'author']]);
        $r->add_responsive_control('avatar_size', ['label'=>'Tamaño del avatar','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}} .obx-post-info__avatar'=>'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],'condition'=>['show_avatar'=>'yes']]);
        $r->add_control('comments_custom_strings', ['label'=>'Textos personalizados','type'=>Controls_Manager::SWITCHER,'default'=>false,'condition'=>['type'=>'comments']]);
        $r->add_control('string_no_comments', ['label'=>'Sin comentarios','type'=>Controls_Manager::TEXT,'placeholder'=>'Sin comentarios','condition'=>['comments_custom_strings'=>'yes','type'=>'comments']]);
        $r->add_control('string_one_comment', ['label'=>'Un comentario','type'=>Controls_Manager::TEXT,'placeholder'=>'1 comentario','condition'=>['comments_custom_strings'=>'yes','type'=>'comments']]);
        $r->add_control('string_comments', ['label'=>'Varios comentarios','type'=>Controls_Manager::TEXT,'placeholder'=>'%s comentarios','condition'=>['comments_custom_strings'=>'yes','type'=>'comments']]);
        $r->add_control('custom_text', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true],'label_block'=>true,'condition'=>['type'=>'custom']]);
        $r->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['type!'=>['time','custom']]]);
        $r->add_control('custom_url', ['label'=>'Enlace personalizado','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'condition'=>['type'=>'custom']]);
        $r->add_control('show_icon', ['label'=>'Icono','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['none'=>'Ninguno','default'=>'Predeterminado','custom'=>'Personalizado'],'condition'=>['show_avatar!'=>'yes']]);
        $r->add_control('selected_icon', ['label'=>'Elegir icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','condition'=>['show_icon'=>'custom','show_avatar!'=>'yes']]);
        $this->add_control('icon_list', ['label'=>'','type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),
            'default'=>[['type'=>'author','selected_icon'=>['value'=>'far fa-user-circle','library'=>'fa-regular']],['type'=>'date','selected_icon'=>['value'=>'fas fa-calendar','library'=>'fa-solid']],['type'=>'time','selected_icon'=>['value'=>'far fa-clock','library'=>'fa-regular']],['type'=>'comments','selected_icon'=>['value'=>'far fa-comment-dots','library'=>'fa-regular']]],
            'title_field'=>'{{{ type }}}']);
        $this->end_controls_section();

        $this->start_controls_section('section_icon_list', ['label'=>'Lista','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('space_between', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('icon_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'selectors_dictionary'=>['left'=>'flex-start','start'=>'flex-start','right'=>'flex-end','end'=>'flex-end','center'=>'center'],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'justify-content: {{VALUE}};']]);
        $this->add_control('divider', ['label'=>'Separador','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','selectors'=>['{{WRAPPER}} .obx-post-info > li:not(:last-child)::after'=>'content: ""']]);
        $this->add_control('divider_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'solid','options'=>['solid'=>'Sólido','double'=>'Doble','dotted'=>'Puntos','dashed'=>'Guiones'],'condition'=>['divider'=>'yes'],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'--obx-divider-style: {{VALUE}};']]);
        $this->add_control('divider_weight', ['label'=>'Grosor','type'=>Controls_Manager::SLIDER,'default'=>['size'=>1],'range'=>['px'=>['min'=>1,'max'=>20]],'condition'=>['divider'=>'yes'],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'--obx-divider-weight: {{SIZE}}{{UNIT}};']]);
        $this->add_control('divider_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['unit'=>'%'],'condition'=>['divider'=>'yes','view!'=>'inline'],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'--obx-divider-width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('divider_height', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px'],'default'=>['unit'=>'%'],'range'=>['px'=>['min'=>1,'max'=>100],'%'=>['min'=>1,'max'=>100]],'condition'=>['divider'=>'yes','view'=>'inline'],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'--obx-divider-height: {{SIZE}}{{UNIT}};']]);
        $this->add_control('divider_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'#ddd','condition'=>['divider'=>'yes'],'selectors'=>['{{WRAPPER}} .obx-post-info'=>'--obx-divider-color: {{VALUE}};']]);
        $this->end_controls_section();

        $this->start_controls_section('section_icon_style', ['label'=>'Icono','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('icon_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post-info__icon i'=>'color: {{VALUE}};','{{WRAPPER}} .obx-post-info__icon svg'=>'fill: {{VALUE}};']]);
        $this->add_responsive_control('icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'default'=>['size'=>14],'range'=>['px'=>['min'=>6]],'selectors'=>['{{WRAPPER}} .obx-post-info__icon'=>'--obx-icon-size: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('section_text_style', ['label'=>'Texto','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('text_indent', ['label'=>'Sangría','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-post-info__text'=>'padding-inline-start: {{SIZE}}{{UNIT}};']]);
        $this->add_control('text_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post-info__text, {{WRAPPER}} .obx-post-info__text a'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'icon_typography','selector'=>'{{WRAPPER}} .obx-post-info > li']);
        $this->end_controls_section();
    }

    protected static function upgrade_settings(array $s): array {
        $items = [];
        foreach (['author'=>'author','date'=>'date','modified'=>'date','terms'=>'terms','comments'=>'comments'] as $old => $type) {
            $default = in_array($old, ['date','author','terms'], true) ? 'yes' : '';
            if (($s['show_' . $old] ?? $default) !== 'yes') { continue; }
            $item = ['_id'=>substr(md5($old), 0, 7),'type'=>$type,'show_icon'=>'none','link'=>($s['link'] ?? 'yes') === 'yes' ? 'yes' : ''];
            if ($old === 'terms') { $item['taxonomy'] = [$s['taxonomy'] ?? 'category']; }
            if ($old === 'modified') { $item['text_prefix'] = 'Actualizado:'; $item['obx_modified'] = 'yes'; }
            $items[] = $item;
            unset($s['show_' . $old]);
        }
        unset($s['taxonomy'], $s['link']);
        $s['icon_list'] = $items;
        $s['divider'] = 'yes';
        if (isset($s['text_align'])) { $s['icon_align'] = in_array($s['text_align'], ['left','center','right'], true) ? $s['text_align'] : 'left'; unset($s['text_align']); }
        unset($s['text_hover'], $s['separator']);
        return self::rename_prefix($s, 'text_typography_', 'icon_typography_');
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        if (!$post) { $this->placeholder('La información de la entrada aparecerá aquí.'); return; }
        $items = '';
        foreach ((array) ($s['icon_list'] ?? []) as $item) { $items .= $this->item($item, $post); }
        if ($items === '') { return; }
        $view = ($s['view'] ?? 'inline') === 'traditional' ? 'traditional' : 'inline';
        echo '<ul class="obx-post-info obx-post-info--' . $view . (($s['divider'] ?? '') === 'yes' ? ' obx-post-info--divider' : '') . '">' . $items . '</ul>'; // Escaped per item.
    }

    private function item(array $item, \WP_Post $post): string {
        $type = (string) ($item['type'] ?? '');
        $link = ($item['link'] ?? 'yes') === 'yes';
        $url = '';
        $avatar = '';
        switch ($type) {
            case 'author':
                $text = esc_html(get_the_author_meta('display_name', (int) $post->post_author));
                $url = get_author_posts_url((int) $post->post_author);
                if (($item['show_avatar'] ?? '') === 'yes') { $avatar = get_avatar((int) $post->post_author, 96, '', '', ['class'=>'obx-post-info__avatar']); }
                break;
            case 'date':
                $format = self::format((string) ($item['date_format'] ?? 'default'), (string) ($item['custom_date_format'] ?? ''), self::DATE_FORMATS, (string) get_option('date_format'));
                $modified = ($item['obx_modified'] ?? '') === 'yes';
                $text = '<time datetime="' . esc_attr($modified ? get_the_modified_date('c', $post) : get_the_date('c', $post)) . '">' . esc_html($modified ? get_the_modified_date($format, $post) : get_the_date($format, $post)) . '</time>';
                $url = get_day_link((int) get_the_date('Y', $post), (int) get_the_date('m', $post), (int) get_the_date('d', $post));
                break;
            case 'time':
                $text = esc_html(get_the_time(self::format((string) ($item['time_format'] ?? 'default'), (string) ($item['custom_time_format'] ?? ''), self::TIME_FORMATS, (string) get_option('time_format')), $post));
                $link = false;
                break;
            case 'comments':
                if (!comments_open($post) && !get_comments_number($post)) { return ''; }
                $count = (int) get_comments_number($post);
                if (($item['comments_custom_strings'] ?? '') === 'yes') {
                    $string = $count === 0 ? ($item['string_no_comments'] ?? '') : ($count === 1 ? ($item['string_one_comment'] ?? '') : ($item['string_comments'] ?? ''));
                    $text = esc_html($string !== '' ? sprintf(str_replace('%s', '%1$s', $string), $count) : Posts_Base::comments_label($count));
                } else {
                    $text = esc_html(Posts_Base::comments_label($count));
                }
                $url = get_comments_link($post);
                break;
            case 'terms':
                $taxonomy = (string) (is_array($item['taxonomy'] ?? null) ? (reset($item['taxonomy']) ?: '') : ($item['taxonomy'] ?? ''));
                $terms = taxonomy_exists($taxonomy) ? get_the_terms($post, $taxonomy) : false;
                if (!is_array($terms) || !$terms) { return ''; }
                $text = implode(', ', array_map(static fn($t) => $link ? '<a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . '</a>' : esc_html($t->name), $terms));
                $link = false;
                break;
            case 'custom':
                $text = esc_html((string) ($item['custom_text'] ?? ''));
                if ($text === '') { return ''; }
                $url = (string) ($item['custom_url']['url'] ?? '');
                $link = $url !== '';
                break;
            default:
                return '';
        }
        $prefix = (string) ($item['text_prefix'] ?? '');
        $inner = ($prefix !== '' ? '<span class="obx-post-info__prefix">' . esc_html($prefix) . '</span> ' : '') . $text;
        $icon = '';
        if ($avatar === '' && ($item['show_icon'] ?? 'default') !== 'none') {
            $choice = ($item['show_icon'] ?? 'default') === 'custom' && !empty($item['selected_icon']['value']) ? $item['selected_icon'] : ['value'=>self::ICONS[$type],'library'=>str_starts_with(self::ICONS[$type], 'far') ? 'fa-regular' : 'fa-solid'];
            $icon = Icons_Manager::try_get_icon_html($choice, ['aria-hidden'=>'true']);
        }
        $body = ($avatar ?: ($icon ? '<span class="obx-post-info__icon">' . $icon . '</span>' : '')) . '<span class="obx-post-info__text">' . $inner . '</span>';
        if ($link && $url) { $body = '<a href="' . esc_url($url) . '">' . $body . '</a>'; }
        return '<li class="obx-post-info__item obx-post-info__item--' . esc_attr($type) . ' elementor-repeater-item-' . esc_attr((string) ($item['_id'] ?? '')) . '">' . $body . '</li>';
    }

    private static function format(string $choice, string $custom, array $options, string $fallback): string {
        if ($choice === 'custom') { return $custom !== '' ? $custom : $fallback; }
        return $choice !== 'default' && isset($options[$choice]) ? $options[$choice] : $fallback;
    }
}
