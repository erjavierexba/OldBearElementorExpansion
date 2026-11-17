<?php
namespace OldBearExpansion\Dynamic;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/**
 * Dynamic tags with Elementor Pro's names, groups and setting keys, so «__dynamic__» values saved
 * with Pro (e.g. [elementor-tag name="post-title" settings="…"]) resolve here.
 */
final class Tags {
    public const GROUPS = ['post'=>'Entrada','archive'=>'Archivo','site'=>'Sitio','media'=>'Medios','action'=>'Acciones','author'=>'Autor','comments'=>'Comentarios','woocommerce'=>'WooCommerce'];

    public static function boot(): void {
        add_action('elementor/dynamic_tags/register', [self::class, 'register']);
    }

    public static function register($manager): void {
        foreach (self::GROUPS as $name => $title) { $manager->register_group($name, ['title'=>$title]); }
        foreach (self::classes() as $class) {
            if ($class::available()) { $manager->register(new $class()); }
        }
    }

    /** @return class-string[] */
    public static function classes(): array {
        return [
            Post_Title::class, Post_Excerpt::class, Post_Date::class, Post_Time::class, Post_Id::class, Post_Terms::class, Post_Custom_Field::class, Post_Url::class, Post_Featured_Image::class, Post_Gallery::class,
            Page_Title::class, Archive_Title::class, Archive_Description::class, Archive_Url::class,
            Site_Title::class, Site_Tagline::class, Site_Url::class, Site_Logo::class, Current_Date_Time::class, Shortcode::class, Request_Parameter::class, User_Info::class, User_Profile_Picture::class, Internal_Url::class,
            Author_Name::class, Author_Info::class, Author_Meta::class, Author_Url::class, Author_Profile_Picture::class,
            Comments_Number::class, Comments_Url::class,
            Lightbox::class, Popup::class, Contact_Url::class,
            Product_Title::class, Product_Price::class, Product_Sku::class, Product_Short_Description::class, Product_Stock::class, Product_Rating::class, Product_Image::class,
        ];
    }

    public static function post(): ?\WP_Post { return Context::post(); }

    public static function date_format(string $format, string $custom): string {
        return match ($format) { '', 'default' => (string) get_option('date_format'), 'custom' => $custom, default => $format };
    }
}

/** Shared bits of our text tags. */
abstract class Text_Tag extends Tag {
    public const CATEGORIES = [TagsModule::TEXT_CATEGORY];
    public const GROUP = 'post';
    public static function available(): bool { return true; }
    public function get_group() { return static::GROUP; }
    public function get_categories() { return static::CATEGORIES; }
    public function render() { echo wp_kses_post($this->value()); }
    abstract protected function value(): string;
}

/** URL and image tags return data instead of printing. */
abstract class Value_Tag extends Data_Tag {
    public const CATEGORIES = [TagsModule::URL_CATEGORY];
    public const GROUP = 'post';
    public static function available(): bool { return true; }
    public function get_group() { return static::GROUP; }
    public function get_categories() { return static::CATEGORIES; }
    protected static function image(int $id, string $url = ''): array {
        return $id ? ['id'=>$id,'url'=>(string) wp_get_attachment_image_url($id, 'full')] : ['id'=>'','url'=>$url];
    }
}

final class Post_Title extends Text_Tag {
    public function get_name() { return 'post-title'; }
    public function get_title() { return 'Título de la entrada'; }
    protected function value(): string { $p = Tags::post(); return $p ? wp_strip_all_tags(get_the_title($p)) : ''; }
}

