<?php
namespace OldBearExpansion\Extras;

use Elementor\Controls_Manager;
use Elementor\Element_Base;
use Elementor\Plugin as Elementor;
use OldBearExpansion\Context;
use OldBearExpansion\Plugin;
use const OldBearExpansion\VERSION;

if (!defined('ABSPATH')) { exit; }

/**
 * Per-element display conditions, stored as Elementor Pro stores them so data moves both ways:
 * `e_display_conditions` is a hidden setting holding [ JSON ], the JSON being a list of groups
 * (any group may pass) of conditions (all must pass): {"condition":"…","comparator":"…",…}.
 * Dates are m-d-Y, times m-d-Y H:i and searchable values lists of {id, text}.
 */
final class Display_Conditions {
    public const KEY = 'e_display_conditions';
    private const REST_NS = 'obx/v1';
    private const DATE = 'm-d-Y';
    /** Advanced-tab control each element's trigger goes after, by stack and section. */
    private const PLACES = ['section'=>['section_advanced','css_classes'],'column'=>['section_advanced','css_classes'],'container'=>['section_layout','css_classes'],'common'=>['_section_style','_css_classes'],'common-optimized'=>['_section_style','_css_classes']];
    private static array $hidden = [];

    public static function boot(): void {
        add_action('elementor/element/after_section_end', [self::class, 'controls'], 10, 3);
        add_action('elementor/frontend/before_render', [self::class, 'before_render']);
        add_action('elementor/frontend/after_render', [self::class, 'after_render']);
        add_filter('elementor/element/is_dynamic_content', [self::class, 'is_dynamic'], 10, 2);
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'editor_assets']);
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function controls($element, $section_id, $args): void {
        if (!$element instanceof \Elementor\Controls_Stack) { return; }
        [$section, $after] = self::PLACES[$element->get_name()] ?? ['', ''];
        if ($section !== $section_id || $element->get_controls(self::KEY)) { return; }
        $injected = (bool) $element->get_controls($after);
        if ($injected) { $element->start_injection(['of'=>$after]); }
        $element->add_control('e_display_conditions_trigger', ['type'=>Controls_Manager::RAW_HTML,'separator'=>'before','raw'=>'<div class="obx-dc-control"><span class="obx-dc-control__label">Condiciones de visualización</span><button type="button" class="obx-dc-control__button" data-obx-display-conditions><i class="eicon-flow" aria-hidden="true"></i><span class="obx-dc-control__count"></span><span class="elementor-screen-only">Editar las condiciones de visualización</span></button></div>']);
        $element->add_control(self::KEY, ['type'=>Controls_Manager::HIDDEN]);
        if ($injected) { $element->end_injection(); }
    }

    /** Groups saved in the settings; a flat list from older versions counts as one group. */
    public static function groups($value): array {
        $json = is_array($value) ? ($value[0] ?? '') : $value;
        $groups = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        if (!is_array($groups) || !$groups) { return []; }
        foreach ($groups as $group) { if (!is_array($group) || isset($group['condition'])) { return [$groups]; } }
        return array_values(array_filter($groups, static fn($group) => is_array($group) && $group));
    }

    public static function passes(array $groups): bool {
        if (!$groups) { return true; }
        foreach ($groups as $group) {
            $all = true;
            foreach ($group as $condition) { if (is_array($condition) && !self::check($condition)) { $all = false; break; } }
            if ($all) { return true; }
        }
        return false;
    }

    public static function before_render(Element_Base $element): void {
        if (Elementor::$instance->editor->is_edit_mode()) { return; }
        $groups = self::groups($element->get_settings(self::KEY));
        if (!$groups || self::passes($groups)) { return; }
        self::$hidden[] = $element->get_id();
        add_filter('elementor/element/get_child_type', '__return_false');
        add_filter('elementor/frontend/' . $element->get_type() . '/should_render', '__return_false');
    }

    public static function after_render(Element_Base $element): void {
        $index = array_search($element->get_id(), self::$hidden, true);
        if ($index === false) { return; }
        unset(self::$hidden[$index]);
        remove_filter('elementor/element/get_child_type', '__return_false');
        remove_filter('elementor/frontend/' . $element->get_type() . '/should_render', '__return_false');
    }

    /** Elements with conditions must not come from Elementor's element cache. */
    public static function is_dynamic($dynamic, $raw): bool {
        return $dynamic || !empty($raw['settings'][self::KEY]);
    }

    /* ---------------------------------------------------------------- Conditions */

    /** Definitions shared by the editor and the checks: group, label and fields (key → spec). */
    public static function definitions(): array {
        $date_comparators = ['is','is_not','is_before','is_after','is_before_inclusive','is_after_inclusive'];
        $is = ['is','is_not'];
        $one_of = ['is_one_of','is_none_of'];
        $clock = ['type'=>'select','options'=>['server'=>'Hora del servidor (UTC)'],'default'=>'server'];
        $date = static fn(string $label) => ['comparator'=>['type'=>'comparator','options'=>$date_comparators,'default'=>'is'],'date'=>['type'=>'date','label'=>$label],'date_type'=>$clock];
        $query = static fn(string $object, array $query = []) => ['type'=>'query','object'=>$object,'query'=>$query];
        $roles = [];
        foreach (wp_roles()->roles as $slug => $role) { $roles[$slug] = translate_user_role($role['name']); }
        return [
            'login_status'=>['group'=>'user','label'=>'Estado de la sesión','fields'=>['comparator'=>['type'=>'comparator','options'=>['is'],'default'=>'is'],'status'=>['type'=>'select','options'=>['logged_in'=>'Con sesión iniciada','logged_out'=>'Sin sesión iniciada'],'default'=>'logged_in']]],
            'user_role'=>['group'=>'user','label'=>'Rol','fields'=>['comparator'=>['type'=>'comparator','options'=>$one_of,'default'=>'is_one_of'],'roles'=>['type'=>'multi','options'=>$roles,'default'=>[]]]],
            'user_registration_date'=>['group'=>'user','label'=>'Fecha de registro','fields'=>$date('Fecha')],
            'current_date'=>['group'=>'date','label'=>'Fecha actual','fields'=>$date('Fecha')],
            'day_of_the_week'=>['group'=>'date','label'=>'Día de la semana','fields'=>['comparator'=>['type'=>'comparator','options'=>$one_of,'default'=>'is_one_of'],'days'=>['type'=>'multi','options'=>['monday'=>'Lunes','tuesday'=>'Martes','wednesday'=>'Miércoles','thursday'=>'Jueves','friday'=>'Viernes','saturday'=>'Sábado','sunday'=>'Domingo'],'default'=>[]],'time_type'=>$clock]],
            'time_of_the_day'=>['group'=>'date','label'=>'Hora del día','fields'=>['comparator'=>['type'=>'comparator','options'=>$date_comparators,'default'=>'is'],'time'=>['type'=>'time','label'=>'Hora'],'time_type'=>$clock]],
            'page_title'=>['group'=>'page','label'=>'Título de la página','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'titles'=>$query('post', ['post_type'=>'page'])]],
            'page_parent'=>['group'=>'page','label'=>'Página superior','fields'=>['comparator'=>['type'=>'comparator','options'=>$one_of,'default'=>'is_one_of'],'pages'=>$query('post', ['post_type'=>'page'])]],
            'page_author'=>['group'=>'page','label'=>'Autor','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'authors'=>$query('author')]],
            'post_title'=>['group'=>'post','label'=>'Título de la entrada','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'titles'=>$query('post', ['post_type'=>'post'])]],
            'post_author'=>['group'=>'post','label'=>'Autor','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'authors'=>$query('author')]],
            'in_categories'=>['group'=>'post','label'=>'En las categorías','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'categories'=>$query('tax', ['taxonomy'=>'category'])]],
            'in_tags'=>['group'=>'post','label'=>'Con las etiquetas','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'tags'=>$query('tax', ['taxonomy'=>'post_tag'])]],
            'featured_image'=>['group'=>'post','label'=>'Imagen destacada','fields'=>['comparator'=>['type'=>'comparator','options'=>['is'],'default'=>'is'],'status'=>['type'=>'select','options'=>['set'=>'Con imagen','not_set'=>'Sin imagen'],'default'=>'set']]],
            'number_of_comments'=>['group'=>'post','label'=>'Número de comentarios','fields'=>['comparator'=>['type'=>'comparator','options'=>['is_greater_than_inclusive','is_less_than_inclusive','is','is_not'],'default'=>'is'],'number_of_comments'=>['type'=>'number','default'=>1,'min'=>0]]],
            'date_of_publish'=>['group'=>'post','label'=>'Fecha de publicación','fields'=>$date('Fecha')],
            'date_of_modification'=>['group'=>'post','label'=>'Fecha de modificación','fields'=>$date('Fecha')],
            'archive_of_categories'=>['group'=>'archive','label'=>'De las categorías','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'categories'=>$query('tax', ['taxonomy'=>'category'])]],
            'archive_of_tags'=>['group'=>'archive','label'=>'De las etiquetas','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'tags'=>$query('tax', ['taxonomy'=>'post_tag'])]],
            'archive_of_authors'=>['group'=>'archive','label'=>'De los autores','fields'=>['comparator'=>['type'=>'comparator','options'=>$is,'default'=>'is'],'authors'=>$query('author')]],
            'from_url'=>['group'=>'other','label'=>'Viene de la URL','fields'=>['comparator'=>['type'=>'comparator','options'=>['is','is_not','contains','not_contain'],'default'=>'is'],'from_url'=>['type'=>'text','placeholder'=>'https://']]],
            'dynamic_tags'=>['group'=>'other','label'=>'Etiquetas dinámicas','fields'=>['dynamic_tag'=>['type'=>'select','options'=>self::tag_options(),'default'=>'archive_title'],'comparator'=>['type'=>'comparator','options'=>['is','is_not','contains','not_contain','is_empty','is_not_empty'],'default'=>'is'],'dynamic_tag_value'=>['type'=>'text','hide_for'=>['is_empty','is_not_empty']]]],
        ];
    }

    /** Values the «dynamic tags» condition reads: Pro's keys, then the site's custom fields. */
    private const TAGS = [
        'archive_title'=>['Archivo', 'Título', 'archive-title', ['include_context'=>'no']],
        'featured_image_data_title'=>['Imagen destacada', 'Título de la imagen', 'featured-image-data', ['attachment_data'=>'title']],
        'featured_image_data_alt'=>['Imagen destacada', 'Texto alternativo', 'featured-image-data', ['attachment_data'=>'alt']],
        'featured_image_data_caption'=>['Imagen destacada', 'Leyenda', 'featured-image-data', ['attachment_data'=>'caption']],
        'author_info_bio'=>['Autor', 'Biografía', 'author-info', ['key'=>'description']],
        'author_info_email'=>['Autor', 'Correo electrónico', 'author-info', ['key'=>'email']],
        'author_info_website'=>['Autor', 'Web', 'author-info', ['key'=>'url']],
    ];

    private static function tag_options(): array {
        $options = [];
        foreach (self::TAGS as $key => [$group, $label]) {
            if ($key === 'author_info_email' && !current_user_can('manage_options')) { continue; }
            $options[$group][$key] = $label;
        }
        global $wpdb;
        $keys = (array) $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE %s ORDER BY meta_key LIMIT %d", $wpdb->esc_like('_') . '%', 500));
        if ($keys) { $options['Campo personalizado'] = array_combine($keys, $keys); }
        return $options;
    }

    public static function comparators(): array {
        return ['is'=>'Es','is_not'=>'No es','is_one_of'=>'Es','is_none_of'=>'No es','contains'=>'Contiene','not_contain'=>'No contiene','is_before'=>'Es anterior a','is_after'=>'Es posterior a','is_less_than_inclusive'=>'Menor o igual que','is_greater_than_inclusive'=>'Mayor o igual que','is_before_inclusive'=>'Es igual o anterior a','is_after_inclusive'=>'Es igual o posterior a','is_empty'=>'Está vacío','is_not_empty'=>'No está vacío'];
    }

    /** One condition. Unknown conditions pass, as do those still missing their values. */
    public static function check(array $c): bool {
        $name = (string) ($c['condition'] ?? '');
        $comparator = (string) ($c['comparator'] ?? '');
        $ids = static fn(string $key): array => array_map('intval', array_column(array_filter((array) ($c[$key] ?? []), 'is_array'), 'id'));
        $post = Context::post() ?: get_post();
        switch ($name) {
            case 'login_status': return self::equal($comparator ?: 'is', ($c['status'] ?? 'logged_in') === 'logged_in', is_user_logged_in());
            case 'user_role':
                $roles = is_user_logged_in() ? (array) wp_get_current_user()->roles : [];
                return self::contains($comparator ?: 'is_one_of', $roles, (array) ($c['roles'] ?? []));
            case 'user_registration_date':
                $user = wp_get_current_user();
                return $user->exists() && self::date($c, gmdate(self::DATE, strtotime($user->user_registered . ' UTC')));
            case 'current_date': return self::date($c, gmdate(self::DATE));
            case 'date_of_publish': return $post && self::date($c, get_the_date(self::DATE, $post));
            case 'date_of_modification': return $post && self::date($c, get_the_modified_date(self::DATE, $post));
            case 'day_of_the_week': return self::contains($comparator ?: 'is_one_of', [strtolower(gmdate('l'))], (array) ($c['days'] ?? []));
            case 'time_of_the_day':
                $time = \DateTime::createFromFormat('m-d-Y H:i', (string) ($c['time'] ?? ''));
                return $time && self::compare($comparator ?: 'is', gmdate('H:i'), $time->format('H:i'));
            case 'page_title': case 'post_title':
                if (!($c['titles'] ?? [])) { return true; }
                return self::contains($comparator ?: 'is', [$post ? get_the_title($post) : ''], array_map('get_the_title', $ids('titles')));
            case 'page_parent':
                if (!($c['pages'] ?? [])) { return true; }
                return self::contains($comparator ?: 'is_one_of', [$post ? (int) $post->post_parent : 0], $ids('pages'));
            case 'page_author': case 'post_author':
                if (!($c['authors'] ?? []) || !$post) { return true; }
                return self::contains($comparator ?: 'is', [(int) $post->post_author], $ids('authors'));
            case 'in_categories':
                if (!($c['categories'] ?? [])) { return true; }
                $terms = $post ? wp_get_post_categories($post->ID, ['fields'=>'ids']) : [];
                if (!$terms) { return ($comparator ?: 'is') !== 'is'; }
                return self::contains($comparator ?: 'is', $ids('categories'), array_map('intval', $terms));
            case 'in_tags':
                if (!($c['tags'] ?? [])) { return true; }
                return self::contains($comparator ?: 'is', $ids('tags'), $post ? array_map('intval', wp_get_post_tags($post->ID, ['fields'=>'ids'])) : []);
            case 'featured_image': return self::equal($comparator ?: 'is', ($c['status'] ?? 'set') === 'set', $post && has_post_thumbnail($post));
            case 'number_of_comments':
                $actual = $post ? (int) get_comments_number($post) : 0;
                $set = (int) ($c['number_of_comments'] ?? 1);
                return match ($comparator ?: 'is') { 'is' => $actual === $set, 'is_not' => $actual !== $set, 'is_less_than_inclusive' => $actual <= $set, 'is_greater_than_inclusive' => $actual >= $set, default => false };
            case 'archive_of_categories': case 'archive_of_tags': case 'archive_of_authors':
                $key = substr($name, strlen('archive_of_'));
                if (!($c[$key] ?? [])) { return true; }
                $match = match ($key) { 'categories' => is_category($ids($key)), 'tags' => is_tag($ids($key)), default => is_author($ids($key)) };
                return match ($comparator ?: 'is') { 'is' => $match, 'is_not' => !$match, default => false };
            case 'from_url': return self::text($comparator ?: 'is', (string) ($c['from_url'] ?? ''), (string) wp_get_raw_referer());
            case 'dynamic_tags':
                $value = self::tag_value((string) ($c['dynamic_tag'] ?? ''), $post);
                if ($value === null) { return false; }
                $comparator = $comparator ?: 'is';
                if ($comparator === 'is_empty') { return trim($value) === ''; }
                if ($comparator === 'is_not_empty') { return trim($value) !== ''; }
                return self::text($comparator, (string) ($c['dynamic_tag_value'] ?? ''), $value);
        }
        return true;
    }

    private static function tag_value(string $key, ?\WP_Post $post): ?string {
        if (isset(self::TAGS[$key])) {
            [, , $tag, $settings] = self::TAGS[$key];
            $value = Elementor::$instance->dynamic_tags->get_tag_data_content(null, $tag, $settings);
            return is_scalar($value) ? wp_strip_all_tags((string) $value) : '';
        }
        if ($key === '' || !$post || !metadata_exists('post', $post->ID, $key)) { return null; }
        $value = get_post_meta($post->ID, $key, true);
        return is_scalar($value) ? (string) $value : '';
    }

    /** Date set in the condition (m-d-Y) against a date of the same format. */
    private static function date(array $c, string $actual): bool {
        $set = \DateTime::createFromFormat('!' . self::DATE, (string) ($c['date'] ?? ''));
        $now = \DateTime::createFromFormat('!' . self::DATE, $actual);
        if (!$set || !$now || $set->format(self::DATE) !== (string) $c['date']) { return false; }
        return self::compare((string) ($c['comparator'] ?? 'is') ?: 'is', $now->format('Y-m-d'), $set->format('Y-m-d'));
    }

    /** $actual against $set: «is after» means $actual comes after $set. Works on sortable strings. */
    private static function compare(string $comparator, string $actual, string $set): bool {
        return match ($comparator) {
            'is' => $actual === $set, 'is_not' => $actual !== $set,
            'is_after' => $actual > $set, 'is_before' => $actual < $set,
            'is_after_inclusive' => $actual >= $set, 'is_before_inclusive' => $actual <= $set,
            default => false,
        };
    }

    private static function contains(string $comparator, array $values, array $list): bool {
        $found = (bool) array_intersect(array_map('strval', $values), array_map('strval', $list));
        return match ($comparator) { 'is', 'is_one_of' => $found, 'is_not', 'is_none_of' => !$found, default => false };
    }

    private static function equal(string $comparator, bool $expected, bool $actual): bool {
        return match ($comparator) { 'is' => $expected === $actual, 'is_not' => $expected !== $actual, default => false };
    }

    private static function text(string $comparator, string $expected, string $actual): bool {
        $expected = mb_strtolower($expected); $actual = mb_strtolower($actual);
        return match ($comparator) { 'is' => $expected === $actual, 'is_not' => $expected !== $actual, 'contains' => str_contains($actual, $expected), 'not_contain' => !str_contains($actual, $expected), default => false };
    }

    /* ---------------------------------------------------------------- Editor */

    public static function editor_assets(): void {
        wp_enqueue_style('obx-editor-display-conditions', Plugin::url('assets/css/editor-display-conditions.css'), [], VERSION);
        wp_enqueue_script('obx-editor-display-conditions', Plugin::url('assets/js/editor-display-conditions.js'), ['jquery'], VERSION, true);
        $config = [
            'key'=>self::KEY,
            'rest'=>esc_url_raw(rest_url(self::REST_NS . '/display-conditions/search')),
            'nonce'=>wp_create_nonce('wp_rest'),
            'groups'=>['date'=>'Fecha y hora','user'=>'Usuario','page'=>'Página','post'=>'Entrada','archive'=>'Archivo','other'=>'Otras'],
            'comparators'=>self::comparators(),
            'conditions'=>self::definitions(),
        ];
        wp_add_inline_script('obx-editor-display-conditions', 'window.obxDisplayConditions = ' . wp_json_encode($config) . ';', 'before');
    }

    public static function routes(): void {
        register_rest_route(self::REST_NS, '/display-conditions/search', ['methods'=>'GET','callback'=>[self::class, 'search'],'permission_callback'=>static fn() => current_user_can('edit_posts'),
            'args'=>['object'=>['type'=>'string','enum'=>['post','tax','author'],'required'=>true],'q'=>['type'=>'string','default'=>''],'post_type'=>['type'=>'string','default'=>'post'],'taxonomy'=>['type'=>'string','default'=>'category']]]);
    }

    /** Values for the searchable fields, as {id, text}. */
    public static function search(\WP_REST_Request $request): array {
        $q = sanitize_text_field((string) $request['q']);
        switch ($request['object']) {
            case 'tax':
                $taxonomy = sanitize_key((string) $request['taxonomy']);
                if (!taxonomy_exists($taxonomy)) { return []; }
                $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'number'=>20,'search'=>$q]);
                return is_array($terms) ? array_map(static fn($t) => ['id'=>$t->term_id,'text'=>$t->name], $terms) : [];
            case 'author':
                $users = get_users(['number'=>20,'search'=>$q !== '' ? '*' . $q . '*' : '','search_columns'=>['user_login','display_name','user_nicename'],'capability'=>['edit_posts'],'fields'=>['ID','display_name']]);
                return array_map(static fn($u) => ['id'=>(int) $u->ID,'text'=>$u->display_name], $users);
            default:
                $type = sanitize_key((string) $request['post_type']);
                if (!post_type_exists($type)) { return []; }
                $posts = get_posts(['post_type'=>$type,'post_status'=>'publish','s'=>$q,'posts_per_page'=>20,'orderby'=>$q === '' ? 'title' : 'relevance','order'=>'ASC']);
                return array_map(static fn($p) => ['id'=>$p->ID,'text'=>get_the_title($p) ?: '#' . $p->ID], $posts);
        }
    }
}
