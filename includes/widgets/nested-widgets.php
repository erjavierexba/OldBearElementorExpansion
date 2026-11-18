<?php
namespace OldBearExpansion\Widgets;

if (!defined('ABSPATH')) { exit; }

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;

/** Nested widgets need Elementor's nested elements and containers. */
trait Nested_Widget {
    public static function names(): array { return [self::PRO]; }
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