final class Post_Excerpt extends Text_Tag {
    public function get_name() { return 'post-excerpt'; }
    public function get_title() { return 'Extracto de la entrada'; }
    protected function register_controls() {
        $this->add_control('max_length', ['label'=>'Longitud máxima (palabras)','type'=>Controls_Manager::NUMBER]);
        $this->add_control('apply_to_post_content', ['label'=>'Usar el contenido si no hay extracto','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
    }
    protected function value(): string {
        $p = Tags::post();
        if (!$p || post_password_required($p)) { return ''; }
        $text = $p->post_excerpt;
        if ($text === '' && $this->get_settings('apply_to_post_content') === 'yes') { $text = wp_strip_all_tags(strip_shortcodes(excerpt_remove_blocks($p->post_content))); }
        $max = (int) $this->get_settings('max_length');
        return $max > 0 ? wp_trim_words($text, $max, '…') : $text;
    }
}

final class Post_Date extends Text_Tag {
    public function get_name() { return 'post-date'; }
    public function get_title() { return 'Fecha de la entrada'; }
    protected function register_controls() {
        $this->add_control('type', ['label'=>'Fecha','type'=>Controls_Manager::SELECT,'default'=>'post_date_gmt','options'=>['post_date_gmt'=>'Publicación','post_modified_gmt'=>'Última modificación']]);
        $this->add_control('format', ['label'=>'Formato','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','F j, Y'=>date_i18n('F j, Y'),'Y-m-d'=>date_i18n('Y-m-d'),'m/d/Y'=>date_i18n('m/d/Y'),'d/m/Y'=>date_i18n('d/m/Y'),'human'=>'Hace… (relativo)','custom'=>'Personalizado']]);
        $this->add_control('custom_format', ['label'=>'Formato personalizado','type'=>Controls_Manager::TEXT,'condition'=>['format'=>'custom']]);
    }
    protected function value(): string {
        $p = Tags::post();
        if (!$p) { return ''; }
        $modified = $this->get_settings('type') === 'post_modified_gmt';
        if ($this->get_settings('format') === 'human') {
            return sprintf('Hace %s', human_time_diff((int) get_post_time('U', true, $p), time()));
        }
        $format = Tags::date_format((string) $this->get_settings('format'), (string) $this->get_settings('custom_format'));
        return (string) ($modified ? get_the_modified_date($format, $p) : get_the_date($format, $p));
    }
}

final class Post_Time extends Text_Tag {
    public function get_name() { return 'post-time'; }
    public function get_title() { return 'Hora de la entrada'; }
    protected function register_controls() {
        $this->add_control('type', ['label'=>'Hora','type'=>Controls_Manager::SELECT,'default'=>'post_date_gmt','options'=>['post_date_gmt'=>'Publicación','post_modified_gmt'=>'Última modificación']]);
        $this->add_control('format', ['label'=>'Formato','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto','g:i a'=>date_i18n('g:i a'),'g:i A'=>date_i18n('g:i A'),'H:i'=>date_i18n('H:i'),'custom'=>'Personalizado']]);
        $this->add_control('custom_format', ['label'=>'Formato personalizado','type'=>Controls_Manager::TEXT,'condition'=>['format'=>'custom']]);
    }
    protected function value(): string {
        $p = Tags::post();
        if (!$p) { return ''; }
        $format = match ((string) $this->get_settings('format')) { '', 'default' => (string) get_option('time_format'), 'custom' => (string) $this->get_settings('custom_format'), default => (string) $this->get_settings('format') };
        return (string) ($this->get_settings('type') === 'post_modified_gmt' ? get_the_modified_time($format, $p) : get_the_time($format, $p));
    }
}

final class Post_Id extends Text_Tag {
    public const CATEGORIES = [TagsModule::TEXT_CATEGORY, TagsModule::NUMBER_CATEGORY];
    public function get_name() { return 'post-id'; }
    public function get_title() { return 'ID de la entrada'; }
    protected function value(): string { $p = Tags::post(); return $p ? (string) $p->ID : ''; }
}

