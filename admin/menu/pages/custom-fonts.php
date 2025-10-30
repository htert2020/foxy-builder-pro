<?php

namespace FoxyBuilderPro\Admin\Menu\Pages\CustomFonts;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDER_PLUGIN_PATH . '/includes/security.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/notice.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/table.php';

class Table extends \FoxyBuilder\Admin\Includes\Table\Table
{
    public function __construct()
    {
        parent::__construct('foxybdrp_font');
    }

    protected function post_edit_url($post)
    {
        return $_SERVER['PHP_SELF'] . '?page=foxybuilder_custom_fonts&foxybdr_subpage=edit&foxybdr_id=' . (string)$post->ID;
    }

    protected function on_print_cell($value, $post, $column_definition)
    {
        if ($column_definition->is_attribute_meta === true && $column_definition->attribute_name === '_foxybdrp_css_url')
        {
            $css_url = $post->__get('_foxybdrp_css_url');
            $title = $post->post_title;

            ?><span class="foxybdrp-font-preview" style="font-family: '<?php echo esc_attr($title); ?>';"><?php
                ?><?php echo esc_html($title); ?><?php
            ?></span><?php

            ?><link href="<?php echo esc_url($css_url); ?>" rel="stylesheet" /><?php
        }
        else
        {
            parent::on_print_cell($value, $post, $column_definition);
        }
    }

    protected function on_print_empty_results()
    {
        ?>

            <div><?php echo esc_html__('There are no custom fonts yet.', 'foxy-builder-pro'); ?></div>

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

        if (wp_verify_nonce($nonce, 'custom_fonts') === false)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Security check failed. Please reload this page.', 'foxy-builder-pro'));

            return;
        }

        $action = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-action');

        switch ($action)
        {
            case 'delete':
                $this->delete_custom_font();
                break;
        }
    }

    private function delete_custom_font()
    {
        $id = (int)\FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-id');

        $post = wp_delete_post($id);

        if ($post !== null && $post !== false)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('OK', __('The custom font has been deleted.', 'foxy-builder-pro'));
        }
        else
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Unable to delete the custom font.', 'foxy-builder-pro'));
        }
    }

    private function prepare_page()
    {
        $table = new Table();
        $table->add_column(__('Title', 'foxy-builder-pro'), 'title', false, 40, 'left');
        $table->add_column(__('Font Preview', 'foxy-builder-pro'), '_foxybdrp_css_url', true, 40, 'left');
        $table->add_column(__('Last Modified', 'foxy-builder-pro'), 'modified', false, 20, 'center');
        $table->set_page_size(20);
        $this->table = $table;

        $this->add_new_url = $_SERVER['PHP_SELF'] . "?page=foxybuilder_custom_fonts&foxybdr_subpage=edit";
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

    <h1>Custom Fonts</h1>

    <?php \FoxyBuilder\Admin\Includes\Notice::instance()->print_output_html(); ?>

    <?php ThePage::instance()->print_table_html(); ?>

    <a id="foxybdrp-add-custom-font-button" href="<?php echo esc_url(ThePage::instance()->add_new_url); ?>">
        <span class="dashicons dashicons-plus"></span>
        <span><?php echo esc_html__('Add Custom Font', 'foxy-builder-pro'); ?></span>
    </a>

    <form method="post" id="foxybdr-action-form">
        <input type="hidden" name="foxybdr-action" value="" />
        <input type="hidden" name="foxybdr-id" value="" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('custom_fonts')); ?>" />
    </form>

</div>
