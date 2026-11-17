<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

final class Site_Logo extends Base {
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-site-logo';
    public const PRO = 'theme-site-logo';
    public function get_title(): string { return 'Logotipo del sitio'; }
    public function get_icon(): string { return 'eicon-site-logo'; }
    public function get_keywords(): array { return ['logo','logotipo','marca','brand']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Logotipo']);
        $this->add_control('image', ['label'=>'Imagen alternativa','type'=>Controls_Manager::MEDIA,'description'=>'Se usa si no hay logotipo en Apariencia → Personalizar → Identidad del sitio.']);
        $this->add_control('link', ['label'=>'Enlazar a la portada','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,
            'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-text-align-right']],
            'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]);
        $this->end_controls_section();
        $this->start_controls_section('logo_style', ['label'=>'Logotipo','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('width', ['label'=>'Ancho máximo','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%'],'range'=>['px'=>['min'=>10,'max'=>600]],'selectors'=>['{{WRAPPER}} img'=>'max-width: {{SIZE}}{{UNIT}}; width: 100%;']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $logo = (int) get_theme_mod('custom_logo');
        $name = get_bloginfo('name');
        if ($logo) { $image = wp_get_attachment_image($logo, 'full', false, ['class'=>'obx-site-logo__img','alt'=>$name]); }
        elseif (!empty($s['image']['url'])) { $image = '<img class="obx-site-logo__img" src="' . esc_url($s['image']['url']) . '" alt="' . esc_attr($name) . '">'; }
        elseif ($this->editing()) { $image = '<img class="obx-site-logo__img" src="' . esc_url(Utils::get_placeholder_image_src()) . '" alt="">'; }
        else { return; }
        echo '<div class="obx-site-logo">' . ($s['link'] === 'yes' ? '<a href="' . esc_url(home_url('/')) . '" rel="home">' . $image . '</a>' : $image) . '</div>';
    }
}

final class Site_Title extends Base {
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-site-title';
    public const PRO = 'theme-site-title';
    public function get_title(): string { return 'Título del sitio'; }
    public function get_icon(): string { return 'eicon-site-title'; }
    public function get_keywords(): array { return ['site','sitio','titulo','nombre','tagline','lema']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Título']);
        $this->tag_control('p');
        $this->add_control('tagline', ['label'=>'Mostrar descripción corta','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->add_control('link', ['label'=>'Enlazar a la portada','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->end_controls_section();
        $this->text_style('.obx-site-title', 'Título');
        $this->text_style('.obx-site-tagline', 'Descripción corta', 'tagline', false);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $name = esc_html(get_bloginfo('name'));
        if ($s['link'] === 'yes') { $name = '<a href="' . esc_url(home_url('/')) . '" rel="home">' . $name . '</a>'; }
        printf('<%1$s class="obx-site-title">%2$s</%1$s>', $this->tag(), $name);
        if ($s['tagline'] === 'yes' && get_bloginfo('description')) { echo '<p class="obx-site-tagline">' . esc_html(get_bloginfo('description')) . '</p>'; }
    }
}

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
        $this->start_controls_section('content', ['label'=>'Menú']);
        $menus = [];
        foreach (wp_get_nav_menus() as $menu) { $menus[(string) $menu->term_id] = $menu->name; }
        if ($menus) {
            $this->add_control('menu', ['label'=>'Menú','type'=>Controls_Manager::SELECT,'options'=>$menus,'default'=>(string) array_key_first($menus),'description'=>'Gestiona los enlaces en Apariencia → Menús.']);
        } else {
            $this->add_control('menu_missing', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'No hay menús. Crea uno en Apariencia → Menús.','content_classes'=>'elementor-panel-alert elementor-panel-alert-info']);
        }
        $this->add_control('layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'options'=>['horizontal'=>'Horizontal','vertical'=>'Vertical'],'default'=>'horizontal']);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::SELECT,'options'=>['flex-start'=>'Inicio','center'=>'Centro','flex-end'=>'Final','space-between'=>'Justificado'],'default'=>'flex-start','selectors'=>['{{WRAPPER}} .obx-nav__list'=>'justify-content: {{VALUE}};','{{WRAPPER}} .obx-nav'=>'justify-content: {{VALUE}};']]);
        $this->add_control('depth', ['label'=>'Niveles','type'=>Controls_Manager::SELECT,'options'=>['1'=>'Solo primer nivel','0'=>'Todos (submenús desplegables)'],'default'=>'0']);
        $this->add_control('breakpoint', ['label'=>'Menú desplegable en','type'=>Controls_Manager::SELECT,'options'=>['mobile'=>'Móvil (≤ 767 px)','tablet'=>'Tableta (≤ 1024 px)','none'=>'Nunca'],'default'=>'tablet','separator'=>'before']);
        $this->add_control('toggle_label', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Menú','condition'=>['breakpoint!'=>'none']]);
        $this->end_controls_section();

        $this->start_controls_section('items_style', ['label'=>'Elementos','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'items_typography','selector'=>'{{WRAPPER}} .obx-nav__list a']);
        $this->add_control('items_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__list a'=>'color: {{VALUE}};','{{WRAPPER}} .obx-nav__toggle'=>'color: {{VALUE}}; border-color: {{VALUE}};']]);
        $this->add_control('items_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__list a:hover, {{WRAPPER}} .obx-nav__list a:focus'=>'color: {{VALUE}};']]);
        $this->add_control('items_active', ['label'=>'Color del elemento activo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav__list .current-menu-item > a, {{WRAPPER}} .obx-nav__list .current-menu-ancestor > a'=>'color: {{VALUE}};']]);
        $this->add_responsive_control('items_gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'range'=>['px'=>['min'=>0,'max'=>80]],'default'=>['unit'=>'px','size'=>24],'selectors'=>['{{WRAPPER}} .obx-nav__list'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('items_padding', ['label'=>'Relleno de cada enlace','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em'],'selectors'=>['{{WRAPPER}} .obx-nav__list a'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('dropdown_style', ['label'=>'Desplegable','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('dropdown_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-bg: {{VALUE}};']]);
        $this->add_control('dropdown_color', ['label'=>'Color de los enlaces','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-nav'=>'--obx-dropdown-color: {{VALUE}};']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $menu = absint($s['menu'] ?? 0);
        if (!$menu || !wp_get_nav_menu_object($menu)) { $this->placeholder('Elige un menú en los ajustes del widget.'); return; }
        $id = 'obx-nav-' . $this->get_id();
        $html = wp_nav_menu(['menu'=>$menu,'container'=>false,'menu_class'=>'obx-nav__list','fallback_cb'=>false,'depth'=>absint($s['depth']),'echo'=>false]);
        if (!$html) { return; }
        $breakpoint = in_array($s['breakpoint'], ['mobile','tablet'], true) ? $s['breakpoint'] : 'none';
        $classes = 'obx-nav obx-nav--' . ($s['layout'] === 'vertical' ? 'vertical' : 'horizontal') . ' obx-nav--bp-' . $breakpoint;
        echo '<nav class="' . esc_attr($classes) . '" aria-label="' . esc_attr(wp_get_nav_menu_object($menu)->name) . '">';
        if ($breakpoint !== 'none') {
            echo '<button class="obx-nav__toggle" type="button" aria-expanded="false" aria-controls="' . esc_attr($id) . '"><span class="obx-nav__icon" aria-hidden="true"></span><span>' . esc_html($s['toggle_label']) . '</span></button>';
        }
        echo '<div class="obx-nav__menu" id="' . esc_attr($id) . '">' . $html . '</div></nav>';
    }
}