final class Post_Terms extends Text_Tag {
    public function get_name() { return 'post-terms'; }
    public function get_title() { return 'Términos de la entrada'; }
    protected function register_controls() {
        $options = [];
        foreach (get_taxonomies(['show_in_nav_menus'=>true], 'objects') as $taxonomy) { $options[$taxonomy->name] = $taxonomy->label; }
        $this->add_control('taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT,'options'=>$options,'default'=>'post_tag']);
        $this->add_control('separator', ['label'=>'Separador','type'=>Controls_Manager::TEXT,'default'=>', ']);
        $this->add_control('link', ['label'=>'Enlazar','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
    }
    protected function value(): string {
        $p = Tags::post();
        $taxonomy = (string) $this->get_settings('taxonomy') ?: 'post_tag';
        if (!$p || !taxonomy_exists($taxonomy)) { return ''; }
        $terms = get_the_terms($p, $taxonomy);
        if (!is_array($terms)) { return ''; }
        $link = $this->get_settings('link') === 'yes';
        $items = array_map(static fn(\WP_Term $t) => $link ? '<a href="' . esc_url((string) get_term_link($t)) . '">' . esc_html($t->name) . '</a>' : esc_html($t->name), $terms);
        return implode(esc_html((string) $this->get_settings('separator')), $items);
    }
}

final class Post_Custom_Field extends Text_Tag {
    public const CATEGORIES = [TagsModule::TEXT_CATEGORY, TagsModule::URL_CATEGORY, TagsModule::POST_META_CATEGORY, TagsModule::COLOR_CATEGORY, TagsModule::NUMBER_CATEGORY];
    public function get_name() { return 'post-custom-field'; }
    public function get_title() { return 'Campo personalizado'; }
    public function is_settings_required() { return true; }
    protected function register_controls() {
        $this->add_control('key', ['label'=>'Campo','type'=>Controls_Manager::SELECT,'options'=>[''=>'Elige un campo'] + self::keys(),'default'=>'']);
        $this->add_control('custom_key', ['label'=>'Clave personalizada','type'=>Controls_Manager::TEXT,'condition'=>['key'=>'']]);
    }
    private static function keys(): array {
        global $wpdb;
        $keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE '\\_%' ORDER BY meta_key LIMIT 200");
        return array_combine($keys, $keys) ?: [];
    }
    protected function value(): string {
        $p = Tags::post();
        $key = (string) ($this->get_settings('key') ?: $this->get_settings('custom_key'));
        if (!$p || $key === '' || is_protected_meta($key, 'post')) { return ''; }
        $value = get_post_meta($p->ID, $key, true);
        return is_scalar($value) ? (string) $value : '';
    }
}

final class Post_Url extends Value_Tag {
    public function get_name() { return 'post-url'; }
    public function get_title() { return 'Dirección de la entrada'; }
    public function get_value(array $options = []) { $p = Tags::post(); return $p ? (string) get_permalink($p) : ''; }
}

final class Post_Featured_Image extends Value_Tag {
    public const CATEGORIES = [TagsModule::IMAGE_CATEGORY, TagsModule::MEDIA_CATEGORY];
    public function get_name() { return 'post-featured-image'; }
    public function get_title() { return 'Imagen destacada'; }
    protected function register_controls() { $this->add_control('fallback', ['label'=>'Alternativa','type'=>Controls_Manager::MEDIA]); }
    public function get_value(array $options = []) {
        $p = Tags::post();
        $id = $p ? (int) get_post_thumbnail_id($p) : 0;
        if ($id) { return Value_Tag::image($id); }
        $fallback = (array) $this->get_settings('fallback');
        return ['id'=>$fallback['id'] ?? '','url'=>$fallback['url'] ?? ''];
    }
}

final class Post_Gallery extends Value_Tag {
    public const CATEGORIES = [TagsModule::GALLERY_CATEGORY];
    public function get_name() { return 'post-gallery'; }
    public function get_title() { return 'Imágenes adjuntas'; }
    public function get_value(array $options = []) {
        $p = Tags::post();
        if (!$p) { return []; }
        $ids = get_posts(['post_parent'=>$p->ID,'post_type'=>'attachment','post_mime_type'=>'image','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'menu_order','order'=>'ASC']);
        return array_map(static fn($id) => ['id'=>(int) $id], $ids);
    }
}

final class Page_Title extends Text_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'page-title'; }
    public function get_title() { return 'Título de la página'; }
    protected function register_controls() {
        $this->add_control('include_context', ['label'=>'Incluir contexto','type'=>Controls_Manager::SWITCHER]);
        $this->add_control('show_home_title', ['label'=>'Título en la portada','type'=>Controls_Manager::SWITCHER]);
    }
    protected function value(): string {
        if (is_home() && $this->get_settings('show_home_title') !== 'yes') { return ''; }
        if (Context::template() || is_singular()) { $p = Tags::post(); return $p ? wp_strip_all_tags(get_the_title($p)) : ''; }
        if (is_search()) { return sprintf('Resultados de búsqueda: %s', get_search_query()); }
        if (is_404()) { return 'Página no encontrada'; }
        $title = $this->get_settings('include_context') === 'yes' ? get_the_archive_title() : (is_archive() ? single_term_title('', false) ?: get_the_archive_title() : get_bloginfo('name'));
        return wp_strip_all_tags((string) $title);
    }
}

final class Archive_Title extends Text_Tag {
    public const GROUP = 'archive';
    public function get_name() { return 'archive-title'; }
    public function get_title() { return 'Título del archivo'; }
    protected function register_controls() { $this->add_control('include_context', ['label'=>'Incluir contexto','type'=>Controls_Manager::SWITCHER,'default'=>'yes']); }
    protected function value(): string {
        if (Context::template() || !is_archive() || $this->get_settings('include_context') === 'yes') { return Context::archive_title(); }
        return wp_strip_all_tags((string) (single_term_title('', false) ?: post_type_archive_title('', false) ?: get_the_archive_title()));
    }
}

final class Archive_Description extends Text_Tag {
    public const GROUP = 'archive';
    public function get_name() { return 'archive-description'; }
    public function get_title() { return 'Descripción del archivo'; }
    protected function value(): string { return (string) get_the_archive_description(); }
}

final class Archive_Url extends Value_Tag {
    public const GROUP = 'archive';
    public function get_name() { return 'archive-url'; }
    public function get_title() { return 'Dirección del archivo'; }
    public function get_value(array $options = []) {
        if (is_category() || is_tag() || is_tax()) { return (string) get_term_link(get_queried_object()); }
        if (is_author()) { return get_author_posts_url((int) get_queried_object_id()); }
        if (is_post_type_archive()) { return (string) get_post_type_archive_link((string) get_query_var('post_type')); }
        return (string) home_url(add_query_arg([]));
    }
}

final class Site_Title extends Text_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'site-title'; }
    public function get_title() { return 'Título del sitio'; }
    protected function value(): string { return (string) get_bloginfo('name'); }
}

final class Site_Tagline extends Text_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'site-tagline'; }
    public function get_title() { return 'Descripción corta del sitio'; }
    protected function value(): string { return (string) get_bloginfo('description'); }
}

