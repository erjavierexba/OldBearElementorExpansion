<?php
namespace OldBearExpansion\ThemeBuilder;

if (!defined('ABSPATH')) { exit; }

/** One-off data migrations between plugin versions. */
final class Upgrade {
    public const OPTION = 'obx_db_version';
    public const VERSION = 2;

    /** Template types used before v0.4.0 → Elementor Pro's types. */
    public const LEGACY_TYPES = ['obx-header'=>'header','obx-footer'=>'footer','obx-single'=>'single-post','obx-archive'=>'archive','obx-error-404'=>'error-404'];

    public static function boot(): void {
        add_action('init', [self::class, 'maybe_run'], 20);
    }

    public static function maybe_run(): void {
        if ((int) get_option(self::OPTION, 0) >= self::VERSION) { return; }
        self::run();
    }

    /** @return int templates migrated */
    public static function run(): int {
        $migrated = 0;
        $posts = get_posts(['post_type'=>'elementor_library','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids',
            'meta_query'=>[['key'=>'_elementor_template_type','value'=>array_keys(self::LEGACY_TYPES),'compare'=>'IN']]]);
        foreach ($posts as $id) {
            $type = self::LEGACY_TYPES[get_post_meta($id, '_elementor_template_type', true)] ?? '';
            if ($type === '') { continue; }
            update_post_meta($id, '_elementor_template_type', $type);
            wp_set_object_terms($id, $type, 'elementor_library_type');
            $settings = get_post_meta($id, '_elementor_page_settings', true);
            $rows = is_array($settings) && isset($settings[Location_Document::CONDITIONS]) ? $settings[Location_Document::CONDITIONS] : null;
            // Before v0.4.0 an untouched template used the default rule of its location.
            $conditions = $rows === null ? [['mode'=>'include','rule'=>Conditions::default_rule(Module::TYPES[$type]::LOCATION),'ids'=>[]]] : Conditions::normalize($rows);
            Location_Document::store_conditions((int) $id, $conditions);
            $migrated++;
        }
        update_option(self::OPTION, self::VERSION, true);
        Locations::flush();
        return $migrated;
    }
}
