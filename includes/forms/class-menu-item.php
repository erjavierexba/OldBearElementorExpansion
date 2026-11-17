<?php
namespace OldBearExpansion\Forms;

use Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_Third_Level_Interface;
use Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_With_Custom_Url_Interface;

if (!defined('ABSPATH')) { exit; }

/** «Envíos» entry in Elementor's admin menu (the slot Elementor keeps for form submissions). */
final class Menu_Item implements Menu_Item_Third_Level_Interface, Menu_Item_With_Custom_Url_Interface {
    public function get_capability(): string { return 'manage_options'; }
    public function get_label(): string { return 'Envíos'; }
    public function get_parent_slug(): string { return 'elementor'; }
    public function is_visible(): bool { return true; }
    public function get_position(): int { return 50; }
    public function get_slug(): string { return 'edit.php?post_type=' . Handler::POST_TYPE; }
    public function get_group_id(): string { return 'elementor-editor-items'; }
    public function get_icon(): string { return 'send'; }
    public function has_children(): bool { return false; }
    public function get_menu_url(): string { return admin_url('edit.php?post_type=' . Handler::POST_TYPE); }
}
