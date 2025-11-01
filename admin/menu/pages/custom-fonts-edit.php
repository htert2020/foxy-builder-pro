<?php

namespace FoxyBuilderPro\Admin\Menu\Pages\CustomFontsEdit;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDER_PLUGIN_PATH . '/includes/security.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/notice.php';
require_once FOXYBUILDER_PLUGIN_PATH . '/admin/includes/panel.php';

class VariationsSanitizer
{
    public static function sanitize($variations)
    {
        if (!is_array($variations) || !array_is_list($variations))
            return [];

        $new_array = [];

        foreach ($variations as $variation)
        {
            $new_array[] = self::sanitize_variation($variation);
        }

        return $new_array;
    }

    public static function sanitize_variation($variation)
    {
        if (!is_array($variation))
            return [];

        $new_array = [];

        foreach ($variation as $key => $value)
        {
            switch ($key)
            {
                case 'weight':
                    $new_array[$key] = self::sanitize_select($value, [ "normal", "bold", "100", "200", "300", "400", "500", "600", "700", "800", "900" ]);
                    break;

                case 'style':
                    $new_array[$key] = self::sanitize_select($value, [ "normal", "italic", "oblique" ]);
                    break;

                case 'files':
                    $new_array[$key] = self::sanitize_files($value);
                    break;
            }
        }

        return $new_array;
    }

    public static function sanitize_files($files)
    {
        if (!is_array($files))
            return [];

        $new_array = [];

        foreach ($files as $key => $value)
        {
            if (!in_array($key, [ 'woff', 'woff2', 'eot', 'ttf' ], true))
                continue;

            $new_array[$key] = self::sanitize_file($value);
        }

        return $new_array;
    }

    public static function sanitize_file($file)
    {
        if (!is_array($file))
            return [];

        $new_array = [];

        foreach ($file as $key => $value)
        {
            switch ($key)
            {
                case 'id':
                    $new_array[$key] = $value !== null ? (int)$value : null;
                    break;

                case 'filename':
                    $new_array[$key] = $value !== null ? sanitize_file_name((string)$value) : null;
                    break;
            }
        }

        return $new_array;
    }

    public static function sanitize_select($value, $valid_values)
    {
        if (!is_string($value) || !in_array($value, $valid_values, true))
            return $valid_values[0];

        return $value;
    }
}

class ThePage
{
    private $created_id = null;

    public $form_id_str = '';

    public $form_title = '';

    public $form_content = '';

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

        if (wp_verify_nonce($nonce, 'custom_fonts_edit') === false)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Security check failed. Please reload this page.', 'foxy-builder-pro'));

