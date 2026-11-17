<?php
namespace OldBearExpansion\Admin;

use Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_Interface;
use Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_Third_Level_Interface;
use Elementor\Core\Admin\EditorOneMenu\Interfaces\Menu_Item_With_Custom_Url_Interface;

if (!defined('ABSPATH')) { exit; }

/** A level-three group in Elementor's 2026 editor menu. */
final class Menu_Parent implements Menu_Item_Third_Level_Interface {
    public function __construct(private string $slug, private string $label, private string $group, private string $icon, private int $position) {}
    public function get_capability(): string { return 'manage_options'; }
    public function get_label(): string { return $this->label; }
    public function get_parent_slug(): string { return 'elementor'; }
    public function is_visible(): bool { return true; }
    public function get_position(): int { return $this->position; }
    public function get_slug(): string { return $this->slug; }
    public function get_group_id(): string { return $this->group; }
    public function get_icon(): string { return $this->icon; }
    public function has_children(): bool { return true; }
}

/** A clickable level-four item inside an Elementor editor-menu group. */
final class Menu_Child implements Menu_Item_Interface, Menu_Item_With_Custom_Url_Interface {
    public function __construct(private string $slug, private string $label, private string $url, private string $group, private int $position, private string $capability = 'manage_options') {}
    public function get_capability(): string { return $this->capability; }
    public function get_label(): string { return $this->label; }
    public function get_parent_slug(): string { return 'elementor'; }
    public function is_visible(): bool { return true; }
    public function get_position(): int { return $this->position; }
    public function get_slug(): string { return $this->slug; }
    public function get_group_id(): string { return $this->group; }
    public function get_menu_url(): string { return $this->url; }
}