final class Site_Url extends Value_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'site-url'; }
    public function get_title() { return 'Dirección del sitio'; }
    public function get_value(array $options = []) { return home_url('/'); }
}

final class Site_Logo extends Value_Tag {
    public const GROUP = 'site';
    public const CATEGORIES = [TagsModule::IMAGE_CATEGORY];
    public function get_name() { return 'site-logo'; }
    public function get_title() { return 'Logotipo del sitio'; }
    public function get_value(array $options = []) {
        $id = (int) get_theme_mod('custom_logo');
        return $id ? Value_Tag::image($id) : ['id'=>'','url'=>\Elementor\Utils::get_placeholder_image_src()];
    }
}

final class Current_Date_Time extends Text_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'current-date-time'; }
    public function get_title() { return 'Fecha y hora actuales'; }
    protected function register_controls() {
        $this->add_control('date_format', ['label'=>'Formato de fecha','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto',''=>'Ninguno','F j, Y'=>date_i18n('F j, Y'),'Y-m-d'=>date_i18n('Y-m-d'),'m/d/Y'=>date_i18n('m/d/Y'),'d/m/Y'=>date_i18n('d/m/Y'),'custom'=>'Personalizado']]);
        $this->add_control('time_format', ['label'=>'Formato de hora','type'=>Controls_Manager::SELECT,'default'=>'default','options'=>['default'=>'Por defecto',''=>'Ninguno','g:i a'=>date_i18n('g:i a'),'g:i A'=>date_i18n('g:i A'),'H:i'=>date_i18n('H:i')],'condition'=>['date_format!'=>'custom']]);
        $this->add_control('custom_format', ['label'=>'Formato personalizado','type'=>Controls_Manager::TEXT,'default'=>(string) get_option('date_format') . ' ' . (string) get_option('time_format'),'condition'=>['date_format'=>'custom']]);
    }
    protected function value(): string {
        $date = (string) $this->get_settings('date_format');
        if ($date === 'custom') { return wp_date((string) $this->get_settings('custom_format')); }
        $time = (string) $this->get_settings('time_format');
        $parts = [];
        if ($date !== '') { $parts[] = $date === 'default' ? (string) get_option('date_format') : $date; }
        if ($time !== '') { $parts[] = $time === 'default' ? (string) get_option('time_format') : $time; }
        return $parts ? wp_date(implode(' ', $parts)) : '';
    }
}

final class Shortcode extends Text_Tag {
    public const GROUP = 'site';
    public const CATEGORIES = [TagsModule::TEXT_CATEGORY, TagsModule::URL_CATEGORY, TagsModule::POST_META_CATEGORY, TagsModule::GALLERY_CATEGORY, TagsModule::IMAGE_CATEGORY, TagsModule::MEDIA_CATEGORY, TagsModule::NUMBER_CATEGORY, TagsModule::COLOR_CATEGORY];
    public function get_name() { return 'shortcode'; }
    public function get_title() { return 'Shortcode'; }
    protected function register_controls() { $this->add_control('shortcode', ['label'=>'Shortcode','type'=>Controls_Manager::TEXTAREA]); }
    protected function value(): string { return do_shortcode(shortcode_unautop((string) $this->get_settings('shortcode'))); }
    public function render() { echo $this->value(); } // Shortcode output is trusted like the core shortcode widget.
}

