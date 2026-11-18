<?php
namespace OldBearExpansion\Widgets;

if (!defined('ABSPATH')) { exit; }

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;

/** Nested widgets need Elementor's nested elements and containers. */
trait Nested_Widget {
    public static function names(): array { return [self::PRO]; }
    /** Saved with Pro's keys from the start: nothing to convert. */
    public static function wants_upgrade(string $name, array $settings): bool { return false; }
    public static function convert(string $name, array $settings): array { return $settings; }
    public static function available(): bool {
        $experiments = \Elementor\Plugin::$instance->experiments;
        return class_exists(Widget_Nested_Base::class) && $experiments->is_feature_active('nested-elements') && $experiments->is_feature_active('container');
    }
    public function get_name(): string { return self::PRO; }
    public function get_categories(): array { return ['pro-elements']; }
    public function get_style_depends(): array { return ['obx-frontend']; }
    public function show_in_panel(): bool { return true; }
    public function get_custom_help_url() { return \OldBearExpansion\Admin\Branding::URL . '#readme'; }
    public function has_widget_inner_wrapper(): bool { return false; }
}

/**
 * Off-Canvas: a panel that slides over the page with one container inside.
 * Opened, closed or toggled by any link whose dynamic tag is «Off-Canvas» (action off_canvas:{open|close|toggle}).
 */
final class Off_Canvas extends Widget_Nested_Base {
    use Nested_Widget;
    public const NAME = '';
    public const PRO = 'off-canvas';
    public const ALIASES = [];

    public function get_title(): string { return 'Off-Canvas'; }
    public function get_icon(): string { return 'eicon-off-canvas'; }
    public function get_keywords(): array { return ['off','canvas','panel','lateral','menu','cajon','drawer']; }
    public function get_script_depends(): array { return ['obx-off-canvas']; }

    protected function get_default_children_elements() { return [['elType'=>'container','settings'=>['content_width'=>'full']]]; }
    protected function get_default_repeater_title_setting_key() { return ''; }
    protected function get_default_children_placeholder_selector() { return '.obx-off-canvas__content'; }
    protected function is_dynamic_content(): bool { return false; }

