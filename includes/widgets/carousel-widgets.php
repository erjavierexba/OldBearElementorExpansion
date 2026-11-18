<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Icons_Manager;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Repeater;
use Elementor\Utils;

if (!defined('ABSPATH')) { exit; }

/**
 * Carousel controls and markup shared by our carousels. The layout is CSS scroll-snap
 * (slides per view and gaps come from responsive controls, so Elementor writes the CSS for every
 * breakpoint); assets/js/widgets.js adds arrows, pagination, autoplay and loop on top.
 */
trait Carousel {
    /** @param array{show:string,scroll:string,gap:string,arrows:string,loop:string,slides_default:string} $keys */
    protected function carousel_controls(array $keys, array $defaults = []): void {
        $numbers = [''=>'Por defecto'] + array_combine(array_map('strval', range(1, 10)), array_map('strval', range(1, 10)));
        $this->add_responsive_control($keys['show'], ['label'=>'Diapositivas visibles','type'=>Controls_Manager::SELECT,'options'=>$numbers,'default'=>$defaults['show'] ?? '','tablet_default'=>$defaults['show_tablet'] ?? '','mobile_default'=>$defaults['show_mobile'] ?? '','selectors'=>['{{WRAPPER}} .obx-carousel'=>'--obx-slides: {{VALUE}};'],'frontend_available'=>true]);
        $this->add_responsive_control($keys['scroll'], ['label'=>'Diapositivas por avance','type'=>Controls_Manager::SELECT,'options'=>$numbers,'default'=>'','description'=>'Cuántas diapositivas avanza cada paso.','selectors'=>['{{WRAPPER}} .obx-carousel'=>'--obx-scroll: {{VALUE}};'],'frontend_available'=>true]);
        $this->add_control('autoplay', ['label'=>'Reproducción automática','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before','frontend_available'=>true]);
        $this->add_control('autoplay_speed', ['label'=>'Intervalo (ms)','type'=>Controls_Manager::NUMBER,'default'=>5000,'condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control('pause_on_hover', ['label'=>'Pausar al pasar el ratón','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control('pause_on_interaction', ['label'=>'Pausar al interactuar','type'=>Controls_Manager::SWITCHER,'default'=>'yes','condition'=>['autoplay'=>'yes'],'frontend_available'=>true]);
        $this->add_control($keys['loop'], ['label'=>'Bucle infinito','type'=>Controls_Manager::SWITCHER,'default'=>'yes','frontend_available'=>true]);
        $this->add_control('speed', ['label'=>'Duración de la transición (ms)','type'=>Controls_Manager::NUMBER,'default'=>500,'frontend_available'=>true]);
        $this->add_control($keys['arrows'], ['label'=>'Flechas','type'=>Controls_Manager::SWITCHER,'default'=>'yes','separator'=>'before','frontend_available'=>true]);
        $this->add_control('pagination', ['label'=>'Paginación','type'=>Controls_Manager::SELECT,'default'=>'bullets','options'=>[''=>'Ninguna','bullets'=>'Puntos','fraction'=>'Fracción','progressbar'=>'Barra de progreso'],'frontend_available'=>true]);
    }

    /** Pro's «section_navigation» of the classic carousels (arrows size/colour, pagination gap/size/colours). */
    protected function carousel_navigation_style(string $arrows_key): void {
        $this->start_controls_section('section_navigation', ['label'=>'Navegación','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('heading_arrows', ['label'=>'Flechas','type'=>Controls_Manager::HEADING,'condition'=>[$arrows_key=>'yes']]);
        $this->slider('arrows_size', 'Tamaño', '.obx-carousel__arrow', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'default'=>['unit'=>'px','size'=>20],'range'=>['px'=>['min'=>20,'max'=>60]],'condition'=>[$arrows_key=>'yes']]);
        $this->color('arrows_color', 'Color', '.obx-carousel__arrow', 'color', ['condition'=>[$arrows_key=>'yes']]);
        $this->add_control('heading_pagination', ['label'=>'Paginación','type'=>Controls_Manager::HEADING,'separator'=>'before','condition'=>['pagination!'=>'']]);
        $this->slider('pagination_gap', 'Separación', '.obx-carousel__pagination--bullets', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>40]],'condition'=>['pagination'=>'bullets']]);
        $this->slider('pagination_size', 'Tamaño', '.obx-carousel__pagination', '--obx-dot: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem','custom'],'range'=>['px'=>['max'=>20]],'condition'=>['pagination!'=>'']]);
        $this->color('pagination_color_inactive', 'Color', '.obx-carousel__pagination', '--obx-dot-inactive', ['condition'=>['pagination!'=>'']]);
        $this->color('pagination_color', 'Color activo', '.obx-carousel__pagination', '--obx-dot-active', ['condition'=>['pagination!'=>'']]);
        $this->end_controls_section();
    }

    /** Direction and partly visible side slides (Pro's direction, offset_sides, offset_width). */
    protected function carousel_offset_controls(string $loop_key): void {
        $this->add_control('direction', ['label'=>'Dirección','type'=>Controls_Manager::SELECT,'default'=>'ltr','options'=>['ltr'=>'De izquierda a derecha','rtl'=>'De derecha a izquierda'],'separator'=>'before']);
        $this->add_control('offset_sides', ['label'=>'Mostrar parte de las diapositivas vecinas','type'=>Controls_Manager::SELECT,'default'=>'none','options'=>['none'=>'No','both'=>'A ambos lados','left'=>'A la izquierda','right'=>'A la derecha'],'prefix_class'=>'obx-carousel-offset-','condition'=>[$loop_key=>'yes']]);
        $this->add_responsive_control('offset_width', ['label'=>'Ancho visible','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw'],'default'=>['unit'=>'px','size'=>80],'range'=>['px'=>['max'=>500]],'selectors'=>['{{WRAPPER}} .obx-carousel'=>'--obx-offset: {{SIZE}}{{UNIT}};'],'condition'=>['offset_sides!'=>'none',$loop_key=>'yes']]);
    }

    /** Arrow placement by orientation, as Pro stores it (navigation_{previous,next}_icon_{horizontal,vertical}_*). */
    protected function carousel_arrow_position_controls(string $arrows_key, array $condition = []): void {
        $h = ['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right']];
        $v = ['start'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'end'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']];
        $hd = ['start'=>'inset-inline-start: var(--obx-arrow-h, 0px); inset-inline-end: auto; --obx-arrow-tx: 0%;','center'=>'inset-inline-start: calc(50% + var(--obx-arrow-h, 0px)); inset-inline-end: auto; --obx-arrow-tx: -50%;','end'=>'inset-inline-start: auto; inset-inline-end: var(--obx-arrow-h, 0px); --obx-arrow-tx: 0%;'];
        $vd = ['start'=>'top: var(--obx-arrow-v, 0px); --obx-arrow-ty: 0%;','center'=>'top: calc(50% + var(--obx-arrow-v, 0px)); --obx-arrow-ty: -50%;','end'=>'top: calc(100% - var(--obx-arrow-v, 0px)); --obx-arrow-ty: -100%;'];
        $when = [$arrows_key=>'yes'] + $condition;
        foreach (['previous'=>['prev','start'],'next'=>['next','end']] as $name => [$class, $side]) {
            $selector = '{{WRAPPER}} .obx-carousel__arrow--' . $class;
            $label = $name === 'previous' ? 'anterior' : 'siguiente';
            $this->add_responsive_control('navigation_' . $name . '_icon_horizontal_orientation', ['label'=>'Flecha ' . $label . ': horizontal','type'=>Controls_Manager::CHOOSE,'default'=>$side,'options'=>$h,'toggle'=>false,'selectors_dictionary'=>$hd,'selectors'=>[$selector=>'{{VALUE}}'],'condition'=>$when,'separator'=>$name === 'previous' ? 'before' : 'none']);
            $this->add_responsive_control('navigation_' . $name . '_icon_horizontal_position', ['label'=>'Desplazamiento horizontal','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw'],'default'=>['unit'=>'px','size'=>0],'range'=>['px'=>['min'=>-500,'max'=>500],'%'=>['min'=>-100,'max'=>100]],'selectors'=>[$selector=>'--obx-arrow-h: {{SIZE}}{{UNIT}};'],'condition'=>$when]);
            $this->add_responsive_control('navigation_' . $name . '_icon_vertical_orientation', ['label'=>'Flecha ' . $label . ': vertical','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>$v,'toggle'=>false,'selectors_dictionary'=>$vd,'selectors'=>[$selector=>'{{VALUE}}'],'condition'=>$when]);
            $this->add_responsive_control('navigation_' . $name . '_icon_vertical_position', ['label'=>'Desplazamiento vertical','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vh'],'default'=>['unit'=>'px','size'=>0],'range'=>['px'=>['min'=>-500,'max'=>500],'%'=>['min'=>-100,'max'=>100]],'selectors'=>[$selector=>'--obx-arrow-v: {{SIZE}}{{UNIT}};'],'condition'=>$when]);
        }
    }

    /** Arrows, dots, fraction and progress bar styles with Pro's keys («section_design_navigation», «section_pagination_design»). */
    protected function carousel_pro_navigation_style(string $arrows_key, array $condition = []): void {
        $arrow = '{{WRAPPER}} .obx-carousel__arrow';
        $when = [$arrows_key=>'yes'] + $condition;
        $this->start_controls_section('section_design_navigation', ['label'=>'Flechas','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$when]);
        $this->slider('arrows_size', 'Tamaño', '.obx-carousel__arrow', 'font-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['min'=>6,'max'=>100]]], true);
        $this->add_control('arrows_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'inside','options'=>['inside'=>'Dentro','outside'=>'Fuera'],'prefix_class'=>'obx-carousel-arrows-']);
        $this->start_controls_tabs('arrows_colors_border_shadow');
        foreach (['normal'=>[$arrow, 'Normal'],'hover'=>[$arrow . ':hover, ' . $arrow . ':focus-visible', 'Al pasar el ratón']] as $state => [$selector, $label]) {
            $this->start_controls_tab('arrows_' . $state . '_tab', ['label'=>$label]);
            $this->add_control('arrow_' . $state . '_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>[$selector=>'color: {{VALUE}}; fill: {{VALUE}};']]);
            $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'arrows_' . $state . '_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$selector]);
            $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'arrows_' . $state . '_border_type','selector'=>$selector]);
            $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'arrows_' . $state . '_box_shadow','selector'=>$selector]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->dimensions('arrows_border_radius', 'Radio del borde', '.obx-carousel__arrow', 'border-radius', ['separator'=>'before']);
        $this->dimensions('arrows_padding', 'Relleno', '.obx-carousel__arrow', 'padding');
        $this->end_controls_section();

        $this->start_controls_section('section_pagination_design', ['label'=>'Paginación','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['pagination!'=>''] + $condition]);
        $bullets = ['pagination'=>'bullets'];
        $fraction = ['pagination'=>'fraction'];
        $progress = ['pagination'=>'progressbar'];
        $this->slider('dots_gap', 'Separación entre puntos', '.obx-carousel__pagination--bullets', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>40]],'condition'=>$bullets], true);
        $this->slider('dots_size', 'Tamaño de los puntos', '.obx-carousel__pagination', '--obx-dot: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>30]],'condition'=>$bullets], true);
        $this->start_controls_tabs('dots_colors', ['condition'=>$bullets]);
        $this->start_controls_tab('dots_normal_tab', ['label'=>'Normal']);
        $this->color('dots_normal_color', 'Color', '.obx-carousel__pagination', '--obx-dot-inactive');
        $this->end_controls_tab();
        $this->start_controls_tab('dots_hover_tab', ['label'=>'Activo y al pasar el ratón']);
        $this->color('dots_hover_color', 'Color', '.obx-carousel__pagination', '--obx-dot-active');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->position_controls('dots', $bullets);
        $this->typography('typography_fraction', '.obx-carousel__pagination--fraction', ['condition'=>$fraction]);
        $this->color('fraction_color', 'Color', '.obx-carousel__pagination--fraction', 'color', ['condition'=>$fraction]);
        $this->position_controls('fraction', $fraction);
        $this->slider('progressbar_height', 'Altura', '.obx-carousel__pagination--progressbar', 'height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>20]],'condition'=>$progress], true);
        $this->start_controls_tabs('progressbar_colors', ['condition'=>$progress]);
        $this->start_controls_tab('progressbar_normal_tab', ['label'=>'Fondo']);
        $this->color('progressbar_normal_color', 'Color', '.obx-carousel__pagination--progressbar', '--obx-dot-inactive');
        $this->end_controls_tab();
        $this->start_controls_tab('progressbar_hover_tab', ['label'=>'Progreso']);
        $this->color('progressbar_hover_color', 'Color', '.obx-carousel__pagination--progressbar', '--obx-dot-active');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->slider('dots_pagination_spacing', 'Distancia a las diapositivas', '.obx-carousel__pagination--bullets', 'margin-top: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]],'condition'=>$bullets,'separator'=>'before'], true);
        $this->slider('fraction_pagination_spacing', 'Distancia a las diapositivas', '.obx-carousel__pagination--fraction, .obx-carousel__pagination--progressbar', 'margin-top: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]],'condition'=>['pagination'=>['fraction','progressbar']],'separator'=>'before'], true);
        $this->end_controls_section();
    }

    /** {dots,fraction}_custom_position, _{horizontal,vertical}_{position,offset} and _position (inside/outside). */
    private function position_controls(string $p, array $condition): void {
        $selector = '{{WRAPPER}} .obx-carousel__pagination--' . ($p === 'dots' ? 'bullets' : 'fraction');
        $h = ['start'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-h-align-center'],'end'=>['title'=>'Final','icon'=>'eicon-h-align-right']];
        $v = ['start'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'center'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'end'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']];
        $this->add_control($p . '_position', ['label'=>'Posición','type'=>Controls_Manager::SELECT,'default'=>'outside','options'=>['outside'=>'Fuera','inside'=>'Dentro'],'prefix_class'=>'obx-carousel-' . $p . '-','condition'=>$condition,'separator'=>'before']);
        $this->add_control($p . '_custom_position', ['label'=>'Posición personalizada','type'=>Controls_Manager::SWITCHER,'prefix_class'=>'obx-carousel-' . $p . '-custom-','condition'=>$condition]);
        $on = $condition + [$p . '_custom_position'=>'yes'];
        $this->add_responsive_control($p . '_horizontal_position', ['label'=>'Horizontal','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>$h,'toggle'=>false,'selectors_dictionary'=>['start'=>'flex-start','center'=>'center','end'=>'flex-end'],'selectors'=>[$selector=>'justify-content: {{VALUE}};'],'condition'=>$on]);
        $this->add_responsive_control($p . '_horizontal_offset', ['label'=>'Desplazamiento horizontal','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw'],'default'=>['unit'=>'px','size'=>0],'range'=>['px'=>['min'=>-500,'max'=>500]],'selectors'=>[$selector=>'--obx-pag-x: {{SIZE}}{{UNIT}};'],'condition'=>$on]);
        $this->add_responsive_control($p . '_vertical_position', ['label'=>'Vertical','type'=>Controls_Manager::CHOOSE,'default'=>'end','options'=>$v,'toggle'=>false,'selectors_dictionary'=>['start'=>'top: 0; bottom: auto; --obx-pag-ty: 0%;','center'=>'top: 50%; bottom: auto; --obx-pag-ty: -50%;','end'=>'top: auto; bottom: 0; --obx-pag-ty: 0%;'],'selectors'=>[$selector=>'{{VALUE}}'],'condition'=>$on]);
        $this->add_responsive_control($p . '_vertical_offset', ['label'=>'Desplazamiento vertical','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vh'],'default'=>['unit'=>'px','size'=>0],'range'=>['px'=>['min'=>-500,'max'=>500]],'selectors'=>[$selector=>'--obx-pag-y: {{SIZE}}{{UNIT}};'],'condition'=>$on]);
    }

    /** @return string data-carousel JSON */
    protected function carousel_options(array $s, string $arrows_key, string $loop_key): string {
        return (string) wp_json_encode([
            'autoplay'=>($s['autoplay'] ?? 'yes') === 'yes' && !$this->editing(),
            'autoplaySpeed'=>max(500, (int) ($s['autoplay_speed'] ?? 5000)),
            'pauseOnHover'=>($s['pause_on_hover'] ?? 'yes') === 'yes',
            'pauseOnInteraction'=>($s['pause_on_interaction'] ?? 'yes') === 'yes',
            'loop'=>($s[$loop_key] ?? 'yes') === 'yes',
            'speed'=>max(0, (int) ($s['speed'] ?? 500)),
        ]);
    }

    protected function carousel_controls_html(array $s, string $arrows_key, string $prev = '', string $next = ''): string {
        $html = '';
        $chevron = static fn(string $d) => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        if (($s[$arrows_key] ?? 'yes') === 'yes') {
            $html .= '<div class="obx-carousel__arrow obx-carousel__arrow--prev" role="button" tabindex="0" aria-label="Diapositiva anterior">' . ($prev ?: $chevron('M15 4 7 12l8 8')) . '</div>';
            $html .= '<div class="obx-carousel__arrow obx-carousel__arrow--next" role="button" tabindex="0" aria-label="Diapositiva siguiente">' . ($next ?: $chevron('m9 4 8 8-8 8')) . '</div>';
        }
        $type = (string) ($s['pagination'] ?? 'bullets');
        if (in_array($type, ['bullets','fraction','progressbar'], true)) { $html .= '<div class="obx-carousel__pagination obx-carousel__pagination--' . $type . '"></div>'; }
        return $html;
    }
}

final class Testimonial_Carousel extends Element {
    use Carousel;
    public const PRO = 'testimonial-carousel';
    public function get_title(): string { return 'Carrusel de testimonios'; }
    public function get_icon(): string { return 'eicon-testimonial-carousel'; }
    public function get_keywords(): array { return ['testimonial','testimonios','carousel','carrusel','opiniones','reseñas']; }

    protected function register_controls(): void {
        $this->start_controls_section('section_slides', ['label'=>'Testimonios']);
        $r = new Repeater();
        $r->add_control('content', ['label'=>'Texto','type'=>Controls_Manager::TEXTAREA,'dynamic'=>['active'=>true]]);
        $r->add_control('image', ['label'=>'Foto','type'=>Controls_Manager::MEDIA,'dynamic'=>['active'=>true]]);
        $r->add_control('name', ['label'=>'Nombre','type'=>Controls_Manager::TEXT,'default'=>'Nombre','dynamic'=>['active'=>true]]);
        $r->add_control('title', ['label'=>'Cargo','type'=>Controls_Manager::TEXT,'default'=>'Cargo','dynamic'=>['active'=>true]]);
        $sample = 'Cuenta aquí lo que opina un cliente de tu trabajo: qué problema tenía y cómo le ayudaste.';
        $this->add_control('slides', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[['content'=>$sample,'name'=>'Nombre','title'=>'Cargo'],['content'=>$sample,'name'=>'Nombre','title'=>'Cargo'],['content'=>$sample,'name'=>'Nombre','title'=>'Cargo']],'title_field'=>'{{{ name }}}']);
        $this->add_control('skin', ['label'=>'Apariencia','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','bubble'=>'Bocadillo'],'prefix_class'=>'obx-testimonials--skin-','separator'=>'before']);
        $this->add_control('layout', ['label'=>'Disposición','type'=>Controls_Manager::SELECT,'default'=>'image_inline','options'=>['image_inline'=>'Foto en línea','image_stacked'=>'Foto apilada','image_above'=>'Foto arriba','image_left'=>'Foto a la izquierda','image_right'=>'Foto a la derecha'],'prefix_class'=>'obx-testimonials--layout-']);
        $this->add_responsive_control('alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-text-align-right']],'prefix_class'=>'obx-testimonials%s--align-','condition'=>['layout'=>['image_inline','image_stacked','image_above']]]);
        $this->add_control('slides_name', ['type'=>Controls_Manager::HIDDEN,'default'=>'Testimonios']);
        $this->end_controls_section();

        $this->start_controls_section('section_additional_options', ['label'=>'Carrusel']);
        $this->carousel_controls(['show'=>'slides_per_view','scroll'=>'slides_to_scroll','gap'=>'space_between','arrows'=>'show_arrows','loop'=>'loop']);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'image_size','default'=>'full','separator'=>'before']);
        $this->add_control('lazyload', ['label'=>'Carga diferida','type'=>Controls_Manager::SWITCHER]);
        $this->end_controls_section();

        $this->start_controls_section('section_slides_style', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('space_between', 'Separación', '.obx-carousel', '--obx-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $this->slider('width', 'Ancho', '.obx-carousel', 'max-width: {{SIZE}}{{UNIT}};', ['size_units'=>['%','px','vw'],'range'=>['px'=>['min'=>100,'max'=>1140],'%'=>['min'=>50]]], true);
        $this->color('slide_background_color', 'Fondo', '.obx-testimonial', 'background-color', ['separator'=>'before']);
        $this->color('slide_border_color', 'Color del borde', '.obx-testimonial', 'border-color');
        $this->slider('slide_border_size', 'Grosor del borde', '.obx-testimonial', 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;', ['size_units'=>['px'],'range'=>['px'=>['max'=>20]]]);
        $this->slider('slide_border_radius', 'Radio del borde', '.obx-testimonial', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%']]);
        $this->dimensions('slide_padding', 'Relleno', '.obx-testimonial', 'padding');
        $this->end_controls_section();

        $this->start_controls_section('section_content_style', ['label'=>'Contenido','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('content_gap', 'Separación', '.obx-testimonial', '--obx-content-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]], true);
        $this->color('content_color', 'Color del texto', '.obx-testimonial__content');
        $this->typography('content_typography', '.obx-testimonial__content');
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>'{{WRAPPER}} .obx-testimonial__content']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'content_text_shadow','selector'=>'{{WRAPPER}} .obx-testimonial__content']);
        $this->add_control('heading_name_style', ['label'=>'Nombre','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('name_color', 'Color', '.obx-testimonial__name');
        $this->typography('name_typography', '.obx-testimonial__name');
        $this->add_control('heading_title_style', ['label'=>'Cargo','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('title_color', 'Color', '.obx-testimonial__title');
        $this->typography('title_typography', '.obx-testimonial__title');
        $this->end_controls_section();

        $this->start_controls_section('section_image_style', ['label'=>'Foto','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('image_size', 'Tamaño', '.obx-testimonial__image img', 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['min'=>20,'max'=>200]]], true);
        $this->slider('image_gap', 'Separación', '.obx-testimonial__footer', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]], true);
        $this->add_control('image_border', ['label'=>'Borde','type'=>Controls_Manager::SWITCHER,'selectors'=>self::css('.obx-testimonial__image img', 'border-style: solid;')]);
        $this->color('image_border_color', 'Color del borde', '.obx-testimonial__image img', 'border-color', ['condition'=>['image_border'=>'yes']]);
        $this->slider('image_border_width', 'Grosor del borde', '.obx-testimonial__image img', 'border-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'range'=>['px'=>['max'=>20]],'condition'=>['image_border'=>'yes']]);
        $this->slider('image_border_radius', 'Radio del borde', '.obx-testimonial__image img', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['%','px']]);
        $this->end_controls_section();

        $this->carousel_navigation_style('show_arrows');

        $bubble = ['skin'=>'bubble'];
        $content = '{{WRAPPER}} .obx-testimonial__content';
        $this->start_controls_section('section_skin_style', ['label'=>'Bocadillo','tab'=>Controls_Manager::TAB_STYLE,'condition'=>$bubble]);
        $this->add_control('background_color', ['label'=>'Color de fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-testimonial'=>'--obx-bubble-bg: {{VALUE}};'],'condition'=>$bubble]);
        $this->add_control('text_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','vw','custom'],'default'=>['unit'=>'px','top'=>'20','right'=>'20','bottom'=>'20','left'=>'20','isLinked'=>true],'selectors'=>[$content=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],'condition'=>$bubble]);
        $this->add_control('border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>[$content=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],'condition'=>$bubble]);
        $this->add_control('border', ['label'=>'Borde','type'=>Controls_Manager::SWITCHER,'selectors'=>['{{WRAPPER}} .obx-testimonial'=>'--obx-bubble-border-width: 1px;'],'separator'=>'before','condition'=>$bubble]);
        $this->add_control('border_color', ['label'=>'Color del borde','type'=>Controls_Manager::COLOR,'default'=>'#000','selectors'=>['{{WRAPPER}} .obx-testimonial'=>'--obx-bubble-border-color: {{VALUE}};'],'condition'=>$bubble + ['border'=>'yes']]);
        $this->add_control('border_width', ['label'=>'Grosor del borde','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['px'=>['max'=>20]],'selectors'=>['{{WRAPPER}} .obx-testimonial'=>'--obx-bubble-border-width: {{SIZE}}{{UNIT}};'],'condition'=>$bubble + ['border'=>'yes']]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $slides = array_values(array_filter((array) ($s['slides'] ?? []), 'is_array'));
        if (!$slides) { $this->placeholder('Añade algún testimonio.'); return; }
        $lazy = ($s['lazyload'] ?? '') === 'yes' ? 'lazy' : 'eager';
        echo '<div class="obx-carousel obx-testimonials" data-carousel="' . esc_attr($this->carousel_options($s, 'show_arrows', 'loop')) . '" role="region" aria-roledescription="carrusel" aria-label="' . esc_attr((string) ($s['slides_name'] ?? 'Testimonios')) . '">';
        echo '<div class="obx-carousel__track">';
        foreach ($slides as $i => $slide) {
            echo '<div class="obx-carousel__slide" role="group" aria-roledescription="diapositiva" aria-label="' . esc_attr(($i + 1) . ' / ' . count($slides)) . '"><figure class="obx-testimonial">';
            if ((string) ($slide['content'] ?? '') !== '') { echo '<blockquote class="obx-testimonial__content">' . wp_kses_post(wpautop((string) $slide['content'])) . '</blockquote>'; }
            echo '<figcaption class="obx-testimonial__footer">';
            if (!empty($slide['image']['url'])) {
                $size = (string) ($s['image_size_size'] ?? 'full');
                $img = !empty($slide['image']['id']) && $size !== 'custom' ? wp_get_attachment_image((int) $slide['image']['id'], $size, false, ['loading'=>$lazy,'alt'=>(string) ($slide['name'] ?? '')]) : '';
                if ($img === '' && !empty($slide['image']['id'])) { $src = Group_Control_Image_Size::get_attachment_image_src((int) $slide['image']['id'], 'image_size', $s); $slide['image']['url'] = $src ?: $slide['image']['url']; }
                echo '<span class="obx-testimonial__image">' . ($img ?: '<img src="' . esc_url($slide['image']['url']) . '" alt="' . esc_attr((string) ($slide['name'] ?? '')) . '" loading="' . $lazy . '">') . '</span>';
            }
            echo '<cite class="obx-testimonial__cite">';
            if ((string) ($slide['name'] ?? '') !== '') { echo '<span class="obx-testimonial__name">' . esc_html($slide['name']) . '</span>'; }
            if ((string) ($slide['title'] ?? '') !== '') { echo '<span class="obx-testimonial__title">' . esc_html($slide['title']) . '</span>'; }
            echo '</cite></figcaption></figure></div>';
        }
        echo '</div>' . $this->carousel_controls_html($s, 'show_arrows') . '</div>';
    }
}

/** Nested carousel: each slide is a container edited in place (Elementor's nested elements). */
final class Nested_Carousel extends Widget_Nested_Base {
    use Carousel;
    public const NAME = '';
    public const PRO = 'nested-carousel';
    public const ALIASES = [];
    public const PRO_CATEGORY = 'pro-elements';

    public static function names(): array { return [self::PRO]; }
    public static function available(): bool {
        $experiments = \Elementor\Plugin::$instance->experiments;
        return class_exists(Widget_Nested_Base::class) && $experiments->is_feature_active('nested-elements') && $experiments->is_feature_active('container');
    }

    public function get_name(): string { return self::PRO; }
    public function get_title(): string { return 'Carrusel anidado'; }
    public function get_icon(): string { return 'eicon-nested-carousel'; }
    public function get_categories(): array { return [self::PRO_CATEGORY]; }
    public function get_keywords(): array { return ['carousel','carrusel','slider','diapositivas','nested','anidado']; }
    public function get_style_depends(): array { return ['obx-frontend']; }
    public function get_script_depends(): array { return ['obx-widgets']; }
    public function show_in_panel(): bool { return true; }
    public function get_custom_help_url() { return \OldBearExpansion\Admin\Branding::URL . '#readme'; }
    public function has_widget_inner_wrapper(): bool { return false; }
    protected function editing(): bool { return \OldBearExpansion\Context::is_editor(); }
    protected function placeholder(string $message): void { if ($this->editing()) { echo '<div class="obx-placeholder">' . esc_html($message) . '</div>'; } }

    /** Keys saved by earlier versions of this plugin → Pro's keys. */
    private const OLD_KEYS = ['arrows_normal_color'=>'arrow_normal_color','arrows_hover_color'=>'arrow_hover_color','dots_color_inactive'=>'dots_normal_color','dots_color_active'=>'dots_hover_color'];
    public static function wants_upgrade(string $name, array $settings): bool { return $name === self::PRO && (bool) array_intersect_key($settings, self::OLD_KEYS); }
    public static function convert(string $name, array $settings): array {
        if (!self::wants_upgrade($name, $settings)) { return $settings; }
        foreach (self::OLD_KEYS as $old => $new) {
            if (!array_key_exists($new, $settings)) { $settings[$new] = $settings[$old] ?? null; }
            unset($settings[$old]);
        }
        return array_filter($settings, static fn($value) => $value !== null);
    }

    private static function css(string $selector, string $declaration): array {
        return ['{{WRAPPER}} ' . implode(', {{WRAPPER}} ', array_map('trim', explode(',', $selector))) => $declaration];
    }
    private function slider(string $key, string $label, string $selector, string $declaration, array $extra = [], bool $responsive = false): void {
        $args = ['label'=>$label,'type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','%','custom'],'selectors'=>self::css($selector, $declaration)] + $extra;
        $responsive ? $this->add_responsive_control($key, $args) : $this->add_control($key, $args);
    }
    private function color(string $key, string $label, string $selector, string $property = 'color', array $extra = []): void {
        $this->add_control($key, ['label'=>$label,'type'=>Controls_Manager::COLOR,'selectors'=>self::css($selector, $property . ': {{VALUE}};')] + $extra);
    }
    private function dimensions(string $key, string $label, string $selector, string $property, array $extra = [], bool $responsive = true): void {
        $args = ['label'=>$label,'type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>self::css($selector, $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};')] + $extra;
        $responsive ? $this->add_responsive_control($key, $args) : $this->add_control($key, $args);
    }
    private function typography(string $name, string $selector, array $extra = []): void {
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$name,'selector'=>(string) array_key_first(self::css($selector, ''))] + $extra);
    }

    private function slide_container(int $index): array {
        return ['elType'=>'container','settings'=>['_title'=>sprintf('Diapositiva #%d', $index),'content_width'=>'full'],'isInner'=>true];
    }
    protected function get_default_children_elements() { return [$this->slide_container(1), $this->slide_container(2), $this->slide_container(3)]; }
    protected function get_default_repeater_title_setting_key() { return 'slide_title'; }
    protected function get_default_children_title() { return 'Diapositiva #%d'; }
    protected function get_default_children_placeholder_selector() { return '.obx-carousel__track'; }

    protected function register_controls(): void {
        $this->start_controls_section('section_slides', ['label'=>'Diapositivas']);
        $r = new Repeater();
        $r->add_control('slide_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Diapositiva','label_block'=>true]);
        $this->add_control('carousel_items', ['label'=>'Diapositivas','type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[['slide_title'=>'Diapositiva #1'],['slide_title'=>'Diapositiva #2'],['slide_title'=>'Diapositiva #3']],'title_field'=>'{{{ slide_title }}}','button_text'=>'Añadir diapositiva']);
        $this->add_control('carousel_name', ['label'=>'Nombre accesible','type'=>Controls_Manager::TEXT,'default'=>'Carrusel']);
        $this->end_controls_section();

        $this->start_controls_section('section_carousel_settings', ['label'=>'Carrusel']);
        $this->carousel_controls(['show'=>'slides_to_show','scroll'=>'slides_to_scroll','gap'=>'image_spacing_custom','arrows'=>'arrows','loop'=>'infinite'], ['show'=>'3','show_tablet'=>'2','show_mobile'=>'1']);
        $this->add_control('navigation_previous_icon', ['label'=>'Icono anterior','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-chevron-left','library'=>'fa-solid'],'condition'=>['arrows'=>'yes']]);
        $this->add_control('navigation_next_icon', ['label'=>'Icono siguiente','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false,'default'=>['value'=>'fas fa-chevron-right','library'=>'fa-solid'],'condition'=>['arrows'=>'yes']]);
        $this->carousel_arrow_position_controls('arrows');
        $this->carousel_offset_controls('infinite');
        $this->add_control('equal_height', ['label'=>'Misma altura','type'=>Controls_Manager::SWITCHER,'default'=>'yes','prefix_class'=>'obx-ncarousel--equal-','separator'=>'before']);
        $this->end_controls_section();

        $this->start_controls_section('section_slides_style', ['label'=>'Diapositivas','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('image_spacing_custom', 'Separación', '.obx-carousel', '--obx-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>10],'range'=>['px'=>['max'=>100]]], true);
        $slide = '{{WRAPPER}} .obx-carousel__track > .e-con';
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'content_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>$slide,'separator'=>'before']);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'content_border','selector'=>$slide]);
        $this->dimensions('border_radius', 'Radio del borde', '.obx-carousel__track > .e-con', 'border-radius');
        $this->dimensions('content_padding', 'Relleno', '.obx-carousel__track > .e-con', 'padding');
        $this->end_controls_section();

        $this->carousel_pro_navigation_style('arrows');
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $items = array_values(array_filter((array) ($s['carousel_items'] ?? []), 'is_array'));
        $prev = !empty($s['navigation_previous_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($s['navigation_previous_icon'], ['aria-hidden'=>'true']) : '';
        $next = !empty($s['navigation_next_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($s['navigation_next_icon'], ['aria-hidden'=>'true']) : '';
        $dir = ($s['direction'] ?? 'ltr') === 'rtl' ? ' dir="rtl"' : '';
        echo '<div class="obx-carousel obx-ncarousel" data-carousel="' . esc_attr($this->carousel_options($s, 'arrows', 'infinite')) . '"' . $dir . ' role="region" aria-roledescription="carrusel" aria-label="' . esc_attr((string) ($s['carousel_name'] ?? 'Carrusel')) . '"><div class="obx-carousel__track">';
        // The slide containers are the carousel slides (direct children of the track, as in the editor).
        foreach (array_keys($items) as $index) { $this->print_child($index); }
        echo '</div>' . $this->carousel_controls_html($s, 'arrows', $prev, $next) . '</div>';
    }

    protected function content_template(): void {
        ?>
        <div class="obx-carousel obx-ncarousel" data-carousel="{}"<# if ( 'rtl' === settings.direction ) { #> dir="rtl"<# } #>>
            <div class="obx-carousel__track">
            </div>
            <# if ( 'yes' === settings.arrows ) { #>
            <div class="obx-carousel__arrow obx-carousel__arrow--prev" role="button" tabindex="0" aria-label="Diapositiva anterior">{{{ elementor.helpers.renderIcon( view, settings.navigation_previous_icon, { 'aria-hidden': true }, 'i', 'object' )?.value || '‹' }}}</div>
            <div class="obx-carousel__arrow obx-carousel__arrow--next" role="button" tabindex="0" aria-label="Diapositiva siguiente">{{{ elementor.helpers.renderIcon( view, settings.navigation_next_icon, { 'aria-hidden': true }, 'i', 'object' )?.value || '›' }}}</div>
            <# } #>
            <# if ( settings.pagination ) { #><div class="obx-carousel__pagination obx-carousel__pagination--{{ settings.pagination }}"></div><# } #>
        </div>
        <?php
    }
}

final class Gallery extends Element {
    /** Hover animations of the overlay and its content (Elementor Pro's entrance, reaction and exit sets). */
    private const HOVER_ANIMATIONS = [
        ''=>'Ninguna',
        'enter-from-right'=>'Entrar por la derecha','enter-from-left'=>'Entrar por la izquierda','enter-from-top'=>'Entrar por arriba','enter-from-bottom'=>'Entrar por abajo','enter-zoom-in'=>'Entrar acercando','enter-zoom-out'=>'Entrar alejando','fade-in'=>'Aparecer',
        'grow'=>'Crecer','shrink'=>'Encoger','move-right'=>'Mover a la derecha','move-left'=>'Mover a la izquierda','move-up'=>'Mover arriba','move-down'=>'Mover abajo',
        'exit-to-right'=>'Salir por la derecha','exit-to-left'=>'Salir por la izquierda','exit-to-top'=>'Salir por arriba','exit-to-bottom'=>'Salir por abajo','exit-zoom-in'=>'Salir acercando','exit-zoom-out'=>'Salir alejando','fade-out'=>'Desaparecer',
    ];
    /** Values used before 0.11.0 that Pro does not have. */
    private const OLD_ANIMATIONS = ['slide-in-up'=>'enter-from-bottom','zoom-in'=>'enter-zoom-in'];

    public static function wants_upgrade(string $name, array $settings): bool { return $name === self::PRO && isset(self::OLD_ANIMATIONS[$settings['content_hover_animation'] ?? '']); }
    protected static function upgrade_settings(array $s): array {
        $s['content_hover_animation'] = self::OLD_ANIMATIONS[$s['content_hover_animation']] ?? $s['content_hover_animation'];
        return $s;
    }

    public const PRO = 'gallery';
    public function get_title(): string { return 'Galería'; }
    public function get_icon(): string { return 'eicon-gallery-justified'; }
    public function get_keywords(): array { return ['gallery','galeria','imagenes','fotos','masonry','justified','portfolio']; }

    protected function register_controls(): void {
        $this->start_controls_section('settings', ['label'=>'Galería']);
        $this->add_control('gallery_type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'single','options'=>['single'=>'Una galería','multiple'=>'Varias galerías']]);
        $this->add_control('gallery', ['label'=>'Imágenes','type'=>Controls_Manager::GALLERY,'dynamic'=>['active'=>true],'condition'=>['gallery_type'=>'single']]);
        $r = new Repeater();
        $r->add_control('gallery_title', ['label'=>'Título','type'=>Controls_Manager::TEXT,'default'=>'Nueva galería','dynamic'=>['active'=>true]]);
        $r->add_control('multiple_gallery', ['label'=>'Imágenes','type'=>Controls_Manager::GALLERY,'dynamic'=>['active'=>true]]);
        $this->add_control('galleries', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'title_field'=>'{{{ gallery_title }}}','default'=>[['gallery_title'=>'Nueva galería']],'condition'=>['gallery_type'=>'multiple']]);
        $this->add_control('order_by', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Por defecto','random'=>'Aleatorio']]);
        $this->add_control('lazyload', ['label'=>'Carga diferida','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('gallery_layout', ['label'=>'Diseño','type'=>Controls_Manager::SELECT,'default'=>'grid','options'=>['grid'=>'Cuadrícula','justified'=>'Justificado','masonry'=>'Mosaico'],'separator'=>'before']);
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::NUMBER,'default'=>4,'tablet_default'=>2,'mobile_default'=>1,'min'=>1,'max'=>24,'selectors'=>['{{WRAPPER}} .obx-gallery'=>'--obx-columns: {{VALUE}};'],'condition'=>['gallery_layout!'=>'justified']]);
        $this->add_responsive_control('ideal_row_height', ['label'=>'Altura de fila','type'=>Controls_Manager::SLIDER,'size_units'=>['px'],'range'=>['px'=>['min'=>50,'max'=>500]],'default'=>['size'=>200],'selectors'=>['{{WRAPPER}} .obx-gallery'=>'--obx-row-height: {{SIZE}}px;'],'condition'=>['gallery_layout'=>'justified']]);
        $this->add_responsive_control('gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem'],'default'=>['size'=>10],'selectors'=>['{{WRAPPER}} .obx-gallery'=>'--obx-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('link_to', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'default'=>'file','options'=>[''=>'Ninguno','file'=>'Archivo multimedia','custom'=>'Dirección personalizada'],'separator'=>'before']);
        $this->add_control('url', ['label'=>'Dirección','type'=>Controls_Manager::URL,'condition'=>['link_to'=>'custom']]);
        $this->add_control('open_lightbox', ['label'=>'Caja de luz','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','yes'=>'Sí','no'=>'No'],'condition'=>['link_to'=>'file']]);
        $this->add_control('aspect_ratio', ['label'=>'Proporción','type'=>Controls_Manager::SELECT,'default'=>'3:2','options'=>['1:1'=>'1:1','3:2'=>'3:2','4:3'=>'4:3','9:16'=>'9:16','16:9'=>'16:9','21:9'=>'21:9'],'condition'=>['gallery_layout'=>'grid']]);
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'thumbnail_image','default'=>'medium']);
        $this->end_controls_section();

        $this->start_controls_section('section_filter_bar_content', ['label'=>'Barra de filtros','condition'=>['gallery_type'=>'multiple']]);
        $this->add_control('show_all_galleries', ['label'=>'Opción «Todas»','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('show_all_galleries_label', ['label'=>'Texto','type'=>Controls_Manager::TEXT,'default'=>'Todas','condition'=>['show_all_galleries'=>'yes']]);
        $this->add_control('pointer', ['label'=>'Indicador','type'=>Controls_Manager::SELECT,'default'=>'underline','options'=>['none'=>'Ninguno','underline'=>'Subrayado','overline'=>'Sobrerrayado','double-line'=>'Doble línea','framed'=>'Marco','background'=>'Fondo','text'=>'Texto']]);
        $none = ['none'=>'Ninguna'];
        $this->add_control('animation_line', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'fade','options'=>['fade'=>'Fundido','slide'=>'Deslizar','grow'=>'Crecer','drop-in'=>'Caer hacia dentro','drop-out'=>'Caer hacia fuera'] + $none,'condition'=>['pointer'=>['underline','overline','double-line']]]);
        $this->add_control('animation_framed', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'fade','options'=>['fade'=>'Fundido','grow'=>'Crecer','shrink'=>'Encoger','draw'=>'Dibujar','corners'=>'Esquinas'] + $none,'condition'=>['pointer'=>'framed']]);
        $this->add_control('animation_background', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'fade','options'=>['fade'=>'Fundido','grow'=>'Crecer','shrink'=>'Encoger','sweep-left'=>'Barrer a la izquierda','sweep-right'=>'Barrer a la derecha','sweep-up'=>'Barrer hacia arriba','sweep-down'=>'Barrer hacia abajo','shutter-in-vertical'=>'Persiana hacia dentro (vertical)','shutter-out-vertical'=>'Persiana hacia fuera (vertical)','shutter-in-horizontal'=>'Persiana hacia dentro (horizontal)','shutter-out-horizontal'=>'Persiana hacia fuera (horizontal)'] + $none,'condition'=>['pointer'=>'background']]);
        $this->add_control('animation_text', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'grow','options'=>['grow'=>'Crecer','shrink'=>'Encoger','sink'=>'Hundir','float'=>'Flotar','skew'=>'Inclinar','rotate'=>'Girar'] + $none,'condition'=>['pointer'=>'text']]);
        $this->end_controls_section();

        $this->start_controls_section('overlay', ['label'=>'Capa al pasar el ratón']);
        $this->add_control('overlay_background', ['label'=>'Fondo','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $fields = [''=>'Ninguno','title'=>'Título','caption'=>'Leyenda','alt'=>'Texto alternativo','description'=>'Descripción'];
        $this->add_control('overlay_title', ['label'=>'Título','type'=>Controls_Manager::SELECT,'default'=>'','options'=>$fields]);
        $this->add_control('overlay_description', ['label'=>'Descripción','type'=>Controls_Manager::SELECT,'default'=>'','options'=>$fields]);
        $this->end_controls_section();

        $this->start_controls_section('image_style', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE]);
        $this->start_controls_tabs('image_tabs');
        $this->start_controls_tab('image_normal', ['label'=>'Normal']);
        $this->color('image_border_color', 'Color del borde', '.obx-gallery__item', 'border-color');
        $this->slider('image_border_width', 'Grosor del borde', '.obx-gallery__item', 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;', ['size_units'=>['px'],'range'=>['px'=>['max'=>20]]]);
        $this->slider('image_border_radius', 'Radio del borde', '.obx-gallery__item', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%']]);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'image_css_filters','selector'=>'{{WRAPPER}} .obx-gallery__image']);
        $this->end_controls_tab();
        $this->start_controls_tab('image_hover', ['label'=>'Al pasar el ratón']);
        $this->color('image_border_color_hover', 'Color del borde', '.obx-gallery__item:hover', 'border-color');
        $this->slider('image_border_radius_hover', 'Radio del borde', '.obx-gallery__item:hover', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%']]);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'image_css_filters_hover','selector'=>'{{WRAPPER}} .obx-gallery__item:hover .obx-gallery__image']);
        $this->add_control('image_hover_animation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguna','grow'=>'Acercar','shrink-contained'=>'Alejar','move-contained-left'=>'Mover a la izquierda','move-contained-right'=>'Mover a la derecha','move-contained-top'=>'Mover arriba','move-contained-bottom'=>'Mover abajo'],'separator'=>'before']);
        $this->slider('image_animation_duration', 'Duración de la animación (ms)', '.obx-gallery__image', 'transition-duration: {{SIZE}}ms;', ['size_units'=>['px'],'default'=>['unit'=>'px','size'=>800],'range'=>['px'=>['max'=>3000,'step'=>100]]]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section('overlay_style', ['label'=>'Capa','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['overlay_background'=>'yes']]);
        $this->start_controls_tabs('overlay_background_tabs');
        $this->start_controls_tab('overlay_normal', ['label'=>'Normal']);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'overlay_background','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-gallery__overlay']);
        $this->end_controls_tab();
        $this->start_controls_tab('overlay_hover', ['label'=>'Al pasar el ratón']);
        $this->add_group_control(Group_Control_Background::get_type(), ['name'=>'overlay_background_hover','types'=>['classic','gradient'],'exclude'=>['image'],'selector'=>'{{WRAPPER}} .obx-gallery__item:hover .obx-gallery__overlay, {{WRAPPER}} .obx-gallery__item:focus-visible .obx-gallery__overlay','fields_options'=>['background'=>['default'=>'classic'],'color'=>['default'=>'rgba(0,0,0,0.5)']]]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('image_blend_mode', ['label'=>'Mezcla','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Normal','multiply'=>'Multiplicar','screen'=>'Trama','overlay'=>'Superponer','darken'=>'Oscurecer','lighten'=>'Aclarar','color-dodge'=>'Sobreexponer color','color-burn'=>'Subexponer color','hue'=>'Tono','saturation'=>'Saturación','color'=>'Color','exclusion'=>'Exclusión','luminosity'=>'Luminosidad'],'selectors'=>self::css('.obx-gallery__overlay', 'mix-blend-mode: {{VALUE}};')]);
        $this->add_control('background_overlay_hover_animation', ['label'=>'Animación al pasar el ratón','type'=>Controls_Manager::SELECT,'options'=>self::HOVER_ANIMATIONS,'separator'=>'before']);
        $this->add_control('background_overlay_animation_duration', ['label'=>'Duración de la animación (ms)','type'=>Controls_Manager::SLIDER,'default'=>['unit'=>'px','size'=>800],'range'=>['px'=>['max'=>3000,'step'=>100]],'selectors'=>self::css('.obx-gallery', '--obx-overlay-duration: {{SIZE}}ms;')]);
        $this->end_controls_section();

        $this->start_controls_section('overlay_content_style', ['label'=>'Contenido de la capa','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_control('content_alignment', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'center','options'=>self::align_options(),'selectors'=>self::css('.obx-gallery__content', 'text-align: {{VALUE}};')]);
        $this->add_control('content_vertical_position', ['label'=>'Posición vertical','type'=>Controls_Manager::CHOOSE,'options'=>['top'=>['title'=>'Arriba','icon'=>'eicon-v-align-top'],'middle'=>['title'=>'Centro','icon'=>'eicon-v-align-middle'],'bottom'=>['title'=>'Abajo','icon'=>'eicon-v-align-bottom']],'selectors_dictionary'=>['top'=>'flex-start','middle'=>'center','bottom'=>'flex-end'],'selectors'=>self::css('.obx-gallery__content', 'justify-content: {{VALUE}};')]);
        $this->slider('content_padding', 'Relleno', '.obx-gallery__content', 'padding: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>20]], true);
        $this->color('title_color', 'Color del título', '.obx-gallery__title', 'color', ['separator'=>'before']);
        $this->typography('title_typography', '.obx-gallery__title');
        $this->slider('title_spacing', 'Separación del título', '.obx-gallery__title', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem']]);
        $this->color('description_color', 'Color de la descripción', '.obx-gallery__description', 'color', ['separator'=>'before']);
        $this->typography('description_typography', '.obx-gallery__description');
        $this->add_control('content_hover_animation', ['label'=>'Animación al pasar el ratón','type'=>Controls_Manager::SELECT,'default'=>'fade-in','options'=>self::HOVER_ANIMATIONS,'separator'=>'before']);
        $this->add_control('content_animation_duration', ['label'=>'Duración de la animación (ms)','type'=>Controls_Manager::SLIDER,'default'=>['unit'=>'px','size'=>800],'range'=>['px'=>['max'=>3000,'step'=>100]],'selectors'=>self::css('.obx-gallery', '--obx-content-duration: {{SIZE}}ms;'),'condition'=>['content_hover_animation!'=>'']]);
        $this->add_control('content_sequenced_animation', ['label'=>'Animación escalonada','type'=>Controls_Manager::SWITCHER,'condition'=>['content_hover_animation!'=>'']]);
        $this->end_controls_section();

        $this->start_controls_section('filter_bar_style', ['label'=>'Barra de filtros','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['gallery_type'=>'multiple']]);
        $this->add_control('align_filter_bar_items', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::align_options(),'selectors_dictionary'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end'],'selectors'=>self::css('.obx-gallery__filters', 'justify-content: {{VALUE}};')]);
        $this->start_controls_tabs('filter_bar_colors');
        $this->start_controls_tab('filter_bar_normal', ['label'=>'Normal']);
        $this->color('galleries_title_color_normal', 'Color', '.obx-gallery__filter');
        $this->end_controls_tab();
        $this->start_controls_tab('filter_bar_hover', ['label'=>'Al pasar el ratón']);
        $this->color('galleries_title_color_hover', 'Color', '.obx-gallery__filter:hover', 'color', ['condition'=>['pointer!'=>'background']]);
        $this->color('galleries_title_color_hover_pointer_bg', 'Color', '.obx-gallery__filters--background .obx-gallery__filter:hover, .obx-gallery__filters--background .obx-gallery__filter.is-active', 'color', ['default'=>'#fff','condition'=>['pointer'=>'background']]);
        $this->color('galleries_pointer_color_hover', 'Color del indicador', '.obx-gallery__filters', '--obx-pointer-hover', ['condition'=>['pointer!'=>['none','text']]]);
        $this->end_controls_tab();
        $this->start_controls_tab('filter_bar_active', ['label'=>'Activo']);
        $this->color('galleries_title_color_active', 'Color', '.obx-gallery__filter.is-active');
        $this->color('galleries_pointer_color_active', 'Color del indicador', '.obx-gallery__filters', '--obx-pointer-active', ['condition'=>['pointer!'=>['none','text']]]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->slider('pointer_width', 'Grosor del indicador', '.obx-gallery__filters', '--obx-pointer-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'range'=>['px'=>['max'=>30]]]);
        $this->typography('galleries_titles_typography', '.obx-gallery__filter');
        $this->slider('galleries_titles_space_between', 'Separación entre filtros', '.obx-gallery__filters', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem']]);
        $this->slider('galleries_titles_gap', 'Distancia a la galería', '.obx-gallery__filters', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem']]);
        $this->end_controls_section();
    }

    private static function field(int $id, string $field): string {
        if ($id <= 0 || $field === '') { return ''; }
        $post = get_post($id);
        if (!$post) { return ''; }
        return match ($field) {
            'title'=>(string) $post->post_title,
            'caption'=>(string) $post->post_excerpt,
            'alt'=>(string) get_post_meta($id, '_wp_attachment_image_alt', true),
            'description'=>(string) $post->post_content,
            default=>'',
        };
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $multiple = ($s['gallery_type'] ?? 'single') === 'multiple';
        $sets = $multiple ? array_values(array_filter((array) ($s['galleries'] ?? []), 'is_array')) : [['gallery_title'=>'','multiple_gallery'=>(array) ($s['gallery'] ?? [])]];
        // Image id/url => list of gallery indexes containing it.
        $items = [];
        foreach ($sets as $g => $set) {
            foreach ((array) ($set['multiple_gallery'] ?? []) as $image) {
                if (!is_array($image) || (empty($image['id']) && empty($image['url']))) { continue; }
                $key = !empty($image['id']) ? 'id' . (int) $image['id'] : 'u' . md5((string) $image['url']);
                $items[$key] ??= ['image'=>$image,'galleries'=>[]];
                $items[$key]['galleries'][] = $g;
            }
        }
        if (!$items) { $this->placeholder('Añade imágenes a la galería.'); return; }
        $items = array_values($items);
        if (($s['order_by'] ?? '') === 'random' && !$this->editing()) { shuffle($items); }
        $layout = in_array($s['gallery_layout'] ?? 'grid', ['grid','justified','masonry'], true) ? $s['gallery_layout'] : 'grid';
        $ratio = array_map('intval', explode(':', (string) ($s['aspect_ratio'] ?? '3:2'))) + [1, 1];
        $link_to = (string) ($s['link_to'] ?? 'file');
        $lightbox = (string) ($s['open_lightbox'] ?? 'default');
        $lazy = ($s['lazyload'] ?? 'yes') === 'yes';
        $id = $this->get_id();

        if ($multiple) {
            $pointer = sanitize_html_class((string) ($s['pointer'] ?? 'underline'));
            $pointer_animation = match ($pointer) { 'underline','overline','double-line' => $s['animation_line'] ?? 'fade', 'framed' => $s['animation_framed'] ?? 'fade', 'background' => $s['animation_background'] ?? 'fade', 'text' => $s['animation_text'] ?? 'grow', default => '' };
            echo '<div class="obx-gallery__filters obx-gallery__filters--' . esc_attr($pointer) . ($pointer_animation ? ' obx-gallery__filters--anim-' . esc_attr(sanitize_html_class((string) $pointer_animation)) : '') . '" role="tablist">';
            if (($s['show_all_galleries'] ?? 'yes') === 'yes') { echo '<button type="button" class="obx-gallery__filter is-active" data-gallery="all" role="tab" aria-selected="true">' . esc_html((string) ($s['show_all_galleries_label'] ?? 'Todas')) . '</button>'; }
            foreach ($sets as $g => $set) {
                $first = $g === 0 && ($s['show_all_galleries'] ?? 'yes') !== 'yes';
                echo '<button type="button" class="obx-gallery__filter' . ($first ? ' is-active' : '') . '" data-gallery="' . $g . '" role="tab" aria-selected="' . ($first ? 'true' : 'false') . '">' . esc_html((string) ($set['gallery_title'] ?? '')) . '</button>';
            }
            echo '</div>';
        }
        $animation = sanitize_html_class((string) ($s['image_hover_animation'] ?? ''));
        $content_anim = sanitize_html_class(self::OLD_ANIMATIONS[$s['content_hover_animation'] ?? ''] ?? (string) ($s['content_hover_animation'] ?? 'fade-in'));
        $overlay_anim = sanitize_html_class((string) ($s['background_overlay_hover_animation'] ?? ''));
        $extra = ($overlay_anim ? ' obx-gallery--overlay-' . $overlay_anim : '') . ($content_anim && ($s['content_sequenced_animation'] ?? '') === 'yes' ? ' obx-gallery--sequenced' : '');
        printf('<div class="obx-gallery obx-gallery--%s%s%s%s" style="--obx-ratio: %s"%s>', esc_attr($layout), $animation ? ' obx-gallery--hover-' . esc_attr($animation) : '', $content_anim ? ' obx-gallery--content-' . esc_attr($content_anim) : '', esc_attr($extra), esc_attr(max(1, $ratio[0]) . ' / ' . max(1, $ratio[1])), $multiple && ($s['show_all_galleries'] ?? 'yes') !== 'yes' ? ' data-initial="0"' : '');
        foreach ($items as $item) {
            $image = $item['image'];
            $attachment = (int) ($image['id'] ?? 0);
            $src = $attachment ? (string) Group_Control_Image_Size::get_attachment_image_src($attachment, 'thumbnail_image', $s) : '';
            $src = $src ?: (string) ($image['url'] ?? '');
            if ($src === '') { continue; }
            $meta = $attachment ? wp_get_attachment_metadata($attachment) : [];
            $w = max(1, (int) ($meta['width'] ?? 3));
            $h = max(1, (int) ($meta['height'] ?? 2));
            $alt = $attachment ? (string) get_post_meta($attachment, '_wp_attachment_image_alt', true) : '';
            $title = self::field($attachment, (string) ($s['overlay_title'] ?? ''));
            $description = self::field($attachment, (string) ($s['overlay_description'] ?? ''));
            $href = '';
            $attrs = '';
            if ($link_to === 'file') {
                $href = $attachment ? (string) wp_get_attachment_url($attachment) : (string) ($image['url'] ?? '');
                $attrs = ' data-elementor-open-lightbox="' . esc_attr($lightbox) . '" data-elementor-lightbox-slideshow="' . esc_attr('all-' . $id) . '"' . ($title !== '' ? ' data-elementor-lightbox-title="' . esc_attr($title) . '"' : '');
            } elseif ($link_to === 'custom' && !empty($s['url']['url'])) {
                $href = (string) $s['url']['url'];
                $attrs = !empty($s['url']['is_external']) ? ' target="_blank" rel="noopener"' : '';
            }
            $tag = $href !== '' ? 'a' : 'div';
            printf('<%1$s class="obx-gallery__item" data-galleries="%2$s" style="--w: %3$d; --h: %4$d"%5$s%6$s>', $tag, esc_attr(implode(',', $item['galleries'])), $w, $h, $href !== '' ? ' href="' . esc_url($href) . '"' : '', $attrs);
            echo '<img class="obx-gallery__image" src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '"' . ($lazy ? ' loading="lazy"' : '') . ' width="' . $w . '" height="' . $h . '">';
            if (($s['overlay_background'] ?? 'yes') === 'yes') { echo '<span class="obx-gallery__overlay" aria-hidden="true"></span>'; }
            if ($title !== '' || $description !== '') {
                echo '<span class="obx-gallery__content">';
                if ($title !== '') { echo '<span class="obx-gallery__title">' . esc_html($title) . '</span>'; }
                if ($description !== '') { echo '<span class="obx-gallery__description">' . esc_html(wp_strip_all_tags($description)) . '</span>'; }
                echo '</span>';
            }
            echo '</' . $tag . '>';
        }
        echo '</div>';
    }
}
