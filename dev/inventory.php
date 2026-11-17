<?php
// Read-only inventory of Elementor registries. Run with: wp eval-file inventory.php > out.json
// Lists names, groups and control keys only (no code).
use Elementor\Plugin;

$el  = Plugin::$instance;
if ( class_exists( '\Elementor\Core\Frontend\Performance' ) && property_exists( '\Elementor\Core\Frontend\Performance', 'is_frontend' ) ) {
	$pf = new ReflectionProperty( '\Elementor\Core\Frontend\Performance', 'is_frontend' );
	$pf->setAccessible( true );
	$pf->setValue( null, false );
}
add_filter( 'wp_die_handler', fn() => function ( $m ) { throw new RuntimeException( is_string( $m ) ? $m : 'wp_die' ); }, PHP_INT_MAX );
$out = [ 'versions' => [
	'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
	'pro'       => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
	'obx'       => defined( 'OBX_VERSION' ) ? OBX_VERSION : null,
	'wc'        => defined( 'WC_VERSION' ) ? WC_VERSION : null,
] ];

$src = function ( $obj ) {
	$f = ( new ReflectionClass( $obj ) )->getFileName();
	if ( false !== strpos( $f, '/elementor-pro/' ) ) return 'pro';
	if ( false !== strpos( $f, '/oldbear-elementor-expansion/' ) ) return 'obx';
	if ( false !== strpos( $f, '/plugins/elementor/' ) ) return 'free';
	return 'other';
};

$controls_of = function ( $stack ) {
	$res = [];
	try { $controls = $stack->get_controls(); } catch ( Throwable $e ) { return [ '__error' => $e->getMessage() ]; }
	foreach ( $controls as $key => $c ) {
		$t = $c['type'] ?? '';
		if ( in_array( $t, [ 'section', 'tab', 'heading', 'divider', 'raw_html', 'deprecated_notice', 'alert', 'notice' ], true ) ) {
			if ( 'section' === $t ) $res[ $key ] = [ 'type' => 'section', 'tab' => $c['tab'] ?? '' ];
			continue;
		}
		$row = [ 'type' => $t, 'section' => $c['section'] ?? '' ];
		if ( array_key_exists( 'default', $c ) && '' !== $c['default'] && [] !== $c['default'] ) $row['default'] = $c['default'];
		if ( ! empty( $c['condition'] ) ) $row['condition'] = $c['condition'];
		if ( ! empty( $c['conditions'] ) ) $row['conditions'] = $c['conditions'];
		if ( ! empty( $c['responsive'] ) ) $row['responsive'] = true;
		if ( isset( $c['size_units'] ) ) $row['size_units'] = $c['size_units'];
		if ( ! empty( $c['options'] ) && is_array( $c['options'] ) && in_array( $t, [ 'select', 'select2', 'choose' ], true ) ) {
			$row['options'] = array_map( 'strval', array_keys( $c['options'] ) );
		}
		if ( 'repeater' === $t && ! empty( $c['fields'] ) ) {
			$row['fields'] = [];
			foreach ( $c['fields'] as $fk => $f ) {
				$fk = is_string( $fk ) ? $fk : ( $f['name'] ?? '' );
				$fr = [ 'type' => $f['type'] ?? '' ];
				if ( array_key_exists( 'default', $f ) && '' !== $f['default'] ) $fr['default'] = $f['default'];
				if ( ! empty( $f['condition'] ) ) $fr['condition'] = $f['condition'];
				if ( ! empty( $f['options'] ) && is_array( $f['options'] ) ) $fr['options'] = array_map( 'strval', array_keys( $f['options'] ) );
				$row['fields'][ $fk ] = $fr;
			}
		}
		if ( ! empty( $c['dynamic']['active'] ) ) $row['dynamic'] = true;
		$res[ $key ] = $row;
	}
	return $res;
};

// Widgets.
$out['widgets'] = [];
foreach ( $el->widgets_manager->get_widget_types() as $name => $w ) {
	$s = $src( $w );
	if ( 'free' === $s && ! isset( $_SERVER['INV_ALL'] ) ) {
		$out['widgets'][ $name ] = [ 'src' => 'free', 'title' => $w->get_title(), 'categories' => $w->get_categories() ];
		continue;
	}
	$out['widgets'][ $name ] = [
		'src'        => $s,
		'title'      => $w->get_title(),
		'categories' => $w->get_categories(),
		'controls'   => $controls_of( $w ),
	];
}

// Elements (containers, atomic elements…).
$out['elements'] = [];
foreach ( $el->elements_manager->get_element_types() as $name => $e ) {
	$out['elements'][ $name ] = [ 'src' => $src( $e ), 'controls' => $controls_of( $e ) ];
}
// Common (advanced tab of widgets).
foreach ( [ 'common', 'common-optimized', 'common-base' ] as $n ) {
	$w = $el->widgets_manager->get_widget_types( $n );
	if ( $w ) $out['elements'][ '__' . $n ] = [ 'src' => $src( $w ), 'controls' => $controls_of( $w ) ];
}

