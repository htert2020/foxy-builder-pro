<?php

namespace FoxyBuilderPro\Admin;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDERPRO_PLUGIN_PATH . '/admin/menu/custom-fonts.php';

class Admin
{
    private static $_instance = null;
    
    public static function instance()
    {
        if (self::$_instance === null)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    protected function __construct()
    {
    }

    public function init()
    {
        add_action('foxybdr_admin_menu', [ $this, 'action_foxybdrp_menu' ]);

        \FoxyBuilderPro\Admin\Menu\CustomFonts::instance()->init();
    }

    public function action_foxybdrp_menu($after_page_name)
    {
        switch ($after_page_name)
        {
            case 'foxybuilder':
                \FoxyBuilderPro\Admin\Menu\CustomFonts::instance()->admin_menu();
                break;
        }
    }
}