            return;
        }

        $action = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-action');

        switch ($action)
        {
            case 'save':
                $this->save_custom_font();
                break;
        }
    }

    private function save_custom_font()
    {
        $id = $this->id();

        $title = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-title');
        $title = sanitize_text_field($title);
        $title = str_replace([ "'", '"', '/', '\\' ], '', $title);

        $content = \FoxyBuilder\Includes\Security::sanitize_request($_POST, 'foxybdr-content');
        $variations = json_decode($content, true);
        if ($variations === null)
        {
            \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Invalid data submitted.', 'foxy-builder-pro'));
            return;
        }
        $variations = VariationsSanitizer::sanitize($variations);
        $content_sanitized = json_encode($variations);

        if ($id === null)
        {
            $post_id = wp_insert_post([
                'post_type' => 'foxybdrp_font',
                'post_title' => $title,
                'post_content' => '',
                'post_status' => 'publish',
                'meta_input' => [
                    '_foxybdrp_content' => $content_sanitized,
                ],
            ]);

            if ($post_id === 0 || $post_id instanceof \WP_Error)
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Failed to create custom font.', 'foxy-builder-pro'));
            }
            else
            {
                $this->created_id = $post_id;

                $url = $this->save_css_file($post_id, $title, $variations);

                update_post_meta($post_id, '_foxybdrp_css_url', $url);

                \FoxyBuilder\Admin\Includes\Notice::instance()->add('OK', __('The custom font has been created.', 'foxy-builder-pro'));
            }
        }
        else
        {
            $url = $this->save_css_file($id, $title, $variations);

            $post_id = wp_update_post([
                'ID' => $id,
                'post_title' => $title,
                'meta_input' => [
                    '_foxybdrp_content' => $content_sanitized,
                    '_foxybdrp_css_url' => $url,
                ],
            ]);

            if ($post_id === 0 || $post_id instanceof \WP_Error)
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Failed to save custom font.', 'foxy-builder-pro'));
            }
            else
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('OK', __('The custom font has been saved.', 'foxy-builder-pro'));
            }
        }
    }

    private function save_css_file($id, $title, $variations)
    {
        $css = $this->generate_css($title, $variations);

        $uploadDir = wp_upload_dir();
        $fontsPath = $uploadDir['basedir'] . '/foxy-builder-pro/fonts';
        $fontPathAndFile = $fontsPath . "/font-{$id}.css";
        
        if (!is_dir($fontsPath))
            mkdir($fontsPath, 0777, true);
            
        file_put_contents($fontPathAndFile, $css);

        return $uploadDir['baseurl'] . "/foxy-builder-pro/fonts/font-{$id}.css";
    }

    private function generate_css($title, $variations)
    {
        $file_formats = [
            'woff'  => 'woff',
            'woff2' => 'woff2',
            'eot'   => 'embedded-opentype',
            'ttf'   => 'truetype',
        ];

        $font_faces = [];

        foreach ($variations as $variation)
        {
            $url_list = [];
            foreach ($file_formats as $type => $format)
            {
                $file_id = $variation['files'][$type]['id'];

                if ($file_id === null)
                    continue;

                $file_post = get_post((int)$file_id);

                if ($file_post === null)
                    continue;
                
                $url_list[] = "url('" . $file_post->guid . "') format('" . $format . "')";
            }

            if (count($url_list) === 0)
                continue;

            $fields = [];
            $fields['font-family'] = "'" . $title . "'";
            $fields['font-weight'] = $variation['weight'];
            $fields['font-style'] = $variation['style'];
            $fields['font-stretch'] = '100%';
            $fields['font-display'] = 'swap';
            $fields['src'] = implode(', ', $url_list);

            $props = [];
            foreach ($fields as $key => $value)
                $props[] = $key . ': ' . $value . ';';

            $css_body = implode(' ', $props);

            $font_faces[] = "@font-face { " . $css_body . " }";
        }

        return implode(' ', $font_faces);
    }

    private function prepare_page()
    {
        $id = $this->id();

        if ($id === null)
        {
            $this->form_id_str = '';
            $this->form_title = '';
            $this->form_content = '';
        }
        else
        {
            $this->form_id_str = (string)$id;

            $post = get_post($id);

            if ($post !== null)
            {
                $this->form_title = $post->post_title;
                $this->form_content = $post->__get('_foxybdrp_content');
            }
            else
            {
                \FoxyBuilder\Admin\Includes\Notice::instance()->add('ERROR', __('ERROR: Failed to load custom font.', 'foxy-builder-pro'));
            }
        }
    }
}

ThePage::instance()->init();

?>

