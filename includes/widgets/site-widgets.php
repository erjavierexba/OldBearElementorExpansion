<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

if (!defined('ABSPATH')) { exit; }

final class Nav_Menu extends Base {
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-nav-menu';
    public const PRO = 'nav-menu';
    public const PRO_CATEGORY = 'pro-elements';
    public function get_title(): string { return 'Menú de navegación'; }
    public function get_icon(): string { return 'eicon-nav-menu'; }
    public function get_keywords(): array { return ['menu','nav','navegacion','navigation']; }
    public function get_script_depends(): array { return ['obx-nav-menu']; }

    protected function register_controls(): void {
        $menus = [];
        foreach (wp_get_nav_menus() as $menu) { $menus[$menu->slug] = $menu->name; }
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->add_control('menu_name', ['label'=>'Nombre accesible','type'=>Controls_Manager::TEXT,'default'=>'Menu']);
        if ($menus) {
            $this->add_control('menu', ['label'=>'Menú','type'=>Controls_Manager::SELECT,'options'=>$menus,'default'=>(string) array_key_first($menus),'save_default'=>true,'description'=>'Gestiona los enlaces en Apariencia → Menús.']);
        } else {
            $this->add_control('menu_missing', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'No hay menús. Crea uno en Apariencia → Menús.','content_classes'=>'elementor-panel-alert elementor-panel-alert-info']);
        }
        $this->add_control('layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'horizontal','options'=>['horizontal'=>'Horizontal','vertical'=>'Vertical','dropdown'=>'Desplegable']]);
        $this->add_control('align_items', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(true),'selectors_dictionary'=>self::flex_dictionary(),'selectors'=>['{{WRAPPER}} .obx-nav__list'=>'justify-content: {{VALUE}};','{{WRAPPER}} .obx-nav'=>'justify-content: {{VALUE}};'],'condition'=>['layout!'=>'dropdown']]);
        $this->add_control('pointer', ['label'=>'Indicador','type'=>Controls_Manager::SELECT,'default'=>'underline','options'=>['none'=>'Ninguno','underline'=>'Subrayado','overline'=>'Sobrerrayado','double-line'=>'Doble línea','framed'=>'Marco','background'=>'Fondo','text'=>'Texto'],'condition'=>['layout!'=>'dropdown']]);
        $this->add_control('submenu_icon', ['label'=>'Icono de submenú','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-caret-down','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false,'recommended'=>['fa-solid'=>['chevron-down','angle-down','caret-down','plus']]]);
        $this->add_control('depth', ['label'=>'Niveles','type'=>Controls_Manager::SELECT,'options'=>['1'=>'Solo primer nivel','0'=>'Todos (submenús desplegables)'],'default'=>'0']);
        $this->add_control('heading_mobile_dropdown', ['label'=>'Menú móvil','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['layout!'=>'dropdown']]);
        $this->add_control('dropdown', ['label'=>'Desplegable a partir de','type'=>Controls_Manager::SELECT,'default'=>'tablet','options'=>['mobile'=>'Móvil (≤ 767 px)','tablet'=>'Tableta (≤ 1024 px)','none'=>'Nunca'],'condition'=>['layout!'=>'dropdown']]);
        $this->add_control('full_width', ['label'=>'Ancho completo','type'=>Controls_Manager::SWITCHER,'return_value'=>'stretch','default'=>'','condition'=>['dropdown!'=>'none']]);
        $this->add_control('text_align', ['label'=>'Alineación del texto','type'=>Controls_Manager::SELECT,'default'=>'aside','options'=>['aside'=>'Lateral','center'=>'Centro'],'condition'=>['dropdown!'=>'none']]);
        $this->add_control('toggle', ['label'=>'Botón','type'=>Controls_Manager::SELECT,'default'=>'burger','options'=>[''=>'Ninguno','burger'=>'Hamburguesa'],'condition'=>['dropdown!'=>'none']]);
        $this->add_control('toggle_icon_normal', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'condition'=>['toggle'=>'burger']]);
        $this->add_control('toggle_icon_active', ['label'=>'Icono al abrir','type'=>Controls_Manager::ICONS,'condition'=>['toggle'=>'burger']]);
        $this->add_control('toggle_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'','condition'=>['toggle'=>'burger']]);
        $this->add_control('toggle_align', ['label'=>'Alineación del botón','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'selectors_dictionary'=>['left'=>'margin-right: auto','center'=>'margin: 0 auto','right'=>'margin-left: auto'],'selectors'=>['{{WRAPPER}} .obx-nav__toggle'=>'{{VALUE}}'],'condition'=>['toggle!'=>'','dropdown!'=>'none']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_main-menu', ['label'=>'Menú principal','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['layout!'=>'dropdown']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'menu_typography','selector'=>'{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a']);
        $this->add_control('color_menu_item', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a'=>'color: {{VALUE}};','{{WRAPPER}} .obx-nav__toggle'=>'color: {{VALUE}};']]);
        $this->add_control('color_menu_item_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a:hover, {{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a:focus'=>'color: {{VALUE}};'],'condition'=>['pointer!'=>'background']]);
        $this->add_control('color_menu_item_hover_pointer_bg', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'default'=>'#fff','selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a:hover, {{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a:focus'=>'color: {{VALUE}};'],'condition'=>['pointer'=>'background']]);
        $this->add_control('pointer_color_menu_item_hover', ['label'=>'Color del indicador','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-pointer: {{VALUE}};'],'condition'=>['pointer!'=>['none','text']]]);
        $this->add_control('color_menu_item_active', ['label'=>'Color del elemento activo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li.current-menu-item > a, {{WRAPPER}} .obx-nav__menu > .obx-nav__list > li.current-menu-ancestor > a'=>'color: {{VALUE}};']]);
        $this->add_control('pointer_color_menu_item_active', ['label'=>'Color del indicador activo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-pointer-active: {{VALUE}};'],'condition'=>['pointer!'=>['none','text']]]);
        $this->add_control('nav_menu_divider', ['label'=>'Separadores','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','condition'=>['layout'=>'horizontal'],'separator'=>'before']);
        $this->add_control('nav_menu_divider_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'solid','options'=>['solid'=>'Sólido','double'=>'Doble','dotted'=>'Puntos','dashed'=>'Guiones'],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-divider-style: {{VALUE}};'],'condition'=>['nav_menu_divider'=>'yes','layout'=>'horizontal']]);
        $this->add_control('nav_menu_divider_weight', ['label'=>'Grosor','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>1,'max'=>20]],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-divider-weight: {{SIZE}}{{UNIT}};'],'condition'=>['nav_menu_divider'=>'yes','layout'=>'horizontal']]);
        $this->add_control('nav_menu_divider_height', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%'],'range'=>['px'=>['min'=>1,'max'=>100]],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-divider-height: {{SIZE}}{{UNIT}};'],'condition'=>['nav_menu_divider'=>'yes','layout'=>'horizontal']]);
        $this->add_control('nav_menu_divider_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-divider-color: {{VALUE}};'],'condition'=>['nav_menu_divider'=>'yes','layout'=>'horizontal']]);
        $this->add_responsive_control('pointer_width', ['label'=>'Grosor del indicador','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>30]],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-pointer-width: {{SIZE}}{{UNIT}};'],'condition'=>['pointer'=>['underline','overline','double-line','framed']],'separator'=>'before']);
        $this->add_responsive_control('padding_horizontal_menu_item', ['label'=>'Relleno horizontal','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a'=>'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('padding_vertical_menu_item', ['label'=>'Relleno vertical','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a'=>'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('menu_space_between', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'range'=>['px'=>['max'=>100]],'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('border_radius_menu_item', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','%'],'selectors'=>['{{WRAPPER}} .obx-nav__menu > .obx-nav__list > li > a'=>'border-radius: {{SIZE}}{{UNIT}};'],'condition'=>['pointer'=>'background']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_dropdown', ['label'=>'Desplegable','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'dropdown_typography','selector'=>'{{WRAPPER}} .obx-nav .sub-menu a, {{WRAPPER}} .obx-nav.is-dropdown .obx-nav__list a']);
        $this->add_control('color_dropdown_item', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-color: {{VALUE}};']]);
        $this->add_control('background_color_dropdown_item', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-bg: {{VALUE}};']]);
        $this->add_control('color_dropdown_item_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-hover-color: {{VALUE}};']]);
        $this->add_control('background_color_dropdown_item_hover', ['label'=>'Fondo al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-hover-bg: {{VALUE}};']]);
        $this->add_control('color_dropdown_item_active', ['label'=>'Color activo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-active-color: {{VALUE}};']]);
        $this->add_control('background_color_dropdown_item_active', ['label'=>'Fondo activo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-active-bg: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'dropdown_border','selector'=>'{{WRAPPER}} .obx-nav .sub-menu, {{WRAPPER}} .obx-nav__menu.is-panel','separator'=>'before']);
        $this->add_responsive_control('dropdown_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}} .obx-nav .sub-menu, {{WRAPPER}} .obx-nav__menu.is-panel'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'dropdown_box_shadow','selector'=>'{{WRAPPER}} .obx-nav .sub-menu, {{WRAPPER}} .obx-nav__menu.is-panel']);
        $this->add_responsive_control('padding_horizontal_dropdown_item', ['label'=>'Relleno horizontal','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-px: {{SIZE}}{{UNIT}};'],'separator'=>'before']);
        $this->add_responsive_control('padding_vertical_dropdown_item', ['label'=>'Relleno vertical','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh','custom'],'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-py: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'dropdown_divider','selector'=>'{{WRAPPER}} .obx-nav .sub-menu li:not(:last-child), {{WRAPPER}} .obx-nav__menu.is-panel li:not(:last-child)','exclude'=>['width'],'separator'=>'before']);
        $this->add_control('dropdown_divider_width', ['label'=>'Grosor del separador','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>50]],'selectors'=>['{{WRAPPER}} .obx-nav .sub-menu li:not(:last-child), {{WRAPPER}} .obx-nav__menu.is-panel li:not(:last-child)'=>'border-top-width: 0; border-left-width: 0; border-right-width: 0; border-bottom-width: {{SIZE}}{{UNIT}};'],'condition'=>['dropdown_divider_border!'=>'']]);
        $this->add_responsive_control('dropdown_top_distance', ['label'=>'Distancia','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['min'=>-100,'max'=>100]],'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-top: {{SIZE}}{{UNIT}};'],'separator'=>'before']);
        $this->end_controls_section();

        $this->start_controls_section('style_toggle', ['label'=>'Botón de menú','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['toggle!'=>'','dropdown!'=>'none']]);
        $this->add_control('toggle_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__toggle'=>'color: {{VALUE}};']]);
        $this->add_control('toggle_background_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__toggle'=>'background-color: {{VALUE}};']]);
        $this->add_control('toggle_color_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__toggle:hover, {{WRAPPER}} .obx-nav__toggle:focus'=>'color: {{VALUE}};']]);
        $this->add_control('toggle_background_color_hover', ['label'=>'Fondo al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__toggle:hover, {{WRAPPER}} .obx-nav__toggle:focus'=>'background-color: {{VALUE}};']]);
        $this->add_responsive_control('toggle_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>15]],'selectors'=>['{{WRAPPER}} .obx-nav__toggle'=>'font-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('toggle_border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>10]],'selectors'=>['{{WRAPPER}} .obx-nav__toggle'=>'border-width: {{SIZE}}{{UNIT}}; border-style: solid;']]);
        $this->add_responsive_control('toggle_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em'],'selectors'=>['{{WRAPPER}} .obx-nav__toggle'=>'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    protected static function upgrade_settings(array $s): array {
        $s = self::rename($s, ['breakpoint'=>'dropdown','items_color'=>'color_menu_item','items_hover'=>'color_menu_item_hover','items_active'=>'color_menu_item_active','items_gap'=>'menu_space_between','dropdown_bg'=>'background_color_dropdown_item','dropdown_color'=>'color_dropdown_item','toggle_label'=>'toggle_text']);
        $s = self::rename_prefix($s, 'items_typography_', 'menu_typography_');
        if (isset($s['align'])) { $s['align_items'] = ['flex-start'=>'start','flex-end'=>'end','center'=>'center','space-between'=>'justify'][$s['align']] ?? 'start'; unset($s['align']); }
        unset($s['items_padding']);
        $s['pointer'] = $s['pointer'] ?? 'none';
        return $s;
    }

    /** Menu term from a slug (Elementor Pro) or an ID (earlier versions). */
    public static function find_menu($value): ?\WP_Term {
        if ($value === '' || $value === null) { return null; }
        $menu = wp_get_nav_menu_object(is_numeric($value) ? (int) $value : (string) $value);
        return $menu instanceof \WP_Term ? $menu : null;
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $menu = self::find_menu($s['menu'] ?? '');
        if (!$menu) { $this->placeholder('Elige un menú en los ajustes del widget.'); return; }
        $html = wp_nav_menu(['menu'=>$menu->term_id,'container'=>false,'menu_class'=>'obx-nav__list','fallback_cb'=>false,'depth'=>absint($s['depth'] ?? 0),'echo'=>false]);
        if (!$html) { return; }
        if (!empty($s['submenu_icon']['value'])) {
            $icon = Icons_Manager::try_get_icon_html($s['submenu_icon'], ['aria-hidden'=>'true']);
            if ($icon) { $html = preg_replace('~(<li[^>]*menu-item-has-children[^>]*>\s*<a\b[^>]*>)(.*?)(</a>)~s', '$1$2<span class="obx-nav__sub-icon">' . $icon . '</span>$3', $html); }
        }
        $layout = in_array($s['layout'] ?? '', ['horizontal','vertical','dropdown'], true) ? $s['layout'] : 'horizontal';
        $breakpoint = $layout === 'dropdown' ? 'all' : (in_array($s['dropdown'] ?? '', ['mobile','tablet'], true) ? $s['dropdown'] : 'none');
        $pointer = in_array($s['pointer'] ?? '', ['underline','overline','double-line','framed','background','text'], true) ? $s['pointer'] : 'none';
        $classes = ['obx-nav','obx-nav--' . ($layout === 'vertical' ? 'vertical' : 'horizontal'),'obx-nav--bp-' . $breakpoint,'obx-nav--pointer-' . $pointer];
        if (($s['full_width'] ?? '') === 'stretch') { $classes[] = 'obx-nav--stretch'; }
        if (($s['text_align'] ?? 'aside') === 'center') { $classes[] = 'obx-nav--panel-center'; }
        if (($s['nav_menu_divider'] ?? '') === 'yes') { $classes[] = 'obx-nav--divider'; }
        $id = 'obx-nav-' . $this->get_id();
        echo '<nav class="' . esc_attr(implode(' ', $classes)) . '" aria-label="' . esc_attr(($s['menu_name'] ?? '') ?: $menu->name) . '">';
        if ($breakpoint !== 'none' && ($s['toggle'] ?? 'burger') !== '') {
            echo '<button class="obx-nav__toggle" type="button" aria-expanded="false" aria-controls="' . esc_attr($id) . '">';
            $open = !empty($s['toggle_icon_normal']['value']) ? Icons_Manager::try_get_icon_html($s['toggle_icon_normal'], ['aria-hidden'=>'true']) : '';
            $close = !empty($s['toggle_icon_active']['value']) ? Icons_Manager::try_get_icon_html($s['toggle_icon_active'], ['aria-hidden'=>'true']) : '';
            echo $open ? '<span class="obx-nav__icon-open">' . $open . '</span>' : '<span class="obx-nav__icon" aria-hidden="true"></span>';
            if ($close) { echo '<span class="obx-nav__icon-close">' . $close . '</span>'; }
            $text = (string) ($s['toggle_text'] ?? '');
            echo $text !== '' ? '<span>' . esc_html($text) . '</span>' : '<span class="screen-reader-text">Menú</span>';
            echo '</button>';
        }
        echo '<div class="obx-nav__menu" id="' . esc_attr($id) . '">' . $html . '</div></nav>'; // Core menu markup.
    }
}

/** Search box from Elementor Pro's «Search Form» widget (still used by most kits). */
final class Search_Form extends Base {
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-search-form';
    public const PRO = 'search-form';
    public const PRO_CATEGORY = 'pro-elements';
    public function get_title(): string { return 'Formulario de búsqueda'; }
    public function get_icon(): string { return 'eicon-site-search'; }
    public function get_keywords(): array { return ['search','buscar','buscador','busqueda']; }
    public function get_script_depends(): array { return ['obx-widgets']; }

    protected function register_controls(): void {
        $this->start_controls_section('search_content', ['label'=>'Buscador']);
        $this->add_control('skin', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'default'=>'classic','options'=>['classic'=>'Clásico','minimal'=>'Mínimo','full_screen'=>'Pantalla completa']]);
        $this->add_control('placeholder', ['label'=>'Texto de ejemplo','type'=>Controls_Manager::TEXT,'default'=>'Buscar…','separator'=>'before']);
        $this->add_control('button_type', ['label'=>'Tipo de botón','type'=>Controls_Manager::SELECT,'default'=>'icon','options'=>['icon'=>'Icono','text'=>'Texto'],'condition'=>['skin'=>'classic'],'separator'=>'before']);
        $this->add_control('button_text', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Buscar','condition'=>['button_type'=>'text','skin'=>'classic']]);
        $this->add_control('icon', ['label'=>'Icono','type'=>Controls_Manager::CHOOSE,'default'=>'search','options'=>['search'=>['title'=>'Lupa','icon'=>'eicon-search'],'arrow'=>['title'=>'Flecha','icon'=>'eicon-arrow-right']],'condition'=>['button_type'=>'icon','skin'=>'classic']]);
        $this->add_control('size', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'default'=>['size'=>50],'selectors'=>['{{WRAPPER}} .obx-search__field, {{WRAPPER}} .obx-search__submit'=>'min-height: {{SIZE}}{{UNIT}};','{{WRAPPER}} .obx-search__submit'=>'min-width: {{SIZE}}{{UNIT}};'],'condition'=>['skin!'=>'full_screen']]);
        $this->add_control('toggle_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::align_options(),'selectors'=>['{{WRAPPER}} .obx-search-toggle'=>'text-align: {{VALUE}};'],'condition'=>['skin'=>'full_screen']]);
        $this->add_control('toggle_size', ['label'=>'Tamaño del botón','type'=>Controls_Manager::SLIDER,'default'=>['size'=>33],'selectors'=>['{{WRAPPER}} .obx-search-toggle button'=>'font-size: {{SIZE}}{{UNIT}};'],'condition'=>['skin'=>'full_screen']]);
        $this->end_controls_section();

        $this->start_controls_section('section_input_style', ['label'=>'Campo','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'input_typography','selector'=>'{{WRAPPER}} .obx-search__field']);
        foreach (['normal'=>'','focus'=>'_focus'] as $state => $suffix) {
            $sel = '{{WRAPPER}} .obx-search__field' . ($suffix ? ':focus' : '');
            $label = $suffix ? ' (con foco)' : '';
            $this->add_control('input_text_color' . $suffix, ['label'=>'Color del texto' . $label,'type'=>Controls_Manager::COLOR,'selectors'=>[$sel=>'color: {{VALUE}};']]);
            $this->add_control('input_background_color' . $suffix, ['label'=>'Fondo' . $label,'type'=>Controls_Manager::COLOR,'selectors'=>[$sel=>'background-color: {{VALUE}};']]);
            $this->add_control('input_border_color' . $suffix, ['label'=>'Color del borde' . $label,'type'=>Controls_Manager::COLOR,'selectors'=>[$sel=>'border-color: {{VALUE}};']]);
        }
        $this->add_control('border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::DIMENSIONS,'selectors'=>['{{WRAPPER}} .obx-search__field'=>'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::SLIDER,'default'=>['size'=>3],'range'=>['px'=>['max'=>200]],'selectors'=>['{{WRAPPER}} .obx-search'=>'--obx-search-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('section_button_style', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['skin'=>'classic']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'button_typography','selector'=>'{{WRAPPER}} .obx-search__submit','condition'=>['button_type'=>'text']]);
        $this->add_control('button_text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search__submit'=>'color: {{VALUE}};']]);
        $this->add_control('button_background_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search__submit'=>'background-color: {{VALUE}};']]);
        $this->add_control('button_text_color_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search__submit:hover, {{WRAPPER}} .obx-search__submit:focus'=>'color: {{VALUE}};']]);
        $this->add_control('button_background_color_hover', ['label'=>'Fondo al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search__submit:hover, {{WRAPPER}} .obx-search__submit:focus'=>'background-color: {{VALUE}};']]);
        $this->add_control('icon_size', ['label'=>'Tamaño del icono','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} .obx-search__submit'=>'font-size: {{SIZE}}{{UNIT}};'],'condition'=>['button_type'=>'icon']]);
        $this->add_responsive_control('button_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>1,'max'=>10,'step'=>0.1]],'selectors'=>['{{WRAPPER}} .obx-search__submit'=>'min-width: calc({{SIZE}} * 50px);']]);
        $this->end_controls_section();
    }

    protected static function upgrade_settings(array $s): array {
        $s = self::rename($s, ['button'=>'button_text','input_color'=>'input_text_color','input_bg'=>'input_background_color','button_color'=>'button_text_color','button_bg'=>'button_background_color']);
        $s['button_type'] = 'text';
        return $s;
    }

    public static function icon(string $name): string {
        return $name === 'arrow'
            ? '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path fill="currentColor" d="M13.2 5.3 19.9 12l-6.7 6.7-1.4-1.4 4.3-4.3H4v-2h12.1l-4.3-4.3z"/></svg>'
            : '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path fill="currentColor" d="M10 3a7 7 0 0 1 5.6 11.2l5.1 5.1-1.4 1.4-5.1-5.1A7 7 0 1 1 10 3zm0 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10z"/></svg>';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $skin = in_array($s['skin'] ?? '', ['classic','minimal','full_screen'], true) ? $s['skin'] : 'classic';
        $id = 'obx-search-' . $this->get_id();
        $form = '<form class="obx-search obx-search--' . esc_attr($skin) . '" role="search" method="get" action="' . esc_url(home_url('/')) . '">'
            . '<label class="screen-reader-text" for="' . esc_attr($id) . '">Buscar</label>';
        if ($skin === 'minimal') { $form .= '<span class="obx-search__icon">' . self::icon('search') . '</span>'; }
        $form .= '<input id="' . esc_attr($id) . '" class="obx-search__field" type="search" name="s" value="' . esc_attr(get_search_query()) . '" placeholder="' . esc_attr($s['placeholder'] ?? '') . '">';
        if ($skin === 'classic') {
            $label = ($s['button_type'] ?? 'icon') === 'text' ? esc_html($s['button_text'] ?? 'Buscar') : self::icon((string) ($s['icon'] ?? 'search')) . '<span class="screen-reader-text">Buscar</span>';
            $form .= '<button class="obx-search__submit" type="submit">' . $label . '</button>';
        }
        $form .= '</form>';
        if ($skin === 'full_screen') {
            echo '<div class="obx-search-toggle"><button type="button" class="obx-search-toggle__open" aria-expanded="false" aria-controls="' . esc_attr($id) . '-overlay">' . self::icon('search') . '<span class="screen-reader-text">Buscar</span></button>'
                . '<div class="obx-search-overlay" id="' . esc_attr($id) . '-overlay" hidden><button type="button" class="obx-search-overlay__close"><span aria-hidden="true">×</span><span class="screen-reader-text">Cerrar</span></button>' . $form . '</div></div>';
            return;
        }
        echo $form; // Escaped above.
    }
}