    /** Entrance and exit animations used by off-canvas widgets on this page (Elementor ships one stylesheet each). */
    private function animation_styles(array $s): void {
        foreach (['entrance_animation','exit_animation'] as $key) {
            foreach (['','_tablet','_mobile'] as $device) {
                $animation = (string) ($s[$key . $device] ?? '');
                if ($animation !== '' && $animation !== 'none') { \OldBearExpansion\Popups\Popups::animation_style($animation); }
            }
        }
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->add_control('learn_more_alert', ['type'=>Controls_Manager::ALERT,'alert_type'=>'info','content'=>'Para abrir el Off-Canvas, añade un enlace o un botón a la página y elige en su enlace la etiqueta dinámica «Off-Canvas».']);
        $this->add_control('editing_mode', ['label'=>'Modo de edición','description'=>'Muestra el panel abierto en el editor para poder editarlo.','type'=>Controls_Manager::SWITCHER,'label_on'=>'Sí','label_off'=>'No','return_value'=>'yes','default'=>'yes','separator'=>'after']);
        $this->add_control('off_canvas_name', ['label'=>'Nombre del Off-Canvas','type'=>Controls_Manager::TEXT,'default'=>'Off-Canvas','description'=>'Se usa para elegirlo en los enlaces y como nombre accesible del panel.']);
        $this->add_responsive_control('horizontal_position', ['label'=>'Posición horizontal','type'=>Controls_Manager::CHOOSE,'options'=>['flex-start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'flex-end'=>['title'=>'Final','icon'=>'eicon-h-align-right']],'selectors'=>['{{WRAPPER}}'=>'--obx-off-canvas-justify: {{VALUE}}']]);
        $this->add_responsive_control('vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'options'=>['flex-start'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'flex-end'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors'=>['{{WRAPPER}}'=>'--obx-off-canvas-align: {{VALUE}}']]);
        $this->add_responsive_control('width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['max'=>500],'%'=>['max'=>50],'em'=>['min'=>0,'max'=>5],'rem'=>['min'=>0,'max'=>5],'vw'=>['max'=>50]],'selectors'=>['{{WRAPPER}}'=>'--obx-off-canvas-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('height', ['label'=>'Altura','type'=>Controls_Manager::SELECT,'default'=>'custom','options'=>['fit-content'=>'Ajustada al contenido','custom'=>'Personalizada'],'selectors_dictionary'=>['fit-content'=>'--obx-off-canvas-height: fit-content; --obx-off-canvas-overflow: initial;','custom'=>'--obx-off-canvas-height: 100vh; --obx-off-canvas-overflow: auto;'],'selectors'=>['{{WRAPPER}}'=>'{{VALUE}}']]);
        $this->add_responsive_control('custom_height', ['label'=>'Altura personalizada','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vh','custom'],'range'=>['px'=>['max'=>1000],'em'=>['min'=>0],'rem'=>['min'=>0]],'default'=>['unit'=>'vh'],'condition'=>['height'=>'custom'],'selectors'=>['{{WRAPPER}}'=>'--obx-off-canvas-height: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('section_settings', ['label'=>'Ajustes']);
        $this->add_control('animation', ['label'=>'Animación','type'=>Controls_Manager::HEADING]);
        $this->add_responsive_control('entrance_animation', ['label'=>'Entrada','type'=>Controls_Manager::ANIMATION,'frontend_available'=>true]);
        $this->add_responsive_control('exit_animation', ['label'=>'Salida','type'=>Controls_Manager::EXIT_ANIMATION,'frontend_available'=>true]);
        $this->add_responsive_control('offcanvas_animation_duration', ['label'=>'Duración de la animación','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'default'=>['unit'=>'s','size'=>1.5],'range'=>['s'=>['max'=>5,'step'=>0.1],'ms'=>['max'=>5000,'step'=>100]],'selectors'=>['{{WRAPPER}}'=>'--obx-off-canvas-duration: {{SIZE}}{{UNIT}}'],'conditions'=>['relation'=>'or','terms'=>[['name'=>'entrance_animation','operator'=>'!==','value'=>''],['name'=>'exit_animation','operator'=>'!==','value'=>'']]]]);
        $this->add_control('interactions', ['label'=>'Interacciones','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('is_not_close_on_overlay', ['label'=>'No cerrar al pulsar la capa','type'=>Controls_Manager::SWITCHER,'label_on'=>'Sí','label_off'=>'No','return_value'=>'yes','frontend_available'=>true]);
        $this->add_control('is_not_close_on_esc_overlay', ['label'=>'No cerrar con la tecla Esc','type'=>Controls_Manager::SWITCHER,'label_on'=>'Sí','label_off'=>'No','return_value'=>'yes','frontend_available'=>true]);
        $this->add_control('prevent_scroll', ['label'=>'Bloquear el desplazamiento de la página','type'=>Controls_Manager::SWITCHER,'label_on'=>'Sí','label_off'=>'No','return_value'=>'yes','frontend_available'=>true]);
        $this->add_control('wrapper_html_tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>'div','options'=>['div'=>'div','main'=>'main','article'=>'article','header'=>'header','footer'=>'footer','section'=>'section','aside'=>'aside','nav'=>'nav'],'separator'=>'before']);
        $this->end_controls_section();

        $content = '{{WRAPPER}} .obx-off-canvas__content';
        $this->start_controls_section('section_background', ['label'=>'Fondo','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$content,'fields_options'=>['color'=>['label'=>'Fondo']]]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'border','selector'=>$content]);
        $this->add_responsive_control('border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$content=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'box_shadow','selector'=>$content]);
        $this->end_controls_section();

        $this->start_controls_section('section_overlay', ['label'=>'Capa','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('has_overlay', ['label'=>'Capa','type'=>Controls_Manager::SWITCHER,'label_on'=>'Sí','label_off'=>'No','return_value'=>'yes','default'=>'yes','selectors_dictionary'=>['yes'=>'1',''=>'0'],'selectors'=>['{{WRAPPER}}'=>'--obx-off-canvas-overlay-opacity: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'overlay_background','types'=>['classic','gradient'],'selector'=>'{{WRAPPER}} .obx-off-canvas__overlay','fields_options'=>['background'=>['default'=>'classic'],'color'=>['default'=>'rgba(0,0,0,.8)']],'condition'=>['has_overlay'=>'yes']]);
        $this->end_controls_section();
    }

    private static function tag(array $s): string {
        $tag = (string) ($s['wrapper_html_tag'] ?? 'div');
        return in_array($tag, ['div','main','article','header','footer','section','aside','nav'], true) ? $tag : 'div';
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $this->animation_styles($s);
        $tag = self::tag($s);
        // Closed on the site; in the editor it stays open while «Editing mode» is on.
        $open = \OldBearExpansion\Context::is_editor() && ($s['editing_mode'] ?? 'yes') === 'yes';
        printf('<%1$s id="off-canvas-%2$s" class="obx-off-canvas" role="dialog" aria-hidden="%3$s" aria-modal="true" aria-label="%4$s" data-obx-off-canvas="%2$s"%5$s>', $tag, esc_attr($this->get_id()), $open ? 'false' : 'true', esc_attr((string) ($s['off_canvas_name'] ?? 'Off-Canvas')), $open ? '' : ' inert');
        echo '<div class="obx-off-canvas__overlay"></div><div class="obx-off-canvas__main"><div class="obx-off-canvas__content">';
        $this->print_child(0);
        echo '</div></div></' . $tag . '>';
    }

    protected function content_template(): void {
        ?>
        <# const tag = [ 'div', 'main', 'article', 'header', 'footer', 'section', 'aside', 'nav' ].includes( settings.wrapper_html_tag ) ? settings.wrapper_html_tag : 'div', closed = 'yes' !== settings.editing_mode; #>
        <{{ tag }} id="off-canvas-{{ view.getID() }}" class="obx-off-canvas" role="dialog" aria-hidden="{{ closed ? 'true' : 'false' }}" aria-modal="true" aria-label="{{ settings.off_canvas_name }}" data-obx-off-canvas="{{ view.getID() }}">
            <div class="obx-off-canvas__overlay"></div>
            <div class="obx-off-canvas__main"><div class="obx-off-canvas__content"></div></div>
        </{{ tag }}>
        <div class="obx-off-canvas__placeholder"><i class="eicon-off-canvas" aria-hidden="true"></i> {{ settings.off_canvas_name }}</div>
        <?php
    }
}

/**
 * Menu (mega menu): each item is a title with an optional dropdown whose content is a container edited in place.
 * Horizontal bar that switches to a toggle + dropdown list below the chosen breakpoint.
 */
final class Mega_Menu extends Widget_Nested_Base {
    use Nested_Widget;
    public const NAME = '';
    public const PRO = 'mega-menu';
    public const ALIASES = [];
    private const HEADING = '{{WRAPPER}} > .obx-mm > .obx-mm__wrapper > .obx-mm__heading';
    private const TOGGLE_ICON = '{{WRAPPER}} > .obx-mm > .obx-mm__toggle%s > .obx-mm__toggle-icon';
    private const CONTENT = ':where( {{WRAPPER}} > .obx-mm > .obx-mm__wrapper > .obx-mm__heading > .obx-mm__item ) > .e-con';
    private const DROPDOWN_TITLE = "{{WRAPPER}} > .obx-mm[data-layout='dropdown'] > .obx-mm__wrapper > .obx-mm__heading > .obx-mm__item > .obx-mm__title";

    public function get_title(): string { return 'Menú'; }
    public function get_icon(): string { return 'eicon-mega-menu'; }
    public function get_categories(): array { return ['pro-elements', 'theme-elements']; }
    public function get_keywords(): array { return ['mega menu','menu','menú','navegación','nested','anidado']; }
    public function get_script_depends(): array { return ['obx-mega-menu']; }

    private static function item_title(int $n): string { return sprintf('Elemento #%d', $n); }
    protected function get_default_children_elements() {
        return array_map(static fn($n) => ['elType'=>'container','settings'=>['_title'=>self::item_title($n)]], [1, 2, 3]);
    }
    protected function get_default_repeater_title_setting_key() { return 'item_title'; }
    protected function get_default_children_title() { return 'Elemento #%d'; }
    protected function get_default_children_placeholder_selector() { return '.obx-mm__heading'; }
    protected function get_default_children_container_placeholder_selector() { return '.obx-mm__item'; }
    protected function get_initial_config(): array {
        // Elementor's interlaced repeaters: each item container goes inside its own <li>, in the editor as on the site.
        return array_merge(parent::get_initial_config(), ['support_improved_repeaters'=>true,'target_container'=>['.obx-mm__heading'],'node'=>'li','is_interlaced'=>true]);
    }

    /** Text shadow, background, border and box shadow of the titles in one state. */
    private function title_state_groups(string $suffix, string $selector): void {
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'menu_item_title_text_shadow_' . $suffix,'selector'=>$selector,'fields_options'=>['text_shadow_type'=>['label'=>'Sombra']]]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'menu_item_title_background_color' . ($suffix === 'normal' ? '' : '_' . $suffix),'types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$selector,'fields_options'=>['color'=>['label'=>'Color de fondo']]]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'menu_item_title_box_border' . ($suffix === 'normal' ? '' : '_' . $suffix),'selector'=>$selector,'fields_options'=>['color'=>['label'=>'Color del borde'],'width'=>['label'=>'Ancho del borde']]]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'menu_item_title_box_shadow' . ($suffix === 'normal' ? '' : '_' . $suffix),'selector'=>$selector]);
    }
    /** Background, border and box shadow of the toggle icon in one state. */
    private function toggle_state_groups(string $state, string $selector): void {
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'menu_toggle_icon_background_color_' . $state,'types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$selector,'fields_options'=>['color'=>['selectors'=>['{{SELECTOR}}'=>'background: {{VALUE}}']]]]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'menu_toggle_icon_border_' . $state,'selector'=>$selector,'fields_options'=>['color'=>['label'=>'Color del borde'],'width'=>['label'=>'Ancho del borde']]]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'menu_toggle_icon_box_shadow_' . $state,'selector'=>$selector]);
    }
    private static function slider_units(array $units = ['px','em','rem','vw','custom']): array { return $units; }

    /** Breakpoints the layout can switch at («Ninguno» and every active one below widescreen). */
    private static function breakpoint_options(): array {
        $options = ['none'=>'Ninguno'];
        foreach (\Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $key => $breakpoint) {
            if ($key === 'widescreen') { continue; }
            $options[$key] = sprintf('%1$s (> %2$dpx)', $breakpoint->get_label(), $breakpoint->get_value());
        }
        return $options;
    }

    protected function register_controls(): void {
        $start = is_rtl() ? 'end' : 'start'; $end = is_rtl() ? 'start' : 'end';
        $this->start_controls_section('section_layout', ['label'=>'Diseño']);
        $this->add_control('menu_name', ['label'=>'Nombre del menú','type'=>Controls_Manager::TEXT,'default'=>'Menú']);
        $r = new Repeater();
        $r->add_control('item_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Título del elemento','dynamic'=>['active'=>true],'label_block'=>true]);
        $r->add_control('item_link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'placeholder'=>'Pega la URL o escribe','dynamic'=>['active'=>true],'frontend_available'=>true]);
        $r->add_control('item_dropdown_content', ['label'=>'Contenido desplegable','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','default'=>'no','description'=>'Pulsa el elemento del menú para editar su contenido desplegable.','frontend_available'=>true]);
        $r->add_control('item_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','skin'=>'inline','label_block'=>false]);
        $r->add_control('item_icon_active', ['label'=>'Icono activo','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','skin'=>'inline','label_block'=>false,'condition'=>['item_icon[value]!'=>'']]);
        $r->add_control('element_id', ['label'=>'ID CSS','type'=>Controls_Manager::TEXT,'default'=>'','dynamic'=>['active'=>true],'ai'=>['active'=>false],'title'=>'Escribe el ID sin la almohadilla, por ejemplo: mi-id','style_transfer'=>false]);
        $this->add_control('menu_items', ['label'=>'Elementos del menú','type'=>\Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater::CONTROL_TYPE,'fields'=>$r->get_controls(),'default'=>[['item_title'=>self::item_title(1)],['item_title'=>self::item_title(2)],['item_title'=>self::item_title(3)]],'title_field'=>'{{{ item_title }}}','button_text'=>'Añadir elemento','frontend_available'=>true]);
        $this->add_control('content_width', ['label'=>'Ancho del contenido','type'=>Controls_Manager::SELECT,'default'=>'full_width','separator'=>'before','options'=>['full_width'=>'Ancho completo','fit_to_content'=>'Ajustado al contenido'],'prefix_class'=>'obx-mm--','frontend_available'=>true,'selectors_dictionary'=>['full_width'=>'initial','fit_to_content'=>'fit-content'],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-content-max-width: {{VALUE}};']]);
        $horizontal = ['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']];
        $this->add_control('content_horizontal_position', ['label'=>'Posición horizontal del contenido','type'=>Controls_Manager::CHOOSE,'options'=>is_rtl() ? array_reverse($horizontal) : $horizontal,'default'=>'center','condition'=>['content_width'=>'fit_to_content'],'frontend_available'=>true,'render_type'=>'ui']);
        $this->add_control('item_layout', ['label'=>'Disposición de los elementos','type'=>Controls_Manager::SELECT,'default'=>'horizontal','options'=>['horizontal'=>'Horizontal','dropdown'=>'Desplegable'],'prefix_class'=>'obx-mm-layout-','frontend_available'=>true]);
        $this->add_responsive_control('item_position_horizontal', ['label'=>'Posición de los elementos','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Inicio','icon'=>"eicon-align-{$start}-h"],'center'=>['title'=>'Centro','icon'=>'eicon-align-center-h'],'end'=>['title'=>'Final','icon'=>"eicon-align-{$end}-h"],'stretch'=>['title'=>'Estirar','icon'=>'eicon-align-stretch-h']],'selectors_dictionary'=>[
            'start'=>'--obx-mm-heading-justify: initial; --obx-mm-title-grow: initial; --obx-mm-title-justify: initial; --obx-mm-title-justify-dropdown: initial;',
            'center'=>'--obx-mm-heading-justify: center; --obx-mm-title-grow: initial; --obx-mm-title-justify: initial; --obx-mm-title-justify-dropdown: center;',
            'end'=>'--obx-mm-heading-justify: flex-end; --obx-mm-title-grow: initial; --obx-mm-title-justify: initial; --obx-mm-title-justify-dropdown: flex-end;',
            'stretch'=>'--obx-mm-heading-justify: space-between; --obx-mm-title-grow: 1; --obx-mm-title-justify: center; --obx-mm-title-justify-dropdown: center;',
        ],'selectors'=>['{{WRAPPER}}'=>'{{VALUE}}'],'condition'=>['item_layout'=>'horizontal'],'frontend_available'=>true]);
        $this->add_responsive_control('item_position_dropdown', ['label'=>'Posición de los elementos','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Inicio','icon'=>"eicon-align-{$start}-h"],'center'=>['title'=>'Centro','icon'=>'eicon-align-center-h'],'end'=>['title'=>'Final','icon'=>"eicon-align-{$end}-h"]],'selectors_dictionary'=>[
            'start'=>'--obx-mm-title-justify: initial; --obx-mm-title-justify-dropdown: initial;',
            'center'=>'--obx-mm-title-justify: center; --obx-mm-title-justify-dropdown: center;',
            'end'=>'--obx-mm-title-justify: flex-end; --obx-mm-title-justify-dropdown: flex-end;',
        ],'selectors'=>['{{WRAPPER}}'=>'{{VALUE}}'],'condition'=>['item_layout'=>'dropdown']]);
        $this->add_control('submenu_indicator_title', ['type'=>Controls_Manager::HEADING,'label'=>'Indicador del desplegable','separator'=>'before']);
        $this->add_control('menu_item_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-caret-down','library'=>'fa-solid'],'recommended'=>['fa-solid'=>['chevron-down','angle-down','angle-double-down','caret-down','caret-square-down'],'fa-regular'=>['caret-square-down']]]);
        $this->add_control('menu_item_icon_active', ['label'=>'Icono activo','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon_active','skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-caret-up','library'=>'fa-solid'],'recommended'=>['fa-solid'=>['chevron-up','angle-up','angle-double-up','caret-up','caret-square-up'],'fa-regular'=>['caret-square-up']]]);
        $this->end_controls_section();

        $this->start_controls_section('section_dropdown_effect', ['label'=>'Efecto del desplegable']);
        $this->add_control('open_on', ['label'=>'Abrir al','type'=>Controls_Manager::SELECT,'default'=>'hover','options'=>['hover'=>'Pasar el ratón','click'=>'Hacer clic'],'condition'=>['item_layout'=>'horizontal'],'frontend_available'=>true]);
        $this->add_control('open_on_hover_description', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Al pasar el ratón no se abre mientras editas. Previsualiza la página para verlo.','content_classes'=>'elementor-control-field-description','condition'=>['item_layout'=>'horizontal','open_on'=>'hover']]);
        $this->add_control('open_animation', ['label'=>'Animación','type'=>'animation_menu_dropdown','default'=>'','frontend_available'=>true]);
        $this->add_control('open_animation_duration', ['label'=>'Duración de la animación','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'default'=>['unit'=>'ms','size'=>500],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>100]],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-open-duration: {{SIZE}}{{UNIT}}'],'condition'=>['open_animation!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('menu_toggle_section', ['label'=>'Botón del menú']);
        $this->add_responsive_control('toggle_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>['flex-start'=>['title'=>'Inicio','icon'=>"eicon-align-{$start}-h"],'center'=>['title'=>'Centro','icon'=>'eicon-align-center-h'],'flex-end'=>['title'=>'Final','icon'=>"eicon-align-{$end}-h"]],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-align: {{VALUE}}']]);
        $this->start_controls_tabs('menu_toggle_tabs_section');
        $this->start_controls_tab('menu_toggle_normal_options', ['label'=>'Normal']);
        $toggle_skin = ['inline'=>['none'=>['label'=>'Predeterminado','icon'=>'eicon-menu-bar'],'icon'=>['icon'=>'eicon-star']]];
        $this->add_control('menu_toggle_icon_normal', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','skin'=>'inline','label_block'=>false,'skin_settings'=>$toggle_skin,'recommended'=>['fa-solid'=>['plus-square','plus','plus-circle','bars'],'fa-regular'=>['plus-square']]]);
        $this->end_controls_tab();
        $this->start_controls_tab('menu_toggle_hover_options', ['label'=>'Al pasar el ratón']);
        $this->add_control('menu_toggle_icon_hover_animation', ['label'=>'Animación al pasar el ratón','type'=>Controls_Manager::HOVER_ANIMATION,'frontend_available'=>true,'render_type'=>'template']);
        $this->add_control('menu_toggle_animation_duration', ['label'=>'Duración de la animación','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'render_type'=>'ui','default'=>['unit'=>'ms','size'=>500],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>100]],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-animation-duration: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_tab();
        $this->start_controls_tab('menu_toggle_active_options', ['label'=>'Activo']);
        $this->add_control('menu_toggle_icon_active', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'fa4compatibility'=>'icon','skin'=>'inline','label_block'=>false,'skin_settings'=>['inline'=>['none'=>['label'=>'Predeterminado','icon'=>'eicon-close'],'icon'=>['icon'=>'eicon-star']]],'recommended'=>['fa-solid'=>['window-close','times-circle','times','minus-square','minus-circle','minus'],'fa-regular'=>['window-close','times-circle','minus-square']]]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('section_responsive_mega_menu', ['label'=>'Ajustes adicionales']);
        $this->add_responsive_control('horizontal_scroll', ['label'=>'Desplazamiento horizontal','type'=>Controls_Manager::SELECT,'description'=>'Desplaza los elementos del menú si no caben en su contenedor.','options'=>['disable'=>'Desactivado','enable'=>'Activado'],'default'=>'disable','selectors_dictionary'=>['disable'=>'--obx-mm-heading-wrap: wrap; --obx-mm-heading-overflow-x: initial;','enable'=>'--obx-mm-heading-wrap: nowrap; --obx-mm-heading-overflow-x: scroll;'],'selectors'=>['{{WRAPPER}}'=>'{{VALUE}}'],'frontend_available'=>true,'condition'=>['item_layout'=>'horizontal']]);
        $this->add_control('breakpoint_selector', ['label'=>'Punto de ruptura','type'=>Controls_Manager::SELECT,'description'=>'Los elementos pasan a la disposición desplegable en pantallas más pequeñas que el punto elegido.','options'=>self::breakpoint_options(),'default'=>'tablet','prefix_class'=>'obx-mm-bp-','frontend_available'=>true]);
        $this->end_controls_section();

        $this->register_style_controls();
    }

    private function register_style_controls(): void {
        $heading = self::HEADING;
        $title = "{$heading} > .obx-mm__item > .obx-mm__title";
        $inline_start = is_rtl() ? '{{RIGHT}}{{UNIT}}' : '{{LEFT}}{{UNIT}}'; $inline_end = is_rtl() ? '{{LEFT}}{{UNIT}}' : '{{RIGHT}}{{UNIT}}';

        $this->start_controls_section('section_menu_items_style', ['label'=>'Elementos del menú','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('menu_item_title_space_between', ['label'=>'Separación entre elementos','type'=>Controls_Manager::SLIDER,'size_units'=>self::slider_units(),'range'=>['px'=>['max'=>200],'em'=>['max'=>10],'rem'=>['max'=>10]],'default'=>['size'=>0],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-space-between: {{SIZE}}{{UNIT}}']]);
        $this->add_responsive_control('menu_item_title_distance_from_content', ['label'=>'Distancia al contenido','type'=>Controls_Manager::SLIDER,'size_units'=>self::slider_units(),'range'=>['px'=>['max'=>200],'em'=>['max'=>10],'rem'=>['max'=>10]],'default'=>['size'=>0],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-distance-from-content: {{SIZE}}{{UNIT}}'],'frontend_available'=>true]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'menu_item_title_typography','global'=>['default'=>Global_Typography::TYPOGRAPHY_ACCENT],'selector'=>"{$title}, {$title} > .obx-mm__title-container, {$title} > .obx-mm__title-container > span",'fields_options'=>['font_size'=>['selectors'=>['{{WRAPPER}}'=>'--obx-mm-title-font-size: {{SIZE}}{{UNIT}}']],'line_height'=>['selectors'=>['{{SELECTOR}}'=>'--obx-mm-title-line-height: {{SIZE}}']]]]);
        $this->start_controls_tabs('menu_item_title_style');
        $this->start_controls_tab('menu_item_title_normal', ['label'=>'Normal']);
        $this->add_control('menu_item_title_text_color_normal', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-title-color: {{VALUE}}']]);
        $this->title_state_groups('normal', "{$title}:not( .is-current ):not( :hover )");
        $divider = ['menu_divider'=>'yes','item_layout'=>'horizontal'];
        $this->add_control('menu_divider', ['label'=>'Separador','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','condition'=>['item_layout'=>'horizontal'],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-divider-content: "";'],'separator'=>'before']);
        $this->add_control('menu_divider_style', ['label'=>'Estilo','type'=>Controls_Manager::SELECT,'options'=>['solid'=>'Continuo','double'=>'Doble','dotted'=>'Punteado','dashed'=>'Discontinuo'],'default'=>'solid','condition'=>$divider,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-divider-style: {{VALUE}}']]);
        $this->add_control('menu_divider_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'condition'=>$divider,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-divider-width: {{SIZE}}{{UNIT}}']]);
        $this->add_control('menu_divider_height', ['label'=>'Altura','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vh','custom'],'range'=>['px'=>['max'=>100],'%'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'condition'=>$divider,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-divider-height: {{SIZE}}{{UNIT}}']]);
        $this->add_control('menu_divider_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'global'=>['default'=>Global_Colors::COLOR_TEXT],'condition'=>$divider,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-divider-color: {{VALUE}}']]);
        $this->end_controls_tab();
        $this->start_controls_tab('menu_item_title_hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('menu_item_title_text_color_hover', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-title-color-hover: {{VALUE}}']]);
        $this->title_state_groups('hover', "{$title}:hover:not( .is-current )");
        $this->add_control('hover_animation', ['label'=>'Animación al pasar el ratón','type'=>Controls_Manager::HOVER_ANIMATION]);
        $this->add_control('menu_item_title_transition_duration', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>100]],'default'=>['unit'=>'ms','size'=>300],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-title-transition: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_tab();
        $this->start_controls_tab('menu_item_title_active', ['label'=>'Activo']);
        $this->add_control('menu_item_title_text_color_active', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-title-color-active: {{VALUE}}']]);
        $this->title_state_groups('active', "{$title}.is-current");
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_responsive_control('menu_item_title_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'separator'=>'before','selectors'=>[$title=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('menu_item_title_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-title-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('icon_section_style', ['label'=>'Icono','tab'=>Controls_Manager::TAB_STYLE]);
        $block_start = '--obx-mm-title-direction: column; --obx-mm-icon-order: initial; --obx-mm-icon-align: flex-end; --obx-mm-title-justify: center; --obx-mm-title-align-toggle: initial;';
        $inline_end_style = '--obx-mm-title-direction: row; --obx-mm-icon-order: 1; --obx-mm-icon-align: initial; --obx-mm-title-justify: initial; --obx-mm-title-align-toggle: center;';
        $block_end = '--obx-mm-title-direction: column; --obx-mm-icon-order: 1; --obx-mm-icon-align: flex-start; --obx-mm-title-justify: center; --obx-mm-title-align-toggle: initial;';
        $inline_start_style = '--obx-mm-title-direction: row; --obx-mm-icon-order: initial; --obx-mm-icon-align: initial; --obx-mm-title-justify: initial; --obx-mm-title-align-toggle: center;';
        $this->add_responsive_control('icon_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>['block-start'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'inline-end'=>['title'=>'Después','icon'=>'eicon-h-align-' . (is_rtl() ? 'left' : 'right')],'block-end'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom'],'inline-start'=>['title'=>'Antes','icon'=>'eicon-h-align-' . (is_rtl() ? 'right' : 'left')]],
            // Old values (top, end, bottom, start) still style the same way.
            'selectors_dictionary'=>['block-start'=>$block_start,'inline-end'=>$inline_end_style,'block-end'=>$block_end,'inline-start'=>$inline_start_style,'top'=>$block_start,'end'=>$inline_end_style,'bottom'=>$block_end,'start'=>$inline_start_style],'selectors'=>['{{WRAPPER}}'=>'{{VALUE}}']]);
        $this->add_responsive_control('icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'default'=>['unit'=>'px','size'=>16],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-icon-size: {{SIZE}}{{UNIT}}']]);
        $this->add_responsive_control('icon_spacing', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'range'=>['px'=>['max'=>400],'vw'=>['max'=>50,'step'=>0.1]],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-icon-gap: {{SIZE}}{{UNIT}}']]);
        $this->start_controls_tabs('icon_style_states');
        foreach (['normal'=>['icon_color','Normal','--obx-mm-icon-color'],'hover'=>['icon_color_hover','Al pasar el ratón','--obx-mm-icon-color-hover'],'active'=>['icon_color_active','Activo','--obx-mm-icon-color-active']] as $state => [$key, $label, $var]) {
            $this->start_controls_tab('icon_section_' . $state, ['label'=>$label]);
            $this->add_control($key, ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>$var . ': {{VALUE}};']]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('section_dropdown_indicator_style', ['label'=>'Indicador del desplegable','tab'=>Controls_Manager::TAB_STYLE,'conditions'=>['relation'=>'or','terms'=>[['name'=>'menu_item_icon[value]','operator'=>'!==','value'=>''],['name'=>'menu_item_icon_active[value]','operator'=>'!==','value'=>'']]]]);
        $this->add_responsive_control('style_dropdown_indicator_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-indicator-size: {{SIZE}}{{UNIT}}']]);
        $this->add_responsive_control('style_dropdown_indicator_rotate', ['label'=>'Rotación','type'=>Controls_Manager::SLIDER,'size_units'=>['deg','grad','rad','turn','custom'],'default'=>['unit'=>'deg'],'tablet_default'=>['unit'=>'deg'],'mobile_default'=>['unit'=>'deg'],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-indicator-rotate: rotate({{SIZE}}{{UNIT}})']]);
        $this->add_responsive_control('style_dropdown_indicator_space', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-indicator-space: {{SIZE}}{{UNIT}}']]);
        $this->start_controls_tabs('style_menu_dropdown_indicator');
        foreach (['normal'=>'Normal','hover'=>'Al pasar el ratón','active'=>'Activo'] as $state => $label) {
            $this->start_controls_tab('style_menu_dropdown_indicator_' . $state, ['label'=>$label]);
            $this->add_control('menu_dropdown_indicator_color_' . $state, ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--obx-mm-indicator-color-' . $state . ': {{VALUE}};']]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('section_menu_toggle_style', ['label'=>'Botón del menú','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('style_menu_toggle_icon_heading', ['label'=>'Icono del botón','type'=>Controls_Manager::HEADING]);
        $this->add_responsive_control('style_menu_toggle_icon_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vw','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'default'=>['size'=>20],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-size: {{SIZE}}{{UNIT}}']]);
        $this->start_controls_tabs('style_menu_toggle_tabs');
        $this->start_controls_tab('style_menu_toggle_tab_normal', ['label'=>'Normal']);
        $this->add_control('menu_toggle_icon_color_normal', ['label'=>'Color','type'=>Controls_Manager::COLOR,'render_type'=>'ui','selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-color: {{VALUE}};']]);
        $this->toggle_state_groups('normal', sprintf(self::TOGGLE_ICON, '[aria-expanded="false"]:not( :hover )'));
        $this->end_controls_tab();
        $this->start_controls_tab('style_menu_toggle_hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('menu_toggle_icon_color_hover', ['label'=>'Color','type'=>Controls_Manager::COLOR,'render_type'=>'ui','selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-color-hover: {{VALUE}};']]);
        $this->toggle_state_groups('hover', sprintf(self::TOGGLE_ICON, ':hover:is( [aria-expanded="true"], [aria-expanded="false"] )'));
        $this->add_control('menu_toggle_icon_animation_duration', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'size_units'=>['s','ms','custom'],'range'=>['s'=>['min'=>0,'max'=>3,'step'=>0.1],'ms'=>['min'=>0,'max'=>3000,'step'=>100]],'default'=>['unit'=>'ms','size'=>500],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-hover-duration: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_tab();
        $this->start_controls_tab('style_menu_toggle_active', ['label'=>'Activo']);
        $this->add_control('menu_toggle_icon_color_active', ['label'=>'Color','type'=>Controls_Manager::COLOR,'render_type'=>'ui','selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-color-active: {{VALUE}};']]);
        $this->toggle_state_groups('active', sprintf(self::TOGGLE_ICON, '[aria-expanded="true"]'));
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_responsive_control('menu_toggle_icon_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'separator'=>'before','selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('menu_toggle_icon__padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('menu_toggle_icon_distance_from_dropdown', ['label'=>'Distancia al desplegable','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh','custom'],'range'=>['px'=>['max'=>100],'em'=>['max'=>10],'rem'=>['max'=>10]],'default'=>['size'=>0],'placeholder'=>['size'=>0],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-toggle-distance: {{SIZE}}{{UNIT}}']]);
        $this->end_controls_section();

        $content = self::CONTENT;
        $this->start_controls_section('section_content_style', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'content_background_color','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$content,'fields_options'=>['color'=>['label'=>'Color de fondo']]]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'content_border','selector'=>$content,'fields_options'=>['color'=>['label'=>'Color del borde'],'width'=>['label'=>'Ancho del borde']]]);
        $this->add_responsive_control('content_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$content=>'--border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'content_shadow','selector'=>$content]);
        $this->add_responsive_control('content_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'separator'=>'before','selectors'=>[$content=>"--padding-top: {{TOP}}{{UNIT}}; --padding-right: {{RIGHT}}{{UNIT}}; --padding-bottom: {{BOTTOM}}{{UNIT}}; --padding-left: {{LEFT}}{{UNIT}}; --padding-block-start: {{TOP}}{{UNIT}}; --padding-inline-end: {$inline_end}; --padding-block-end: {{BOTTOM}}{{UNIT}}; --padding-inline-start: {$inline_start};"]]);
        $this->end_controls_section();

        $this->start_controls_section('section_dropdown_menu_style', ['label'=>'Menú desplegable','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('dropdown_menu_items_title', ['label'=>'Elementos del menú','type'=>Controls_Manager::HEADING]);
        $this->add_control('dropdown_menu_items_description', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Estos estilos se aplican a los elementos cuando el menú pasa a la disposición desplegable.','content_classes'=>'elementor-control-field-description']);
        $this->start_controls_tabs('menu_dropdown_states_section');
        foreach (['normal'=>['normal_menu_dropdown_states','Normal','--obx-mm-dropdown-title-color',':not( .is-current )'],'active'=>['active_menu_dropdown_states','Activo','--obx-mm-dropdown-title-color-active','.is-current']] as $state => [$tab, $label, $var, $suffix]) {
            $this->start_controls_tab($tab, ['label'=>$label]);
            $this->add_control('dropdown_menu_item_text_color_' . $state, ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>$var . ': {{VALUE}};']]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'dropdown_menu_item_background_color_' . $state,'types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>self::DROPDOWN_TITLE . $suffix,'fields_options'=>['color'=>['selectors'=>['{{SELECTOR}}'=>'background: {{VALUE}}']]]]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'dropdown_menu_item_box_shadow_' . $state,'selector'=>self::DROPDOWN_TITLE . $suffix]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->add_control('menu_dropdown_box_title', ['label'=>'Caja del desplegable','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_control('menu_dropdown_box_description', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Estilo de la caja que contiene los elementos del menú desplegable.','content_classes'=>'elementor-control-field-description']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'menu_dropdown_box_border','fields_options'=>[
            'border'=>['selectors'=>['{{WRAPPER}}'=>'--obx-mm-box-border-style: {{VALUE}}']],
            'color'=>['selectors'=>['{{WRAPPER}}'=>'--obx-mm-box-border-color: {{VALUE}}']],
            'width'=>['selectors'=>['{{WRAPPER}}'=>"--obx-mm-box-border-block-start: {{TOP}}{{UNIT}}; --obx-mm-box-border-inline-end: {$inline_end}; --obx-mm-box-border-block-end: {{BOTTOM}}{{UNIT}}; --obx-mm-box-border-inline-start: {$inline_start};"]],
        ]]);
        $this->add_control('menu_dropdown_box_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}}'=>'--obx-mm-box-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'menu_dropdown_box_shadow','fields_options'=>['box_shadow'=>['selectors'=>['{{WRAPPER}}'=>'--obx-mm-box-shadow: {{box_shadow_position.VALUE}} {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{SPREAD}}px {{COLOR}};']]]]);
        $this->end_controls_section();
    }

    /** Host, path and query of a URL, as compared to mark the current item. */
    private static function url_parts(string $url): array {
        $parts = wp_parse_url($url) ?: [];
        return ['host'=>str_replace('www.', '', (string) ($parts['host'] ?? '')),'path'=>trim((string) ($parts['path'] ?? ''), '/'),'query'=>(string) ($parts['query'] ?? '')];
    }
    /** URL of the page being viewed (archives, search and the posts page included). */
    private static function current_url(): string {
        if (is_front_page()) { return home_url('/'); }
        if (is_home()) { return (string) get_post_type_archive_link('post'); }
        if (is_year()) { return get_year_link((int) get_query_var('year')); }
        if (is_month()) { return get_month_link((int) get_query_var('year'), (int) get_query_var('monthnum')); }
        if (is_day()) { return get_day_link((int) get_query_var('year'), (int) get_query_var('monthnum'), (int) get_query_var('day')); }
        if (is_category() || is_tag() || is_tax()) { $term = get_queried_object(); $link = $term instanceof \WP_Term ? get_term_link($term) : ''; return is_string($link) ? $link : ''; }
        if (is_author()) { return get_author_posts_url((int) get_queried_object_id()); }
        if (is_search()) { return get_search_link(); }
        if (is_post_type_archive()) { return (string) get_post_type_archive_link((string) get_query_var('post_type')); }
        return (string) get_permalink();
    }
    private static function item_state(string $url): string {
        $url = trim($url);
        if ($url === '') { return ''; }
        if (str_contains($url, '#')) { return 'is-anchor'; }
        $current = self::current_url();
        return $current !== '' && self::url_parts($current) === self::url_parts($url) ? 'is-current' : '';
    }
    private static function has_content(?\Elementor\Element_Base $child): bool { return $child && !empty($child->get_data()['elements']); }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $id = $this->get_id();
        $items = array_values(array_filter((array) ($s['menu_items'] ?? []), 'is_array'));
        $children = $this->get_children();
        if (!empty($s['open_animation'])) { \OldBearExpansion\Popups\Popups::animation_style((string) $s['open_animation']); }
        $click = ($s['open_on'] ?? 'hover') === 'click';
        $indicator = Icons_Manager::try_get_icon_html($s['menu_item_icon'] ?? [], ['aria-hidden'=>'true']);
        $indicator_active = Icons_Manager::try_get_icon_html($s['menu_item_icon_active'] ?? [], ['aria-hidden'=>'true']);
        $layout = ($s['item_layout'] ?? 'horizontal') === 'dropdown' ? 'dropdown' : 'horizontal';
        echo '<nav class="obx-mm" data-layout="' . esc_attr($layout) . '"' . ((string) ($s['menu_name'] ?? '') !== '' ? ' aria-label="' . esc_attr((string) $s['menu_name']) . '"' : '') . '>';
        $animation = !empty($s['menu_toggle_icon_hover_animation']) ? ' elementor-animation-' . sanitize_html_class((string) $s['menu_toggle_icon_hover_animation']) : '';
        $open_icon = !empty($s['menu_toggle_icon_normal']['value']) ? $s['menu_toggle_icon_normal'] : ['library'=>'eicons','value'=>'eicon-menu-bar'];
        $close_icon = !empty($s['menu_toggle_icon_active']['value']) ? $s['menu_toggle_icon_active'] : ['library'=>'eicons','value'=>'eicon-close'];
        printf('<button type="button" class="obx-mm__toggle%1$s" id="obx-mm-toggle-%2$s" aria-haspopup="true" aria-expanded="false" aria-controls="obx-mm-bar-%2$s" aria-label="Abrir o cerrar el menú"><span class="obx-mm__toggle-icon obx-mm__toggle-icon--open">%3$s</span><span class="obx-mm__toggle-icon obx-mm__toggle-icon--close">%4$s</span></button>', esc_attr($animation), esc_attr($id), Icons_Manager::try_get_icon_html($open_icon, ['aria-hidden'=>'true']), Icons_Manager::try_get_icon_html($close_icon, ['aria-hidden'=>'true']));
        echo '<div class="obx-mm__wrapper" id="obx-mm-bar-' . esc_attr($id) . '" aria-labelledby="obx-mm-toggle-' . esc_attr($id) . '"><ul class="obx-mm__heading">';
        foreach ($items as $index => $item) {
            $n = $index + 1;
            $url = (string) ($item['item_link']['url'] ?? '');
            $child = $children[$index] ?? null;
            $dropdown = ($item['item_dropdown_content'] ?? '') === 'yes';
            $content = $dropdown && self::has_content($child);
            $title_id = (string) ($item['element_id'] ?? '') !== '' ? (string) $item['element_id'] : 'obx-mm-title-' . $id . $n;
            $state = self::item_state($url);
            $classes = array_filter(['obx-mm__title', $state, $click && $content ? 'is-click' : '', !empty($s['hover_animation']) ? 'elementor-animation-' . sanitize_html_class((string) $s['hover_animation']) : '']);
            echo '<li class="obx-mm__item"><div id="' . esc_attr($title_id) . '" class="' . esc_attr(implode(' ', $classes)) . '">';
            $key = 'title-container-' . $index;
            $this->add_render_attribute($key, 'class', $url !== '' ? ['obx-mm__title-container', 'is-link'] : ['obx-mm__title-container']);
            if ($url !== '') { $this->add_link_attributes($key, (array) $item['item_link']); }
            if ($state === 'is-current') { $this->add_render_attribute($key, 'aria-current', 'page'); }
            echo '<' . ($url !== '' ? 'a' : 'div') . ' ' . $this->get_render_attribute_string($key) . '>';
            $icon = Icons_Manager::try_get_icon_html($item['item_icon'] ?? [], ['aria-hidden'=>'true']);
            if ($icon) {
                $active = !empty($item['item_icon_active']['value']) ? Icons_Manager::try_get_icon_html($item['item_icon_active'], ['aria-hidden'=>'true']) : $icon;
                echo '<span class="obx-mm__icon"><span class="obx-mm__icon-active">' . $active . '</span><span class="obx-mm__icon-inactive">' . $icon . '</span></span>'; // Elementor icon markup.
            }
            echo '<span class="obx-mm__title-text">' . wp_kses_post((string) ($item['item_title'] ?? '')) . '</span></' . ($url !== '' ? 'a' : 'div') . '>';
            if ($dropdown) {
                $label = wp_strip_all_tags((string) ($item['item_title'] ?? ''));
                printf('<button type="button" id="obx-mm-dropdown-%1$s%2$d" class="obx-mm__dropdown-icon" data-tab-index="%2$d" aria-haspopup="true" aria-expanded="false" aria-controls="obx-mm-content-%1$s%2$d"><span class="obx-mm__dropdown-icon-opened">%3$s<span class="elementor-screen-only">Cerrar %5$s</span></span><span class="obx-mm__dropdown-icon-closed">%4$s<span class="elementor-screen-only">Abrir %5$s</span></span></button>', esc_attr($id), $n, $indicator_active, $indicator, esc_html($label));
            }
            echo '</div>';
            if ($content) {
                // The item's container is the dropdown, right after its title (as Elementor places it in the editor).
                $child->add_render_attribute('_wrapper', ['id'=>'obx-mm-content-' . $id . $n,'data-tab-index'=>(string) $n,'aria-labelledby'=>$title_id]);
                $child->print_element();
            }
            echo '</li>';
        }
        echo '</ul></div></nav>';
    }

    /** Editor: one menu item (Elementor adds or re-renders single items with this template). */
    private static function item_template(string $data, string $index): string {
        return <<<JS
            <#
            const itemIndex = {$index}, itemNumber = itemIndex + 1, itemData = {$data},
                itemUrl = 'string' === typeof itemData.item_link ? itemData.item_link : ( itemData.item_link && itemData.item_link.url ) || '',
                itemIcon = itemData.item_icon && itemData.item_icon.value ? elementor.helpers.renderIcon( view, itemData.item_icon, { 'aria-hidden': true }, 'i', 'object' ) : null,
                itemIconActive = itemData.item_icon_active && itemData.item_icon_active.value ? elementor.helpers.renderIcon( view, itemData.item_icon_active, { 'aria-hidden': true }, 'i', 'object' ) : itemIcon,
                rootSettings = view.container.settings.attributes,
                indicator = elementor.helpers.renderIcon( view, rootSettings.menu_item_icon, { 'aria-hidden': true }, 'i', 'object' ),
                indicatorActive = elementor.helpers.renderIcon( view, rootSettings.menu_item_icon_active, { 'aria-hidden': true }, 'i', 'object' ),
                titleId = itemData.element_id || 'obx-mm-title-' + view.getID() + itemNumber,
                titleClasses = [ 'obx-mm__title', itemUrl.indexOf( '#' ) !== -1 ? 'is-anchor' : '', rootSettings.hover_animation ? 'elementor-animation-' + rootSettings.hover_animation : '', 'click' === rootSettings.open_on && 'yes' === itemData.item_dropdown_content ? 'is-click' : '' ].filter( Boolean ).join( ' ' ),
                tag = itemUrl ? 'a' : 'div';
            #>
            <li class="obx-mm__item">
                <div id="{{ titleId }}" class="{{ titleClasses }}">
                    <{{ tag }} class="obx-mm__title-container<# if ( itemUrl ) { #> is-link<# } #>"<# if ( itemUrl ) { #> href="{{ itemUrl }}"<# } #>>
                        <# if ( itemIcon && itemIcon.value ) { #><span class="obx-mm__icon"><span class="obx-mm__icon-active">{{{ itemIconActive.value }}}</span><span class="obx-mm__icon-inactive">{{{ itemIcon.value }}}</span></span><# } #>
                        <span class="obx-mm__title-text">{{{ itemData.item_title }}}</span>
                    </{{ tag }}>
                    <# if ( 'yes' === itemData.item_dropdown_content ) { #>
                    <button type="button" id="obx-mm-dropdown-{{ view.getID() }}{{ itemNumber }}" class="obx-mm__dropdown-icon" data-tab-index="{{ itemNumber }}" aria-haspopup="true" aria-expanded="false" aria-controls="obx-mm-content-{{ view.getID() }}{{ itemNumber }}">
                        <span class="obx-mm__dropdown-icon-opened"><# if ( indicatorActive ) { #>{{{ indicatorActive.value }}}<# } #></span>
                        <span class="obx-mm__dropdown-icon-closed"><# if ( indicator ) { #>{{{ indicator.value }}}<# } #></span>
                    </button>
                    <# } #>
                </div>
            </li>
JS;
    }

    protected function content_template(): void {
        ?>
        <# const toggleOpen = settings.menu_toggle_icon_normal && settings.menu_toggle_icon_normal.value ? elementor.helpers.renderIcon( view, settings.menu_toggle_icon_normal, { 'aria-hidden': true }, 'i', 'object' ).value : '<i class="eicon-menu-bar" aria-hidden="true"></i>',
            toggleClose = settings.menu_toggle_icon_active && settings.menu_toggle_icon_active.value ? elementor.helpers.renderIcon( view, settings.menu_toggle_icon_active, { 'aria-hidden': true }, 'i', 'object' ).value : '<i class="eicon-close" aria-hidden="true"></i>'; #>
        <nav class="obx-mm" data-layout="{{ 'dropdown' === settings.item_layout ? 'dropdown' : 'horizontal' }}" aria-label="{{ settings.menu_name }}">
            <button type="button" class="obx-mm__toggle elementor-clickable<# if ( settings.menu_toggle_icon_hover_animation ) { #> elementor-animation-{{ settings.menu_toggle_icon_hover_animation }}<# } #>" id="obx-mm-toggle-{{ view.getID() }}" aria-haspopup="true" aria-expanded="false" aria-controls="obx-mm-bar-{{ view.getID() }}" aria-label="Abrir o cerrar el menú">
                <span class="obx-mm__toggle-icon obx-mm__toggle-icon--open">{{{ toggleOpen }}}</span>
                <span class="obx-mm__toggle-icon obx-mm__toggle-icon--close">{{{ toggleClose }}}</span>
            </button>
            <div class="obx-mm__wrapper" id="obx-mm-bar-{{ view.getID() }}" aria-labelledby="obx-mm-toggle-{{ view.getID() }}">
                <ul class="obx-mm__heading">
                <# _.each( settings.menu_items, function( item, index ) { #>
                    <?php echo self::item_template('item', 'index'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template. ?>
                <# } ); #>
                </ul>
            </div>
        </nav>
        <?php
    }

    protected function content_template_single_repeater_item(): void {
        echo self::item_template('data', 'view.collection.length'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template.
    }
}