final class Request_Parameter extends Text_Tag {
    public const GROUP = 'site';
    public const CATEGORIES = [TagsModule::TEXT_CATEGORY, TagsModule::POST_META_CATEGORY];
    public function get_name() { return 'request-parameter'; }
    public function get_title() { return 'Parámetro de la petición'; }
    protected function register_controls() {
        $this->add_control('request_type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'get','options'=>['get'=>'GET','post'=>'POST','query_var'=>'Variable de consulta']]);
        $this->add_control('param_name', ['label'=>'Nombre del parámetro','type'=>Controls_Manager::TEXT]);
    }
    protected function value(): string {
        $name = (string) $this->get_settings('param_name');
        if ($name === '') { return ''; }
        $value = match ((string) $this->get_settings('request_type')) { 'post' => $_POST[$name] ?? '', 'query_var' => get_query_var($name), default => $_GET[$name] ?? '' };
        return is_scalar($value) ? esc_html(sanitize_text_field(wp_unslash((string) $value))) : '';
    }
}

final class User_Info extends Text_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'user-info'; }
    public function get_title() { return 'Usuario actual'; }
    protected function register_controls() {
        $this->add_control('type', ['label'=>'Dato','type'=>Controls_Manager::SELECT,'default'=>'display_name','options'=>['id'=>'ID','display_name'=>'Nombre público','login'=>'Usuario','first_name'=>'Nombre','last_name'=>'Apellidos','description'=>'Biografía','email'=>'Correo','website'=>'Web','meta'=>'Campo de usuario']]);
        $this->add_control('meta_key', ['label'=>'Campo','type'=>Controls_Manager::TEXT,'condition'=>['type'=>'meta']]);
    }
    protected function value(): string {
        $user = wp_get_current_user();
        if (!$user->exists()) { return ''; }
        return esc_html((string) match ((string) $this->get_settings('type')) {
            'id' => $user->ID, 'login' => $user->user_login, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'description' => $user->description,
            'email' => $user->user_email, 'website' => $user->user_url, 'meta' => is_scalar($m = get_user_meta($user->ID, (string) $this->get_settings('meta_key'), true)) ? $m : '',
            default => $user->display_name,
        });
    }
}

final class User_Profile_Picture extends Value_Tag {
    public const GROUP = 'site';
    public const CATEGORIES = [TagsModule::IMAGE_CATEGORY];
    public function get_name() { return 'user-profile-picture'; }
    public function get_title() { return 'Foto del usuario actual'; }
    public function get_value(array $options = []) { return ['id'=>'','url'=>(string) get_avatar_url(get_current_user_id(), ['size'=>300])]; }
}

final class Internal_Url extends Value_Tag {
    public const GROUP = 'site';
    public function get_name() { return 'internal-url'; }
    public function get_title() { return 'Enlace interno'; }
    protected function register_controls() {
        $this->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'post','options'=>['post'=>'Contenido','taxonomy'=>'Término','attachment'=>'Archivo multimedia','author'=>'Autor']]);
        foreach (['post_id'=>'post','taxonomy_id'=>'taxonomy','attachment_id'=>'attachment','author_id'=>'author'] as $key => $type) { $this->add_control($key, ['label'=>'ID','type'=>Controls_Manager::NUMBER,'condition'=>['type'=>$type]]); }
    }
    public function get_value(array $options = []) {
        $s = $this->get_settings();
        return (string) match ((string) ($s['type'] ?? 'post')) {
            'taxonomy' => is_wp_error($l = get_term_link((int) ($s['taxonomy_id'] ?? 0))) ? '' : $l,
            'attachment' => get_attachment_link((int) ($s['attachment_id'] ?? 0)),
            'author' => get_author_posts_url((int) ($s['author_id'] ?? 0)),
            default => get_permalink((int) ($s['post_id'] ?? 0)) ?: '',
        };
    }
}

abstract class Author_Tag extends Text_Tag {
    public const GROUP = 'author';
    protected function author(): int {
        if (is_author() && !Context::template()) { return (int) get_queried_object_id(); }
        $p = Tags::post();
        return $p ? (int) $p->post_author : 0;
    }
}

final class Author_Name extends Author_Tag {
    public function get_name() { return 'author-name'; }
    public function get_title() { return 'Nombre del autor'; }
    protected function value(): string { $id = $this->author(); return $id ? esc_html((string) get_the_author_meta('display_name', $id)) : ''; }
}

final class Author_Info extends Author_Tag {
    public function get_name() { return 'author-info'; }
    public function get_title() { return 'Datos del autor'; }
    protected function register_controls() { $this->add_control('key', ['label'=>'Dato','type'=>Controls_Manager::SELECT,'default'=>'description','options'=>['description'=>'Biografía','email'=>'Correo','url'=>'Web']]); }
    protected function value(): string {
        $id = $this->author();
        $key = in_array($this->get_settings('key'), ['description','email','url'], true) ? (string) $this->get_settings('key') : 'description';
        return $id ? wp_kses_post((string) get_the_author_meta($key === 'email' ? 'user_email' : ($key === 'url' ? 'user_url' : 'description'), $id)) : '';
    }
}

