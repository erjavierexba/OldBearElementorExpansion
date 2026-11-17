<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Plugin as Elementor;
use Elementor\Utils;
use OldBearExpansion\Context;

if (!defined('ABSPATH')) { exit; }

final class Post_Title extends Base {
    public const NAME = 'obx-post-title';
    public const PRO = 'theme-post-title';
    public function get_title(): string { return 'Título de la entrada'; }
    public function get_icon(): string { return 'eicon-post-title'; }
    public function get_keywords(): array { return ['title','titulo','post','entrada']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Título']);
        $this->tag_control('h1');
        $this->add_control('link', ['label'=>'Enlazar a la entrada','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->end_controls_section();
        $this->text_style('.obx-post-title', 'Título');
    }
    protected function render(): void {
        $post = Context::post();
        if (!$post) { $this->placeholder('No hay ninguna entrada que mostrar.'); return; }
        $title = esc_html(get_the_title($post));
        if ($this->get_settings_for_display('link') === 'yes') { $title = '<a href="' . esc_url(get_permalink($post)) . '">' . $title . '</a>'; }
        printf('<%1$s class="obx-post-title">%2$s</%1$s>', $this->tag(), $title);
    }
}

final class Post_Content extends Base {
    private static int $depth = 0;
    public const NAME = 'obx-post-content';
    public const PRO = 'theme-post-content';
    public const PRO_CATEGORY = 'theme-elements-single';
    public function get_title(): string { return 'Contenido de la entrada'; }
    public function get_icon(): string { return 'eicon-post-content'; }
    public function get_keywords(): array { return ['content','contenido','post','entrada']; }
    protected function register_controls(): void { $this->text_style('.obx-post-content', 'Contenido'); }
    protected function render(): void {
        $post = Context::post();
        if (!$post || $post->post_type === 'elementor_library' || self::$depth > 0) { $this->placeholder('El contenido de la entrada aparecerá aquí.'); return; }
        self::$depth++;
        echo '<div class="obx-post-content">';
        $document = Elementor::$instance->documents->get($post->ID);
        if ($document && $document->is_built_with_elementor()) {
            echo Elementor::$instance->frontend->get_builder_content_for_display($post->ID); // Elementor output.
        } else {
            $previous = $GLOBALS['post'] ?? null;
            $GLOBALS['post'] = $post;
            setup_postdata($post);
            echo apply_filters('the_content', get_the_content(null, false, $post)); // Core content filters.
            wp_link_pages();
            $GLOBALS['post'] = $previous;
            if ($previous) { setup_postdata($previous); }
        }
        echo '</div>';
        self::$depth--;
    }
}

final class Featured_Image extends Base {
    public const NAME = 'obx-featured-image';
    public const PRO = 'theme-post-featured-image';
    public function get_title(): string { return 'Imagen destacada'; }
    public function get_icon(): string { return 'eicon-featured-image'; }
    public function get_keywords(): array { return ['image','imagen','thumbnail','destacada']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Imagen']);
        $sizes = ['full'=>'Completa'];
        foreach (get_intermediate_image_sizes() as $size) { $sizes[$size] = $size; }
        $this->add_control('size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'options'=>$sizes,'default'=>'large']);
        $this->add_control('link', ['label'=>'Enlace','type'=>Controls_Manager::SELECT,'options'=>[''=>'Ninguno','post'=>'Entrada','file'=>'Archivo de imagen'],'default'=>'']);
        $this->add_control('caption', ['label'=>'Mostrar leyenda','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->end_controls_section();
        $this->start_controls_section('image_style', ['label'=>'Imagen','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('width', ['label'=>'Ancho','type'=>Controls_Manager::SLIDER,'size_units'=>['%','px','vw'],'range'=>['%'=>['min'=>1,'max'=>100],'px'=>['min'=>1,'max'=>2000]],'selectors'=>['{{WRAPPER}} img'=>'width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('height', ['label'=>'Alto','type'=>Controls_Manager::SLIDER,'size_units'=>['px','vh'],'range'=>['px'=>['min'=>1,'max'=>1200]],'selectors'=>['{{WRAPPER}} img'=>'height: {{SIZE}}{{UNIT}}; object-fit: cover;']]);
        $this->add_responsive_control('radius', ['label'=>'Radio del borde','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%'],'selectors'=>['{{WRAPPER}} img'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'border','selector'=>'{{WRAPPER}} img']);
        $this->end_controls_section();
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        $id = $post ? (int) get_post_thumbnail_id($post) : 0;
        if (!$id) {
            if ($this->editing()) { echo '<figure class="obx-featured-image"><img src="' . esc_url(Utils::get_placeholder_image_src()) . '" alt=""></figure>'; }
            return;
        }
        $image = wp_get_attachment_image($id, $s['size'] ?: 'large', false, ['class'=>'obx-featured-image__img']);
        $url = $s['link'] === 'post' ? get_permalink($post) : ($s['link'] === 'file' ? wp_get_attachment_url($id) : '');
        echo '<figure class="obx-featured-image">' . ($url ? '<a href="' . esc_url($url) . '">' . $image . '</a>' : $image);
        if ($s['caption'] === 'yes' && wp_get_attachment_caption($id)) { echo '<figcaption>' . esc_html(wp_get_attachment_caption($id)) . '</figcaption>'; }
        echo '</figure>';
    }
}

final class Post_Excerpt extends Base {
    public const NAME = 'obx-post-excerpt';
    public const PRO = 'theme-post-excerpt';
    public function get_title(): string { return 'Extracto de la entrada'; }
    public function get_icon(): string { return 'eicon-post-excerpt'; }
    public function get_keywords(): array { return ['excerpt','extracto','resumen']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Extracto']);
        $this->add_control('length', ['label'=>'Palabras (si no hay extracto manual)','type'=>Controls_Manager::NUMBER,'min'=>5,'max'=>200,'default'=>40]);
        $this->end_controls_section();
        $this->text_style('.obx-post-excerpt', 'Extracto');
    }
    protected function render(): void {
        $post = Context::post();
        if (!$post) { $this->placeholder('El extracto aparecerá aquí.'); return; }
        echo '<div class="obx-post-excerpt"><p>' . esc_html(Archive_Posts::excerpt($post, (int) $this->get_settings_for_display('length'))) . '</p></div>';
    }
}

final class Post_Info extends Base {
    public const NAME = 'obx-post-info';
    public const PRO = 'post-info';
    public function get_title(): string { return 'Información de la entrada'; }
    public function get_icon(): string { return 'eicon-post-info'; }
    public function get_keywords(): array { return ['meta','fecha','autor','categorias','info']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Elementos']);
        foreach (['date'=>'Fecha','modified'=>'Fecha de actualización','author'=>'Autor','terms'=>'Términos','comments'=>'Comentarios'] as $key=>$label) {
            $this->add_control('show_' . $key, ['label'=>$label,'type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>in_array($key, ['date','author','terms'], true) ? 'yes' : '']);
        }
        $taxonomies = [];
        foreach (get_taxonomies(['public'=>true], 'objects') as $taxonomy) { $taxonomies[$taxonomy->name] = $taxonomy->labels->name; }
        $this->add_control('taxonomy', ['label'=>'Taxonomía','type'=>Controls_Manager::SELECT,'options'=>$taxonomies,'default'=>'category','condition'=>['show_terms'=>'yes']]);
        $this->add_control('separator', ['label'=>'Separador','type'=>Controls_Manager::TEXT,'default'=>'·']);
        $this->add_control('link', ['label'=>'Enlazar autor y términos','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->end_controls_section();
        $this->text_style('.obx-post-info', 'Texto');
    }
    protected function render(): void {
        $s = $this->get_settings_for_display();
        $post = Context::post();
        if (!$post) { $this->placeholder('La información de la entrada aparecerá aquí.'); return; }
        $link = $s['link'] === 'yes';
        $items = [];
        if ($s['show_date'] === 'yes') { $items[] = '<time datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(get_the_date('', $post)) . '</time>'; }
        if ($s['show_modified'] === 'yes') { $items[] = 'Actualizado: <time datetime="' . esc_attr(get_the_modified_date('c', $post)) . '">' . esc_html(get_the_modified_date('', $post)) . '</time>'; }
        if ($s['show_author'] === 'yes') {
            $name = esc_html(get_the_author_meta('display_name', (int) $post->post_author));
            $items[] = $link ? '<a href="' . esc_url(get_author_posts_url((int) $post->post_author)) . '">' . $name . '</a>' : $name;
        }
        if ($s['show_terms'] === 'yes' && taxonomy_exists((string) $s['taxonomy'])) {
            $terms = get_the_terms($post, $s['taxonomy']);
            if (is_array($terms)) {
                $items[] = implode(', ', array_map(static fn($term) => $link ? '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>' : esc_html($term->name), $terms));
            }
        }
        if ($s['show_comments'] === 'yes') { $count = (int) get_comments_number($post); $items[] = esc_html(sprintf(_n('%d comentario', '%d comentarios', $count, 'oldbear-expansion'), $count)); }
        if (!$items) { return; }
        $separator = '<li class="obx-post-info__sep" aria-hidden="true">' . esc_html($s['separator']) . '</li>';
        echo '<ul class="obx-post-info"><li>' . implode('</li>' . $separator . '<li>', $items) . '</li></ul>';
    }
}

final class Archive_Title extends Base {
    public const CATEGORY = 'obx-archive';
    public const NAME = 'obx-archive-title';
    public const PRO = 'theme-archive-title';
    public function get_title(): string { return 'Título del archivo'; }
    public function get_icon(): string { return 'eicon-archive-title'; }
    public function get_keywords(): array { return ['archive','archivo','titulo','categoria']; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label'=>'Título']);
        $this->tag_control('h1');
        $this->add_control('description', ['label'=>'Mostrar descripción','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'']);
        $this->end_controls_section();
        $this->text_style('.obx-archive-title', 'Título');
    }
    protected function render(): void {
        printf('<%1$s class="obx-archive-title">%2$s</%1$s>', $this->tag(), esc_html(Context::archive_title()));
        if ($this->get_settings_for_display('description') === 'yes' && !Context::template()) {
            $description = get_the_archive_description();
            if ($description) { echo '<div class="obx-archive-description">' . wp_kses_post($description) . '</div>'; }
        }
    }
}

class Archive_Posts extends Base {
    public const CATEGORY = 'obx-archive';
    public const NAME = 'obx-archive-posts';
    public const PRO = 'archive-posts';
    public function get_title(): string { return 'Entradas del archivo'; }
    public function get_icon(): string { return 'eicon-archive-posts'; }
    public function get_keywords(): array { return ['posts','entradas','archive','archivo','loop','grid']; }

    public static function excerpt(\WP_Post $post, int $length): string {
        if (has_excerpt($post)) { return wp_strip_all_tags(get_the_excerpt($post)); }
        return wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), max(5, $length ?: 40));
    }

    /** Extra controls before the layout section (the Posts widget adds its query). */
    protected function query_controls(): void {}

    protected function query(): \WP_Query { return Context::query(); }

    protected function register_controls(): void {
        $this->query_controls();
        $this->start_controls_section('content', ['label'=>'Diseño']);
        $this->add_responsive_control('columns', ['label'=>'Columnas','type'=>Controls_Manager::SELECT,'default'=>'3','tablet_default'=>'2','mobile_default'=>'1',
            'options'=>['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6'],'selectors'=>['{{WRAPPER}} .obx-posts'=>'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));']]);
        $this->add_control('show_image', ['label'=>'Imagen','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('image_size', ['label'=>'Tamaño de imagen','type'=>Controls_Manager::SELECT,'options'=>array_combine(get_intermediate_image_sizes(), get_intermediate_image_sizes()) + ['full'=>'full'],'default'=>'medium_large','condition'=>['show_image'=>'yes']]);
        $this->add_control('title_tag', ['label'=>'Etiqueta del título','type'=>Controls_Manager::SELECT,'options'=>['h2'=>'H2','h3'=>'H3','h4'=>'H4','h5'=>'H5','p'=>'p'],'default'=>'h3']);
        $this->add_control('show_date', ['label'=>'Fecha','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('show_excerpt', ['label'=>'Extracto','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('excerpt_length', ['label'=>'Palabras del extracto','type'=>Controls_Manager::NUMBER,'min'=>5,'max'=>100,'default'=>25,'condition'=>['show_excerpt'=>'yes']]);
        $this->add_control('read_more', ['label'=>'Texto «Leer más»','type'=>Controls_Manager::TEXT,'default'=>'Leer más »']);
        $this->add_control('pagination', ['label'=>'Paginación','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','default'=>'yes']);
        $this->add_control('empty', ['label'=>'Mensaje sin resultados','type'=>Controls_Manager::TEXT,'default'=>'No se ha encontrado ningún contenido.','label_block'=>true]);
        $this->end_controls_section();

        $this->start_controls_section('cards_style', ['label'=>'Tarjetas','tab'=>Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('gap', ['label'=>'Separación','type'=>Controls_Manager::SLIDER,'size_units'=>['px','em'],'range'=>['px'=>['min'=>0,'max'=>120]],'default'=>['unit'=>'px','size'=>30],'selectors'=>['{{WRAPPER}} .obx-posts'=>'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('card_bg', ['label'=>'Fondo','type'=>Controls_Manager::COLOR,'selectors'=>['{{WRAPPER}} .obx-post'=>'background-color: {{VALUE}};']]);
        $this->add_responsive_control('card_padding', ['label'=>'Relleno','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','em'],'selectors'=>['{{WRAPPER}} .obx-post__body'=>'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'card_border','selector'=>'{{WRAPPER}} .obx-post']);
        $this->add_control('card_radius', ['label'=>'Radio','type'=>Controls_Manager::DIMENSIONS,'size_units'=>['px','%'],'selectors'=>['{{WRAPPER}} .obx-post'=>'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;']]);
        $this->end_controls_section();
        $this->text_style('.obx-post__title', 'Título', 'title', false);
        $this->text_style('.obx-post__meta', 'Fecha', 'meta', false);
        $this->text_style('.obx-post__excerpt', 'Extracto', 'excerpt', false);
        $this->text_style('.obx-post__more', 'Leer más', 'more', false);
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $query = $this->query();
        if (!$query->have_posts()) { echo '<p class="obx-posts-empty">' . esc_html($s['empty']) . '</p>'; return; }
        $tag = in_array($s['title_tag'], ['h2','h3','h4','h5','p'], true) ? $s['title_tag'] : 'h3';
        echo '<div class="obx-posts">';
        foreach ($query->posts as $post) {
            $url = esc_url(get_permalink($post));
            echo '<article class="obx-post">';
            if ($s['show_image'] === 'yes' && has_post_thumbnail($post)) {
                echo '<a class="obx-post__image" href="' . $url . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail($post, $s['image_size'] ?: 'medium_large', ['loading'=>'lazy','alt'=>'']) . '</a>';
            }
            echo '<div class="obx-post__body"><' . $tag . ' class="obx-post__title"><a href="' . $url . '">' . esc_html(get_the_title($post)) . '</a></' . $tag . '>';
            if ($s['show_date'] === 'yes') { echo '<div class="obx-post__meta"><time datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(get_the_date('', $post)) . '</time></div>'; }
            if ($s['show_excerpt'] === 'yes') { echo '<p class="obx-post__excerpt">' . esc_html(self::excerpt($post, (int) $s['excerpt_length'])) . '</p>'; }
            if ($s['read_more'] !== '') { echo '<a class="obx-post__more" href="' . $url . '">' . esc_html($s['read_more']) . '</a>'; }
            echo '</div></article>';
        }
        echo '</div>';
        if ($s['pagination'] === 'yes' && $query->max_num_pages > 1) {
            $links = paginate_links(['total'=>$query->max_num_pages,'current'=>max(1, (int) get_query_var('paged'), (int) get_query_var('page')),'prev_text'=>'«','next_text'=>'»','type'=>'plain']);
            if ($links) { echo '<nav class="obx-pagination" aria-label="Paginación">' . $links . '</nav>'; }
        }
    }
}
