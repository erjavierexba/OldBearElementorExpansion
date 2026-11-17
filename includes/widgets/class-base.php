<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) { exit; }

abstract class Base extends Widget_Base {
    public const CATEGORY = 'obx-single';

    /** @return class-string<Base>[] */
    public static function registry(): array {
        return [
            Post_Title::class, Post_Content::class, Featured_Image::class, Post_Excerpt::class, Post_Info::class,
            Archive_Title::class, Archive_Posts::class,
            Site_Logo::class, Site_Title::class, Nav_Menu::class, Search_Form::class,
        ];
    }

    public function get_categories(): array { return [static::CATEGORY]; }
    public function get_style_depends(): array { return ['obx-frontend']; }
    protected function is_dynamic_content(): bool { return true; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function editing(): bool { return \OldBearExpansion\Context::is_editor(); }

    protected function placeholder(string $message): void {
        if ($this->editing()) { echo '<div class="obx-placeholder">' . esc_html($message) . '</div>'; }
    }

    protected function tag_control(string $default = 'h2'): void {
        $this->add_control('tag', ['label'=>'Etiqueta HTML','type'=>Controls_Manager::SELECT,'default'=>$default,
            'options'=>['h1'=>'H1','h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','h6'=>'H6','div'=>'div','span'=>'span','p'=>'p']]);
    }

    protected function tag(): string {
        $tag = (string) $this->get_settings_for_display('tag');
        return in_array($tag, ['h1','h2','h3','h4','h5','h6','div','span','p'], true) ? $tag : 'h2';
    }

    /** Alignment, colour, hover colour and typography for a text element. */
    protected function text_style(string $selector, string $label = 'Texto', string $prefix = 'text', bool $align = true): void {
        $this->start_controls_section($prefix . '_style', ['label'=>$label,'tab'=>Controls_Manager::TAB_STYLE]);
        if ($align) {
            $this->add_responsive_control($prefix . '_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,
                'options'=>['left'=>['title'=>'Izquierda','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-text-align-right'],'justify'=>['title'=>'Justificado','icon'=>'eicon-text-align-justify']],
                'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]);
        }
        $this->add_control($prefix . '_color', ['label'=>'Color','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector . ', {{WRAPPER}} ' . $selector . ' a'=>'color: {{VALUE}};']]);
        $this->add_control($prefix . '_hover', ['label'=>'Color al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector . ' a:hover'=>'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$prefix . '_typography','selector'=>'{{WRAPPER}} ' . $selector]);
        $this->end_controls_section();
    }
}
