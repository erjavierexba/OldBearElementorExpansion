<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) { exit; }

abstract class Base extends Widget_Base {
    /** Own name, kept so templates saved with earlier versions still render. Empty for Pro-only widgets. */
    public const NAME = '';
    /** Name of the equivalent Elementor Pro widget, used when Pro is not active. */
    public const PRO = '';
    /** Other Elementor Pro names rendered by this class (older widgets), never offered in the panel. */
    public const ALIASES = [];
    public const CATEGORY = 'obx-single';
    public const PRO_CATEGORY = 'theme-elements';

    private string $obx_name = '';

    /** @return class-string<Base>[] */
    public static function registry(): array {
        return [
            Post_Title::class, Post_Content::class, Featured_Image::class, Post_Excerpt::class, Post_Info::class,
            Archive_Title::class, Archive_Posts::class,
            Site_Logo::class, Site_Title::class, Nav_Menu::class, Search_Form::class, Search::class,
            Page_Title::class, Author_Box::class, Post_Navigation::class, Post_Comments::class, Breadcrumbs::class, Sitemap::class,
            Posts::class, Blockquote::class, Call_To_Action::class, Flip_Box::class, Price_Table::class, Price_List::class,
            Countdown::class, Share_Buttons::class, Animated_Headline::class, Table_Of_Contents::class, Progress_Tracker::class,
            Login::class, Code_Highlight::class, Template::class, Form::class,
        ];
    }

    /**
     * One class can be registered under its own name and under the Pro name. Elementor passes
     * the registration args to the type instance and the saved data (with widgetType) to the rest.
     */
    public function __construct($data = [], $args = null) {
        $name = is_array($data) && isset($data['widgetType']) ? $data['widgetType'] : (is_array($args) ? ($args['obx_name'] ?? '') : '');
        $this->obx_name = in_array($name, static::names(), true) ? $name : (static::NAME ?: static::PRO);
        // Templates saved with our own names before v0.4.0 used different setting keys.
        if (isset($data['settings']) && is_array($data['settings']) && static::wants_upgrade((string) $name, $data['settings'])) {
            $data['settings'] = static::upgrade_settings($data['settings']);
        }
        parent::__construct($data, $args);
    }

    /** @return string[] */
    public static function names(): array { return array_values(array_filter(array_merge([static::NAME, static::PRO], static::ALIASES))); }

    /** Whether saved data comes from an earlier version and needs upgrade_settings(). */
    public static function wants_upgrade(string $name, array $settings): bool { return static::NAME !== '' && $name === static::NAME; }

    /** Pro-format settings for data saved under $name (unchanged when no translation is needed). */
    public static function convert(string $name, array $settings): array {
        return static::wants_upgrade($name, $settings) ? static::upgrade_settings($settings) : $settings;
    }

    /** Translates settings saved by earlier versions (own keys) to Elementor Pro's keys. */
    protected static function upgrade_settings(array $settings): array { return $settings; }

    /** Renames keys that are not already set. */
    protected static function rename(array $settings, array $map): array {
        foreach ($map as $old => $new) {
            if (array_key_exists($old, $settings) && !array_key_exists($new, $settings)) { $settings[$new] = $settings[$old]; }
            if ($old !== $new) { unset($settings[$old]); }
        }
        return $settings;
    }

    public function get_name(): string { return $this->obx_name; }

    protected function as_pro(): bool { return static::PRO !== '' && $this->obx_name === static::PRO; }

    public function get_categories(): array { return [$this->as_pro() ? static::PRO_CATEGORY : static::CATEGORY]; }

    /** With Pro inactive only the Pro names are offered; the legacy names keep rendering existing templates. */
    public function show_in_panel(): bool {
        if (in_array($this->obx_name, static::ALIASES, true)) { return false; }
        return $this->as_pro() || static::PRO === '' || \OldBearExpansion\Plugin::pro_active();
    }

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

