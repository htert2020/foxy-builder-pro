<?php

namespace FoxyBuilderPro\Admin\Menu\Pages\CustomIcons;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDER_PLUGIN_PATH . '/includes/security.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/notice.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/table.php';
require_once FOXYBUILDERPRO_PLUGIN_PATH . '/includes/file-system.php';

class Table extends \FoxyBuilder\Admin\Includes\Table\Table
{
    private $icons_path;

    public function __construct()
    {
        parent::__construct('foxybdrp_icons');

        $upload_dir = wp_upload_dir();
        $this->icons_path = $upload_dir['basedir'] . '/foxy-builder-pro/icons';
    }

    protected function post_edit_url($post)
    {
        return $_SERVER['PHP_SELF'] . '?page=foxybuilder_custom_icons&foxybdr_subpage=edit&foxybdr_id=' . (string)$post->ID;
    }

    protected function on_print_cell($value, $post, $column_definition)
    {
        if ($column_definition->is_attribute_meta === true && $column_definition->attribute_name === '_foxybdrp_css_urls')
        {
            $css_urls = json_decode($value, true);
            $css_prefix = $post->__get('_foxybdrp_css_prefix');

            $json_str = file_get_contents($this->icons_path . '/' . (string)$post->ID . '.json');
            $icon_details = json_decode($json_str, true);

            $icon_names = [];
            foreach ($icon_details['icons'] as $icon)
                $icon_names[] = $icon['name'];

            $MAX_ICONS = 10;
            $icons_truncated = false;
            if (count($icon_names) > $MAX_ICONS)
            {
                array_splice($icon_names, $MAX_ICONS);
                $icons_truncated = true;
            }

            ?><div class="foxybdrp-icon-preview"><?php

                foreach ($icon_names as $icon_name)
                {
                    ?><i class="<?php echo esc_attr($css_prefix . $icon_name); ?>"></i><?php
                }

                if ($icons_truncated === true)
                {
                    ?> . . .<?php
                }

            ?></div><?php

            foreach ($css_urls as $css_url)
            {
                ?><link href="<?php echo esc_url($css_url); ?>" rel="stylesheet" /><?php
            }
        }
        else
        {
            parent::on_print_cell($value, $post, $column_definition);
        }
    }

    protected function on_print_empty_results()
    {
        ?>

            <div><?php echo esc_html__('There are no custom icons yet.', 'foxy-builder-pro'); ?></div>

        <?php
    }
}

class ThePage
{
    private $table = null;

    public $add_new_url = '';

    private static $_instance = null;
    
    public static function instance()
    {
        if (self::$_instance === null)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function init()
    {
        $this->process_action();

        $this->prepare_page();
    }

    private function process_action()
    {
        if (!isset($_POST['foxybdr-action']))
            return;

        $nonce = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'nonce');

        if (wp_verify_nonce($nonce, 'custom_icons') === false)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Security check failed. Please reload this page.', 'foxy-builder-pro'));

            return;
        }

        $action = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-action');

        switch ($action)
        {
            case 'delete':
                $this->delete_custom_icons();
                break;
        }
    }

    private function delete_custom_icons()
    {
        $id = (int)\FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-id');

        $upload_dir = wp_upload_dir();
        $icons_path = $upload_dir['basedir'] . '/foxy-builder-pro/icons';
        $extraction_path = $icons_path . '/' . (string)$id;

        \FoxyBuilderPro\Includes\FileSystem::delete_directory($extraction_path);

        $icon_details_file_path = $icons_path . '/' . (string)$id . '.json';
        
        if (is_file($icon_details_file_path))
            unlink($icon_details_file_path);

        $post = wp_delete_post($id);

        if ($post !== null && $post !== false)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('OK', __('The custom icon library has been deleted.', 'foxy-builder-pro'));
        }
        else
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Unable to delete the custom icon library.', 'foxy-builder-pro'));
        }
    }

    private function prepare_page()
    {
        $table = new Table();
        $table->add_column(__('Title', 'foxy-builder-pro'), 'title', false, 35, 'left');
        $table->add_column(__('Sample Icons', 'foxy-builder-pro'), '_foxybdrp_css_urls', true, 35, 'left');
        $table->add_column(__('Icon Count', 'foxy-builder-pro'), '_foxybdrp_icon_count', true, 10, 'center');
        $table->add_column(__('Last Modified', 'foxy-builder-pro'), 'modified', false, 20, 'center');
        $table->set_page_size(20);
        $this->table = $table;

        $this->add_new_url = $_SERVER['PHP_SELF'] . "?page=foxybuilder_custom_icons&foxybdr_subpage=edit";
    }

    public function print_table_html()
    {
        if ($this->table)
            $this->table->print_output_html();
    }
}

ThePage::instance()->init();

?>

<div class="foxybdr-admin-page">

    <h1><?php echo esc_html__('Custom Icons', 'foxy-builder-pro'); ?></h1>

    <?php \FoxyBuilder\Admin\Includes\Notice::instance()->print_output_html(); ?>

    <?php ThePage::instance()->print_table_html(); ?>

    <a id="foxybdrp-add-custom-icon-button" href="<?php echo esc_url(ThePage::instance()->add_new_url); ?>">
        <span class="dashicons dashicons-plus"></span>
        <span><?php echo esc_html__('Add Custom Icons', 'foxy-builder-pro'); ?></span>
    </a>

    <form method="post" id="foxybdr-action-form">
        <input type="hidden" name="foxybdr-action" value="" />
        <input type="hidden" name="foxybdr-id" value="" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('custom_icons')); ?>" />
    </form>

</div>
