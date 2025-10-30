<?php

namespace FoxyBuilderPro\Admin\Menu;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDER_PLUGIN_PATH . '/includes/security.php';

class CustomFonts
{
    private $page_loading = false;

    private $sub_page = null;

    public $menu_short_name = null;

    public $menu_long_name = null;

    const FONT_MIME_TYPES = [
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'eot'   => 'application/vnd.ms-fontobject',
        'ttf'   => 'font/sfnt',
    ];

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
        add_action('in_admin_header', [ $this, 'action_in_admin_header' ]);
        add_filter('upload_mimes', [ $this, 'filter_upload_mimes' ]);
    }

    public function admin_menu()
    {
        $capability = 'edit_pages';
        $app_title = __('Foxy Builder', 'foxy-builder-pro');
        $page_label = __('Custom Fonts', 'foxy-builder-pro');
        $this->menu_short_name = 'foxybuilder_custom_fonts';

        $this->menu_long_name = add_submenu_page(
            'foxybuilder',
            "{$app_title} - {$page_label}",
            $page_label,
            $capability,
            $this->menu_short_name,
            [ $this, 'print_page' ]
        );

        add_action('admin_print_scripts-' . $this->menu_long_name, [ $this, 'enqueue' ]);
    }

    public function enqueue()
    {
        $this->page_loading = true;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style('foxybdr-admin-includes-notice', FOXYBUILDER_PLUGIN_URL . "admin/assets/css/includes-notice.css", [], FOXYBUILDER_VERSION);
        wp_enqueue_style('foxybdr-admin-includes-header-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap', [], FOXYBUILDER_VERSION);
        wp_enqueue_style('foxybdr-admin-includes-header', FOXYBUILDER_PLUGIN_URL . "admin/assets/css/includes-header.css", [], FOXYBUILDER_VERSION);

        $this->sub_page = \FoxyBuilder\Includes\Security::sanitize_request($_GET, 'foxybdr_subpage');

        switch ($this->sub_page)
        {
            case null:

                wp_enqueue_style('foxybdr-admin-includes-table', FOXYBUILDER_PLUGIN_URL . "admin/assets/css/includes-table.css", [], FOXYBUILDER_VERSION);
                wp_enqueue_script('foxybdr-admin-includes-table', FOXYBUILDER_PLUGIN_URL . 'admin/assets/js/includes-table' . $suffix . '.js', [], FOXYBUILDER_VERSION);

                wp_enqueue_style('foxybdrp-admin-menu-custom-fonts', FOXYBUILDERPRO_PLUGIN_URL . "admin/assets/css/menu/custom-fonts.css", [], FOXYBUILDERPRO_VERSION);
                wp_enqueue_script('foxybdrp-admin-menu-custom-fonts', FOXYBUILDERPRO_PLUGIN_URL . 'admin/assets/js/menu/custom-fonts' . $suffix . '.js', [], FOXYBUILDERPRO_VERSION);
                wp_localize_script('foxybdrp-admin-menu-custom-fonts', 'FOXYAPP', [
                    'dialogs' => [
                        'delete' => [
                            'title' => __('Delete', 'foxy-builder-pro'),
                            'message' => __('Are you sure you want to delete the custom font', 'foxy-builder-pro'),
                            'cancelLabel' => __('Cancel', 'foxy-builder-pro'),
                            'confirmLabel' => __('Confirm', 'foxy-builder-pro'),
                        ],
                    ],
                ]);

                break;

            case 'edit':

                // enqueue js for wp.media (WordPress Media Uploader)
                wp_enqueue_media();

                wp_enqueue_style('foxybdr-admin-includes-panel', FOXYBUILDER_PLUGIN_URL . "admin/assets/css/includes-panel.css", [], FOXYBUILDER_VERSION);

                wp_enqueue_style('foxybdrp-admin-menu-custom-fonts-edit', FOXYBUILDERPRO_PLUGIN_URL . "admin/assets/css/menu/custom-fonts-edit.css", [], FOXYBUILDERPRO_VERSION);
                wp_enqueue_script('foxybdrp-admin-menu-custom-fonts-edit', FOXYBUILDERPRO_PLUGIN_URL . 'admin/assets/js/menu/custom-fonts-edit' . $suffix . '.js', [], FOXYBUILDERPRO_VERSION);
                wp_localize_script('foxybdrp-admin-menu-custom-fonts-edit', 'FOXYAPP', [
                    'dialogs' => [
                        'mediaUploader' => [
                            'title' =>  __('Upload Font File', 'foxy-builder-pro'),
                            'buttonText' => __('Select File', 'foxy-builder-pro'),
                        ],
                        'validationError' => [
                            'missingTitle' => [
                                'title' => __('Missing Data', 'foxy-builder-pro'),
                                'message' => __('Please fill in the font name.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                            'missingVariations' => [
                                'title' => __('Missing Data', 'foxy-builder-pro'),
                                'message' => __('Please provide at least one font variation.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                            'missingFileUpload' => [
                                'title' => __('Missing Data', 'foxy-builder-pro'),
                                'message' => __('Please upload or select a file.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                        ],
                    ],
                    'mimeTypes' => self::FONT_MIME_TYPES,
                ]);

                break;
        }
    }

    public function action_in_admin_header()
    {
        if ($this->page_loading)
        {
            require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/header.php';

            \FoxyBuilder\Admin\Includes\Header::instance()->set_title(__('Custom Fonts', 'foxy-builder-pro'));

            switch ($this->sub_page)
            {
                case null:
                    $url = $_SERVER['PHP_SELF'] . "?page=" . urlencode($this->menu_short_name) . "&foxybdr_subpage=edit";
                    \FoxyBuilder\Admin\Includes\Header::instance()->add_button(__('Add New', 'foxy-builder-pro'), $url);
                    break;

                case 'edit':
                    $url = $_SERVER['PHP_SELF'] . "?page=" . urlencode($this->menu_short_name);
                    \FoxyBuilder\Admin\Includes\Header::instance()->add_button('&#8592; ' . __('Go Back', 'foxy-builder-pro'), $url);
                    break;
            }

            \FoxyBuilder\Admin\Includes\Header::instance()->print_output_html();
        }
    }

     public function print_page()
    {
        switch ($this->sub_page)
        {
            case null:
                require FOXYBUILDERPRO_PLUGIN_PATH . '/admin/menu/pages/custom-fonts.php';
                break;

            case 'edit':
                require FOXYBUILDERPRO_PLUGIN_PATH . '/admin/menu/pages/custom-fonts-edit.php';
                break;
        }
    }

    public function filter_upload_mimes($mimes)
    {
        $user = wp_get_current_user();

        if ($user->ID !== 0 && $user->has_cap('edit_pages'))
        {
            $upload_context = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdrp-media-upload');

            if ($upload_context === 'foxybdrp-custom-fonts')
            {
                foreach (self::FONT_MIME_TYPES as $key => $value)
                {
                    if (!isset($mimes[$key]))
                    {
                        $mimes[$key] = $value;
                    }
                }
            }
        }

        return $mimes;
    }
}
