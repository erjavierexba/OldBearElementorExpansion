<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

/**
 * Query controls and WP_Query builder shared by Posts («posts_» keys) and the Loop widgets
 * («post_query_» keys), with the same key names Elementor Pro stores.
 */
final class Query {
    /** IDs already shown on this page, for «Evitar duplicados». */
    public static array $shown = [];

    /**
     * Post query controls. $condition is added to every control (the Loop Grid shows them for its «post»
     * skin); with $close false the section stays open for the product and term queries.
     */
    public static function controls(Controls_Stack $w, string $p, string $section = 'section_query', array $condition = [], bool $close = true): void {
        $add = static function (string $id, array $args) use ($w, $condition) { if ($condition) { $args['condition'] = ($args['condition'] ?? []) + $condition; } $w->add_control($id, $args); };
        $types = [];
        foreach (get_post_types(['public'=>true], 'objects') as $type) { if (!in_array($type->name, ['attachment','elementor_library','e-landing-page'], true)) { $types[$type->name] = $type->labels->name; } }
        $types += ['by_id'=>'Selección manual','current_query'=>'Consulta actual','related'=>'Relacionadas'];
        $terms = [];
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) {
            if ($taxonomy->name === 'post_format') { continue; }
            foreach (get_terms(['taxonomy'=>$taxonomy->name,'hide_empty'=>false,'number'=>200]) ?: [] as $term) {
                if ($term instanceof \WP_Term) { $terms[(string) $term->term_id] = $taxonomy->labels->singular_name . ': ' . $term->name; }
            }
        }
        $authors = [];
        foreach (get_users(['capability'=>['edit_posts'],'number'=>200,'fields'=>['ID','display_name']]) as $user) { $authors[(string) $user->ID] = $user->display_name; }
        $posts = [];
        foreach (get_posts(['post_type'=>array_keys(array_diff_key($types, array_flip(['by_id','current_query','related']))),'numberposts'=>200,'orderby'=>'date','post_status'=>'publish']) as $post) { $posts[(string) $post->ID] = get_the_title($post) ?: '#' . $post->ID; }
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true,'show_ui'=>true], 'objects') as $taxonomy) { $taxonomies[$taxonomy->name] = $taxonomy->labels->name; }
        if (taxonomy_exists('post_format')) { $taxonomies['post_format'] = 'Formatos'; }

        $plain = [$p . 'post_type!'=>['by_id','current_query','related']];
        if ($section !== '') { $w->start_controls_section($section, ['label'=>'Consulta']); }
        $add($p . 'post_type', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'post','options'=>$types]);
        $add($p . 'posts_ids', ['label'=>'Entradas','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>[$p . 'post_type'=>'by_id']]);
        $add('archive_query_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Muestra las entradas de la página en la que se use (archivo, búsqueda o blog).','content_classes'=>'elementor-panel-alert elementor-panel-alert-info','condition'=>[$p . 'post_type'=>'current_query']]);
        $add($p . 'related_taxonomies', ['label'=>'Relacionadas por','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$taxonomies,'default'=>['category','post_tag'],'condition'=>[$p . 'post_type'=>'related']]);
        $add($p . 'include', ['label'=>'Incluir solo','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['terms'=>'Términos','authors'=>'Autores'],'condition'=>$plain]);
        $add($p . 'include_term_ids', ['label'=>'Términos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>$plain + [$p . 'include'=>'terms']]);
        $add($p . 'include_authors', ['label'=>'Autores','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$authors,'condition'=>$plain + [$p . 'include'=>'authors']]);
        $add($p . 'exclude', ['label'=>'Excluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['current_post'=>'Entrada actual','manual_selection'=>'Selección manual','terms'=>'Términos','authors'=>'Autores'],'condition'=>[$p . 'post_type!'=>['by_id','current_query']]]);
        $add($p . 'exclude_ids', ['label'=>'Entradas excluidas','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>[$p . 'exclude'=>'manual_selection']]);
        $add($p . 'exclude_term_ids', ['label'=>'Términos excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>[$p . 'exclude'=>'terms']]);
        $add($p . 'exclude_authors', ['label'=>'Autores excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$authors,'condition'=>[$p . 'exclude'=>'authors']]);
        $add($p . 'avoid_duplicates', ['label'=>'Evitar duplicados','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','description'=>'Omite las entradas que ya muestran otros widgets de esta página.','condition'=>[$p . 'post_type!'=>'current_query']]);
        $add($p . 'offset', ['label'=>'Saltar','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'condition'=>$plain]);
        $add($p . 'select_date', ['label'=>'Fecha','type'=>Controls_Manager::SELECT,'default'=>'anytime','options'=>['anytime'=>'Cualquiera','today'=>'Último día','week'=>'Última semana','month'=>'Último mes','quarter'=>'Último trimestre','year'=>'Último año','exact'=>'Personalizada'],'condition'=>$plain,'separator'=>'before']);
        $add($p . 'date_after', ['label'=>'Después de','type'=>Controls_Manager::DATE_TIME,'condition'=>$plain + [$p . 'select_date'=>'exact']]);
        $add($p . 'date_before', ['label'=>'Antes de','type'=>Controls_Manager::DATE_TIME,'condition'=>$plain + [$p . 'select_date'=>'exact']]);
        $add($p . 'orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'post_date','options'=>['post_date'=>'Fecha','post_title'=>'Título','menu_order'=>'Orden del menú','modified'=>'Última modificación','comment_count'=>'Número de comentarios','rand'=>'Aleatorio'],'condition'=>[$p . 'post_type!'=>'current_query']]);
        $add($p . 'order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente'],'condition'=>[$p . 'post_type!'=>'current_query']]);
        $add($p . 'ignore_sticky_posts', ['label'=>'Ignorar entradas fijadas','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','condition'=>[$p . 'post_type'=>'post']]);
        $add($p . 'query_id', ['label'=>'ID de consulta','type'=>Controls_Manager::TEXT,'default'=>'','description'=>'Permite modificar la consulta desde código con la acción elementor/query/{ID}.','separator'=>'before']);
        $add($p . 'related_fallback', ['label'=>'Si no hay relacionadas','type'=>Controls_Manager::SELECT,'default'=>'fallback_none','options'=>['fallback_none'=>'No mostrar nada','fallback_by_id'=>'Selección manual','fallback_recent'=>'Entradas recientes'],'condition'=>[$p . 'post_type'=>'related']]);
        $add($p . 'fallback_ids', ['label'=>'Entradas de reserva','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>[$p . 'post_type'=>'related',$p . 'related_fallback'=>'fallback_by_id']]);
        if ($close) { $w->end_controls_section(); }
    }

    /** @return int[] */
    public static function ints($value): array { return array_values(array_filter(array_map('absint', (array) $value))); }

    public static function build(array $s, string $p, int $per_page, int $paged, $widget = null, array $extra = []): \WP_Query {
        $source = (string) ($s[$p . 'post_type'] ?? 'post');
        if ($source === 'current_query') { return Context::query(); }
        $orderby = (string) ($s[$p . 'orderby'] ?? 'post_date');
        $orderby = ['post_date'=>'date','date'=>'date','post_title'=>'title','title'=>'title','menu_order'=>'menu_order','modified'=>'modified','post_modified'=>'modified','comment_count'=>'comment_count','rand'=>'rand'][$orderby] ?? 'date';
        $args = ['post_status'=>'publish','posts_per_page'=>$per_page,'orderby'=>$orderby,'order'=>strtolower((string) ($s[$p . 'order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC','ignore_sticky_posts'=>true];
        $meta_order = ['price'=>'_price','popularity'=>'total_sales','rating'=>'_wc_average_rating'][(string) ($s[$p . 'orderby'] ?? '')] ?? '';
        if ($meta_order !== '') { $args['orderby'] = 'meta_value_num'; $args['meta_key'] = $meta_order; }
        $current = Context::template() ? null : get_queried_object();
        $exclude = (array) ($s[$p . 'exclude'] ?? []);
        $not_in = [];

        if ($source === 'by_id') {
            $ids = self::ints($s[$p . 'posts_ids'] ?? []);
            $args += ['post_type'=>'any','post__in'=>$ids ?: [0],'orderby'=>$args['orderby']];
        } elseif ($source === 'related') {
            $post = Context::post();
            $args['post_type'] = $post ? $post->post_type : 'post';
            $tax_query = ['relation'=>'OR'];
            foreach ((array) ($s[$p . 'related_taxonomies'] ?? []) as $taxonomy) {
                if (!$post || !taxonomy_exists((string) $taxonomy)) { continue; }
                $ids = wp_get_post_terms($post->ID, (string) $taxonomy, ['fields'=>'ids']);
                if (!is_wp_error($ids) && $ids) { $tax_query[] = ['taxonomy'=>(string) $taxonomy,'terms'=>$ids]; }
            }
            if (count($tax_query) > 1) { $args['tax_query'] = $tax_query; } else { $args['post__in'] = [0]; }
            if ($post) { $not_in[] = $post->ID; }
        } else {
            $args['post_type'] = post_type_exists($source) ? $source : 'post';
            $args['ignore_sticky_posts'] = $source !== 'post' || ($s[$p . 'ignore_sticky_posts'] ?? 'yes') === 'yes';
            $include = (array) ($s[$p . 'include'] ?? []);
            $tax_query = [];
            if (in_array('terms', $include, true) && ($terms = self::ints($s[$p . 'include_term_ids'] ?? []))) { $tax_query = array_merge($tax_query, self::term_clauses($terms, 'IN')); }
            if (in_array('terms', $exclude, true) && ($terms = self::ints($s[$p . 'exclude_term_ids'] ?? []))) { $tax_query = array_merge($tax_query, self::term_clauses($terms, 'NOT IN')); }
            if ($tax_query) { $args['tax_query'] = ['relation'=>'AND'] + $tax_query; }
            if (in_array('authors', $include, true) && ($authors = self::ints($s[$p . 'include_authors'] ?? []))) { $args['author__in'] = $authors; }
            $offset = max(0, (int) ($s[$p . 'offset'] ?? 0));
            if ($offset) { $args['offset'] = $offset + ($paged - 1) * $per_page; }
            $date = (string) ($s[$p . 'select_date'] ?? 'anytime');
            $since = ['today'=>'-1 day','week'=>'-1 week','month'=>'-1 month','quarter'=>'-3 month','year'=>'-1 year'][$date] ?? null;
            if ($since) { $args['date_query'] = [['after'=>$since,'inclusive'=>true]]; }
            if ($date === 'exact') { $args['date_query'] = [array_filter(['after'=>$s[$p . 'date_after'] ?? '','before'=>$s[$p . 'date_before'] ?? '','inclusive'=>true])]; }
        }
        if ($source !== 'by_id') {
            if (in_array('current_post', $exclude, true) && $current instanceof \WP_Post) { $not_in[] = $current->ID; }
            if (in_array('manual_selection', $exclude, true)) { $not_in = array_merge($not_in, self::ints($s[$p . 'exclude_ids'] ?? [])); }
            if (in_array('authors', $exclude, true) && ($authors = self::ints($s[$p . 'exclude_authors'] ?? []))) { $args['author__not_in'] = $authors; }
            if (($s[$p . 'avoid_duplicates'] ?? '') === 'yes') { $not_in = array_merge($not_in, self::$shown); }
        }
        if ($not_in) { $args['post__not_in'] = array_values(array_unique($not_in)); }
        $args['paged'] = $paged;
        foreach ($extra as $key => $value) {
            $args[$key] = $key === 'tax_query' && !empty($args['tax_query']) ? ['relation'=>'AND', $args['tax_query'], $value] : $value;
        }

        $query = new \WP_Query();
        $query_id = sanitize_key((string) ($s[$p . 'query_id'] ?? ''));
        // Elementor Pro's custom query hook, so existing snippets keep working.
        $hook = $query_id !== '' ? static function($q) use ($query_id, $query, $widget) { if ($q === $query) { do_action('elementor/query/' . $query_id, $q, $widget); } } : null;
        if ($hook) { add_action('pre_get_posts', $hook); }
        $query->query($args);
        if ($hook) { remove_action('pre_get_posts', $hook); }

        if (!$query->have_posts() && $source === 'related') {
            $fallback = (string) ($s[$p . 'related_fallback'] ?? 'fallback_none');
            if ($fallback === 'fallback_recent') { $query = new \WP_Query(['post_type'=>$args['post_type'],'posts_per_page'=>$per_page,'post__not_in'=>$args['post__not_in'] ?? [],'ignore_sticky_posts'=>true]); }
            if ($fallback === 'fallback_by_id') { $query = new \WP_Query(['post_type'=>'any','post__in'=>self::ints($s[$p . 'fallback_ids'] ?? []) ?: [0],'posts_per_page'=>$per_page]); }
        }
        return $query;
    }

    /** Product sources of the Loop Grid's «product» skin (Pro's product_query_post_type). */
    public const PRODUCT_SOURCES = ['current_query'=>'Consulta actual','product'=>'Últimos productos','sale'=>'Rebajados','featured'=>'Destacados','by_id'=>'Selección manual','related_products'=>'Relacionados','upsells'=>'Ventas dirigidas','cross_sells'=>'Ventas cruzadas'];

    public static function product_controls(Controls_Stack $w, string $p, array $condition): void {
        $products = $terms = [];
        if (post_type_exists('product')) {
            foreach (get_posts(['post_type'=>'product','numberposts'=>200,'post_status'=>'publish']) as $post) { $products[(string) $post->ID] = get_the_title($post) ?: '#' . $post->ID; }
            foreach (get_object_taxonomies('product', 'objects') as $taxonomy) {
                if (!$taxonomy->public || !$taxonomy->show_ui) { continue; }
                foreach (get_terms(['taxonomy'=>$taxonomy->name,'hide_empty'=>false,'number'=>200]) ?: [] as $term) { if ($term instanceof \WP_Term) { $terms[(string) $term->term_id] = $taxonomy->labels->singular_name . ': ' . $term->name; } }
            }
        }
        $authors = [];
        foreach (get_users(['capability'=>['edit_posts'],'number'=>200,'fields'=>['ID','display_name']]) as $user) { $authors[(string) $user->ID] = $user->display_name; }
        $add = static function (string $id, array $args) use ($w, $condition) { $args['condition'] = ($args['condition'] ?? []) + $condition; $w->add_control($id, $args); };
        $add($p . 'post_type', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'product','options'=>self::PRODUCT_SOURCES]);
        $add($p . 'posts_ids', ['label'=>'Productos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$products,'condition'=>[$p . 'post_type'=>'by_id']]);
        $add($p . 'include', ['label'=>'Incluir solo','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['terms'=>'Términos','authors'=>'Autores'],'condition'=>[$p . 'post_type!'=>['cross_sells','related_products','upsells','by_id','current_query']]]);
        $add($p . 'include_term_ids', ['label'=>'Términos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>[$p . 'include'=>'terms']]);
        $add($p . 'include_authors', ['label'=>'Autores','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$authors,'condition'=>[$p . 'include'=>'authors']]);
        $add($p . 'exclude', ['label'=>'Excluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['current_post'=>'Producto actual','manual_selection'=>'Selección manual','terms'=>'Términos','authors'=>'Autores'],'condition'=>[$p . 'post_type!'=>['cross_sells','related_products','upsells','by_id','current_query']]]);
        $add($p . 'exclude_ids', ['label'=>'Productos excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$products,'condition'=>[$p . 'exclude'=>'manual_selection']]);
        $add($p . 'exclude_term_ids', ['label'=>'Términos excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>[$p . 'exclude'=>'terms']]);
        $add($p . 'orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'date','options'=>['date'=>'Fecha','title'=>'Título','price'=>'Precio','popularity'=>'Popularidad','rating'=>'Valoración','rand'=>'Aleatorio','menu_order'=>'Orden del menú'],'condition'=>[$p . 'post_type!'=>'current_query']]);
        $add($p . 'order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente'],'condition'=>[$p . 'post_type!'=>'current_query']]);
    }

    /** Products for the «product» skin, built on the post query (same include/exclude/order keys). */
    public static function products(array $s, string $p, int $per_page, int $paged, $widget = null): \WP_Query {
        $source = (string) ($s[$p . 'post_type'] ?? 'product');
        if (!post_type_exists('product')) { return new \WP_Query(['post__in'=>[0]]); }
        if ($source === 'current_query') { return Context::query(); }
        $s[$p . 'post_type'] = 'product';
        $extra = [];
        $visibility = function_exists('wc_get_product_visibility_term_ids') ? wc_get_product_visibility_term_ids() : [];
        if (!empty($visibility['exclude-from-catalog'])) { $extra['tax_query'] = [['taxonomy'=>'product_visibility','field'=>'term_taxonomy_id','terms'=>[$visibility['exclude-from-catalog']],'operator'=>'NOT IN']]; }
        $ids = null;
        $product = null;
        $post = Context::post();
        if ($post && function_exists('wc_get_product')) { $product = wc_get_product($post->ID) ?: null; }
        switch ($source) {
            case 'sale': $ids = function_exists('wc_get_product_ids_on_sale') ? wc_get_product_ids_on_sale() : []; break;
            case 'featured': $ids = function_exists('wc_get_featured_product_ids') ? wc_get_featured_product_ids() : []; break;
            case 'by_id': $ids = self::ints($s[$p . 'posts_ids'] ?? []); break;
            case 'related_products': $ids = $product && function_exists('wc_get_related_products') ? wc_get_related_products($product->get_id(), max($per_page, 1) * 4) : []; break;
            case 'upsells': $ids = $product ? $product->get_upsell_ids() : []; break;
            case 'cross_sells':
                $ids = $product ? $product->get_cross_sell_ids() : [];
                if (!$ids && function_exists('WC') && WC()->cart) { $ids = WC()->cart->get_cross_sells(); }
                break;
        }
        if ($ids !== null) {
            $extra['post__in'] = array_map('intval', $ids) ?: [0];
            if (in_array($source, ['related_products','upsells','cross_sells'], true)) { $s[$p . 'include'] = []; $s[$p . 'exclude'] = []; }
        }
        return self::build($s, $p, $per_page, $paged, $widget, $extra);
    }

    /** Term loop controls («post_taxonomy_» / «product_taxonomy_» keys of the Loop Grid). */
    public static function term_controls(Controls_Stack $w, string $p, array $taxonomies, string $default, array $condition): void {
        $terms = [];
        foreach (array_keys($taxonomies) as $taxonomy) {
            foreach (get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'number'=>200]) ?: [] as $term) { if ($term instanceof \WP_Term) { $terms[(string) $term->term_id] = ($taxonomies[$taxonomy] ?? $taxonomy) . ': ' . $term->name; } }
        }
        $add = static function (string $id, array $args) use ($w, $condition) { $args['condition'] = ($args['condition'] ?? []) + $condition; $w->add_control($id, $args); };
        $add($p . 'query_post_type', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT,'default'=>$default,'options'=>$taxonomies]);
        $add($p . 'query_filter_by', ['label'=>'Términos','type'=>Controls_Manager::SELECT,'default'=>'show_all','options'=>['show_all'=>'Todos','manual_selection'=>'Selección manual']]);
        $add($p . 'posts_ids', ['label'=>'Incluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>[$p . 'query_filter_by'=>'manual_selection']]);
        $add($p . 'exclude_ids', ['label'=>'Excluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms]);
        $add($p . 'child_of', ['label'=>'Hijos de','type'=>Controls_Manager::SELECT,'options'=>[''=>'Cualquiera'] + $terms,'description'=>'Muestra solo los descendientes de ese término.']);
        $add($p . 'hierarchical', ['label'=>'Solo el primer nivel','type'=>Controls_Manager::SWITCHER,'default'=>'no','description'=>'Muestra los términos de primer nivel (o los hijos directos de «Hijos de»).']);
        $add($p . 'child_taxonomy_depth', ['label'=>'Profundidad','type'=>Controls_Manager::SELECT,'default'=>'0','options'=>array_combine(array_map('strval', range(0, 6)), array_merge(['Todas'], array_map('strval', range(1, 6)))),'condition'=>[$p . 'hierarchical!'=>'yes']]);
        $add($p . 'hide_empty', ['label'=>'Ocultar términos vacíos','type'=>Controls_Manager::SWITCHER,'default'=>'no']);
        $add($p . 'avoid_duplicates', ['label'=>'Evitar duplicados','type'=>Controls_Manager::SWITCHER,'default'=>'no']);
        $add($p . 'offset', ['label'=>'Saltar','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0]);
        $add($p . 'orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'name','options'=>['name'=>'Nombre','term_id'=>'ID']]);
        $add($p . 'order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'DESC','options'=>['ASC'=>'Ascendente','DESC'=>'Descendente']]);
        $add($p . 'term_taxonomy_id', ['type'=>Controls_Manager::HIDDEN]);
    }

    /** Term IDs already shown on this page, for the term loops' «avoid duplicates». */
    public static array $shown_terms = [];

    /** @return \WP_Term[] */
    public static function terms(array $s, string $p, string $default, int $number): array {
        $taxonomy = (string) ($s[$p . 'query_post_type'] ?? $default) ?: $default;
        if (!taxonomy_exists($taxonomy)) { return []; }
        $args = ['taxonomy'=>$taxonomy,'hide_empty'=>($s[$p . 'hide_empty'] ?? 'no') === 'yes','number'=>max(1, $number),'offset'=>max(0, (int) ($s[$p . 'offset'] ?? 0)),
            'orderby'=>($s[$p . 'orderby'] ?? 'name') === 'term_id' ? 'term_id' : 'name','order'=>strtoupper((string) ($s[$p . 'order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC'];
        if (($s[$p . 'query_filter_by'] ?? 'show_all') === 'manual_selection') { $args['include'] = self::ints($s[$p . 'posts_ids'] ?? []) ?: [0]; }
        $exclude = self::ints($s[$p . 'exclude_ids'] ?? []);
        if (($s[$p . 'avoid_duplicates'] ?? 'no') === 'yes') { $exclude = array_merge($exclude, self::$shown_terms); }
        if ($exclude) { $args['exclude'] = array_values(array_unique($exclude)); }
        $parent = absint($s[$p . 'child_of'] ?? 0);
        if (($s[$p . 'hierarchical'] ?? 'no') === 'yes') { $args['parent'] = $parent; }
        elseif ($parent) { $args['child_of'] = $parent; }
        $terms = get_terms($args);
        if (!is_array($terms)) { return []; }
        $depth = (int) ($s[$p . 'child_taxonomy_depth'] ?? 0);
        if ($depth > 0 && ($s[$p . 'hierarchical'] ?? 'no') !== 'yes') {
            $base = $parent ? count(get_ancestors($parent, $taxonomy, 'taxonomy')) + 1 : 0;
            $terms = array_values(array_filter($terms, static fn($t) => count(get_ancestors($t->term_id, $taxonomy, 'taxonomy')) - $base < $depth));
        }
        return array_values(array_filter($terms, static fn($t) => $t instanceof \WP_Term));
    }

    /** Groups term IDs by taxonomy into tax_query clauses. */
    public static function term_clauses(array $ids, string $operator): array {
        $by_taxonomy = [];
        foreach ($ids as $id) {
            $term = get_term($id);
            if ($term instanceof \WP_Term) { $by_taxonomy[$term->taxonomy][] = $id; }
        }
        $clauses = [];
        foreach ($by_taxonomy as $taxonomy => $terms) { $clauses[] = ['taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>$terms,'operator'=>$operator]; }
        return $clauses;
    }
}
