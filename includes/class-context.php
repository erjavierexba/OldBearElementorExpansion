<?php
namespace OldBearExpansion;

use Elementor\Plugin as Elementor;
use OldBearExpansion\ThemeBuilder\Location_Document;

if (!defined('ABSPATH')) { exit; }

/** Which post / query a theme widget should show: the real one, or a preview while editing a template. */
final class Context {
    private static array $stack = [];

    public static function push(string $location): void { self::$stack[] = $location; }
    public static function pop(): void { array_pop(self::$stack); }

    public static function is_editor(): bool {
        $elementor = Elementor::$instance;
        return $elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode() || wp_doing_ajax();
    }

    /** Theme Builder template currently being edited, if any. */
    public static function template(): ?Location_Document {
        $post = get_post();
        if (!$post || $post->post_type !== 'elementor_library') { return null; }
        $document = Elementor::$instance->documents->get($post->ID);
        return $document instanceof Location_Document ? $document : null;
    }

    public static function post(): ?\WP_Post {
        $template = self::template();
        if (!$template) { return get_post(); }
        $id = absint($template->get_settings(Location_Document::PREVIEW));
        if ($id && get_post_status($id) === 'publish') { return get_post($id); }
        $latest = get_posts(['post_type'=>'post','posts_per_page'=>1,'post_status'=>'publish']);
        return $latest[0] ?? null;
    }

    /** Main query for archive widgets; recent posts while editing a template. */
    public static function query(): \WP_Query {
        global $wp_query;
        if (self::template() || !$wp_query instanceof \WP_Query) {
            return new \WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>(int) get_option('posts_per_page', 10),'ignore_sticky_posts'=>true]);
        }
        return $wp_query;
    }

    public static function archive_title(): string {
        if (self::template()) { return 'Título del archivo'; }
        if (is_search()) { return sprintf('Resultados de búsqueda: %s', get_search_query()); }
        if (is_home()) {
            $page = (int) get_option('page_for_posts');
            return $page ? get_the_title($page) : 'Últimas entradas';
        }
        return wp_strip_all_tags(get_the_archive_title());
    }
}
