<?php

namespace FoxyBuilderPro\Admin\Menu\Pages\CustomIconsEdit;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDER_PLUGIN_PATH . '/includes/security.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/notice.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/panel.php';
require_once FOXYBUILDERPRO_PLUGIN_PATH . '/admin/includes/icon-provider.php';

class ThePage
{
    private $created_id = null;

    public $form_id_str = '';

    public $form_title = '';

    public $form_provider = '';

    public $form_zip_id_str = '';

    public $form_zip_filename = '';

    public $provider_select_options = '';

    private static $_instance = null;
    
    public static function instance()
    {
        if (self::$_instance === null)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function id()
    {
        if ($this->created_id !== null)
            return $this->created_id;

        $id_str = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-id');
        if ($id_str !== null && $id_str !== '')
            return (int)$id_str;

        $id_str = \FoxyBuilder\Includes\Security::sanitize_request($_GET, 'foxybdr_id');
        if ($id_str !== null)
            return (int)$id_str;

        return null;
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

        if (wp_verify_nonce($nonce, 'custom_icons_edit') === false)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Security check failed. Please reload this page.', 'foxy-builder-pro'));

            return;
        }

        $action = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-action');

        switch ($action)
        {
            case 'save':
                $this->save_custom_icon();
                break;
        }
    }

    private function save_custom_icon()
    {
        $id = $this->id();

        $provider_list = \FoxyBuilderPro\Admin\Includes\IconProvider\Type::list();

        $title = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-title');
        $title = sanitize_text_field($title);

        $provider = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-provider');
        $provider = in_array($provider, $provider_list, true) ? $provider : $provider_list[0];

        $zip_id = (int)\FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-zip-id');

        $zip_filename = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-zip-filename');
        $zip_filename = sanitize_file_name($zip_filename);

        if ($id === null)
        {
            $post_id = wp_insert_post([
                'post_type' => 'foxybdrp_icons',
                'post_title' => $title,
                'post_content' => '',
                'post_status' => 'publish',
                'meta_input' => [
                    '_foxybdrp_provider' => $provider,
                    '_foxybdrp_zip_id' => (string)$zip_id,
                    '_foxybdrp_zip_filename' => $zip_filename,
                ],
            ]);

            if ($post_id === 0 || $post_id instanceof \WP_Error)
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Failed to create custom icon library.', 'foxy-builder-pro'));
            }
            else
            {
                $this->created_id = $post_id;

                $this->generate_icon_library($post_id, $provider, $zip_id);

                \FoxyBuilder\Admin\Includes\Notice::instance()->add('OK', __('The custom icon library has been created.', 'foxy-builder-pro'));
            }
        }
        else
        {
            $post_id = wp_update_post([
                'ID' => $id,
                'post_title' => $title,
                'meta_input' => [
                    '_foxybdrp_provider' => $provider,
                    '_foxybdrp_zip_id' => (string)$zip_id,
                    '_foxybdrp_zip_filename' => $zip_filename,
                ],
            ]);

            if ($post_id === 0 || $post_id instanceof \WP_Error)
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Failed to save custom icon library.', 'foxy-builder-pro'));
            }
            else
            {
                $this->generate_icon_library($post_id, $provider, $zip_id);

                \FoxyBuilder\Admin\Includes\Notice::instance()->add('OK', __('The custom icon library has been saved.', 'foxy-builder-pro'));
            }
        }
    }

    private function generate_icon_library($post_id, $provider, $zip_id)
    {
        $upload_dir = wp_upload_dir();

        $icons_path = $upload_dir['basedir'] . '/foxy-builder-pro/icons';
        $icons_url  = $upload_dir['baseurl'] . '/foxy-builder-pro/icons';

        $extraction_path = $icons_path . '/' . (string)$post_id;
        $extraction_url  = $icons_url  . '/' . (string)$post_id;

        $ext_result = \FoxyBuilderPro\Admin\Includes\IconProvider\extract_icon_zip_file($zip_id, $extraction_path, $provider);

        for ($i = 0; $i < count($ext_result['css_urls']); $i++)
            $ext_result['css_urls'][$i] = $extraction_url . $ext_result['css_urls'][$i];

        update_post_meta($post_id, '_foxybdrp_status', $ext_result['status']);
        update_post_meta($post_id, '_foxybdrp_status_message', $ext_result['status_message']);
        update_post_meta($post_id, '_foxybdrp_css_urls', json_encode($ext_result['css_urls']));
        update_post_meta($post_id, '_foxybdrp_css_prefix', $ext_result['css_prefix']);
        update_post_meta($post_id, '_foxybdrp_icon_count', (string)count($ext_result['icon_names']));

        $icon_details_file_path = $icons_path . '/' . (string)$post_id . '.json';

        if (is_file($icon_details_file_path))
            unlink($icon_details_file_path);

        if ($ext_result['status'] === 'OK')
        {
            $icons = [];

            foreach ($ext_result['icon_names'] as $name)
            {
                $icons[] = [
                    'name' => $name,
                ];
            }

            $icon_details = [
                'icons' => $icons,
            ];

            file_put_contents($icon_details_file_path, json_encode($icon_details));
        }

        \FoxyBuilder\Admin\Includes\Notice::instance()->add($ext_result['status'], $ext_result['status_message']);
    }

    private function prepare_page()
    {
        $id = $this->id();

        if ($id === null)
        {
            $this->form_id_str = '';
            $this->form_title = '';
            $this->form_provider = \FoxyBuilderPro\Admin\Includes\IconProvider\Type::list()[0];
            $this->form_zip_id_str = '';
            $this->form_zip_filename = '';
        }
        else
        {
            $this->form_id_str = (string)$id;

            $post = get_post($id);

            if ($post !== null)
            {
                $this->form_title = $post->post_title;
                $this->form_provider = $post->__get('_foxybdrp_provider');
                $this->form_zip_id_str = $post->__get('_foxybdrp_zip_id');
                $this->form_zip_filename = $post->__get('_foxybdrp_zip_filename');
            }
            else
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Failed to load custom icon library.', 'foxy-builder-pro'));
            }
        }

        $options = [];
        $provider_friendly_names = \FoxyBuilderPro\Admin\Includes\IconProvider\Type::friendly_names();
        foreach (\FoxyBuilderPro\Admin\Includes\IconProvider\Type::list() as $provider)
        {
            $name = $provider_friendly_names[$provider];
            $selected_attr = $provider === $this->form_provider ? ' selected="selected"' : '';
            $options[] = '<option value="' . esc_attr($provider) . '"' . $selected_attr . '>' . esc_html($name) . '</option>';
        }
        $this->provider_select_options = implode('', $options);

        if (!class_exists('ZipArchive', false) || !extension_loaded('zip'))
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('The PHP zip extension is disabled. Zip files cannot be processed at this time.', 'foxy-builder-pro'));
    }
}

