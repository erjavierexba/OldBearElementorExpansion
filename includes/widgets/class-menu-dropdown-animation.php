<?php
namespace OldBearExpansion\Widgets;

if (!defined('ABSPATH')) { exit; }

/** «Animation» of the Menu widget's dropdowns: Pro's control type with its single option. */
final class Menu_Dropdown_Animation extends \Elementor\Control_Hover_Animation {
    public const TYPE = 'animation_menu_dropdown';
    public function get_type(): string { return self::TYPE; }
    public static function get_animations(): array { return ['fadeIn'=>'Aparecer']; }
}