// Dynamic tags.
$out['dynamic_tags'] = [];
foreach ( $el->dynamic_tags->get_tags() as $name => $t ) {
	$cls = $t['class'] ?? '';
	$inst = $t['instance'] ?? null;
	$file = $cls && class_exists( $cls ) ? ( new ReflectionClass( $cls ) )->getFileName() : '';
	$out['dynamic_tags'][ $name ] = [
		'src'        => false !== strpos( $file, '/elementor-pro/' ) ? 'pro' : ( false !== strpos( $file, '/oldbear-elementor-expansion/' ) ? 'obx' : 'free' ),
		'group'      => $inst ? $inst->get_group() : '',
		'categories' => $inst ? $inst->get_categories() : [],
		'title'      => $inst ? $inst->get_title() : '',
	];
}
$out['dynamic_groups'] = array_keys( (array) $el->dynamic_tags->get_config()['groups'] ?? [] );

// Documents.
$out['documents'] = [];
foreach ( $el->documents->get_document_types() as $name => $cls ) {
	$file = ( new ReflectionClass( $cls ) )->getFileName();
	$row  = [ 'src' => false !== strpos( $file, '/elementor-pro/' ) ? 'pro' : ( false !== strpos( $file, '/oldbear-elementor-expansion/' ) ? 'obx' : 'free' ) ];
	try {
		$doc = new $cls( [ 'post_id' => 0 ] );
	} catch ( Throwable $e ) { $doc = null; }
	if ( $doc && method_exists( $doc, 'get_controls' ) ) {
		try { $row['controls'] = $controls_of( $doc ); } catch ( Throwable $e ) { $row['controls_error'] = $e->getMessage(); }
	}
	$out['documents'][ $name ] = $row;
}

// Kit (site settings) tabs.
$out['kit_tabs'] = [];
$kit = $el->kits_manager->get_active_kit();
if ( $kit && method_exists( $kit, 'get_tabs' ) ) {
	foreach ( $kit->get_tabs() as $id => $tab ) $out['kit_tabs'][ $id ] = $src( $tab );
}
if ( $kit ) $out['kit_controls'] = array_keys( $kit->get_controls() );

// Experiments.
$out['experiments'] = [];
foreach ( $el->experiments->get_features() as $n => $f ) {
	$out['experiments'][ $n ] = [ 'state' => $f['state'] ?? '', 'default' => $f['default'] ?? '', 'tag' => $f['tag'] ?? '' ];
}

// Pro-only registries when present.
if ( class_exists( '\ElementorPro\Modules\Forms\Module' ) ) {
	$m = \ElementorPro\Modules\Forms\Module::instance();
	$out['form_actions'] = isset( $m->actions_registrar ) ? array_keys( $m->actions_registrar->get() ) : [];
	$out['form_fields']  = isset( $m->fields_registrar ) ? array_keys( $m->fields_registrar->get() ) : [];
}
if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
	$cm = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
	$names = [];
	foreach ( (array) $cm->get_conditions_config() as $n => $c ) $names[ $n ] = [ 'label' => $c['label'] ?? '', 'sub' => array_values( (array) ( $c['sub_conditions'] ?? [] ) ) ];
	$out['tb_conditions'] = $names;
	$out['tb_locations']  = array_keys( \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_locations_manager()->get_locations() );
}
if ( class_exists( '\ElementorPro\Modules\Popup\Module' ) ) {
	// Popup triggers/timing are document controls; captured under documents.popup.
}

// Post types, taxonomies, REST namespaces, shortcodes, CLI, caps, options.
$out['post_types'] = array_values( array_filter( get_post_types(), fn( $p ) => preg_match( '/^(elementor|e-|e_)/', $p ) ) );
$out['taxonomies'] = array_values( array_filter( get_taxonomies(), fn( $p ) => preg_match( '/^(elementor|e-|e_)/', $p ) ) );
$out['rest'] = array_values( array_unique( array_map( fn( $r ) => preg_replace( '#^/([^/]+(/v\d+)?).*#', '$1', $r ), array_keys( rest_get_server()->get_routes() ) ) ) );
$out['rest_routes'] = array_values( array_filter( array_keys( rest_get_server()->get_routes() ), fn( $r ) => preg_match( '#^/(elementor|elementor-pro|obx)#', $r ) ) );
global $shortcode_tags;
$out['shortcodes'] = array_values( array_filter( array_keys( $shortcode_tags ), fn( $s ) => preg_match( '/elementor|obx/', $s ) ) );

// Admin menu (simulate admin).
$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
if ( $admins && ! getenv( "INV_NO_ADMIN" ) ) {
	wp_set_current_user( $admins[0]->ID );
	if ( ! function_exists( 'add_menu_page' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
	global $menu, $submenu, $_wp_submenu_nopriv, $_registered_pages;
	$menu = $submenu = [];
	set_current_screen( 'dashboard' );
	try { do_action( 'admin_menu', '' ); } catch ( Throwable $e ) {}
	$out['admin_submenu'] = [];
	foreach ( (array) $submenu as $parent => $items ) {
		if ( ! preg_match( '/elementor|obx|edit\.php\?post_type=elementor/', $parent ) ) continue;
		foreach ( $items as $it ) $out['admin_submenu'][ $parent ][] = wp_strip_all_tags( $it[0] ) . ' => ' . $it[2];
	}
}

echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