final class Search_Form extends Base {
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-search-form';
    public const PRO = 'search';
    public const PRO_CATEGORY = 'pro-elements';
    public function get_title(): string { return 'Formulario de búsqueda'; }
    public function get_icon(): string { return 'eicon-site-search'; }
    public function get_keywords(): array { return ['search','buscar','buscador','busqueda']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Buscador']);
        $this->add_control('placeholder', ['label'=>'Texto de ejemplo','type'=>Controls_Manager::TEXT,'default'=>'Buscar…']);
        $this->add_control('button', ['label'=>'Texto del botón','type'=>Controls_Manager::TEXT,'default'=>'Buscar']);
        $this->end_controls_section();
        $this->start_controls_section('form_style', ['label'=>'Formulario','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'input_typography','selector'=>'{{WRAPPER}} .obx-search input, {{WRAPPER}} .obx-search button']);
        $this->add_control('input_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search input'=>'color: {{VALUE}};']]);
        $this->add_control('input_bg', ['label'=>'Fondo del campo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search input'=>'background-color: {{VALUE}};']]);
        $this->add_control('button_color', ['label'=>'Color del botón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search button'=>'color: {{VALUE}};']]);
        $this->add_control('button_bg', ['label'=>'Fondo del botón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-search button'=>'background-color: {{VALUE}};']]);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $id = 'obx-search-' . $this->get_id();
        echo '<form class="obx-search" role="search" method="get" action="' . esc_url(home_url('/')) . '"><label class="screen-reader-text" for="' . esc_attr($id) . '">Buscar</label>';
        echo '<input id="' . esc_attr($id) . '" type="search" name="s" value="' . esc_attr(get_search_query()) . '" placeholder="' . esc_attr($s['placeholder']) . '">';
        echo '<button type="submit">' . esc_html($s['button']) . '</button></form>';
    }
}
