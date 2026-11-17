<?php
namespace OldBearExpansion\Extras;

use Elementor\Controls_Manager;
use Elementor\Core\Base\Document;
use Elementor\Core\DocumentTypes\PageBase;
use Elementor\Core\Kits\Documents\Kit;
use Elementor\Group_Control_Background;
use Elementor\Plugin as Elementor;
use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * Site Settings → Page Transitions (with preloader) and Advanced → Scroll Snap, stored under Elementor
 * Pro's keys (settings_page_transitions_*, scroll_snap*). Free only shows a teaser for the first one.
 */
final class Site_Settings {
    private const PT = 'settings_page_transitions_';
    public const ENTRANCE = [''=>'Ninguna','fade-out'=>'Desvanecer','fade-out-down'=>'Desvanecer hacia abajo','fade-out-right'=>'Desvanecer a la derecha','fade-out-up'=>'Desvanecer hacia arriba','fade-out-left'=>'Desvanecer a la izquierda','zoom-out'=>'Alejar','slide-out-down'=>'Deslizar hacia abajo','slide-out-right'=>'Deslizar a la derecha','slide-out-up'=>'Deslizar hacia arriba','slide-out-left'=>'Deslizar a la izquierda'];
    public const EXIT = [''=>'Ninguna','fade-in'=>'Aparecer','fade-in-down'=>'Aparecer desde arriba','fade-in-right'=>'Aparecer desde la izquierda','fade-in-up'=>'Aparecer desde abajo','fade-in-left'=>'Aparecer desde la derecha','zoom-in'=>'Acercar','slide-in-down'=>'Deslizar desde arriba','slide-in-right'=>'Deslizar desde la izquierda','slide-in-up'=>'Deslizar desde abajo','slide-in-left'=>'Deslizar desde la derecha'];
    public const LOADERS = ['circle'=>'Círculo','circle-dashed'=>'Círculo discontinuo','bouncing-dots'=>'Puntos que rebotan','pulsing-dots'=>'Puntos que laten','pulse'=>'Pulso','overlap'=>'Superposición','spinners'=>'Ruedas','nested-spinners'=>'Ruedas anidadas','opposing-nested-spinners'=>'Ruedas anidadas opuestas','opposing-nested-rings'=>'Anillos anidados opuestos','progress-bar'=>'Barra de progreso','two-way-progress-bar'=>'Barra de progreso doble','repeating-bar'=>'Barra repetida'];
    public const ATTENTION = [''=>'Ninguna','eicon-spin'=>'Girar','bounce'=>'Rebotar','flash'=>'Destello','pulse'=>'Latido','rubberBand'=>'Goma elástica','shake'=>'Sacudir','headShake'=>'Negar','swing'=>'Balancear','tada'=>'Tada','wobble'=>'Tambalear','jello'=>'Gelatina'];

