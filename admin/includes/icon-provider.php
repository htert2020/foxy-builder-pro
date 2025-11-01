<?php

namespace FoxyBuilderPro\Admin\Includes\IconProvider;

if (!defined('ABSPATH'))
    exit;

require_once FOXYBUILDERPRO_PLUGIN_PATH . '/includes/file-system.php';

class Type
{
    public static $ICOMOON = 'icomoon';

    public static $FONTELLO = 'fontello';

    public static function list()
    {
        return [ self::$ICOMOON, self::$FONTELLO ];
    }

    public static function friendly_names()
    {
        return [
            self::$ICOMOON => 'IcoMoon',
            self::$FONTELLO => 'Fontello',
        ];
    }
}

abstract class BaseProvider
{
    public $css_urls = [];

    public $css_prefix = '';

    public $icon_names = [];

    public function ingest_library_files($root_path)
    {
        $file_count = 0;
        $dir_count = 0;
        $dir_name = null;

        $dir_handle = opendir($root_path);

        if ($dir_handle)
        {
            while (($filename = readdir($dir_handle)) !== false)
            {
                if (in_array($filename, [ '.', '..' ], true))
                    continue;

                $file_path = $root_path . '/' . $filename;

                if (is_dir($file_path))
                {
                    $dir_count++;
                    $dir_name = $filename;
                }
                else
                {
                    $file_count++;
                }
            }
            
            closedir($dir_handle);
        }

        if (!($file_count === 0 && $dir_count === 1))
            $dir_name = null;

        $root_path = $dir_name != null ? $root_path . '/' . $dir_name : $root_path;

        $status = $this->ingest($root_path);

        if ($dir_name !== null)
        {
            for ($i = 0; $i < count($this->css_urls); $i++)
            {
                $this->css_urls[$i] = '/' . $dir_name . $this->css_urls[$i];
            }
        }

        return $status;
    }

    abstract protected function ingest($root_path);
}

class IcoMoon extends BaseProvider
{
    protected function ingest($root_path)
    {
        if (!is_file($root_path . '/style.css'))
            return false;

        $this->css_urls = [
            '/style.css'
        ];

        $json_file_path = $root_path . '/selection.json';

        if (!is_file($json_file_path))
            return false;

        $json_str = file_get_contents($json_file_path);

        $config = json_decode($json_str, true);

        if ($config === null)
            return false;

        if (!isset($config['preferences']) || !isset($config['preferences']['fontPref']) || !isset($config['preferences']['fontPref']['prefix']))
            return false;

        $this->css_prefix = $config['preferences']['fontPref']['prefix'];

        $this->icon_names = [];

        if (!isset($config['icons']))
            return false;

        foreach ($config['icons'] as $icon)
        {
            if (!isset($icon['properties']) || !isset($icon['properties']['name']))
                return false;

            $this->icon_names[] = $icon['properties']['name'];
        }

        return true;
    }
}

class Fontello extends BaseProvider
{
    protected function ingest($root_path)
    {
        $json_file_path = $root_path . '/config.json';

        if (!is_file($json_file_path))
            return false;

        $json_str = file_get_contents($json_file_path);

        $config = json_decode($json_str, true);

        if ($config === null)
            return false;

        if (!isset($config['css_prefix_text']))
            return false;

        $this->css_prefix = $config['css_prefix_text'];

        $this->icon_names = [];

        if (!isset($config['glyphs']))
            return false;

        foreach ($config['glyphs'] as $icon)
        {
            if (!isset($icon['css']))
                return false;

            $this->icon_names[] = $icon['css'];
        }

        if (!isset($config['name']))
            return false;

        $font_name = $config['name'];

        $css_file_path = "{$root_path}/css/{$font_name}.css";

        if (!is_file($css_file_path))
            return false;

        $css_content = file_get_contents($css_file_path);
        $css_content = preg_replace([ '/margin-left:.*;/', '/margin-right:.*;/' ], '', $css_content);
        file_put_contents($css_file_path, $css_content);

        $this->css_urls = [
            "/css/{$font_name}.css"
        ];

        return true;
    }
}

function create_provider($provider)
{
    switch ($provider)
    {
        case Type::$ICOMOON:
            return new IcoMoon();
            break;

        case Type::$FONTELLO:
            return new Fontello();
            break;
    }

    return null;
}

function extract_icon_zip_file($zip_id, $extraction_path, $provider)
{
    $result = [
        'status' => 'OK',
        'status_message' => __('Successfully extracted zip file.', 'foxy-builder-pro'),
        'css_urls' => [],
        'css_prefix' => '',
        'icon_names' => [],
    ];

    if (is_dir($extraction_path))
    {
        \FoxyBuilderPro\Includes\FileSystem::delete_directory($extraction_path);
    }
    else if (is_file($extraction_path))
    {
        unlink($extraction_path);
    }

    mkdir($extraction_path, 0777, true);

    $zip_post = get_post($zip_id);

    if ($zip_post === null || $zip_post->post_mime_type !== 'application/zip')
    {
        $result['status'] = 'ERROR';
        $result['status_message'] = __('Invalid zip file.', 'foxy-builder-pro');
        return $result;
    }

    $upload_dir = wp_upload_dir();

    $zip_file_path = $upload_dir['basedir'] . '/' . $zip_post->__get('_wp_attached_file');

    if (!class_exists('ZipArchive', false) || !extension_loaded('zip'))
    {
        $result['status'] = 'ERROR';
        $result['status_message'] = __('The PHP zip extension is disabled.', 'foxy-builder-pro');
        return $result;
    }

    $zipper = new \ZipArchive;

    if ($zipper->open($zip_file_path) !== true)
    {
        $result['status'] = 'ERROR';
        $result['status_message'] = __('Failed to open zip file.', 'foxy-builder-pro');
        return $result;
    }

    $zipper->extractTo($extraction_path);

    $zipper->close();

    $icon_provider = create_provider($provider);

    if ($icon_provider->ingest_library_files($extraction_path) === false)
    {
        $result['status'] = 'ERROR';
        $result['status_message'] = __('The data inside the zip file does not match the specified icon provider.', 'foxy-builder-pro');
        return $result;
    }

    $result['css_urls']   = $icon_provider->css_urls;
    $result['css_prefix'] = $icon_provider->css_prefix;
    $result['icon_names'] = $icon_provider->icon_names;

    return $result;
}