final class Author_Meta extends Author_Tag {
    public function get_name() { return 'author-meta'; }
    public function get_title() { return 'Campo del autor'; }
    protected function register_controls() { $this->add_control('key', ['label'=>'Campo','type'=>Controls_Manager::TEXT]); }
    protected function value(): string {
        $id = $this->author();
        $key = (string) $this->get_settings('key');
        if (!$id || $key === '' || in_array($key, ['user_pass','user_activation_key','session_tokens'], true) || is_protected_meta($key, 'user')) { return ''; }
        $value = get_the_author_meta($key, $id);
        return is_scalar($value) ? esc_html((string) $value) : '';
    }
}

final class Author_Url extends Value_Tag {
    public const GROUP = 'author';
    public function get_name() { return 'author-url'; }
    public function get_title() { return 'Enlace del autor'; }
    protected function register_controls() { $this->add_control('url', ['label'=>'Destino','type'=>Controls_Manager::SELECT,'default'=>'archive','options'=>['archive'=>'Entradas del autor','website'=>'Web del autor']]); }
    public function get_value(array $options = []) {
        $p = Tags::post();
        $id = is_author() && !Context::template() ? (int) get_queried_object_id() : ($p ? (int) $p->post_author : 0);
        if (!$id) { return ''; }
        return $this->get_settings('url') === 'website' ? (string) get_the_author_meta('url', $id) : get_author_posts_url($id);
    }
}

final class Author_Profile_Picture extends Value_Tag {
    public const GROUP = 'author';
    public const CATEGORIES = [TagsModule::IMAGE_CATEGORY];
    public function get_name() { return 'author-profile-picture'; }
    public function get_title() { return 'Foto del autor'; }
    public function get_value(array $options = []) {
        $p = Tags::post();
        $id = is_author() && !Context::template() ? (int) get_queried_object_id() : ($p ? (int) $p->post_author : 0);
        return ['id'=>'','url'=>$id ? (string) get_avatar_url($id, ['size'=>300]) : ''];
    }
}