    public static function boot(): void {
        add_action('elementor/element/after_section_end', [self::class, 'replace_teaser'], 10, 3);
        add_action('elementor/documents/register_controls', [self::class, 'scroll_snap_controls']);
        add_action('wp_body_open', [self::class, 'print_transition'], 1);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('elementor/preview/enqueue_scripts', [self::class, 'preview_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'editor_assets']);
    }

    /** Elementor Free registers «section_page_transitions_teaser» in the kit: swap it for the real settings. */
    public static function replace_teaser($element, $section_id, $args): void {
        if ($element instanceof Kit && in_array($section_id, ['section_custom_css','section_custom_css_pro'], true)) { self::scroll_snap_controls($element, $args['tab'] ?? Controls_Manager::TAB_ADVANCED); return; }
        if ($section_id !== 'section_page_transitions_teaser' || !$element instanceof Kit || $element->get_controls(self::PT . 'entrance_animation')) { return; }
        $tab = $args['tab'] ?? 'settings-page-transitions';
        $element->remove_control('page_transitions_teaser');
        $element->remove_control('section_page_transitions_teaser');
        $p = self::PT;
        $overlay = '{{WRAPPER}} .obx-page-transition';

        $element->start_controls_section('section_page_transitions', ['label'=>'Transiciones de página','tab'=>$tab]);
        $element->add_control($p . 'entrance_animation', ['label'=>'Al entrar en la página','type'=>Controls_Manager::SELECT,'default'=>'','options'=>self::ENTRANCE,'description'=>'La capa de transición se retira cuando la página termina de cargar.']);
        $element->add_control($p . 'exit_animation', ['label'=>'Al salir de la página','type'=>Controls_Manager::SELECT,'default'=>'','options'=>self::EXIT,'condition'=>[$p . 'entrance_animation!'=>'']]);
        $element->add_control($p . 'animation_duration', ['label'=>'Duración (ms)','type'=>Controls_Manager::SLIDER,'size_units'=>['ms'],'default'=>['unit'=>'ms','size'=>1500],'range'=>['ms'=>['min'=>0,'max'=>5000,'step'=>50]],'condition'=>[$p . 'entrance_animation!'=>''],'selectors'=>[$overlay=>'--obx-pt-duration: {{SIZE}}ms;']]);
        $element->add_group_control(Group_Control_Background::get_type(), ['name'=>'settings_page_transitions_background','types'=>['classic','gradient'],'selector'=>$overlay,'fields_options'=>['background'=>['default'=>'classic'],'color'=>['default'=>'#FFBC7D']]]);
        $element->add_control($p . 'page_transition_play_button', ['type'=>Controls_Manager::BUTTON,'label'=>'Probar','text'=>'Reproducir transición','separator'=>'before','event'=>'obx:pageTransition:preview','condition'=>[$p . 'entrance_animation!'=>'']]);
        $element->end_controls_section();

        $element->start_controls_section('section_preloader', ['label'=>'Precargador','tab'=>$tab]);
        $element->add_control($p . 'preloader_type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguno','animation'=>'Animación','icon'=>'Icono','image'=>'Imagen']]);
        $element->add_control($p . 'preloader_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'default'=>['value'=>'fas fa-spinner','library'=>'fa-solid'],'condition'=>[$p . 'preloader_type'=>'icon']]);
        $element->add_control($p . 'preloader_image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'condition'=>[$p . 'preloader_type'=>'image']]);
        $element->add_control($p . 'preloader_animation_type', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'circle','options'=>self::LOADERS,'condition'=>[$p . 'preloader_type'=>'animation']]);
        $element->add_control($p . 'preloader_animation', ['label'=>'Animación','type'=>Controls_Manager::SELECT,'default'=>'','options'=>self::ATTENTION,'condition'=>[$p . 'preloader_type'=>['icon','image']]]);
        $element->add_control($p . 'preloader_animation_duration', ['label'=>'Duración de la animación (ms)','type'=>Controls_Manager::SLIDER,'size_units'=>['ms'],'default'=>['unit'=>'ms','size'=>1500],'range'=>['ms'=>['min'=>0,'max'=>5000,'step'=>50]],'selectors'=>[$overlay=>'--obx-preloader-duration: {{SIZE}}ms;']]);
        $element->add_control($p . 'preloader_delay', ['label'=>'Retraso (ms)','type'=>Controls_Manager::SLIDER,'size_units'=>['ms'],'default'=>['unit'=>'ms','size'=>0],'range'=>['ms'=>['min'=>0,'max'=>5000,'step'=>50]],'condition'=>[$p . 'preloader_type!'=>''],'selectors'=>[$overlay=>'--obx-preloader-delay: {{SIZE}}ms;']]);
        $element->add_control($p . 'preloader_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'default'=>'#FFF','condition'=>[$p . 'preloader_type'=>['icon','animation']],'selectors'=>[$overlay=>'--obx-preloader-color: {{VALUE}};']]);
        $element->add_responsive_control($p . 'preloader_size', ['label'=>'Tamaño','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','custom'],'default'=>['unit'=>'px','size'=>20],'range'=>['px'=>['min'=>1,'max'=>300]],'condition'=>[$p . 'preloader_type'=>['icon','animation']],'selectors'=>[$overlay=>'--obx-preloader-size: {{SIZE}}{{UNIT}};']]);
        $element->add_control($p . 'preloader_rotate', ['label'=>'Rotación','type'=>Controls_Manager::SLIDER,'size_units'=>['deg'],'default'=>['unit'=>'deg','size'=>0],'range'=>['deg'=>['min'=>0,'max'=>360]],'condition'=>[$p . 'preloader_type'=>'icon',$p . 'preloader_animation!'=>['eicon-spin','bounce','flash','pulse','rubberBand','shake','headShake','swing','tada','wobble','jello']],'selectors'=>[$overlay=>'--obx-preloader-rotate: {{SIZE}}deg;']]);
        $element->add_responsive_control($p . 'preloader_width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px','vw','custom'],'default'=>['unit'=>'%'],'condition'=>[$p . 'preloader_type'=>'image'],'selectors'=>[$overlay . ' .obx-preloader img'=>'width: {{SIZE}}{{UNIT}};']]);
        $element->add_responsive_control($p . 'preloader_max_width', ['label'=>'Ancho máximo','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px','vw','custom'],'default'=>['unit'=>'%'],'condition'=>[$p . 'preloader_type'=>'image'],'selectors'=>[$overlay . ' .obx-preloader img'=>'max-width: {{SIZE}}{{UNIT}};']]);
        $element->add_control($p . 'preloader_opacity', ['label'=>'Opacidad','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>1,'min'=>0.1,'step'=>0.01]],'condition'=>[$p . 'preloader_type'=>'image'],'selectors'=>[$overlay . ' .obx-preloader img'=>'opacity: {{SIZE}};']]);
        $element->add_control($p . 'preloader_play_button', ['type'=>Controls_Manager::BUTTON,'label'=>'Probar','text'=>'Reproducir transición','separator'=>'before','event'=>'obx:pageTransition:preview','condition'=>[$p . 'entrance_animation!'=>'']]);
        $element->end_controls_section();
    }

    /** Advanced → Scroll Snap, in the kit and in pages/posts (Pro's «section_scroll_snap»). */
    public static function scroll_snap_controls($document, string $tab = Controls_Manager::TAB_ADVANCED): void {
        $theme = $document instanceof \OldBearExpansion\ThemeBuilder\Location_Document && $document::HAS_PAGE_TEMPLATE;
        if ((!$document instanceof PageBase && !$theme) || $document->get_controls('scroll_snap')) { return; }
        $html = 'html:has({{WRAPPER}})';
        // The kit wrapper is <body>; a page wrapper is the page's own .elementor root.
        $children = $document instanceof Kit
            ? '{{WRAPPER}} .elementor > .e-con, {{WRAPPER}} .elementor > .elementor-section, {{WRAPPER}} .elementor-section-wrap > .elementor-section'
            : '{{WRAPPER}} > .e-con, {{WRAPPER}} > .elementor-section, {{WRAPPER}} > .elementor-section-wrap > .elementor-section';
        $document->start_controls_section('section_scroll_snap', ['label'=>'Ajuste del desplazamiento','tab'=>$tab]);
        $document->add_control('scroll_snap', ['label'=>'Ajustar al desplazar','type'=>Controls_Manager::SWITCHER,'label_off'=>'No','label_on'=>'Sí','return_value'=>'yes','description'=>'Al desplazarse, la página se detiene en cada contenedor o sección principal.','selectors'=>[$html=>'scroll-snap-type: y mandatory;']]);
        $document->add_control('scroll_snap_position', ['label'=>'Posición de ajuste','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Inicio','center'=>'Centro','end'=>'Final'],'condition'=>['scroll_snap!'=>''],'selectors_dictionary'=>[''=>'start'],'selectors'=>[$children=>'scroll-snap-align: {{VALUE}};']]);
        $document->add_responsive_control('scroll_snap_padding', ['label'=>'Margen de ajuste','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh','custom'],'range'=>['px'=>['max'=>500]],'condition'=>['scroll_snap!'=>''],'selectors'=>[$html=>'scroll-padding: {{SIZE}}{{UNIT}};']]);
        $document->add_control('force_stop', ['label'=>'Detenerse siempre','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'No','always'=>'Sí'],'description'=>'Impide saltarse contenedores con un desplazamiento rápido.','condition'=>['scroll_snap!'=>''],'selectors'=>[$children=>'scroll-snap-stop: {{VALUE}};']]);
        $document->end_controls_section();
        // Position «start» is the default and is not saved: still align children when snapping is on.
        $document->update_control('scroll_snap', ['selectors'=>[$html=>'scroll-snap-type: y mandatory;', $children=>'scroll-snap-align: start;']]);
    }

    /** Active kit settings for page transitions, or [] when they are off. */
    public static function settings(): array {
        $kit = Elementor::$instance->kits_manager->get_active_kit_for_frontend();
        if (!$kit) { return []; }
        $s = $kit->get_settings_for_display();
        $entrance = (string) ($s[self::PT . 'entrance_animation'] ?? '');
        $preloader = (string) ($s[self::PT . 'preloader_type'] ?? '');
        if ($entrance === '' && $preloader === '') { return []; }
        return $s;
    }

    public static function assets(): void {
        wp_register_script('obx-page-transitions', Plugin::url('assets/js/page-transitions.js'), [], VERSION, ['in_footer'=>false]);
        if (self::settings() && !Elementor::$instance->preview->is_preview_mode()) { wp_enqueue_script('obx-page-transitions'); }
    }

    public static function preview_assets(): void {
        wp_enqueue_script('obx-page-transitions', Plugin::url('assets/js/page-transitions.js'), [], VERSION, ['in_footer'=>false]);
    }

    public static function editor_assets(): void {
        wp_add_inline_script('elementor-editor', self::editor_script());
    }

    /** «Play» buttons: replay the transition in the preview with the settings currently in the panel. */
    private static function editor_script(): string {
        return "window.addEventListener('elementor/init',function(){elementor.channels.editor.on('obx:pageTransition:preview',function(){var kit=elementor.documents.get(elementor.config.kit_id),s=kit&&kit.container?kit.container.settings.toJSON():{};var w=elementor.\$preview&&elementor.\$preview[0]&&elementor.\$preview[0].contentWindow;if(w&&w.obxPageTransition){w.obxPageTransition.preview(s);}});});";
    }

    /** The transition layer, printed first in <body> so it covers the page while it loads. */
    public static function print_transition(): void {
        $preview = Elementor::$instance->preview->is_preview_mode();
        $s = $preview ? self::kit_settings() : self::settings();
        if (!$s && !$preview) { return; }
        $p = self::PT;
        $entrance = (string) ($s[$p . 'entrance_animation'] ?? '');
        $exit = (string) ($s[$p . 'exit_animation'] ?? '');
        $type = (string) ($s[$p . 'preloader_type'] ?? '');
        $attrs = ['class'=>'obx-page-transition' . ($preview ? ' obx-page-transition--preview' : ''),'data-entrance'=>$entrance,'data-exit'=>$exit,'aria-hidden'=>'true'];
        echo '<div';
        foreach ($attrs as $k => $v) { echo ' ' . esc_attr($k) . '="' . esc_attr($v) . '"'; }
        echo '>' . self::preloader_html($s) . '</div>';
        if (!$preview) { echo '<script>document.documentElement.classList.add("obx-pt-loading");setTimeout(function(){document.documentElement.classList.remove("obx-pt-loading");},10000);</script>'; }
    }

    private static function kit_settings(): array {
        $kit = Elementor::$instance->kits_manager->get_active_kit_for_frontend();
        return $kit ? $kit->get_settings_for_display() : [];
    }

    public static function preloader_html(array $s): string {
        $p = self::PT;
        $type = (string) ($s[$p . 'preloader_type'] ?? '');
        if ($type === '') { return ''; }
        $attention = (string) ($s[$p . 'preloader_animation'] ?? '');
        $class = 'obx-preloader obx-preloader--' . sanitize_html_class($type);
        if ($type === 'animation') {
            $loader = (string) ($s[$p . 'preloader_animation_type'] ?? 'circle');
            $loader = isset(self::LOADERS[$loader]) ? $loader : 'circle';
            return '<div class="' . esc_attr($class . ' obx-loader obx-loader--' . $loader) . '"><span></span><span></span><span></span></div>';
        }
        if ($attention === 'eicon-spin') { $class .= ' obx-preloader--spin'; }
        elseif ($attention !== '') { $class .= ' animated infinite ' . sanitize_html_class($attention); self::attention_style($attention); }
        if ($type === 'icon') {
            ob_start();
            \Elementor\Icons_Manager::render_icon($s[$p . 'preloader_icon'] ?? ['value'=>'fas fa-spinner','library'=>'fa-solid'], ['aria-hidden'=>'true']);
            return '<div class="' . esc_attr($class) . '">' . ob_get_clean() . '</div>';
        }
        $image = $s[$p . 'preloader_image'] ?? [];
        $url = is_array($image) ? (string) ($image['url'] ?? '') : '';
        return $url === '' ? '' : '<div class="' . esc_attr($class) . '"><img src="' . esc_url($url) . '" alt=""></div>';
    }

    private static function attention_style(string $animation): void {
        $handle = 'e-animation-' . $animation;
        if (wp_style_is($handle, 'registered')) { wp_enqueue_style($handle); return; }
        if (defined('ELEMENTOR_ASSETS_PATH') && is_file(ELEMENTOR_ASSETS_PATH . 'lib/animations/styles/' . $animation . '.min.css')) {
            wp_enqueue_style($handle, ELEMENTOR_ASSETS_URL . 'lib/animations/styles/' . $animation . '.min.css', [], ELEMENTOR_VERSION);
        }
    }
}
