<?php
namespace OldBearExpansion\ThemeBuilder;

if (!defined('ABSPATH')) { exit; }

/**
 * Display conditions: which template applies to the current request.
 *
 * A condition is ['mode' => include|exclude, 'rule' => string, 'ids' => int[]].
 * Rules: general, singular, front_page, singular/{post_type}, in/{taxonomy},
 * archive, blog, archive/{post_type}, taxonomy/{taxonomy}, author, date, search, not_found.
 */
final class Conditions {
    /** Rules that accept a list of IDs (posts, terms or users). */
    public const ID_RULES = ['singular/', 'in/', 'taxonomy/', 'author'];

    /** @return array<string,string> rule => label, filtered for a location. */
    public static function rules(string $location): array {
        $singular = ['singular'=>'Contenido individual · Todo','front_page'=>'Contenido individual · Portada'];
        foreach (self::post_types() as $type=>$label) { $singular['singular/' . $type] = 'Contenido individual · ' . $label; }
        foreach (self::taxonomies() as $taxonomy=>$label) { $singular['in/' . $taxonomy] = 'Contenido individual · Con término de «' . $label . '»'; }
        $singular['not_found'] = 'Página 404';

        $archive = ['archive'=>'Archivos · Todos','blog'=>'Archivos · Página de entradas'];
        foreach (self::post_types(true) as $type=>$label) { $archive['archive/' . $type] = 'Archivos · ' . $label; }
        foreach (self::taxonomies() as $taxonomy=>$label) { $archive['taxonomy/' . $taxonomy] = 'Archivos · ' . $label; }
        $archive += ['author'=>'Archivos · Autor','date'=>'Archivos · Fecha','search'=>'Archivos · Resultados de búsqueda'];

        return match ($location) {
            'single' => $singular,
            'archive' => $archive,
            'error-404' => ['not_found'=>'Página 404'],
            default => ['general'=>'Todo el sitio'] + $singular + $archive,
        };
    }

    public static function default_rule(string $location): string {
        return match ($location) { 'single'=>'singular/post', 'archive'=>'archive', 'error-404'=>'not_found', default=>'general' };
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

    public static function accepts_ids(string $rule): bool {
        foreach (self::ID_RULES as $prefix) { if (str_starts_with($rule, $prefix)) { return true; } }
        return false;
    }

    /** Normalizes repeater rows from the editor into stored conditions. */
    public static function normalize($rows): array {
        if (!is_array($rows)) { return []; }
        $conditions = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['rule']) || !is_string($row['rule'])) { continue; }
            $rule = sanitize_text_field($row['rule']);
            if (!preg_match('~^[a-z0-9_/\-]+$~', $rule)) { continue; }
            $ids = self::accepts_ids($rule) ? self::ids($row['object_id'] ?? '') : [];
            if (str_starts_with($rule, 'in/') && !$ids) { continue; }
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

    /** Specificity: the most specific matching include wins. */
    public static function score(array $condition): int {
        $rule = $condition['rule'];
        if ($rule === 'general') { return 10; }
        if ($rule === 'singular' || $rule === 'archive') { return 20; }
        if ($rule === 'front_page' || $rule === 'not_found') { return 50; }
        if (str_starts_with($rule, 'in/')) { return 35; }
        return $condition['ids'] ? 40 : 30;
    }

    /** Evaluates a rule against the main query. */
    public static function matches(array $condition): bool {
        $rule = $condition['rule'];
        $ids = $condition['ids'];
        $object = get_queried_object_id();
        $archive = is_archive() || is_home() || is_search();
        switch (true) {
            case $rule === 'general': return true;
            case $rule === 'singular': return is_singular() && !is_404();
            case $rule === 'front_page': return is_front_page();
            case $rule === 'not_found': return is_404();
            case $rule === 'archive': return $archive;
            case $rule === 'blog': return is_home();
            case $rule === 'author': return is_author() && (!$ids || in_array($object, $ids, true));
            case $rule === 'date': return is_date();
            case $rule === 'search': return is_search();
            case str_starts_with($rule, 'singular/'):
                return is_singular(substr($rule, 9)) && (!$ids || in_array($object, $ids, true));
            case str_starts_with($rule, 'in/'):
                return is_singular() && $ids && has_term($ids, substr($rule, 3), $object);
            case str_starts_with($rule, 'archive/'):
                return is_post_type_archive(substr($rule, 8));
            case str_starts_with($rule, 'taxonomy/'):
                $taxonomy = substr($rule, 9);
                if ($taxonomy === 'category') { return is_category($ids ?: ''); }
                if ($taxonomy === 'post_tag') { return is_tag($ids ?: ''); }
                return is_tax($taxonomy, $ids ?: '');
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
        if (!$conditions) { return $location === 'error-404' ? 'Página 404' : 'Sin condiciones: no se muestra'; }
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