    /** Background, padding, border and radius of a box. */
    protected function box_style(string $selector, string $label, string $prefix, array $condition = []): void {
        $this->start_controls_section($prefix . '_box', ['label'=>$label,'tab'=>Controls_Manager::TAB_STYLE] + ($condition ? ['condition'=>$condition] : []));
        $this->add_control($prefix . '_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector=>'background-color: {{VALUE}};']]);
        $this->add_responsive_control($prefix . '_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em','%'],'selectors'=>['{{WRAPPER}} ' . $selector=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>$prefix . '_border','selector'=>'{{WRAPPER}} ' . $selector]);
        $this->add_control($prefix . '_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%'],'selectors'=>['{{WRAPPER}} ' . $selector=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    /** Typography, colours (normal and hover), padding and radius of a button. */
    protected function button_style(string $selector, string $label = 'Botón', string $prefix = 'button', array $condition = []): void {
        $this->start_controls_section($prefix . '_style', ['label'=>$label,'tab'=>Controls_Manager::TAB_STYLE] + ($condition ? ['condition'=>$condition] : []));
        $this->add_group_control(Group_Control_Typography::get_type(), ['name'=>$prefix . '_typography','selector'=>'{{WRAPPER}} ' . $selector]);
        $this->add_control($prefix . '_color', ['label'=>'Color del texto','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector=>'color: {{VALUE}};']]);
        $this->add_control($prefix . '_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector=>'background-color: {{VALUE}};']]);
        $this->add_control($prefix . '_hover_color', ['label'=>'Color del texto al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector . ':hover, {{WRAPPER}} ' . $selector . ':focus'=>'color: {{VALUE}};']]);
        $this->add_control($prefix . '_hover_bg', ['label'=>'Fondo al pasar el ratón','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} ' . $selector . ':hover, {{WRAPPER}} ' . $selector . ':focus'=>'background-color: {{VALUE}};']]);
        $this->add_responsive_control($prefix . '_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em'],'selectors'=>['{{WRAPPER}} ' . $selector=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control($prefix . '_radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%'],'selectors'=>['{{WRAPPER}} ' . $selector=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    /** Opening tag of a link built from a URL control, or a plain element when there is no URL. */
    protected function open_link(string $key, $link, string $class, string $fallback = 'span'): string {
        if (is_array($link) && !empty($link['url'])) {
            $this->add_link_attributes($key, $link);
            $this->add_render_attribute($key, 'class', $class);
            return '<a ' . $this->get_render_attribute_string($key) . '>';
        }
        return '<' . $fallback . ' class="' . esc_attr($class) . '">';
    }

    protected function close_link($link, string $fallback = 'span'): string {
        return is_array($link) && !empty($link['url']) ? '</a>' : '</' . $fallback . '>';
    }

    /** Renames every key that starts with $old (group controls such as typography). */
    protected static function rename_prefix(array $settings, string $old, string $new): array {
        foreach ($settings as $key => $value) {
            if (str_starts_with($key, $old)) {
                $target = $new . substr($key, strlen($old));
                if (!array_key_exists($target, $settings)) { $settings[$target] = $value; }
                unset($settings[$key]);
            }
        }
        return $settings;
    }

    /** CHOOSE options with logical directions, as stored by current Elementor versions. */
    protected static function logical_align(bool $justify = false): array {
        $options = ['start'=>['title'=>'Inicio','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'end'=>['title'=>'Final','icon'=>'eicon-text-align-right']];
        if ($justify) { $options['justify'] = ['title'=>'Justificado','icon'=>'eicon-text-align-justify']; }
        return $options;
    }

    /** Older data stores left/right; flexbox needs flex-start/flex-end. */
    protected static function flex_dictionary(): array {
        return ['left'=>'flex-start','start'=>'flex-start','right'=>'flex-end','end'=>'flex-end','center'=>'center','justify'=>'space-between','stretch'=>'stretch'];
    }

    protected static function align_options(): array {
        return ['left'=>['title'=>'Izquierda','icon'=>'eicon-text-align-left'],'center'=>['title'=>'Centro','icon'=>'eicon-text-align-center'],'right'=>['title'=>'Derecha','icon'=>'eicon-text-align-right']];
    }
}
