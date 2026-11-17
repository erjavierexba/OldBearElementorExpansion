<?php
namespace OldBearExpansion\ThemeBuilder;

if (!defined('ABSPATH')) { exit; }

/**
 * Display conditions: which template applies to the current request.
 *
 * Rules use the same names Elementor Pro stores in the `_elementor_conditions` meta
 * ("include/singular/post/12"), so templates work with either plugin:
 *   general
 *   singular · singular/front_page · singular/{post_type} · singular/in_{taxonomy} · singular/in_{taxonomy}_children
 *   singular/child_of · singular/any_child_of · singular/by_author · singular/{post_type}_by_author · singular/not_found404
 *   singular/attachment · singular/attachment_by_author · singular/in_post_format
 *   archive · archive/post_archive · archive/{post_type}_archive · archive/{taxonomy} · archive/author · archive/date · archive/search
 *   archive/child_of_{taxonomy} · archive/any_child_of_{taxonomy} · archive/post_format
 *   woocommerce · product · product/in_product_{cat,tag,brand}[_children] · product/product_by_author
 *   product_archive · product_archive/{shop_page,product_search,product_cat,product_tag,product_brand}
 * Pro may also save the WooCommerce rules under the group name ("woocommerce/product_archive/shop_page").
 * A condition is ['mode' => include|exclude, 'rule' => string, 'ids' => int[]].
 */
final class Conditions {
    /** Post meta Elementor Pro reads. */
    public const META = '_elementor_conditions';

    /** Rules from earlier versions of this plugin. */
    private const LEGACY = ['front_page'=>'singular/front_page','not_found'=>'singular/not_found404','blog'=>'archive/post_archive','author'=>'archive/author','date'=>'archive/date','search'=>'archive/search',
        'singular/product'=>'product','archive/product_archive'=>'product_archive','singular/product_by_author'=>'product/product_by_author'];

    /** Rule groups Elementor Pro stores as the first segment. */
    public const GROUPS = 'general|singular|archive|woocommerce|product_archive|product';

    /** WooCommerce taxonomies, which Pro lists under its own group. */
    public const PRODUCT_TAXONOMIES = ['product_cat','product_tag','product_brand'];

    /** @return array<string,string> rule => label, filtered for a location. */
    public static function rules(string $location): array {
        $singular = ['singular'=>'Contenido individual · Todo','singular/front_page'=>'Contenido individual · Portada'];
        foreach (self::post_types() as $type => $label) { $singular['singular/' . $type] = 'Contenido individual · ' . $label; }
        foreach (self::taxonomies() as $taxonomy => $label) {
            $singular['singular/in_' . $taxonomy] = 'Contenido individual · Con término de «' . $label . '»';
            if (is_taxonomy_hierarchical($taxonomy)) { $singular['singular/in_' . $taxonomy . '_children'] = 'Contenido individual · Con hijos de un término de «' . $label . '»'; }
        }
        if (post_type_supports('post', 'post-formats')) { $singular['singular/in_post_format'] = 'Contenido individual · Con formato de entrada'; }
        $singular += ['singular/attachment'=>'Contenido individual · Medios','singular/attachment_by_author'=>'Contenido individual · Medios de un autor'];
        $singular += ['singular/child_of'=>'Contenido individual · Hijo directo de','singular/any_child_of'=>'Contenido individual · Descendiente de','singular/by_author'=>'Contenido individual · De un autor'];
        foreach (self::post_types() as $type => $label) { $singular['singular/' . $type . '_by_author'] = 'Contenido individual · ' . $label . ' de un autor'; }
        $singular['singular/not_found404'] = 'Página 404';

        $archive = ['archive'=>'Archivos · Todos','archive/post_archive'=>'Archivos · Entradas'];
        foreach (self::post_types(true) as $type => $label) { $archive['archive/' . $type . '_archive'] = 'Archivos · ' . $label; }
        foreach (self::taxonomies() as $taxonomy => $label) {
            $archive['archive/' . $taxonomy] = 'Archivos · ' . $label;
            if (is_taxonomy_hierarchical($taxonomy)) {
                $archive['archive/child_of_' . $taxonomy] = 'Archivos · ' . $label . ' · Hijo directo de';
                $archive['archive/any_child_of_' . $taxonomy] = 'Archivos · ' . $label . ' · Descendiente de';
            }
        }
        if (taxonomy_exists('post_format')) { $archive['archive/post_format'] = 'Archivos · Formatos de entrada'; }
        $archive += ['archive/author'=>'Archivos · Autor','archive/date'=>'Archivos · Fecha','archive/search'=>'Archivos · Resultados de búsqueda'];

        $product = $product_archive = [];
        if (post_type_exists('product')) {
            $product = ['product'=>'WooCommerce · Productos'];
            $product_archive = ['product_archive'=>'WooCommerce · Archivos de productos','product_archive/shop_page'=>'WooCommerce · Página de la tienda','product_archive/product_search'=>'WooCommerce · Resultados de búsqueda de productos'];
            foreach (self::PRODUCT_TAXONOMIES as $taxonomy) {
                if (!taxonomy_exists($taxonomy)) { continue; }
                $label = get_taxonomy($taxonomy)->labels->name;
                $product['product/in_' . $taxonomy] = 'WooCommerce · Productos con término de «' . $label . '»';
                if (is_taxonomy_hierarchical($taxonomy)) { $product['product/in_' . $taxonomy . '_children'] = 'WooCommerce · Productos con hijos de un término de «' . $label . '»'; }
                $product_archive['product_archive/' . $taxonomy] = 'WooCommerce · Archivo de «' . $label . '»';
            }
            $product['product/product_by_author'] = 'WooCommerce · Productos de un autor';
        }

        return match ($location) {
            'single' => $singular + $product,
            'archive' => $archive + $product_archive,
            'error-404' => ['singular/not_found404'=>'Página 404'],
            default => ['general'=>'Todo el sitio'] + $singular + $archive + ($product ? ['woocommerce'=>'WooCommerce · Toda la tienda'] + $product_archive + $product : []),
        };
    }

