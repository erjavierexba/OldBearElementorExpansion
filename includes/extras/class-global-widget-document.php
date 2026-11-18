<?php
namespace OldBearExpansion\Extras;

use Elementor\Core\Base\Document;
use Elementor\Modules\Library\Documents\Library_Document;
use Elementor\User;

if (!defined('ABSPATH')) { exit; }

/** Library document `widget`: the template behind a global widget (Pro's type and properties). */
final class Global_Widget_Document extends Library_Document {
    public const EXPORT_GROUP = 'global-widget';

    public static function get_properties() {
        $properties = parent::get_properties();
        $properties['admin_tab_group'] = 'library';
        $properties['show_in_library'] = false;
        $properties['is_editable'] = false;
        $properties['show_in_finder'] = false;
        return $properties;
    }

    public function get_name() { return Global_Widgets::TYPE; }

    public static function get_title() { return 'Widget global'; }

    public static function get_plural_title() { return 'Widgets globales'; }

    public function is_editable_by_current_user() { return User::is_current_user_can_edit($this->get_main_id()); }

    public function import(array $data) {
        parent::import($data);
        if (!empty($data['content'][0]['widgetType'])) { $this->update_main_meta(Global_Widgets::WIDGET_TYPE_META, (string) $data['content'][0]['widgetType']); }
    }

    /** Always published: a global widget has no drafts of its own. */
    public function save($data) {
        $data['settings'] = ['post_status'=>Document::STATUS_PUBLISH];
        $saved = parent::save($data);
        Global_Widgets::forget((int) $this->get_main_id());
        if (!empty($data['elements'][0]['widgetType'])) { $this->update_main_meta(Global_Widgets::WIDGET_TYPE_META, (string) $data['elements'][0]['widgetType']); }
        return $saved;
    }
}
