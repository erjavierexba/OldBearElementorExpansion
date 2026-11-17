<?php
/** Dynamic tags, loops, popups, extras, media widgets and optional WooCommerce. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }

use Elementor\Plugin as Elementor;
use OldBearExpansion\Admin\Dashboard;
use OldBearExpansion\ThemeBuilder\Location_Document;

$checks=0; $created=[]; $grid_id='';
$assert=static function($ok,string $message)use(&$checks){if(!$ok){fwrite(STDERR,"Fallo: {$message}\n");throw new RuntimeException($message);}$checks++;};
$uid=static fn()=>substr(str_replace('-','',wp_generate_uuid4()),0,7);
$widget=static fn(string $type,array $settings=[],array $elements=[])=>['id'=>$uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>$elements];
$container=static fn(array $elements,array $settings=[])=>['id'=>$uid(),'elType'=>'container','isInner'=>false,'settings'=>$settings,'elements'=>$elements];
$admin=get_users(['role'=>'administrator','number'=>1]); wp_set_current_user($admin[0]->ID);
$save=static function(string $type,string $title,array $elements,array $settings=[])use(&$created){$d=Elementor::$instance->documents->create($type,['post_title'=>$title,'post_status'=>'publish']);if(is_wp_error($d)){throw new RuntimeException($d->get_error_message());}$id=(int)$d->get_main_id();$created[]=$id;$d->set_is_built_with_elementor(true);if(!$d->save(['elements'=>$elements,'settings'=>$settings])){throw new RuntimeException('No se pudo guardar '.$type);}return $id;};

$errors=[]; set_error_handler(static function($n,$m,$f,$l)use(&$errors){if(str_contains($f,'oldbear-elementor-expansion')){$errors[]="$m ($f:$l)";}return false;});
try{
    $types=Elementor::$instance->widgets_manager->get_widget_types();
    foreach(['loop-grid','loop-carousel','taxonomy-filter','slides','media-carousel'] as $name){$assert(isset($types[$name]),"Falta {$name}.");}
    foreach(['loop-item','popup','product','product-archive'] as $name){$assert((bool)Elementor::$instance->documents->get_document_type($name,false),"Falta el documento {$name}.");}
    $native=Elementor::$instance->widgets_manager->get_widget_types()['heading']->get_controls();
    foreach(['custom_css','_attributes','sticky','motion_fx_motion_fx_scrolling','motion_fx_motion_fx_mouse'] as $control){$assert(isset($native[$control]),"Falta el control avanzado {$control} en widgets nativos.");}

    foreach([Dashboard::FONT,Dashboard::ICON,Dashboard::CODE] as $post_type){$assert(post_type_exists($post_type),"Falta la herramienta administrativa {$post_type}.");}
    $provider=new class{public array $items=[];public function register_menu($item):void{$this->items[]=$item;}};
    Dashboard::editor_menu($provider);$labels=array_map(static fn($item)=>$item->get_label(),$provider->items);
    foreach(['Elementos personalizados','Fuentes','Iconos','Código','Theme Builder','Popups'] as $label){$assert(in_array($label,$labels,true),"Falta {$label} en el menú de Elementor.");}
    ob_start();Dashboard::render_dashboard();$dashboard_html=ob_get_clean();
    foreach(['Theme Builder','Popups','Envíos','Fuentes','Iconos','Código','Administrador de roles'] as $label){$assert(str_contains($dashboard_html,$label),"Falta {$label} en el panel de inicio de WordPress.");}
    $remote=\OldBearExpansion\Clean\Clean_Mode::remote_info(['pro_widgets'=>['locked'],'admin_notice'=>['title'=>'Oferta']]);
    $assert($remote['pro_widgets']===[]&&!isset($remote['admin_notice']),'El modo limpio debe retirar la publicidad remota del escritorio.');
    $safe_svg=Dashboard::sanitize_svg('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><path d="M0 0"/></svg>');
    $assert($safe_svg!==''&&!str_contains($safe_svg,'script')&&!str_contains($safe_svg,'onload'),'Los SVG personalizados deben sanear scripts y eventos.');
    $font=wp_insert_post(['post_type'=>Dashboard::FONT,'post_status'=>'publish','post_title'=>'OBX Test Font']);$created[]=$font;update_post_meta($font,'_obx_font_url','https://example.org/test.woff2');
    $icon=wp_insert_post(['post_type'=>Dashboard::ICON,'post_status'=>'publish','post_title'=>'OBX Paw']);$created[]=$icon;update_post_meta($icon,'_obx_icon_url','https://example.org/paw.svg');
    $code=wp_insert_post(['post_type'=>Dashboard::CODE,'post_status'=>'publish','post_title'=>'OBX Test Code']);$created[]=$code;update_post_meta($code,'_obx_code_location','head');update_post_meta($code,'_obx_code_condition','entire_site');update_post_meta($code,'_obx_code_priority',5);update_post_meta($code,'_obx_code','<meta name="obx-code-test" content="ok">');
    $fonts=Dashboard::fonts([]);$tabs=Dashboard::icon_tabs([]);ob_start();Dashboard::font_styles();$font_css=ob_get_clean();ob_start();Dashboard::print_code('head');$custom_code=ob_get_clean();
    $assert(($fonts['OBX Test Font']??'')==='obx-custom'&&str_contains($font_css,'@font-face'),'Las fuentes personalizadas deben registrarse y generar CSS.');
    $assert(isset($tabs['obx-custom-icons'])&&in_array('obx-paw',$tabs['obx-custom-icons']['icons'],true),'Los iconos personalizados deben entrar en la biblioteca de Elementor.');
    $assert(str_contains($custom_code,'obx-code-test'),'El código personalizado debe respetar ubicación y condición.');
    ob_start();Dashboard::role_control('editor',[]);$role_html=ob_get_clean();$assert(str_contains($role_html,'value="design"'),'El Administrador de roles debe ofrecer edición de solo contenido.');

    $cat=wp_insert_term('obx-loop-'.$uid(),'category'); $term=(int)$cat['term_id'];
    $posts=[]; foreach(['Uno','Dos','Tres'] as $title){$id=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Bucle '.$title,'post_content'=>'Contenido '.$title,'post_category'=>[$term]]);$created[]=$id;$posts[]=$id;}
    $dynamic='[elementor-tag id="" name="post-title" settings="%7B%7D"]';
    $loop=$save('loop-item','Tarjeta bucle',[$container([$widget('heading',['title'=>'Reserva','header_size'=>'h3','__dynamic__'=>['title'=>$dynamic]])])],[Location_Document::PREVIEW=>(string)$posts[0]]);
    update_post_meta($loop,'_elementor_template_type','loop-item');

    $grid_id='grid'.$uid();
    $page=$save('page','Prueba bucles',[$container([
        ['id'=>$grid_id,'elType'=>'widget','widgetType'=>'loop-grid','settings'=>['template_id'=>(string)$loop,'columns'=>'2','posts_per_page'=>2,'post_query_post_type'=>'post','post_query_include'=>['terms'],'post_query_include_term_ids'=>[(string)$term],'post_query_orderby'=>'post_title','post_query_order'=>'asc','pagination_type'=>'numbers'],'elements'=>[]],
        $widget('loop-carousel',['template_id'=>(string)$loop,'posts_per_page'=>3,'post_query_post_type'=>'post','post_query_include'=>['terms'],'post_query_include_term_ids'=>[(string)$term],'slides_to_show'=>'2','arrows'=>'','pagination'=>'bullets']),
        $widget('taxonomy-filter',['selected_element'=>$grid_id,'taxonomy'=>'category','show_first_item'=>'yes','first_item_title'=>'Todos'])
    ])]);
    $html=(string)Elementor::$instance->frontend->get_builder_content_for_display($page);
    $assert(substr_count($html,'class="e-loop-item ')>=5,'Loop Grid y Loop Carousel deben renderizar sus plantillas.');
    $assert(str_contains($html,'Bucle Uno')&&str_contains($html,'Bucle Dos')&&!str_contains($html,'>Reserva<'),'Las etiquetas dinámicas deben resolver el post de cada elemento.');
    $assert(str_contains($html,'class="obx-tfilter"')&&str_contains($html,'Todos'),'El filtro de taxonomías debe renderizarse.');

    $_GET['e-filter-'.$grid_id.'-category']=get_term($term)->slug;
    $html=(string)Elementor::$instance->frontend->get_builder_content_for_display($page); unset($_GET['e-filter-'.$grid_id.'-category']);
    $assert(substr_count($html,'class="e-loop-item ')>=5,'El filtro debe conservar los resultados del término elegido.');

    $media=$save('page','Prueba medios',[$container([
        $widget('slides',['slides'=>[['_id'=>'a1','heading'=>'Diapositiva segura','description'=>'Descripción','button_text'=>'Entrar','link'=>['url'=>'https://example.org/']]],'show_arrows'=>'','pagination'=>'']),
        $widget('media-carousel',['slides'=>[['_id'=>'m1','image'=>['url'=>'https://example.org/image.jpg'],'video_url'=>['url'=>'https://example.org/video.mp4']]],'show_arrows'=>'','pagination'=>''])
    ])]);
    $html=(string)Elementor::$instance->frontend->get_builder_content_for_display($media);
    $assert(str_contains($html,'obx-slides')&&str_contains($html,'Diapositiva segura'),'Diapositivas debe renderizar contenido y enlace.');
    $assert(str_contains($html,'obx-media')&&str_contains($html,'image.jpg'),'Carrusel de medios debe renderizar imágenes y vídeos.');

    $extras=$save('page','Prueba extras',[$container([$widget('heading',['title'=>'CSS y atributos','custom_css'=>'selector .elementor-heading-title{color:rgb(1,2,3)}','_attributes'=>"data-test|correcto\nonclick|alert(1)\nhref|javascript:alert(1)",'sticky'=>'top','sticky_on'=>['desktop'],'motion_fx_motion_fx_scrolling'=>'yes','motion_fx_opacity_effect'=>'yes'])])]);
    $html=(string)Elementor::$instance->frontend->get_builder_content_for_display($extras);
    $css=new \Elementor\Core\Files\CSS\Post($extras);$css->update();$content=(string)$css->get_content();
    $assert(str_contains($html,'data-test="correcto"')&&!str_contains($html,'onclick=')&&!str_contains($html,'javascript:'),'Los atributos personalizados deben filtrar vectores de script.');
    $assert(str_contains($content,'rgb(1,2,3)')&&str_contains($html,'data-settings='),'CSS personalizado y ajustes de efectos deben llegar al frontend.');

    $popup=$save('popup','Popup de prueba',[$container([$widget('heading',['title'=>'Contenido emergente'])])],['obx_page_load'=>'yes','obx_page_load_delay'=>0,'obx_show_times'=>2,'overlay'=>'yes','close_button'=>'yes']);
    Location_Document::store_conditions($popup,[['mode'=>'include','rule'=>'general','ids'=>[]]]);
    \OldBearExpansion\Popups\Popups::enqueue($popup); ob_start(); \OldBearExpansion\Popups\Popups::render(); $html=ob_get_clean();
    $assert(str_contains($html,'data-obx-popup="'.$popup.'"')&&str_contains($html,'Contenido emergente'),'El popup debe renderizar el documento publicado.');
    $assert(str_contains($html,'page_load')&&str_contains($html,'data-popup-close'),'El popup debe incluir disparadores y cierre accesible.');

    if(class_exists('WooCommerce')){
        $product_id=wp_insert_post(['post_type'=>'product','post_status'=>'publish','post_title'=>'Producto de prueba','post_content'=>'Descripción completa','post_excerpt'=>'Descripción corta']);$created[]=$product_id;
        $product=wc_get_product($product_id);$product->set_regular_price('19.95');$product->set_sku('OBX-TEST');$product->set_manage_stock(true);$product->set_stock_quantity(4);$product->save();
        global $wp_query,$wp_the_query;$wp_the_query=$wp_query=new WP_Query(['p'=>$product_id,'post_type'=>'product']);$wp_query->the_post();
        $woo=$save('page','Prueba WooCommerce',[$container([$widget('woocommerce-product-title'),$widget('woocommerce-product-price'),$widget('woocommerce-product-stock'),$widget('woocommerce-product-meta'),$widget('woocommerce-product-short-description'),$widget('woocommerce-product-add-to-cart')])]);
        $html=(string)Elementor::$instance->frontend->get_builder_content_for_display($woo);
        $assert(str_contains($html,'Producto de prueba')&&str_contains($html,'19')&&str_contains($html,'OBX-TEST'),'Los widgets de producto deben usar el producto actual.');
        $assert(str_contains($html,'single_add_to_cart_button')&&str_contains($html,'Descripción corta'),'Añadir al carrito y descripción corta deben renderizarse.');
        wp_reset_postdata();
    }
    $assert(!$errors,'Avisos PHP: '.implode(' | ',array_slice($errors,0,4)));
}finally{
    restore_error_handler(); unset($_GET['e-filter-'.$grid_id.'-category']);
    foreach(array_reverse($created)as$id){if(get_post($id)){wp_delete_post($id,true);}}
    if(isset($term)&&term_exists($term,'category')){wp_delete_term($term,'category');}
}
WP_CLI::success("{$checks} pruebas de funciones ampliadas superadas.");