    public static function default_rule(string $location): string {
        return match ($location) { 'single'=>'singular/post', 'archive'=>'archive', 'error-404'=>'singular/not_found404', default=>'general' };
    }

    /** @return array<string,string> */
    public static function post_types(bool $with_archive = false): array {
        $types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) {
            if (in_array($type->name, ['attachment','elementor_library','e-landing-page','product'], true)) { continue; }
            if ($with_archive && !$type->has_archive) { continue; }
            $types[$type->name] = $type->labels->name;
        }
        return $types;
    }

    /** @return array<string,string> */
    public static function taxonomies(): array {
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) {
            if ($taxonomy->name === 'post_format' || in_array($taxonomy->name, self::PRODUCT_TAXONOMIES, true)) { continue; }
            $taxonomies[$taxonomy->name] = $taxonomy->labels->name;
        }
        return $taxonomies;
    }

    /** Rules whose meaning needs at least one ID. */
    public static function requires_ids(string $rule): bool {
        $rule = self::canonical($rule);
        return (bool) preg_match('~^(singular/(in_[a-z0-9_\-]+|child_of|any_child_of|by_author|[a-z0-9_\-]+_by_author)|archive/(any_)?child_of_[a-z0-9_\-]+|product/(in_[a-z0-9_\-]+|product_by_author))$~', $rule);
    }

    /**
     * "woocommerce/product…" and "woocommerce/product_archive…" (how Pro saves them from the general list)
     * mean the same as "product…" and "product_archive…"; a bare WooCommerce sub-rule belongs to its group.
     */
    public static function canonical(string $rule): string {
        if (!str_starts_with($rule, 'woocommerce/')) { return $rule; }
        $sub = substr($rule, 12);
        if ($sub === 'product' || $sub === 'product_archive' || str_starts_with($sub, 'product/') || str_starts_with($sub, 'product_archive/')) { return $sub; }
        if (str_starts_with($sub, 'in_product_') || $sub === 'product_by_author') { return 'product/' . $sub; }
        return 'product_archive/' . $sub;
    }

    public static function upgrade_rule(string $rule): string {
        if (isset(self::LEGACY[$rule])) { return self::LEGACY[$rule]; }
        if (preg_match('~^singular/(in_product_(?:cat|tag|brand)(?:_children)?)$~', $rule, $m)) { return 'product/' . $m[1]; }
        if (preg_match('~^archive/(product_(?:cat|tag|brand))$~', $rule, $m)) { return 'product_archive/' . $m[1]; }
        if (str_starts_with($rule, 'in/')) { return 'singular/in_' . substr($rule, 3); }
        if (str_starts_with($rule, 'taxonomy/')) { return 'archive/' . substr($rule, 9); }
        if (str_starts_with($rule, 'archive/') && post_type_exists(substr($rule, 8)) && !taxonomy_exists(substr($rule, 8))) { return 'archive/' . substr($rule, 8) . '_archive'; }
        return $rule;
    }

    /**
     * Normalizes repeater rows from the editor into conditions. Elementor saves repeater rows without the
     * values equal to the control default, so a missing rule is the control's default ($default_rule).
     */
    public static function normalize($rows, string $default_rule = ''): array {
        if (!is_array($rows)) { return []; }
        $conditions = [];
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            if (!isset($row['rule']) && $default_rule !== '') { $row['rule'] = $default_rule; }
            if (empty($row['rule']) || !is_string($row['rule'])) { continue; }
            $rule = self::upgrade_rule(sanitize_text_field($row['rule']));
            if (!preg_match('~^(' . self::GROUPS . ')(/[a-z0-9_\-]+){0,2}$~', $rule)) { continue; }
            $ids = self::ids($row['object_id'] ?? '');
            if (self::requires_ids($rule) && !$ids) { continue; }
            $conditions[] = ['mode'=>($row['mode'] ?? 'include') === 'exclude' ? 'exclude' : 'include','rule'=>$rule,'ids'=>$ids];
        }
        return $conditions;
    }

    /** @return int[] */
    public static function ids($value): array {
        if (is_array($value)) { $value = implode(',', $value); }
        if (!is_scalar($value)) { return []; }
        return array_values(array_unique(array_filter(array_map('absint', preg_split('~[\s,]+~', (string) $value)))));
    }

    /** Conditions → Elementor Pro strings ("include/singular/post/12"), one per ID. */
    public static function to_strings(array $conditions): array {
        $strings = [];
        foreach ($conditions as $condition) {
            $base = $condition['mode'] . '/' . $condition['rule'];
            if (!$condition['ids']) { $strings[] = $base; continue; }
            foreach ($condition['ids'] as $id) { $strings[] = $base . '/' . $id; }
        }
        return array_values(array_unique($strings));
    }

    /** Elementor Pro strings or {type,name,sub_name,sub_id} arrays → conditions. */
    public static function from_strings($items): array {
        $conditions = [];
        foreach ((array) $items as $item) {
            if (is_array($item)) { $item = implode('/', array_filter([$item['type'] ?? 'include', $item['name'] ?? '', $item['sub_name'] ?? '', (string) ($item['sub_id'] ?? '')], 'strlen')); }
            if (!is_string($item) || !preg_match('~^(include|exclude)/(' . self::GROUPS . ')((?:/[a-z][a-z0-9_\-]*){0,2})(?:/(\d+))?$~', trim($item), $m)) { continue; }
            $rule = self::upgrade_rule($m[2] . $m[3]);
            $id = isset($m[4]) ? (int) $m[4] : 0;
            $key = $m[1] . '|' . $rule;
            if (!isset($conditions[$key])) { $conditions[$key] = ['mode'=>$m[1],'rule'=>$rule,'ids'=>[]]; }
            if ($id) { $conditions[$key]['ids'][] = $id; }
        }
        return array_values(array_filter($conditions, static fn($c) => !self::requires_ids($c['rule']) || $c['ids']));
    }

    /** Editor repeater rows for a set of conditions. */
    public static function to_rows(array $conditions): array {
        return array_map(static fn($c) => ['_id'=>substr(md5($c['mode'] . $c['rule'] . implode(',', $c['ids'])), 0, 7),'mode'=>$c['mode'],'rule'=>$c['rule'],'object_id'=>implode(', ', $c['ids'])], $conditions);
    }

    /** Specificity: the most specific matching include wins. */
    public static function score(array $condition): int {
        $rule = $condition['rule'];
        if ($rule === 'general') { return 10; }
        if ($rule === 'woocommerce') { return 15; }
        if ($rule === 'singular' || $rule === 'archive') { return 20; }
        if ($rule === 'singular/front_page' || $rule === 'singular/not_found404') { return 50; }
        if ($condition['ids'] && !self::requires_ids($rule)) { return 40; }
        if (self::requires_ids($rule)) { return 35; }
        return 30;
    }

    /** Evaluates a rule against the main query. */
    public static function matches(array $condition): bool {
        [$name, $sub] = array_pad(explode('/', self::canonical($condition['rule']), 2), 2, '');
        $ids = $condition['ids'];
        $object = get_queried_object_id();
        if ($name === 'general') { return true; }
        if ($name === 'woocommerce') { return self::is_shop_request() || (is_singular('product') && !is_404()); }
        if ($name === 'product') {
            if (!is_singular('product') || is_404() || !($post = get_post($object))) { return false; }
            return $sub === '' ? (!$ids || in_array((int) $post->ID, $ids, true)) : self::singular_sub($post, $sub, $ids);
        }
        if ($name === 'product_archive') {
            if (!self::is_shop_request()) { return false; }
            return match ($sub) {
                '' => true,
                'shop_page' => self::is_shop() && !self::is_product_search(),
                'product_search' => self::is_product_search(),
                default => taxonomy_exists($sub) && is_tax($sub, $ids ?: ''),
            };
        }
        if ($name === 'singular') {
            if ($sub === 'not_found404') { return is_404(); }
            if (!is_singular() || is_404()) { return false; }
            $post = get_post($object);
            return $post && self::singular_sub($post, $sub, $ids);
        }
        if ($name === 'archive') {
            if (!(is_archive() || is_home() || is_search())) { return false; }
            switch (true) {
                case $sub === '': return true;
                case $sub === 'post_archive': return is_home() || is_post_type_archive('post');
                case $sub === 'author': return is_author() && (!$ids || in_array($object, $ids, true));
                case $sub === 'date': return is_date();
                case $sub === 'search': return is_search();
                case str_ends_with($sub, '_archive') && post_type_exists(substr($sub, 0, -8)): return is_post_type_archive(substr($sub, 0, -8));
                case $sub === 'category': return is_category($ids ?: '');
                case $sub === 'post_tag': return is_tag($ids ?: '');
                case (bool) preg_match('~^(any_)?child_of_(.+)$~', $sub, $m):
                    $term = get_queried_object();
                    if (!$term instanceof \WP_Term || $term->taxonomy !== $m[2]) { return false; }
                    return $m[1] === '' ? in_array((int) $term->parent, $ids, true) : (bool) array_intersect(array_map('intval', get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')), $ids);
                default: return taxonomy_exists($sub) && is_tax($sub, $ids ?: '');
            }
        }
        return false;
    }

    /** Sub-rules of singular content (also used by the product group). */
    private static function singular_sub(\WP_Post $post, string $sub, array $ids): bool {
        switch (true) {
            case $sub === '': return true;
            case $sub === 'front_page': return is_front_page();
            case $sub === 'child_of': return in_array((int) $post->post_parent, $ids, true);
            case $sub === 'any_child_of': return (bool) array_intersect(array_map('intval', get_post_ancestors($post)), $ids);
            case $sub === 'by_author': return in_array((int) $post->post_author, $ids, true);
            case str_ends_with($sub, '_by_author'): return $post->post_type === substr($sub, 0, -10) && in_array((int) $post->post_author, $ids, true);
            case str_starts_with($sub, 'in_') && str_ends_with($sub, '_children'):
                $taxonomy = substr($sub, 3, -9);
                foreach (wp_get_post_terms($post->ID, $taxonomy, ['fields'=>'ids']) ?: [] as $term) {
                    if (!is_wp_error($term) && array_intersect(array_map('intval', get_ancestors((int) $term, $taxonomy)), $ids)) { return true; }
                }
                return false;
            case str_starts_with($sub, 'in_'): return $ids && has_term($ids, substr($sub, 3), $post);
            default: return $post->post_type === $sub && (!$ids || in_array((int) $post->ID, $ids, true));
        }
    }

    private static function is_shop(): bool {
        return function_exists('is_shop') ? is_shop() : is_post_type_archive('product');
    }

    private static function is_product_search(): bool {
        return is_search() && get_query_var('post_type') === 'product';
    }

    /** Shop page, product taxonomy archives and product search. */
    private static function is_shop_request(): bool {
        if (!post_type_exists('product')) { return false; }
        if (self::is_shop() || self::is_product_search()) { return true; }
        foreach (get_object_taxonomies('product') as $taxonomy) { if (is_tax($taxonomy)) { return true; } }
        return false;
    }

    /** Best score for a set of conditions, or 0 when it does not apply. */
    public static function evaluate(array $conditions): int {
        $best = 0;
        foreach ($conditions as $condition) {
            if (!self::matches($condition)) { continue; }
            if ($condition['mode'] === 'exclude') { return 0; }
            $best = max($best, self::score($condition));
        }
        return $best;
    }

    public static function describe(array $conditions, string $location): string {
        if (!$conditions) { return 'Sin condiciones: no se muestra'; }
        $labels = self::rules('header') + self::rules('single') + self::rules('archive');
        $parts = [];
        foreach ($conditions as $condition) {
            $label = $labels[$condition['rule']] ?? $condition['rule'];
            if ($condition['ids']) { $label .= ' (#' . implode(', #', $condition['ids']) . ')'; }
            $parts[] = ($condition['mode'] === 'exclude' ? 'Excluir: ' : '') . $label;
        }
        return implode(' · ', $parts);
    }
}