ThePage::instance()->init();

?>

<div class="foxybdr-admin-page">

    <h1><?php echo ThePage::instance()->form_id_str === '' ? esc_html__('Add Custom Icons', 'foxy-builder-pro') : esc_html__('Edit Custom Icons', 'foxy-builder-pro'); ?></h1>

    <?php \FoxyBuilder\Admin\Includes\Notice::instance()->print_output_html(); ?>

    <div class="foxybdrp-2-column-container">
        <div>

            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_start_html(__('Icon Library Name', 'foxy-builder-pro'), null, 'foxybdrp-page-panel'); ?>
                <input type="text" value="<?php echo esc_attr(ThePage::instance()->form_title); ?>" id="foxybdrp-post-title" placeholder="Enter name here" size="30" spellcheck="true" autocomplete="off" />
            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_end_html(); ?>

            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_start_html(__('Zip File', 'foxy-builder-pro'), null, 'foxybdrp-page-panel'); ?>

                    <div class="foxybdrp-field-box">
                        <span><?php echo esc_html__('Icon Provider', 'foxy-builder-pro'); ?>:</span>
                        <select class="foxybdrp-provider-select">
                            <?php echo ThePage::instance()->provider_select_options; ?>
                        </select>
                    </div>

                    <div class="foxybdrp-field-box">
                        <span><?php echo esc_html__('Upload Zip File', 'foxy-builder-pro'); ?>:</span>
                        <div class="foxybdrp-file-panel">
                            <div>
                                <input type="text" value="" readonly="readonly" />
                            </div>
                            <div>
                                <button class="foxybdrp-upload"><?php echo esc_html__('Upload', 'foxy-builder-pro'); ?></button>
                                <button class="foxybdrp-clear"><?php echo esc_html__('Clear', 'foxy-builder-pro'); ?></button>
                            </div>
                        </div>
                    </div>

            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_end_html(); ?>

        </div>
        <div>

            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_start_html(__('Save', 'foxy-builder-pro')); ?>
                <button id="foxybdrp-save-button" class="button button-primary button-large"><?php echo esc_html__('Save Changes', 'foxy-builder-pro'); ?></button>
            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_end_html(); ?>

        </div>
    </div>

    <form method="post" id="foxybdr-action-form">
        <input type="hidden" name="foxybdr-action" value="save" />
        <input type="hidden" name="foxybdr-id" value="<?php echo esc_attr(ThePage::instance()->form_id_str); ?>" />
        <input type="hidden" name="foxybdr-title" value="<?php echo esc_attr(ThePage::instance()->form_title); ?>" />
        <input type="hidden" name="foxybdr-provider" value="<?php echo esc_attr(ThePage::instance()->form_provider); ?>" />
        <input type="hidden" name="foxybdr-zip-id" value="<?php echo esc_attr(ThePage::instance()->form_zip_id_str); ?>" />
        <input type="hidden" name="foxybdr-zip-filename" value="<?php echo esc_attr(ThePage::instance()->form_zip_filename); ?>" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('custom_icons_edit')); ?>" />
    </form>

</div>