final class Comments_Number extends Text_Tag {
    public const GROUP = 'comments';
    public function get_name() { return 'comments-number'; }
    public function get_title() { return 'Número de comentarios'; }
    protected function register_controls() {
        $this->add_control('format_no_comments', ['label'=>'Sin comentarios','type'=>Controls_Manager::TEXT,'default'=>'Sin comentarios']);
        $this->add_control('format_one_comments', ['label'=>'Un comentario','type'=>Controls_Manager::TEXT,'default'=>'Un comentario']);
        $this->add_control('format_many_comments', ['label'=>'Varios comentarios','type'=>Controls_Manager::TEXT,'default'=>'{number} comentarios']);
        $this->add_control('link_to', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Ninguno','comments_link'=>'A los comentarios']]);
    }
    protected function value(): string {
        $p = Tags::post();
        if (!$p) { return ''; }
        $n = (int) get_comments_number($p);
        $text = $n === 0 ? $this->get_settings('format_no_comments') : ($n === 1 ? $this->get_settings('format_one_comments') : str_replace('{number}', number_format_i18n($n), (string) $this->get_settings('format_many_comments')));
        $text = esc_html((string) $text);
        return $this->get_settings('link_to') === 'comments_link' ? '<a href="' . esc_url((string) get_comments_link($p)) . '">' . $text . '</a>' : $text;
    }
}

final class Comments_Url extends Value_Tag {
    public const GROUP = 'comments';
    public function get_name() { return 'comments-url'; }
    public function get_title() { return 'Enlace a los comentarios'; }
    public function get_value(array $options = []) { $p = Tags::post(); return $p ? (string) get_comments_link($p) : ''; }
}

final class Lightbox extends Value_Tag {
    public const GROUP = 'action';
    public function get_name() { return 'lightbox'; }
    public function get_title() { return 'Caja de luz'; }
    protected function register_controls() {
        $this->add_control('type', ['label'=>'Tipo','type'=>Controls_Manager::CHOOSE,'options'=>['video'=>['title'=>'Vídeo','icon'=>'eicon-video-camera'],'image'=>['title'=>'Imagen','icon'=>'eicon-image-bold']]]);
        $this->add_control('image', ['label'=>'Imagen','type'=>Controls_Manager::MEDIA,'condition'=>['type'=>'image']]);
        $this->add_control('video_url', ['label'=>'Dirección del vídeo','type'=>Controls_Manager::TEXT,'condition'=>['type'=>'video']]);
    }
    public function get_value(array $options = []) {
        $s = $this->get_settings();
        $type = (string) ($s['type'] ?? '');
        if ($type === 'image' && !empty($s['image']['url'])) {
            return \Elementor\Plugin::$instance->frontend->create_action_hash('lightbox', ['type'=>'image','id'=>$s['image']['id'] ?? '','url'=>$s['image']['url']]);
        }
        if ($type === 'video' && !empty($s['video_url'])) {
            $embed = \Elementor\Embed::get_embed_url((string) $s['video_url'], [], ['lazy_load'=>false]);
            return \Elementor\Plugin::$instance->frontend->create_action_hash('lightbox', ['type'=>'video','videoType'=>'youtube','url'=>$embed ?: (string) $s['video_url']]);
        }
        return '';
    }
}

final class Popup extends Value_Tag {
    public const GROUP = 'action';
    public function get_name() { return 'popup'; }
    public function get_title() { return 'Ventana emergente'; }
    protected function register_controls() {
        $this->add_control('action', ['label'=>'Acción','type'=>Controls_Manager::SELECT,'default'=>'open','options'=>['open'=>'Abrir','close'=>'Cerrar','toggle'=>'Alternar']]);
        $options = [''=>'Elige una'];
        foreach (get_posts(['post_type'=>'elementor_library','posts_per_page'=>100,'meta_key'=>'_elementor_template_type','meta_value'=>'popup']) as $p) { $options[(string) $p->ID] = $p->post_title; }
        $this->add_control('popup', ['label'=>'Ventana emergente','type'=>Controls_Manager::SELECT,'options'=>$options,'condition'=>['action!'=>'close']]);
        $this->add_control('do_not_show_again', ['label'=>'No volver a mostrar','type'=>Controls_Manager::SWITCHER,'condition'=>['action'=>'close']]);
    }
    public function get_value(array $options = []) {
        $s = $this->get_settings();
        $action = (string) ($s['action'] ?? 'open');
        if ($action === 'close') { return \Elementor\Plugin::$instance->frontend->create_action_hash('popup:close', ['do_not_show_again'=>($s['do_not_show_again'] ?? '') === 'yes' ? 'yes' : '']); }
        $id = (string) ($s['popup'] ?? '');
        if ($id === '') { return ''; }
        \OldBearExpansion\Popups\Popups::enqueue((int) $id);
        return \Elementor\Plugin::$instance->frontend->create_action_hash('popup:open', ['id'=>$id,'toggle'=>$action === 'toggle']);
    }
}

final class Contact_Url extends Value_Tag {
    public const GROUP = 'action';
    public function get_name() { return 'contact-url'; }
    public function get_title() { return 'Enlace de contacto'; }
    protected function register_controls() {
        $this->add_control('link_type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Elige','email'=>'Correo','tel'=>'Teléfono','sms'=>'SMS','whatsapp'=>'WhatsApp','skype'=>'Skype','messenger'=>'Messenger','viber'=>'Viber','waze'=>'Waze','google_calendar'=>'Google Calendar']]);
        $this->add_control('mail_to', ['label'=>'Correo','type'=>Controls_Manager::TEXT,'condition'=>['link_type'=>'email']]);
        $this->add_control('mail_subject', ['label'=>'Asunto','type'=>Controls_Manager::TEXT,'condition'=>['link_type'=>'email']]);
        $this->add_control('mail_body', ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'condition'=>['link_type'=>'email']]);
        $this->add_control('tel_number', ['label'=>'Número','type'=>Controls_Manager::TEXT,'condition'=>['link_type'=>['tel','sms','whatsapp','viber']]]);
        $this->add_control('username', ['label'=>'Usuario','type'=>Controls_Manager::TEXT,'condition'=>['link_type'=>['skype','messenger']]]);
        $this->add_control('location', ['label'=>'Dirección','type'=>Controls_Manager::TEXT,'condition'=>['link_type'=>'waze']]);
        foreach (['event_title'=>'Título','event_description'=>'Descripción','event_location'=>'Lugar','event_start_date'=>'Inicio','event_end_date'=>'Fin'] as $k => $l) {
            $this->add_control($k, ['label'=>$l,'type'=>str_contains($k, 'date') ? Controls_Manager::DATE_TIME : Controls_Manager::TEXT,'condition'=>['link_type'=>'google_calendar']]);
        }
    }
    public function get_value(array $options = []) {
        $s = $this->get_settings();
        $tel = preg_replace('/[^0-9+]/', '', (string) ($s['tel_number'] ?? ''));
        $date = static fn($v) => $v ? gmdate('Ymd\THis\Z', (int) strtotime((string) $v)) : '';
        return match ((string) ($s['link_type'] ?? '')) {
            'email' => 'mailto:' . sanitize_email((string) ($s['mail_to'] ?? '')) . '?' . http_build_query(array_filter(['subject'=>$s['mail_subject'] ?? '','body'=>$s['mail_body'] ?? '']), '', '&', PHP_QUERY_RFC3986),
            'tel' => 'tel:' . $tel,
            'sms' => 'sms:' . $tel,
            'whatsapp' => 'https://api.whatsapp.com/send?phone=' . ltrim($tel, '+'),
            'viber' => 'viber://contact?number=' . rawurlencode($tel),
            'skype' => 'skype:' . rawurlencode((string) ($s['username'] ?? '')) . '?chat',
            'messenger' => 'https://m.me/' . rawurlencode((string) ($s['username'] ?? '')),
            'waze' => 'https://waze.com/ul?q=' . rawurlencode((string) ($s['location'] ?? '')),
            'google_calendar' => 'https://calendar.google.com/calendar/render?' . http_build_query(['action'=>'TEMPLATE','text'=>$s['event_title'] ?? '','details'=>$s['event_description'] ?? '','location'=>$s['event_location'] ?? '','dates'=>$date($s['event_start_date'] ?? '') . '/' . $date($s['event_end_date'] ?? '')]),
            default => '',
        };
    }
}