/** Elementor Pro's current «Search» widget (field with icon, clear button and submit). */
final class Search extends Base {
    public const PRO = 'search';
    public const PRO_CATEGORY = 'pro-elements';
    public function get_title(): string { return 'Buscador'; }
    public function get_icon(): string { return 'eicon-site-search'; }
    public function get_keywords(): array { return ['search','buscar','buscador','busqueda']; }
    protected function register_controls(): void {
        $this->start_controls_section('content_section_search_field', ['label'=>'Campo de búsqueda']);
        $this->add_control('search_input_placeholder_text', ['label'=>'Texto de ejemplo','type'=>Controls_Manager::TEXT,'default'=>'Escribe para buscar…']);
        $this->add_control('icon_search', ['label'=>'Icono del campo','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false]);
        $this->add_control('icon_clear', ['label'=>'Icono para borrar','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-times','library'=>'fa-solid'],'skin'=>'inline','label_block'=>false]);
        $this->add_control('submit_trigger', ['label'=>'Enviar con','type'=>Controls_Manager::SELECT,'default'=>'click_submit','options'=>['click_submit'=>'Botón','key_enter'=>'Tecla Intro','both'=>'Ambos']]);
        $this->add_control('submit_button_text', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Buscar','condition'=>['submit_trigger!'=>'key_enter']]);
        $this->add_control('icon_submit', ['label'=>'Icono del botón','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'condition'=>['submit_trigger!'=>'key_enter']]);
        $this->end_controls_section();

        $this->start_controls_section('section_search_field_style', ['label'=>'Campo de búsqueda','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'search_field_typography','selector'=>'{{WRAPPER}} .obx-search__field']);
        $this->add_control('placeholder_color', ['label'=>'Color del texto de ejemplo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search__field::placeholder'=>'color: {{VALUE}};']]);
        $this->add_responsive_control('search_field_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'selectors'=>['{{WRAPPER}} .obx-search__field'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('search_field_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'selectors'=>['{{WRAPPER}} .obx-search__field'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('search_field_submit_gap', ['label'=>'Separación con el botón','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} .obx-search'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('style_section_submit', ['label'=>'Botón','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['submit_trigger!'=>'key_enter']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'submit_typography','selector'=>'{{WRAPPER}} .obx-search__submit']);
        $this->add_responsive_control('submit_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'selectors'=>['{{WRAPPER}} .obx-search__submit'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('submit_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem'],'selectors'=>['{{WRAPPER}} .obx-search__submit'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('icon_submit_size', ['label'=>'Tamaño del icono','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} .obx-search__submit svg, {{WRAPPER}} .obx-search__submit i'=>'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $id = 'obx-search-' . $this->get_id();
        echo '<form class="obx-search obx-search--classic obx-search--new" role="search" method="get" action="' . esc_url(home_url('/')) . '"><label class="screen-reader-text" for="' . esc_attr($id) . '">Buscar</label><span class="obx-search__wrap">';
        if (!empty($s['icon_search']['value'])) { echo '<span class="obx-search__icon">'; Icons_Manager::render_icon($s['icon_search'], ['aria-hidden'=>'true']); echo '</span>'; }
        echo '<input id="' . esc_attr($id) . '" class="obx-search__field" type="search" name="s" value="' . esc_attr(get_search_query()) . '" placeholder="' . esc_attr($s['search_input_placeholder_text']) . '">';
        if (!empty($s['icon_clear']['value'])) { echo '<button type="reset" class="obx-search__clear"><span class="screen-reader-text">Borrar</span>'; Icons_Manager::render_icon($s['icon_clear'], ['aria-hidden'=>'true']); echo '</button>'; }
        echo '</span>';
        if (($s['submit_trigger'] ?? 'click_submit') !== 'key_enter') {
            echo '<button class="obx-search__submit" type="submit">';
            if (!empty($s['icon_submit']['value'])) { Icons_Manager::render_icon($s['icon_submit'], ['aria-hidden'=>'true']); }
            if (($s['submit_button_text'] ?? '') !== '') { echo '<span>' . esc_html($s['submit_button_text']) . '</span>'; }
            echo '</button>';
        }
        echo '</form>';
    }
}
