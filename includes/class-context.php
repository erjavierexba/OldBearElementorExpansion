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
        [$type, $id] = $template->preview();
        $post_type = str_starts_with($type, 'single/') ? substr($type, 7) : '';
        if ($id && get_post_status($id) === 'publish' && (!$post_type || get_post_type($id) === $post_type)) { return get_post($id); }
        if ($post_type === '' || !post_type_exists($post_type)) { $post_type = 'post'; }
        $latest = get_posts(['post_type'=>$post_type,'posts_per_page'=>1,'post_status'=>'publish']);
        if (!$latest && $post_type !== 'post') { $latest = get_posts(['post_type'=>'post','posts_per_page'=>1,'post_status'=>'publish']); }
        return $latest[0] ?? null;
    }

    /**
     * Main query for archive widgets. While editing a template it follows the template's
     * «Preview settings» (recent posts, date, author, term, post type archive or search).
     */
    public static function query(): \WP_Query {
        global $wp_query;
        $template = self::template();
        if (!$template && $wp_query instanceof \WP_Query) { return $wp_query; }
        $args = ['post_type'=>'post','post_status'=>'publish','posts_per_page'=>(int) get_option('posts_per_page', 10),'ignore_sticky_posts'=>true];
        if ($template) {
            [$type, $id, $search] = $template->preview();
            [$group, $name] = array_pad(explode('/', $type, 2), 2, '');
            if ($group === 'archive' && $name === 'author' && $id) { $args['author'] = $id; }
            elseif ($group === 'taxonomy' && taxonomy_exists($name)) {
                $term = $id ? get_term($id, $name) : null;
                if (!$term instanceof \WP_Term) { $terms = get_terms(['taxonomy'=>$name,'number'=>1,'hide_empty'=>true]); $term = $terms[0] ?? null; }
                $object = get_taxonomy($name);
                $args['post_type'] = $object && $object->object_type ? $object->object_type : 'any';
                if ($term instanceof \WP_Term) { $args['tax_query'] = [['taxonomy'=>$name,'terms'=>[$term->term_id]]]; }
            }
            elseif ($group === 'post_type_archive' && post_type_exists($name)) { $args['post_type'] = $name; }
            elseif ($group === 'search') { $args['s'] = $search; $args['post_type'] = 'any'; }
        }
        return new \WP_Query($args);
    }

    /** Term or author of the archive being shown (or previewed), if any. */
    public static function archive_object(): ?object {
        $template = self::template();
        if (!$template) {
            $object = get_queried_object();
            return $object instanceof \WP_Term || $object instanceof \WP_User ? $object : null;
        }
        [$type, $id] = $template->preview();
        [$group, $name] = array_pad(explode('/', $type, 2), 2, '');
        if ($group === 'archive' && $name === 'author') { return $id ? (get_userdata($id) ?: null) : null; }
        if ($group !== 'taxonomy' || !taxonomy_exists($name)) { return null; }
        $term = $id ? get_term($id, $name) : null;
        if (!$term instanceof \WP_Term) { $terms = get_terms(['taxonomy'=>$name,'number'=>1,'hide_empty'=>false]); $term = $terms[0] ?? null; }
        return $term instanceof \WP_Term ? $term : null;
    }

    public static function archive_title(): string {
        $template = self::template();
        if ($template) {
            [$type, $id, $search] = $template->preview();
            [$group, $name] = array_pad(explode('/', $type, 2), 2, '');
            if ($group === 'search') { return sprintf('Resultados de búsqueda: %s', $search); }
            if ($group === 'taxonomy' && $id && ($term = get_term($id, $name)) instanceof \WP_Term) { return $term->name; }
            if ($group === 'archive' && $name === 'author' && $id && ($user = get_userdata($id))) { return $user->display_name; }
            if ($group === 'post_type_archive' && ($object = get_post_type_object($name))) { return $object->labels->name; }
            return 'Título del archivo';
        }
        if (is_search()) { return sprintf('Resultados de búsqueda: %s', get_search_query()); }
        if (is_home()) {
            $page = (int) get_option('page_for_posts');
            return $page ? get_the_title($page) : 'Últimas entradas';
        }
        return wp_strip_all_tags(get_the_archive_title());
    }
}
