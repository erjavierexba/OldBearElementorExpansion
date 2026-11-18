<?php
namespace OldBearExpansion\Widgets;

// Loaded by Module::load_widgets() once Elementor has loaded its Image widget (it includes core widgets itself).

if (!defined('ABSPATH')) { exit; }

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Utils;

/**
 * Hotspot: an image with points on it (circle, icon and/or label), each with an optional link
 * and a tooltip opened on click, on hover or always shown. Built on Elementor's Image widget, as Pro's.
 */
final class Hotspot extends \Elementor\Widget_Image {
    use Pro_Widget;
    public const NAME = '';
    public const PRO = 'hotspot';
    public const ALIASES = [];

    public function get_title(): string { return 'Hotspot'; }
    public function get_icon(): string { return 'eicon-image-hotspot'; }
    public function get_keywords(): array { return ['image','imagen','tooltip','CTA','dot','punto','hotspot']; }
    public function get_style_depends(): array { return ['obx-frontend']; }
    public function get_script_depends(): array { return ['obx-hotspot']; }
    public function has_widget_inner_wrapper(): bool { return true; }
    protected function is_dynamic_content(): bool { return false; }

    protected function register_controls(): void {
        parent::register_controls();
        foreach (['caption_source','caption','link_to','link','open_lightbox'] as $control) { $this->remove_control($control); }
        $this->hotspot_controls();
        $this->tooltip_controls();
        foreach (['section_style_caption','caption_align','text_color','caption_background_color','caption_typography','caption_text_shadow','caption_space','hover_animation'] as $control) { $this->remove_control($control); }
        $this->update_control('align', ['options'=>['flex-start'=>['title'=>'Inicio','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'flex-end'=>['title'=>'Final','icon'=>'eicon-text-align-right']],'selectors'=>['{{WRAPPER}}'=>'--background-align: {{VALUE}};']]);
        $this->update_control('width', ['selectors'=>['{{WRAPPER}}'=>'--container-width: {{SIZE}}{{UNIT}}; --image-width: 100%;']]);
        $this->update_control('space', ['selectors'=>['{{WRAPPER}}'=>'--container-max-width: {{SIZE}}{{UNIT}}']]);
        $this->update_control('height', ['selectors'=>['{{WRAPPER}}'=>'--container-height: {{SIZE}}{{UNIT}};']]);
        $this->update_control('opacity', ['selectors'=>['{{WRAPPER}}'=>'--opacity: {{SIZE}};']]);
        $this->update_control('opacity_hover', ['selectors'=>['{{WRAPPER}} .elementor-widget-container>img:hover'=>'--opacity: {{SIZE}};']]);
        $this->hotspot_style_controls();
        $this->tooltip_style_controls();
    }

    private static function sizes(array $units, array $max): array {
        $range = [];
        foreach ($max as $unit => $value) { $range[$unit] = ['max'=>$value]; }
        return ['size_units'=>$units,'range'=>$range];
    }

    /** Box position choices: Pro names them by the side the box grows from. */
    private static function box_positions(): array {
        return ['right'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'bottom'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'left'=>['title'=>'Derecha','icon'=>'eicon-h-align-right'],'top'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']];
    }

    private function hotspot_controls(): void {
        $this->start_controls_section('hotspot_section', ['label'=>'Hotspot']);
        $item = new Repeater();
        $item->start_controls_tabs('hotspot_repeater');
        $item->start_controls_tab('hotspot_content_tab', ['label'=>'Contenido']);
        $item->add_control('hotspot_label', ['label'=>'Etiqueta','type'=>Controls_Manager::TEXT,'default'=>'','label_block'=>true,'dynamic'=>['active'=>true]]);
        $item->add_control('hotspot_link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true]]);
        $item->add_control('hotspot_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false]);
        $start = is_rtl() ? 'right' : 'left';
        $end = is_rtl() ? 'left' : 'right';
        $both = ['hotspot_icon[value]!'=>'','hotspot_label[value]!'=>''];
        $item->add_control('hotspot_icon_position', ['label'=>'Posición del icono','type'=>Controls_Manager::CHOOSE,'options'=>['start'=>['title'=>'Inicio','icon'=>"eicon-h-align-{$start}"],'end'=>['title'=>'Final','icon'=>"eicon-h-align-{$end}"]],'selectors_dictionary'=>['start'=>'grid-column: 1;','end'=>'grid-column: 2;'],'condition'=>$both,'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}} .e-hotspot__icon'=>'{{VALUE}}'],'default'=>'start']);
        $item->add_control('hotspot_icon_spacing', ['label'=>'Espacio del icono','type'=>Controls_Manager::SLIDER,'default'=>['size'=>5],'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}} .e-hotspot__button'=>'grid-gap: {{SIZE}}{{UNIT}};'],'condition'=>$both] + self::sizes(['px','em','rem','custom'], ['px'=>100,'em'=>10,'rem'=>10]));
        $item->add_control('hotspot_custom_size', ['label'=>'Tamaño propio','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','default'=>'no','description'=>'Tamaño solo para este punto.']);
        $item->add_control('hotspot_width', ['label'=>'Ancho mínimo','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}}'=>'--hotspot-min-width: {{SIZE}}{{UNIT}}'],'condition'=>['hotspot_custom_size'=>'yes']] + self::sizes(['px','%','em','rem','vw','custom'], ['px'=>1000,'em'=>100,'rem'=>100]));
        $item->add_control('hotspot_height', ['label'=>'Altura mínima','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}}'=>'--hotspot-min-height: {{SIZE}}{{UNIT}}'],'condition'=>['hotspot_custom_size'=>'yes']] + self::sizes(['px','em','rem','vh','custom'], ['px'=>1000,'em'=>100,'rem'=>100]));
        $item->add_control('hotspot_tooltip_content', ['render_type'=>'template','label'=>'Contenido del tooltip','type'=>Controls_Manager::WYSIWYG,'default'=>'Escribe aquí el texto del tooltip']);
        $item->end_controls_tab();
        $item->start_controls_tab('hotspot_position_tab', ['label'=>'Posición']);
        $item->add_control('hotspot_horizontal', ['label'=>'Orientación horizontal','type'=>Controls_Manager::CHOOSE,'default'=>is_rtl() ? 'right' : 'left','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'toggle'=>false]);
        $item->add_responsive_control('hotspot_offset_x', ['label'=>'Desplazamiento','type'=>Controls_Manager::SLIDER,'size_units'=>['%'],'default'=>['unit'=>'%','size'=>50],'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}}'=>'{{hotspot_horizontal.VALUE}}: {{SIZE}}%; --hotspot-translate-x: {{SIZE}}%;']]);
        $item->add_control('hotspot_vertical', ['label'=>'Orientación vertical','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'default'=>'top','toggle'=>false]);
        $item->add_responsive_control('hotspot_offset_y', ['label'=>'Desplazamiento','type'=>Controls_Manager::SLIDER,'size_units'=>['%'],'default'=>['unit'=>'%','size'=>50],'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}}'=>'{{hotspot_vertical.VALUE}}: {{SIZE}}%; --hotspot-translate-y: {{SIZE}}%;']]);
        $item->add_control('hotspot_tooltip_position', ['label'=>'Tooltip propio','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','default'=>'no','description'=>'Apertura del tooltip solo para este punto.']);
        $item->add_control('hotspot_heading', ['label'=>'Caja','type'=>Controls_Manager::HEADING,'condition'=>['hotspot_tooltip_position'=>'yes']]);
        $item->add_responsive_control('hotspot_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'options'=>self::box_positions(),'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}} .e-hotspot--tooltip-position'=>'right: initial;bottom: initial;left: initial;top: initial;{{VALUE}}: calc(100% + 5px );'],'condition'=>['hotspot_tooltip_position'=>'yes'],'render_type'=>'template']);
        $item->add_responsive_control('hotspot_tooltip_width', ['label'=>'Ancho mínimo','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}} .e-hotspot__tooltip'=>'min-width: {{SIZE}}{{UNIT}}'],'condition'=>['hotspot_tooltip_position'=>'yes']] + self::sizes(['px','%','em','rem','vw','custom'], ['px'=>2000,'em'=>200,'rem'=>200]));
        $item->add_control('hotspot_tooltip_text_wrap', ['label'=>'Ajustar el texto','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','selectors'=>['{{WRAPPER}} {{CURRENT_ITEM}}'=>'--white-space: normal'],'condition'=>['hotspot_tooltip_position'=>'yes']]);
        $item->end_controls_tab();
        $item->end_controls_tabs();
        $this->add_control('hotspot', ['label'=>'Hotspot','type'=>Controls_Manager::REPEATER,'fields'=>$item->get_controls(),'title_field'=>'{{{ hotspot_label }}}','default'=>[[]],'frontend_available'=>true]);
        $this->add_control('hotspot_animation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'options'=>['e-hotspot--soft-beat'=>'Latido suave','e-hotspot--expand'=>'Expandir','e-hotspot--overlay'=>'Superposición',''=>'Ninguna'],'default'=>'e-hotspot--expand','separator'=>'before']);
        $this->add_control('hotspot_sequenced_animation', ['label'=>'Animación en secuencia','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','default'=>'no','frontend_available'=>true,'render_type'=>'none']);
        $this->add_control('hotspot_sequenced_animation_duration', ['label'=>'Duración de la secuencia (ms)','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>20000,'step'=>100]],'condition'=>['hotspot_sequenced_animation'=>'yes'],'frontend_available'=>true,'render_type'=>'ui']);
        $this->end_controls_section();
    }

    private function tooltip_controls(): void {
        $this->start_controls_section('tooltip_section', ['label'=>'Tooltip']);
        $this->add_responsive_control('tooltip_position', ['label'=>'Posición','type'=>Controls_Manager::CHOOSE,'default'=>'top','toggle'=>false,'options'=>self::box_positions(),'selectors'=>['{{WRAPPER}} .e-hotspot--tooltip-position'=>'right: initial;bottom: initial;left: initial;top: initial;{{VALUE}}: calc(100% + 5px );'],'frontend_available'=>true]);
        $this->add_responsive_control('tooltip_trigger', ['label'=>'Activador','type'=>Controls_Manager::SELECT,'options'=>['mouseenter'=>'Al pasar el ratón','click'=>'Al hacer clic','none'=>'Ninguno (siempre visible)'],'default'=>'click','frontend_available'=>true]);
        $this->add_control('tooltip_animation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'options'=>['e-hotspot--fade-in-out'=>'Aparecer y desaparecer','e-hotspot--fade-grow'=>'Aparecer creciendo','e-hotspot--fade-direction'=>'Aparecer desde su lado','e-hotspot--slide-direction'=>'Deslizar desde su lado'],'default'=>'e-hotspot--fade-in-out','condition'=>['tooltip_trigger!'=>'none'],'frontend_available'=>true]);
        $this->add_control('tooltip_animation_duration', ['label'=>'Duración de la animación (ms)','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>10000,'step'=>100]],'selectors'=>['{{WRAPPER}}'=>'--tooltip-transition-duration: {{SIZE}}ms;'],'condition'=>['tooltip_trigger!'=>'none']]);
        $this->end_controls_section();
    }

    private function hotspot_style_controls(): void {
        $this->start_controls_section('section_style_hotspot', ['label'=>'Hotspot','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('style_hotspot_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--hotspot-color: {{VALUE}};'],'global'=>['default'=>Global_Colors::COLOR_PRIMARY]]);
        $this->add_responsive_control('style_hotspot_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}}'=>'--hotspot-size: {{SIZE}}{{UNIT}};']] + self::sizes(['px','%','em','rem','custom'], ['px'=>300,'em'=>30,'rem'=>30]));
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'style_typography','selector'=>'{{WRAPPER}} .e-hotspot__label','global'=>['default'=>Global_Typography::TYPOGRAPHY_PRIMARY]]);
        $this->add_responsive_control('style_hotspot_width', ['label'=>'Ancho mínimo','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}}'=>'--hotspot-min-width: {{SIZE}}{{UNIT}}']] + self::sizes(['px','%','em','rem','vw','custom'], ['px'=>1000,'em'=>100,'rem'=>100]));
        $this->add_responsive_control('style_hotspot_height', ['label'=>'Altura mínima','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}}'=>'--hotspot-min-height: {{SIZE}}{{UNIT}}']] + self::sizes(['px','em','rem','vh','custom'], ['px'=>1000,'em'=>100,'rem'=>100]));
        $this->add_control('style_hotspot_box_color', ['label'=>'Color de la caja','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--hotspot-box-color: {{VALUE}};'],'global'=>['default'=>Global_Colors::COLOR_SECONDARY]]);
        $this->add_responsive_control('style_hotspot_padding', ['label'=>'Relleno','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}}'=>'--hotspot-padding: {{SIZE}}{{UNIT}};']] + self::sizes(['px','%','em','rem','vw','custom'], ['px'=>100,'em'=>10,'rem'=>10]));
        $this->add_control('style_hotspot_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}}'=>'--hotspot-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'style_hotspot_box_shadow','selector'=>'{{WRAPPER}} .e-hotspot:not(.e-hotspot--circle) .e-hotspot__button, {{WRAPPER}} .e-hotspot.e-hotspot--circle .e-hotspot__button .e-hotspot__outer-circle']);
        $this->end_controls_section();
    }

    private function tooltip_style_controls(): void {
        $this->start_controls_section('section_style_tooltip', ['label'=>'Tooltip','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('style_tooltip_text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--tooltip-text-color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'style_tooltip_typography','selector'=>'{{WRAPPER}} .e-hotspot__tooltip','global'=>['default'=>Global_Typography::TYPOGRAPHY_SECONDARY]]);
        $this->add_responsive_control('style_tooltip_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-text-align-right'],'justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],'selectors'=>['{{WRAPPER}}'=>'--tooltip-align: {{VALUE}};']]);
        $this->add_control('style_tooltip_heading', ['label'=>'Caja','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->add_responsive_control('style_tooltip_width', ['label'=>'Ancho mínimo','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}}'=>'--tooltip-min-width: {{SIZE}}{{UNIT}}']] + self::sizes(['px','em','rem','vw','custom'], ['px'=>2000,'em'=>200,'rem'=>200]));
        $this->add_responsive_control('style_tooltip_max_width', ['label'=>'Ancho máximo','type'=>Controls_Manager::SLIDER,'selectors'=>['{{WRAPPER}}'=>'--tooltip-max-width: {{SIZE}}{{UNIT}}']] + self::sizes(['px','em','rem','vw','custom'], ['px'=>2000,'em'=>200,'rem'=>200]));
        $this->add_responsive_control('style_tooltip_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'selectors'=>['{{WRAPPER}}'=>'--tooltip-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('style_tooltip_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'--tooltip-color: {{VALUE}}'],'global'=>['default'=>Global_Colors::COLOR_SECONDARY]]);
        $this->add_control('style_tooltip_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}}'=>'--tooltip-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'style_tooltip_box_shadow','selector'=>'{{WRAPPER}} .e-hotspot__tooltip']);
        $this->end_controls_section();
    }

    /** Classes of one hotspot, its button and its tooltip (shared by the site and the editor template). */
    private static function parts(array $s, array $spot): array {
        $direction = in_array($s['tooltip_animation'] ?? '', ['e-hotspot--slide-direction','e-hotspot--fade-direction'], true);
        $always = ($s['tooltip_trigger'] ?? 'click') === 'none';
        $label = (string) ($spot['hotspot_label'] ?? '');
        $icon = !empty($spot['hotspot_icon']['value']);
        $link = !empty($spot['hotspot_link']['url']);
        $no_tooltip = ($s['tooltip_trigger'] ?? 'click') === 'click' && $link;
        $own = $direction && ($spot['hotspot_tooltip_position'] ?? '') === 'yes' && !empty($spot['hotspot_position']);
        return [
            'link'=>$link,
            'circle'=>$label === '' && !$icon,
            'tooltip'=>(string) ($spot['hotspot_tooltip_content'] ?? '') !== '' && !$no_tooltip,
            'direction'=>$direction,
            'spot'=>array_filter(['e-hotspot','elementor-repeater-item-' . ($spot['_id'] ?? ''),
                ($s['hotspot_sequenced_animation'] ?? '') === 'yes' ? 'e-hotspot--sequenced' : '',
                ($spot['hotspot_offset_x']['unit'] ?? '%') === '%' ? 'e-hotspot--position-' . ($spot['hotspot_horizontal'] ?? 'left') : '',
                ($spot['hotspot_offset_y']['unit'] ?? '%') === '%' ? 'e-hotspot--position-' . ($spot['hotspot_vertical'] ?? 'top') : '',
                $link ? 'e-hotspot--link' : '', $no_tooltip ? 'e-hotspot--no-tooltip' : '',
                $label === '' && !$icon ? 'e-hotspot--circle' : '', $label === '' && $icon ? 'e-hotspot--icon' : '']),
            'button'=>array_filter(['e-hotspot__button', (string) ($s['hotspot_animation'] ?? '')]),
            'mask'=>['e-hotspot__direction-mask','e-hotspot--tooltip-position'],
            'tip'=>array_filter(['e-hotspot__tooltip', $always ? 'e-hotspot--show-tooltip' : '', $direction ? '' : 'e-hotspot--tooltip-position',
                $always ? '' : (string) ($s['tooltip_animation'] ?? ''), $own ? 'e-hotspot--override-tooltip-animation-from-' . $spot['hotspot_position'] : '']),
        ];
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        if (empty($s['image']['url'])) { return; }
        Group_Control_Image_Size::print_attachment_image_html($s, 'image', 'image');
        foreach ((array) ($s['hotspot'] ?? []) as $index => $spot) {
            $p = self::parts($s, $spot);
            $key = $this->get_repeater_setting_key('hotspot', 'hotspots', $index);
            $this->add_render_attribute($key, 'class', $p['spot']);
            if ($p['link']) { $this->add_link_attributes($key, $spot['hotspot_link']); }
            $tag = $p['link'] ? 'a' : 'div';
            echo '<' . $tag . ' ' . $this->get_render_attribute_string($key) . '><div class="' . esc_attr(implode(' ', $p['button'])) . '">';
            if ($p['circle']) {
                echo '<div class="e-hotspot__outer-circle"></div><div class="e-hotspot__inner-circle"></div>';
            } else {
                if (!empty($spot['hotspot_icon']['value'])) { echo '<div class="e-hotspot__icon">'; Icons_Manager::render_icon($spot['hotspot_icon']); echo '</div>'; }
                if ((string) ($spot['hotspot_label'] ?? '') !== '') { echo '<div class="e-hotspot__label">' . wp_kses_post((string) $spot['hotspot_label']) . '</div>'; }
            }
            echo '</div>';
            if ($p['tooltip']) {
                if ($p['direction']) { echo '<div class="' . esc_attr(implode(' ', $p['mask'])) . '">'; }
                echo '<div class="' . esc_attr(implode(' ', $p['tip'])) . '">' . do_shortcode((string) $spot['hotspot_tooltip_content']) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- editor content, as Pro prints it.
                if ($p['direction']) { echo '</div>'; }
            }
            echo '</' . $tag . '>';
        }
    }

    protected function content_template(): void {
        ?>
        <#
        const image = { id: settings.image.id, url: settings.image.url, size: settings.image_size, dimension: settings.image_custom_dimension, model: view.getEditModel() };
        const imageUrl = elementor.imagesManager.getImageUrl( image );
        if ( ! imageUrl ) { return; }
        const direction = [ 'e-hotspot--slide-direction', 'e-hotspot--fade-direction' ].includes( settings.tooltip_animation ),
            always = 'none' === settings.tooltip_trigger;
        #>
        <img src="{{ imageUrl }}" title="" alt="">
        <# _.each( settings.hotspot, ( spot ) => {
            const label = spot.hotspot_label || '', hasIcon = spot.hotspot_icon && spot.hotspot_icon.value,
                link = spot.hotspot_link && spot.hotspot_link.url, noTooltip = 'click' === settings.tooltip_trigger && link,
                own = direction && 'yes' === spot.hotspot_tooltip_position && spot.hotspot_position,
                icon = hasIcon ? elementor.helpers.renderIcon( view, spot.hotspot_icon, {}, 'i', 'object' ) : null,
                tag = link ? 'a' : 'div',
                spotClass = [ 'e-hotspot', 'elementor-repeater-item-' + spot._id, 'yes' === settings.hotspot_sequenced_animation ? 'e-hotspot--sequenced' : '',
                    '%' === spot.hotspot_offset_x.unit ? 'e-hotspot--position-' + spot.hotspot_horizontal : '', '%' === spot.hotspot_offset_y.unit ? 'e-hotspot--position-' + spot.hotspot_vertical : '',
                    link ? 'e-hotspot--link' : '', noTooltip ? 'e-hotspot--no-tooltip' : '', ! label && ! hasIcon ? 'e-hotspot--circle' : '', ! label && hasIcon ? 'e-hotspot--icon' : '' ].filter( Boolean ).join( ' ' ),
                tipClass = [ 'e-hotspot__tooltip', always ? 'e-hotspot--show-tooltip' : '', direction ? '' : 'e-hotspot--tooltip-position', always ? '' : settings.tooltip_animation, own ? 'e-hotspot--override-tooltip-animation-from-' + spot.hotspot_position : '' ].filter( Boolean ).join( ' ' );
        #>
            <{{ tag }} class="{{ spotClass }}"<# if ( link ) { #> href="{{ link }}"<# } #>>
                <div class="e-hotspot__button {{ settings.hotspot_animation }}">
                    <# if ( ! label && ! hasIcon ) { #><div class="e-hotspot__outer-circle"></div><div class="e-hotspot__inner-circle"></div><# } else { #>
                        <# if ( icon ) { #><div class="e-hotspot__icon">{{{ icon.value }}}</div><# } #>
                        <# if ( label ) { #><div class="e-hotspot__label">{{{ label }}}</div><# } #>
                    <# } #>
                </div>
                <# if ( spot.hotspot_tooltip_content && ! noTooltip ) { #>
                    <# if ( direction ) { #><div class="e-hotspot__direction-mask e-hotspot--tooltip-position"><# } #>
                    <div class="{{ tipClass }}">{{{ spot.hotspot_tooltip_content }}}</div>
                    <# if ( direction ) { #></div><# } #>
                <# } #>
            </{{ tag }}>
        <# } ); #>
        <?php
    }
}
