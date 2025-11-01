<?php

namespace FoxyBuilderPro\Admin\Menu;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDER_PLUGIN_PATH . '/includes/security.php';
require_once FOXYBUILDERPRO_PLUGIN_PATH . '/includes/file-system.php';
require_once FOXYBUILDERPRO_PLUGIN_PATH . '/admin/includes/icon-provider.php';

class CustomIcons
{
    private $page_loading = false;

    private $sub_page = null;

    public $menu_short_name = null;

    public $menu_long_name = null;

    private $nonceContext = 'custom-icons';

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
        add_action('wp_ajax_foxybdrp_custom-icons_test_zip', [ $this, 'ajax_test_zip' ]);
    }

    public function admin_menu()
    {
        $capability = 'edit_pages';
        $app_title = __('Foxy Builder', 'foxy-builder-pro');
        $page_label = __('Custom Icons', 'foxy-builder-pro');
        $this->menu_short_name = 'foxybuilder_custom_icons';

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

                wp_enqueue_style('foxybdrp-admin-menu-custom-icons', FOXYBUILDERPRO_PLUGIN_URL . "admin/assets/css/menu/custom-icons.css", [], FOXYBUILDERPRO_VERSION);
                wp_enqueue_script('foxybdrp-admin-menu-custom-icons', FOXYBUILDERPRO_PLUGIN_URL . 'admin/assets/js/menu/custom-icons' . $suffix . '.js', [], FOXYBUILDERPRO_VERSION);
                wp_localize_script('foxybdrp-admin-menu-custom-icons', 'FOXYAPP', [
                    'dialogs' => [
                        'delete' => [
                            'title' => __('Delete', 'foxy-builder-pro'),
                            'message' => __('Are you sure you want to delete the custom icon library', 'foxy-builder-pro'),
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

                wp_enqueue_style('foxybdrp-admin-menu-custom-icons-edit', FOXYBUILDERPRO_PLUGIN_URL . "admin/assets/css/menu/custom-icons-edit.css", [], FOXYBUILDERPRO_VERSION);
                wp_enqueue_script('foxybdrp-admin-menu-custom-icons-edit', FOXYBUILDERPRO_PLUGIN_URL . 'admin/assets/js/menu/custom-icons-edit' . $suffix . '.js', [], FOXYBUILDERPRO_VERSION);
                wp_localize_script('foxybdrp-admin-menu-custom-icons-edit', 'FOXYAPP', [
                    'dialogs' => [
                        'mediaUploader' => [
                            'title' =>  __('Upload Zip File', 'foxy-builder-pro'),
                            'buttonText' => __('Select File', 'foxy-builder-pro'),
                        ],
                        'validationError' => [
                            'missingTitle' => [
                                'title' => __('Missing Data', 'foxy-builder-pro'),
                                'message' => __('Please fill in the icon library name.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                            'missingFileUpload' => [
                                'title' => __('Missing Data', 'foxy-builder-pro'),
                                'message' => __('Please upload or select a zip file.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                        ],
                        'zipTest' => [
                            'success' => [
                                'title' => __('Success', 'foxy-builder-pro'),
                                'message' => __('Zip file validation is successful.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                            'failure' => [
                                'title' => __('Error', 'foxy-builder-pro'),
                                'message' => __('Zip file validation has failed.', 'foxy-builder-pro'),
                                'okLabel' => __('OK', 'foxy-builder-pro'),
                            ],
                        ],
                    ],
                    'nonce' => wp_create_nonce($this->nonceContext),
                ]);

                break;
        }
    }

    public function action_in_admin_header()
    {
        if ($this->page_loading)
        {
            require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/header.php';

            \FoxyBuilder\Admin\Includes\Header::instance()->set_title(__('Custom Icons', 'foxy-builder-pro'));

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
                require FOXYBUILDERPRO_PLUGIN_PATH . '/admin/menu/pages/custom-icons.php';
                break;

            case 'edit':
                require FOXYBUILDERPRO_PLUGIN_PATH . '/admin/menu/pages/custom-icons-edit.php';
                break;
        }
    }

    public function ajax_test_zip()
    {
        $provider_list = \FoxyBuilderPro\Admin\Includes\IconProvider\Type::list();

        $provider = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-provider');
        $provider = in_array($provider, $provider_list, true) ? $provider : $provider_list[0];

        $zip_id = (int)\FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-zip-id');

        if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $this->nonceContext) === false)
        {
            wp_send_json([
                'status' => 'ERROR',
            ], 403);
            return;
        }

        $user = wp_get_current_user();
        $time_str = (string)time();

        $extraction_path = get_temp_dir() . "/foxybdrp-{$user->ID}-{$time_str}";

        $ext_result = \FoxyBuilderPro\Admin\Includes\IconProvider\extract_icon_zip_file($zip_id, $extraction_path, $provider);

        \FoxyBuilderPro\Includes\FileSystem::delete_directory($extraction_path);

        wp_send_json([
            'status' => 'OK',
            'test_result' => $ext_result,
        ], 200);
    }
}
