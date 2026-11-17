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
 *   archive · archive/post_archive · archive/{post_type}_archive · archive/{taxonomy} · archive/author · archive/date · archive/search
 * A condition is ['mode' => include|exclude, 'rule' => string, 'ids' => int[]].
 */
final class Conditions {
    /** Post meta Elementor Pro reads. */
    public const META = '_elementor_conditions';

    /** Rules from earlier versions of this plugin. */
    private const LEGACY = ['front_page'=>'singular/front_page','not_found'=>'singular/not_found404','blog'=>'archive/post_archive','author'=>'archive/author','date'=>'archive/date','search'=>'archive/search'];

    /** @return array<string,string> rule => label, filtered for a location. */
    public static function rules(string $location): array {
        $singular = ['singular'=>'Contenido individual · Todo','singular/front_page'=>'Contenido individual · Portada'];
        foreach (self::post_types() as $type => $label) { $singular['singular/' . $type] = 'Contenido individual · ' . $label; }
        foreach (self::taxonomies() as $taxonomy => $label) {
            $singular['singular/in_' . $taxonomy] = 'Contenido individual · Con término de «' . $label . '»';
            if (is_taxonomy_hierarchical($taxonomy)) { $singular['singular/in_' . $taxonomy . '_children'] = 'Contenido individual · Con hijos de un término de «' . $label . '»'; }
        }
        $singular += ['singular/child_of'=>'Contenido individual · Hijo directo de','singular/any_child_of'=>'Contenido individual · Descendiente de','singular/by_author'=>'Contenido individual · De un autor'];
        foreach (self::post_types() as $type => $label) { $singular['singular/' . $type . '_by_author'] = 'Contenido individual · ' . $label . ' de un autor'; }
        $singular['singular/not_found404'] = 'Página 404';

        $archive = ['archive'=>'Archivos · Todos','archive/post_archive'=>'Archivos · Entradas'];
        foreach (self::post_types(true) as $type => $label) { $archive['archive/' . $type . '_archive'] = 'Archivos · ' . $label; }
        foreach (self::taxonomies() as $taxonomy => $label) { $archive['archive/' . $taxonomy] = 'Archivos · ' . $label; }
        $archive += ['archive/author'=>'Archivos · Autor','archive/date'=>'Archivos · Fecha','archive/search'=>'Archivos · Resultados de búsqueda'];

        return match ($location) {
            'single' => $singular,
            'archive' => $archive,
            'error-404' => ['singular/not_found404'=>'Página 404'],
            default => ['general'=>'Todo el sitio'] + $singular + $archive,
        };
    }

    public static function default_rule(string $location): string {
        return match ($location) { 'single'=>'singular/post', 'archive'=>'archive', 'error-404'=>'singular/not_found404', default=>'general' };
    }

    /** @return array<string,string> */
    public static function post_types(bool $with_archive = false): array {
        $types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) {
            if (in_array($type->name, ['attachment','elementor_library','e-landing-page'], true)) { continue; }
            if ($with_archive && !$type->has_archive) { continue; }
            $types[$type->name] = $type->labels->name;
        }
        return $types;
    }

    /** @return array<string,string> */
    public static function taxonomies(): array {
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) {
            if ($taxonomy->name === 'post_format') { continue; }
            $taxonomies[$taxonomy->name] = $taxonomy->labels->name;
        }
        return $taxonomies;
    }

    /** Rules whose meaning needs at least one ID. */
    public static function requires_ids(string $rule): bool {
        return (bool) preg_match('~^singular/(in_[a-z0-9_\-]+|child_of|any_child_of|by_author|[a-z0-9_\-]+_by_author)$~', $rule);
    }

    public static function upgrade_rule(string $rule): string {
        if (isset(self::LEGACY[$rule])) { return self::LEGACY[$rule]; }
        if (str_starts_with($rule, 'in/')) { return 'singular/in_' . substr($rule, 3); }
        if (str_starts_with($rule, 'taxonomy/')) { return 'archive/' . substr($rule, 9); }
        if (str_starts_with($rule, 'archive/') && post_type_exists(substr($rule, 8)) && !taxonomy_exists(substr($rule, 8))) { return 'archive/' . substr($rule, 8) . '_archive'; }
        return $rule;
    }

    /** Normalizes repeater rows from the editor into conditions. */
    public static function normalize($rows): array {
        if (!is_array($rows)) { return []; }
        $conditions = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['rule']) || !is_string($row['rule'])) { continue; }
            $rule = self::upgrade_rule(sanitize_text_field($row['rule']));
            if (!preg_match('~^(general|singular|archive)(/[a-z0-9_\-]+)?$~', $rule)) { continue; }
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
            if (!is_string($item) || !preg_match('~^(include|exclude)/(general|singular|archive)(?:/([a-z0-9_\-]+))?(?:/(\d+))?$~', trim($item), $m)) { continue; }
            $rule = $m[2] . (!empty($m[3]) ? '/' . $m[3] : '');
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
        if ($rule === 'singular' || $rule === 'archive') { return 20; }
        if ($rule === 'singular/front_page' || $rule === 'singular/not_found404') { return 50; }
        if ($condition['ids'] && !self::requires_ids($rule)) { return 40; }
        if (self::requires_ids($rule)) { return 35; }
        return 30;
    }

    /** Evaluates a rule against the main query. */
    public static function matches(array $condition): bool {
        [$name, $sub] = array_pad(explode('/', $condition['rule'], 2), 2, '');
        $ids = $condition['ids'];
        $object = get_queried_object_id();
        if ($name === 'general') { return true; }
        if ($name === 'singular') {
            if ($sub === 'not_found404') { return is_404(); }
            if (!is_singular() || is_404()) { return false; }
            $post = get_post($object);
            if (!$post) { return false; }
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
                default: return taxonomy_exists($sub) && is_tax($sub, $ids ?: '');
            }
        }
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
        $labels = self::rules('header');
        $parts = [];
        foreach ($conditions as $condition) {
            $label = $labels[$condition['rule']] ?? $condition['rule'];
            if ($condition['ids']) { $label .= ' (#' . implode(', #', $condition['ids']) . ')'; }
            $parts[] = ($condition['mode'] === 'exclude' ? 'Excluir: ' : '') . $label;
        }
        return implode(' · ', $parts);
    }
}