<div class="foxybdr-admin-page">

    <h1><?php echo ThePage::instance()->form_id_str === '' ? esc_html__('Add Custom Font', 'foxy-builder-pro') : esc_html__('Edit Custom Font', 'foxy-builder-pro'); ?></h1>

    <?php \FoxyBuilder\Admin\Includes\Notice::instance()->print_output_html(); ?>

    <div class="foxybdrp-2-column-container">
        <div>
            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_start_html(__('Font Name', 'foxy-builder-pro'), null, 'foxybdrp-page-panel'); ?>
                <input type="text" value="<?php echo esc_attr(ThePage::instance()->form_title); ?>" id="foxybdrp-post-title" placeholder="Enter font name here" size="30" spellcheck="true" autocomplete="off" />
            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_end_html(); ?>
            <?php \FoxyBuilder\Admin\Includes\Panel::instance()->print_start_html(__('Font Variations', 'foxy-builder-pro'), null, 'foxybdrp-page-panel'); ?>
                <div id="foxybdrp-variations">
                </div>
                <button id="foxybdrp-add-variation-button">
                    <span class="dashicons dashicons-plus"></span>
                    <span><?php echo esc_html__('Add Font Variation', 'foxy-builder-pro'); ?></span>
                </button>
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
        <input type="hidden" name="foxybdr-content" value="<?php echo esc_attr(ThePage::instance()->form_content); ?>" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('custom_fonts_edit')); ?>" />
    </form>

    <script id="foxybdrp-template-variation" type="text/html">
        <div class="foxybdrp-variation">
            <div class="foxybdrp-main-panel">
                <div>
                    <span><?php echo esc_html__('Weight', 'foxy-builder-pro'); ?>:</span>
                    <select class="foxybdrp-weight-select">
                        <option value="normal"><?php echo esc_html__('Normal', 'foxy-builder-pro'); ?></option>
                        <option value="bold"><?php echo esc_html__('Bold', 'foxy-builder-pro'); ?></option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                        <option value="400">400</option>
                        <option value="500">500</option>
                        <option value="600">600</option>
                        <option value="700">700</option>
                        <option value="800">800</option>
                        <option value="900">900</option>
                    </select>
                    <span><?php echo esc_html__('Style', 'foxy-builder-pro'); ?>:</span>
                    <select class="foxybdrp-style-select">
                        <option value="normal"><?php echo esc_html__('Normal', 'foxy-builder-pro'); ?></option>
                        <option value="italic"><?php echo esc_html__('Italic', 'foxy-builder-pro'); ?></option>
                        <option value="oblique"><?php echo esc_html__('Oblique', 'foxy-builder-pro'); ?></option>
                    </select>
                    <button>
                        <span><?php echo esc_html__('Upload Files', 'foxy-builder-pro'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
                <div>
                    <span class="dashicons dashicons-no"></span>
                </div>
            </div>
            <div class="foxybdrp-files-panel">
                <span><?php echo esc_html__('Please upload at least one of the following font files for this variation.', 'foxy-builder-pro'); ?></span>
                <div class="foxybdrp-file-panel" foxybdrp-file-type="woff">
                    <div>
                        <span>WOFF <?php echo esc_html__('File', 'foxy-builder-pro'); ?></span>
                    </div>
                    <div>
                        <input type="text" value="" readonly="readonly" />
                    </div>
                    <div>
                        <button class="foxybdrp-upload"><?php echo esc_html__('Upload', 'foxy-builder-pro'); ?></button>
                        <button class="foxybdrp-clear"><?php echo esc_html__('Clear', 'foxy-builder-pro'); ?></button>
                    </div>
                </div>
                <div class="foxybdrp-file-panel" foxybdrp-file-type="woff2">
                    <div>
                        <span>WOFF2 <?php echo esc_html__('File', 'foxy-builder-pro'); ?></span>
                    </div>
                    <div>
                        <input type="text" value="" readonly="readonly" />
                    </div>
                    <div>
                        <button class="foxybdrp-upload"><?php echo esc_html__('Upload', 'foxy-builder-pro'); ?></button>
                        <button class="foxybdrp-clear"><?php echo esc_html__('Clear', 'foxy-builder-pro'); ?></button>
                    </div>
                </div>
                <div class="foxybdrp-file-panel" foxybdrp-file-type="eot">
                    <div>
                        <span>EOT <?php echo esc_html__('File', 'foxy-builder-pro'); ?></span>
                    </div>
                    <div>
                        <input type="text" value="" readonly="readonly" />
                    </div>
                    <div>
                        <button class="foxybdrp-upload"><?php echo esc_html__('Upload', 'foxy-builder-pro'); ?></button>
                        <button class="foxybdrp-clear"><?php echo esc_html__('Clear', 'foxy-builder-pro'); ?></button>
                    </div>
                </div>
                <div class="foxybdrp-file-panel" foxybdrp-file-type="ttf">
                    <div>
                        <span>TTF <?php echo esc_html__('File', 'foxy-builder-pro'); ?></span>
                    </div>
                    <div>
                        <input type="text" value="" readonly="readonly" />
                    </div>
                    <div>
                        <button class="foxybdrp-upload"><?php echo esc_html__('Upload', 'foxy-builder-pro'); ?></button>
                        <button class="foxybdrp-clear"><?php echo esc_html__('Clear', 'foxy-builder-pro'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </script>

</div>
