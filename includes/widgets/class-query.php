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

    public static function controls(Controls_Stack $w, string $p, string $section = 'section_query'): void {
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

        $plain = [$p . 'post_type!'=>['by_id','current_query','related']];
        $w->start_controls_section($section, ['label'=>'Consulta']);
        $w->add_control($p . 'post_type', ['label'=>'Origen','type'=>Controls_Manager::SELECT,'default'=>'post','options'=>$types]);
        $w->add_control($p . 'posts_ids', ['label'=>'Entradas','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>[$p . 'post_type'=>'by_id']]);
        $w->add_control('archive_query_note', ['type'=>Controls_Manager::RAW_HTML,'raw'=>'Muestra las entradas de la página en la que se use (archivo, búsqueda o blog).','content_classes'=>'elementor-panel-alert elementor-panel-alert-info','condition'=>[$p . 'post_type'=>'current_query']]);
        $w->add_control($p . 'related_taxonomies', ['label'=>'Relacionadas por','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$taxonomies,'default'=>['category','post_tag'],'condition'=>[$p . 'post_type'=>'related']]);
        $w->add_control($p . 'include', ['label'=>'Incluir solo','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['terms'=>'Términos','authors'=>'Autores'],'condition'=>$plain]);
        $w->add_control($p . 'include_term_ids', ['label'=>'Términos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>$plain + [$p . 'include'=>'terms']]);
        $w->add_control($p . 'include_authors', ['label'=>'Autores','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$authors,'condition'=>$plain + [$p . 'include'=>'authors']]);
        $w->add_control($p . 'exclude', ['label'=>'Excluir','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>['current_post'=>'Entrada actual','manual_selection'=>'Selección manual','terms'=>'Términos','authors'=>'Autores'],'condition'=>[$p . 'post_type!'=>['by_id','current_query']]]);
        $w->add_control($p . 'exclude_ids', ['label'=>'Entradas excluidas','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>[$p . 'exclude'=>'manual_selection']]);
        $w->add_control($p . 'exclude_term_ids', ['label'=>'Términos excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$terms,'condition'=>[$p . 'exclude'=>'terms']]);
        $w->add_control($p . 'exclude_authors', ['label'=>'Autores excluidos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$authors,'condition'=>[$p . 'exclude'=>'authors']]);
        $w->add_control($p . 'avoid_duplicates', ['label'=>'Evitar duplicados','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'','description'=>'Omite las entradas que ya muestran otros widgets de esta página.','condition'=>[$p . 'post_type!'=>'current_query']]);
        $w->add_control($p . 'offset', ['label'=>'Saltar','type'=>Controls_Manager::NUMBER,'default'=>0,'min'=>0,'condition'=>$plain]);
        $w->add_control($p . 'select_date', ['label'=>'Fecha','type'=>Controls_Manager::SELECT,'default'=>'anytime','options'=>['anytime'=>'Cualquiera','today'=>'Último día','week'=>'Última semana','month'=>'Último mes','quarter'=>'Último trimestre','year'=>'Último año','exact'=>'Personalizada'],'condition'=>$plain,'separator'=>'before']);
        $w->add_control($p . 'date_after', ['label'=>'Después de','type'=>Controls_Manager::DATE_TIME,'condition'=>$plain + [$p . 'select_date'=>'exact']]);
        $w->add_control($p . 'date_before', ['label'=>'Antes de','type'=>Controls_Manager::DATE_TIME,'condition'=>$plain + [$p . 'select_date'=>'exact']]);
        $w->add_control($p . 'orderby', ['label'=>'Ordenar por','type'=>Controls_Manager::SELECT,'default'=>'post_date','options'=>['post_date'=>'Fecha','post_title'=>'Título','menu_order'=>'Orden del menú','modified'=>'Última modificación','comment_count'=>'Número de comentarios','rand'=>'Aleatorio'],'condition'=>[$p . 'post_type!'=>'current_query']]);
        $w->add_control($p . 'order', ['label'=>'Orden','type'=>Controls_Manager::SELECT,'default'=>'desc','options'=>['asc'=>'Ascendente','desc'=>'Descendente'],'condition'=>[$p . 'post_type!'=>'current_query']]);
        $w->add_control($p . 'ignore_sticky_posts', ['label'=>'Ignorar entradas fijadas','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes','condition'=>[$p . 'post_type'=>'post']]);
        $w->add_control($p . 'query_id', ['label'=>'ID de consulta','type'=>Controls_Manager::TEXT,'default'=>'','description'=>'Permite modificar la consulta desde código con la acción elementor/query/{ID}.','separator'=>'before']);
        $w->add_control($p . 'related_fallback', ['label'=>'Si no hay relacionadas','type'=>Controls_Manager::SELECT,'default'=>'fallback_none','options'=>['fallback_none'=>'No mostrar nada','fallback_by_id'=>'Selección manual','fallback_recent'=>'Entradas recientes'],'condition'=>[$p . 'post_type'=>'related']]);
        $w->add_control($p . 'fallback_ids', ['label'=>'Entradas de reserva','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'options'=>$posts,'condition'=>[$p . 'post_type'=>'related',$p . 'related_fallback'=>'fallback_by_id']]);
        $w->end_controls_section();
    }

    /** @return int[] */
    public static function ints($value): array { return array_values(array_filter(array_map('absint', (array) $value))); }

    public static function build(array $s, string $p, int $per_page, int $paged, $widget = null): \WP_Query {
        $source = (string) ($s[$p . 'post_type'] ?? 'post');
        if ($source === 'current_query') { return Context::query(); }
        $orderby = (string) ($s[$p . 'orderby'] ?? 'post_date');
        $orderby = ['post_date'=>'date','date'=>'date','post_title'=>'title','title'=>'title','menu_order'=>'menu_order','modified'=>'modified','post_modified'=>'modified','comment_count'=>'comment_count','rand'=>'rand'][$orderby] ?? 'date';
        $args = ['post_status'=>'publish','posts_per_page'=>$per_page,'orderby'=>$orderby,'order'=>strtolower((string) ($s[$p . 'order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC','ignore_sticky_posts'=>true];
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