/* WooCommerce product tags. */
abstract class Product_Text_Tag extends Text_Tag {
    public const GROUP = 'woocommerce';
    public static function available(): bool { return function_exists('wc_get_product'); }
    protected function product(): ?\WC_Product { $p = Tags::post(); $product = $p ? wc_get_product($p->ID) : null; return $product ?: null; }
}

final class Product_Title extends Product_Text_Tag {
    public function get_name() { return 'woocommerce-product-title-tag'; }
    public function get_title() { return 'Título del producto'; }
    protected function value(): string { $p = $this->product(); return $p ? esc_html($p->get_name()) : ''; }
}

final class Product_Price extends Product_Text_Tag {
    public function get_name() { return 'woocommerce-product-price-tag'; }
    public function get_title() { return 'Precio del producto'; }
    protected function register_controls() { $this->add_control('format', ['label'=>'Formato','type'=>Controls_Manager::SELECT,'default'=>'both','options'=>['both'=>'Normal y rebajado','original'=>'Normal','sale'=>'Rebajado']]); }
    protected function value(): string {
        $p = $this->product();
        if (!$p) { return ''; }
        return match ((string) $this->get_settings('format')) {
            'original' => wc_price((float) $p->get_regular_price()),
            'sale' => $p->is_on_sale() ? wc_price((float) $p->get_sale_price()) : '',
            default => (string) $p->get_price_html(),
        };
    }
}

final class Product_Sku extends Product_Text_Tag {
    public function get_name() { return 'woocommerce-product-sku-tag'; }
    public function get_title() { return 'Referencia (SKU)'; }
    protected function value(): string { $p = $this->product(); return $p ? esc_html((string) $p->get_sku()) : ''; }
}

final class Product_Short_Description extends Product_Text_Tag {
    public function get_name() { return 'woocommerce-product-short-description-tag'; }
    public function get_title() { return 'Descripción corta del producto'; }
    protected function value(): string { $p = $this->product(); return $p ? wp_kses_post((string) $p->get_short_description()) : ''; }
}

final class Product_Stock extends Product_Text_Tag {
    public function get_name() { return 'woocommerce-product-stock-tag'; }
    public function get_title() { return 'Existencias del producto'; }
    protected function value(): string { $p = $this->product(); return $p ? wp_strip_all_tags(wc_get_stock_html($p)) : ''; }
}

final class Product_Rating extends Product_Text_Tag {
    public const CATEGORIES = [TagsModule::TEXT_CATEGORY, TagsModule::NUMBER_CATEGORY];
    public function get_name() { return 'woocommerce-product-rating-tag'; }
    public function get_title() { return 'Valoración del producto'; }
    protected function register_controls() { $this->add_control('field', ['label'=>'Dato','type'=>Controls_Manager::SELECT,'default'=>'average_rating','options'=>['average_rating'=>'Media','rating_count'=>'Valoraciones','review_count'=>'Reseñas']]); }
    protected function value(): string {
        $p = $this->product();
        if (!$p) { return ''; }
        return (string) match ((string) $this->get_settings('field')) { 'rating_count' => $p->get_rating_count(), 'review_count' => $p->get_review_count(), default => $p->get_average_rating() };
    }
}

final class Product_Image extends Value_Tag {
    public const GROUP = 'woocommerce';
    public const CATEGORIES = [TagsModule::IMAGE_CATEGORY, TagsModule::MEDIA_CATEGORY];
    public static function available(): bool { return function_exists('wc_get_product'); }
    public function get_name() { return 'woocommerce-product-image-tag'; }
    public function get_title() { return 'Imagen del producto'; }
    public function get_value(array $options = []) {
        $p = Tags::post();
        $product = $p ? wc_get_product($p->ID) : null;
        $id = $product ? (int) $product->get_image_id() : 0;
        return $id ? Value_Tag::image($id) : ['id'=>'','url'=>function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('full') : ''];
    }
}
