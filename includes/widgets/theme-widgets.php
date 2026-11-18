<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Utils;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/**
 * Title widgets keep the settings of Elementor's Heading widget, as Elementor Pro's theme
 * title widgets do: header_size, link, align, title_color, typography…
 */
trait Heading_Controls {
    protected function heading_controls(string $default_tag, string $link_note = ''): void {
        $this->start_controls_section('section_title', ['label'=>'Título']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'default'=>['url'=>''],'description'=>$link_note]);
        $this->add_control('size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Predeterminado','small'=>'Pequeño','medium'=>'Mediano','large'=>'Grande','xl'=>'XL','xxl'=>'XXL'],'condition'=>['size!'=>'default']]);
        $this->add_control('header_size', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>$default_tag,'options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span','p'=>'p']]);
        $this->extra_title_controls();
        $this->end_controls_section();

        $this->start_controls_section('section_title_style', ['label'=>'Título','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(true),'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]);
        $this->add_control('title_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-heading-title'=>'color: {{VALUE}};','{{WRAPPER}} .elementor-heading-title a'=>'color: inherit;']]);
        $this->add_control('title_hover_color', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .elementor-heading-title:hover, {{WRAPPER}} .elementor-heading-title a:hover'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'typography','selector'=>'{{WRAPPER}} .elementor-heading-title']);
        $this->add_group_control(Group_Control_Text_Stroke::get_type(), ['name'=>'text_stroke','selector'=>'{{WRAPPER}} .elementor-heading-title']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'text_shadow','selector'=>'{{WRAPPER}} .elementor-heading-title']);
        $this->add_control('blend_mode', ['label'=>'Modo de fusión','type'=>Controls_Manager::SELECT,'options'=>[''=>'Normal','multiply'=>'Multiplicar','screen'=>'Trama','overlay'=>'Superponer','darken'=>'Oscurecer','lighten'=>'Aclarar','color-dodge'=>'Sobreexponer color','saturation'=>'Saturación','color'=>'Color','difference'=>'Diferencia','exclusion'=>'Exclusión','hue'=>'Tono','luminosity'=>'Luminosidad'],'selectors'=>['{{WRAPPER}} .elementor-heading-title'=>'mix-blend-mode: {{VALUE}}'],'separator'=>'none']);
        $this->end_controls_section();
    }

    protected function extra_title_controls(): void {}

    protected function heading_tag(): string {
        $tag = (string) $this->get_settings_for_display('header_size');
        return in_array($tag, ['h1','h2','h3','h4','h5','h6','div','span','p'], true) ? $tag : 'h2';
    }

    /** Prints the title; $fallback_url is used when the link control is empty. */
    protected function render_heading(string $text, string $class, string $fallback_url = ''): void {
        $s = $this->get_settings_for_display();
        $size = in_array($s['size'] ?? 'default', ['default','small','medium','large','xl','xxl'], true) ? $s['size'] : 'default';
        $html = esc_html($text);
        if (!empty($s['link']['url'])) {
            $this->add_link_attributes('url', $s['link']);
            $html = '<a ' . $this->get_render_attribute_string('url') . '>' . $html . '</a>';
        } elseif ($fallback_url !== '') {
            $html = '<a href="' . esc_url($fallback_url) . '">' . $html . '</a>';
        }
        printf('<%1$s class="elementor-heading-title elementor-size-%2$s %3$s">%4$s</%1$s>', $this->heading_tag(), esc_attr($size), esc_attr($class), $html);
    }

    /** Settings saved by v0.3 under our own names. */
    protected static function upgrade_heading(array $s): array {
        $s = self::rename($s, ['tag'=>'header_size','text_align'=>'align','text_color'=>'title_color','text_hover'=>'title_hover_color']);
        return self::rename_prefix($s, 'text_typography_', 'typography_');
    }

    public function get_style_depends(): array { return ['obx-frontend','widget-heading']; }
}

final class Post_Title extends Base {
    use Heading_Controls;
    public const NAME = 'obx-post-title';
    public const PRO = 'theme-post-title';
    public function get_title(): string { return 'Título de la entrada'; }
    public function get_icon(): string { return 'eicon-post-title'; }
    public function get_keywords(): array { return ['title','titulo','post','entrada','heading']; }
    protected function register_controls(): void { $this->heading_controls('h1'); }
    protected function extra_title_controls(): void {
        $this->add_control('link_to_post', ['label'=>'Enlazar a la entrada','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','condition'=>['link[url]'=>'']]);
    }
    protected static function upgrade_settings(array $s): array {
        if (($s['link'] ?? '') === 'yes') { $s['link_to_post'] = 'yes'; }
        unset($s['link']);
        return self::upgrade_heading($s);
    }
    protected function render(): void {
        $post = Context::post();
        if (!$post) { $this->placeholder('No hay ninguna entrada que mostrar.'); return; }
        $this->render_heading(get_the_title($post), 'obx-post-title', $this->get_settings_for_display('link_to_post') === 'yes' ? (string) get_permalink($post) : '');
    }
}

final class Archive_Title extends Base {
    use Heading_Controls;
    public const CATEGORY = 'obx-archive';
    public const NAME = 'obx-archive-title';
    public const PRO = 'theme-archive-title';
    public function get_title(): string { return 'Título del archivo'; }
    public function get_icon(): string { return 'eicon-archive-title'; }
    public function get_keywords(): array { return ['archive','archivo','titulo','categoria','heading']; }
    protected function register_controls(): void { $this->heading_controls('h1'); }
    protected function extra_title_controls(): void {
        $this->add_control('description', ['label'=>'Mostrar descripción','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
    }
    protected static function upgrade_settings(array $s): array { return self::upgrade_heading($s); }
    protected function render(): void {
        $this->render_heading(Context::archive_title(), 'obx-archive-title');
        if ($this->get_settings_for_display('description') === 'yes' && (!Context::template() || Context::loop_term())) {
            $description = Context::archive_description();
            if ($description) { echo '<div class="obx-archive-description">' . wp_kses_post($description) . '</div>'; }
        }
    }
}

/** Title of the current page, post, archive or search, whatever the view. */
final class Page_Title extends Base {
    use Heading_Controls;
    public const PRO = 'theme-page-title';
    public function get_title(): string { return 'Título de la página'; }
    public function get_icon(): string { return 'eicon-archive-title'; }
    public function get_keywords(): array { return ['title','titulo','page','pagina','heading']; }
    protected function register_controls(): void { $this->heading_controls('h1'); }
    protected function render(): void {
        if (Context::template() || is_singular()) {
            $post = Context::post();
            $title = $post ? get_the_title($post) : '';
        } elseif (is_404()) {
            $title = 'Página no encontrada';
        } else {
            $title = Context::archive_title();
        }
        if ($title === '') { $this->placeholder('Título de la página'); return; }
        $this->render_heading($title, 'obx-page-title');
    }
}

final class Site_Title extends Base {
    use Heading_Controls;
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-site-title';
    public const PRO = 'theme-site-title';
    public function get_title(): string { return 'Título del sitio'; }
    public function get_icon(): string { return 'eicon-site-title'; }
    public function get_keywords(): array { return ['site','sitio','titulo','nombre','tagline','lema']; }
    protected function register_controls(): void {
        $this->heading_controls('h2', 'Vacío: la portada.');
        $this->text_style('.obx-site-tagline', 'Descripción corta', 'tagline', false);
    }
    protected function extra_title_controls(): void {
        $this->add_control('link_home', ['label'=>'Enlazar a la portada','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','condition'=>['link[url]'=>'']]);
        $this->add_control('tagline', ['label'=>'Mostrar descripción corta','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
    }
    protected static function upgrade_settings(array $s): array {
        $s['link_home'] = ($s['link'] ?? 'yes') === 'yes' ? 'yes' : '';
        unset($s['link']);
        return self::upgrade_heading($s);
    }
    protected function render(): void {
        $this->render_heading(get_bloginfo('name'), 'obx-site-title', $this->get_settings_for_display('link_home') === 'yes' ? home_url('/') : '');
        if ($this->get_settings_for_display('tagline') === 'yes' && get_bloginfo('description')) { echo '<p class="obx-site-tagline">' . esc_html(get_bloginfo('description')) . '</p>'; }
    }
}

/**
 * Image widgets keep the settings of Elementor's Image widget, as Elementor Pro's
 * Site Logo and Featured Image do: image_size, link_to, link, width, height, object-fit…
 */
trait Image_Controls {
    protected function image_controls(string $link_note): void {
        $this->start_controls_section('section_image', ['label'=>'Imagen']);
        $this->add_control('image', ['label'=>'Imagen alternativa','type'=>Controls_Manager::MEDIA,'dynamic'=>['active'=>true],'default'=>['url'=>''],'description'=>$this->image_note()]);
        $this->before_image_size();
        $this->add_group_control(Group_Control_Image_Size::get_type(), ['name'=>'image','default'=>$this->default_image_size()]);
        $this->add_control('caption_source', ['label'=>'Leyenda','type'=>Controls_Manager::SELECT,'options'=>['none'=>'Ninguna','attachment'=>'Leyenda del adjunto','custom'=>'Personalizada'],'default'=>'none']);
        $this->add_control('caption', ['label'=>'Leyenda personalizada','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true],'condition'=>['caption_source'=>'custom']]);
        $this->add_control('link_to', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'options'=>$this->link_options(),'default'=>$this->default_link_to()]);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::URL,'dynamic'=>['active'=>true],'condition'=>['link_to'=>'custom'],'show_label'=>false,'description'=>$link_note]);
        $this->add_control('open_lightbox', ['label'=>'Caja de luz','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','yes'=>'Sí','no'=>'No'],'condition'=>['link_to'=>'file']]);
        $this->end_controls_section();

        $this->start_controls_section('section_style_image', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(),'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]);
        $this->add_responsive_control('width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'default'=>['unit'=>'%'],'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['%'=>['min'=>1,'max'=>100],'px'=>['min'=>1,'max'=>1000],'vw'=>['min'=>1,'max'=>100]],'selectors'=>['{{WRAPPER}} img'=>'width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('space', ['label'=>'Ancho máximo','type'=>Controls_Manager::SLIDER,'default'=>['unit'=>'%'],'size_units'=>['px','%','em','rem','vw','custom'],'range'=>['%'=>['min'=>1,'max'=>100],'px'=>['min'=>1,'max'=>1000]],'selectors'=>['{{WRAPPER}} img'=>'max-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('height', ['label'=>'Alto','type'=>Controls_Manager::SLIDER,'size_units'=>['px','%','em','rem','vh','custom'],'range'=>['px'=>['min'=>1,'max'=>1000],'vh'=>['min'=>1,'max'=>100]],'selectors'=>['{{WRAPPER}} img'=>'height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('object-fit', ['label'=>'Ajuste del objeto','type'=>Controls_Manager::SELECT,'options'=>[''=>'Predeterminado','fill'=>'Rellenar','cover'=>'Cubrir','contain'=>'Contener','scale-down'=>'Reducir'],'default'=>'','condition'=>['height[size]!'=>''],'selectors'=>['{{WRAPPER}} img'=>'object-fit: {{VALUE}};']]);
        $this->add_responsive_control('object-position', ['label'=>'Posición del objeto','type'=>Controls_Manager::SELECT,'options'=>['center center'=>'Centro','center left'=>'Centro izquierda','center right'=>'Centro derecha','top center'=>'Arriba','top left'=>'Arriba izquierda','top right'=>'Arriba derecha','bottom center'=>'Abajo','bottom left'=>'Abajo izquierda','bottom right'=>'Abajo derecha'],'default'=>'center center','condition'=>['height[size]!'=>'','object-fit'=>['cover','contain','scale-down']],'selectors'=>['{{WRAPPER}} img'=>'object-position: {{VALUE}};']]);
        $this->start_controls_tabs('image_effects');
        $this->start_controls_tab('normal', ['label'=>'Normal']);
        $this->add_control('opacity', ['label'=>'Opacidad','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>1,'min'=>0.1,'step'=>0.01]],'selectors'=>['{{WRAPPER}} img'=>'opacity: {{SIZE}};']]);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'css_filters','selector'=>'{{WRAPPER}} img']);
        $this->end_controls_tab();
        $this->start_controls_tab('hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('opacity_hover', ['label'=>'Opacidad','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>1,'min'=>0.1,'step'=>0.01]],'selectors'=>['{{WRAPPER}}:hover img'=>'opacity: {{SIZE}};']]);
        $this->add_group_control(Group_Control_Css_Filter::get_type(), ['name'=>'css_filters_hover','selector'=>'{{WRAPPER}}:hover img']);
        $this->add_control('background_hover_transition', ['label'=>'Duración de la transición (s)','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['max'=>3,'step'=>0.1]],'selectors'=>['{{WRAPPER}} img'=>'transition-duration: {{SIZE}}s;']]);
        $this->add_control('hover_animation', ['label'=>'Animación al pasar el ratón','type'=>Controls_Manager::HOVER_ANIMATION]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'image_border','selector'=>'{{WRAPPER}} img','separator'=>'before']);
        $this->add_responsive_control('image_border_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%','em','rem','custom'],'selectors'=>['{{WRAPPER}} img'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name'=>'image_box_shadow','selector'=>'{{WRAPPER}} img']);
        $this->end_controls_section();

        $this->start_controls_section('section_style_caption', ['label'=>'Leyenda','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['caption_source!'=>'none']]);
        $this->add_responsive_control('caption_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(true),'selectors'=>['{{WRAPPER}} .widget-image-caption'=>'text-align: {{VALUE}};']]);
        $this->add_control('text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .widget-image-caption'=>'color: {{VALUE}};']]);
        $this->add_control('caption_background_color', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .widget-image-caption'=>'background-color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'caption_typography','selector'=>'{{WRAPPER}} .widget-image-caption']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'caption_text_shadow','selector'=>'{{WRAPPER}} .widget-image-caption']);
        $this->add_responsive_control('caption_space', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'range'=>['px'=>['min'=>0,'max'=>100]],'selectors'=>['{{WRAPPER}} .widget-image-caption'=>'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: 0px;']]);
        $this->end_controls_section();
    }

    protected function default_link_to(): string { return 'none'; }
    protected function default_image_size(): string { return 'large'; }
    protected function link_options(): array { return ['none'=>'Ninguno','file'=>'Archivo multimedia','custom'=>'URL personalizada']; }
    protected function before_image_size(): void {}
    protected function image_note(): string { return ''; }

    /**
     * Prints an attachment (or a plain URL) with the widget's size, caption and link.
     * A custom link with an empty URL points to $natural_link (home page or the post).
     */
    protected function render_image(int $id, string $url, string $alt, string $natural_link): void {
        $s = $this->get_settings_for_display();
        $size = (string) ($s['image_size'] ?? 'large');
        if ($id) {
            $size_arg = $size === 'custom' ? 'full' : ($size ?: 'large');
            $attrs = ['alt'=>$alt ?: (string) get_post_meta($id, '_wp_attachment_image_alt', true)];
            if (!empty($s['hover_animation'])) { $attrs['class'] = 'attachment-' . $size_arg . ' size-' . $size_arg . ' wp-image-' . $id . ' elementor-animation-' . $s['hover_animation']; }
            $html = wp_get_attachment_image($id, $size_arg, false, $attrs);
            if ($size === 'custom' && $html) {
                $src = Group_Control_Image_Size::get_attachment_image_src($id, 'image', $s);
                if ($src) { $html = '<img src="' . esc_url($src) . '" alt="' . esc_attr($attrs['alt']) . '"' . (isset($attrs['class']) ? ' class="' . esc_attr($attrs['class']) . '"' : '') . '>'; }
            }
            $file = (string) wp_get_attachment_url($id);
        } else {
            $html = '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '"' . (!empty($s['hover_animation']) ? ' class="elementor-animation-' . esc_attr($s['hover_animation']) . '"' : '') . '>';
            $file = $url;
        }
        if (!$html) { return; }
        $caption = match ($s['caption_source'] ?? 'none') {
            'attachment' => $id ? (string) wp_get_attachment_caption($id) : '',
            'custom' => (string) ($s['caption'] ?? ''),
            default => '',
        };
        $link = '';
        $link_to = (string) ($s['link_to'] ?? $this->default_link_to());
        if ($link_to === 'site_url') { $link = home_url('/'); }
        if ($link_to === 'file') {
            $this->add_render_attribute('file_link', ['href'=>esc_url($file),'data-elementor-open-lightbox'=>(string) ($s['open_lightbox'] ?? 'default')]);
            $html = '<a ' . $this->get_render_attribute_string('file_link') . '>' . $html . '</a>';
        }
        if ($link_to === 'custom') {
            if (!empty($s['link']['url'])) {
                $this->add_link_attributes('link', $s['link']);
                $html = '<a ' . $this->get_render_attribute_string('link') . '>' . $html . '</a>';
            } else {
                $link = $natural_link;
            }
        }
        if ($link !== '') { $html = '<a href="' . esc_url($link) . '">' . $html . '</a>'; }
        if ($caption !== '') {
            echo '<figure class="wp-caption">' . $html . '<figcaption class="widget-image-caption wp-caption-text">' . esc_html($caption) . '</figcaption></figure>';
        } else {
            echo $html; // Core image markup.
        }
    }

    protected static function upgrade_image(array $s): array {
        $s = self::rename($s, ['radius'=>'image_border_radius','size'=>'image_size']);
        return self::rename_prefix($s, 'border_', 'image_border_');
    }
}

final class Site_Logo extends Base {
    use Image_Controls;
    public const CATEGORY = 'obx-site';
    public const NAME = 'obx-site-logo';
    public const PRO = 'theme-site-logo';
    public function get_title(): string { return 'Logotipo del sitio'; }
    public function get_icon(): string { return 'eicon-site-logo'; }
    public function get_keywords(): array { return ['logo','logotipo','marca','brand']; }
    protected function register_controls(): void { $this->image_controls('Vacío: la portada.'); }
    protected function default_link_to(): string { return 'site_url'; }
    protected function default_image_size(): string { return 'full'; }
    protected function link_options(): array { return ['none'=>'Ninguno','file'=>'Archivo multimedia','custom'=>'URL personalizada','site_url'=>'URL del sitio']; }
    protected function before_image_size(): void {
        $this->add_control('change_logo_cta', ['type'=>Controls_Manager::BUTTON,'label_block'=>true,'show_label'=>false,'button_type'=>'default elementor-button-center','text'=>'Cambiar el logotipo del sitio','separator'=>'none','event'=>'obx:site-logo:change']);
    }
    protected function image_note(): string { return 'Se usa si no hay logotipo en Apariencia → Personalizar → Identidad del sitio.'; }
    protected static function upgrade_settings(array $s): array {
        $s['link_to'] = ($s['link'] ?? 'yes') === 'yes' ? 'custom' : 'none';
        unset($s['link']);
        return self::upgrade_image($s);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $logo = (int) get_theme_mod('custom_logo');
        $url = $logo ? '' : (string) ($s['image']['url'] ?? '');
        if (!$logo && $url === '') {
            if (!$this->editing()) { return; }
            $url = Utils::get_placeholder_image_src();
        }
        $this->render_image($logo ?: (int) ($s['image']['id'] ?? 0), $url, get_bloginfo('name'), home_url('/'));
    }
}

final class Featured_Image extends Base {
    use Image_Controls;
    public const NAME = 'obx-featured-image';
    public const PRO = 'theme-post-featured-image';
    public function get_title(): string { return 'Imagen destacada'; }
    public function get_icon(): string { return 'eicon-featured-image'; }
    public function get_keywords(): array { return ['image','imagen','thumbnail','destacada']; }
    protected function register_controls(): void { $this->image_controls('Vacío: la entrada.'); }
    protected function image_note(): string { return 'Se usa si la entrada no tiene imagen destacada.'; }
    protected static function upgrade_settings(array $s): array {
        $link = $s['link'] ?? '';
        $s['link_to'] = $link === 'post' ? 'custom' : ($link === 'file' ? 'file' : 'none');
        unset($s['link']);
        if (($s['caption'] ?? '') === 'yes') { $s['caption_source'] = 'attachment'; }
        unset($s['caption']);
        return self::upgrade_image($s);
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        $id = $post ? (int) get_post_thumbnail_id($post) : 0;
        $url = '';
        if (!$id) {
            $id = (int) ($s['image']['id'] ?? 0);
            $url = (string) ($s['image']['url'] ?? '');
            if (!$id && $url === '') {
                if (!$this->editing()) { return; }
                $url = Utils::get_placeholder_image_src();
            }
        }
        $this->render_image($id, $url, $post ? get_the_title($post) : '', $post ? (string) get_permalink($post) : '');
    }
}

final class Post_Content extends Base {
    private static int $depth = 0;
    public const NAME = 'obx-post-content';
    public const PRO = 'theme-post-content';
    public const PRO_CATEGORY = 'theme-elements-single';
    public function get_title(): string { return 'Contenido de la entrada'; }
    public function get_icon(): string { return 'eicon-post-content'; }
    public function get_keywords(): array { return ['content','contenido','post','entrada']; }
    protected function register_controls(): void {
        $this->start_controls_section('section_style', ['label'=>'Estilo','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(true),'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]);
        $this->add_control('text_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'typography','selector'=>'{{WRAPPER}}']);
        $this->end_controls_section();
    }
    protected static function upgrade_settings(array $s): array {
        $s = self::rename($s, ['text_align'=>'align']);
        unset($s['text_hover']);
        return self::rename_prefix($s, 'text_typography_', 'typography_');
    }
    protected function render(): void {
        $post = Context::post();
        if (!$post || $post->post_type === 'elementor_library' || self::$depth > 0) { $this->placeholder('El contenido de la entrada aparecerá aquí.'); return; }
        self::$depth++;
        echo '<div class="obx-post-content">';
        $document = Elementor::$instance->documents->get($post->ID);
        if ($document && $document->is_built_with_elementor()) {
            echo Elementor::$instance->frontend->get_builder_content_for_display($post->ID); // Elementor output.
        } else {
            $previous = $GLOBALS['post'] ?? null;
            $GLOBALS['post'] = $post;
            setup_postdata($post);
            echo apply_filters('the_content', get_the_content(null, false, $post)); // Core content filters.
            wp_link_pages();
            $GLOBALS['post'] = $previous;
            if ($previous) { setup_postdata($previous); }
        }
        echo '</div>';
        self::$depth--;
    }
}

final class Post_Excerpt extends Base {
    public const NAME = 'obx-post-excerpt';
    public const PRO = 'theme-post-excerpt';
    public function get_title(): string { return 'Extracto de la entrada'; }
    public function get_icon(): string { return 'eicon-post-excerpt'; }
    public function get_keywords(): array { return ['excerpt','extracto','resumen']; }
    protected function register_controls(): void {
        $this->start_controls_section('section_content', ['label'=>'Extracto']);
        $this->add_control('excerpt', ['label'=>'Texto','type'=>Controls_Manager::TEXTAREA,'dynamic'=>['active'=>true],'description'=>'Vacío: el extracto de la entrada.']);
        $this->add_control('excerpt_length', ['label'=>'Palabras (si no hay extracto manual)','type'=>Controls_Manager::NUMBER,'min'=>5,'max'=>200,'default'=>55]);
        $this->end_controls_section();
        $this->start_controls_section('section_style', ['label'=>'Estilo','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'options'=>self::logical_align(true),'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]);
        $this->add_responsive_control('paragraph_spacing', ['label'=>'Separación entre párrafos','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em','rem','vh','custom'],'selectors'=>['{{WRAPPER}} p:not(:last-child)'=>'margin-bottom: {{SIZE}}{{UNIT}};']]);
        $this->add_control('title_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}}'=>'color: {{VALUE}};']]);
        $this->add_control('link_color', ['label'=>'Color de los enlaces','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} a'=>'color: {{VALUE}};']]);
        $this->add_control('link_hover_color', ['label'=>'Color de los enlaces al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} a:hover'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>'typography','selector'=>'{{WRAPPER}}']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name'=>'text_shadow','selector'=>'{{WRAPPER}}']);
        $this->end_controls_section();
    }
    protected static function upgrade_settings(array $s): array {
        $s = self::rename($s, ['length'=>'excerpt_length','text_align'=>'align','text_color'=>'title_color','text_hover'=>'link_hover_color']);
        return self::rename_prefix($s, 'text_typography_', 'typography_');
    }
    protected function render(): void {
        $text = trim((string) $this->get_settings_for_display('excerpt'));
        if ($text === '') {
            $post = Context::post();
            if (!$post) { $this->placeholder('El extracto aparecerá aquí.'); return; }
            $text = Archive_Posts::excerpt($post, (int) $this->get_settings_for_display('excerpt_length'));
        }
        echo '<div class="obx-post-excerpt"><p>' . esc_html($text) . '</p></div>';
    }
}
