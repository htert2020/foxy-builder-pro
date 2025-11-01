<?php

namespace FoxyBuilderPro\Includes;

if (!defined('ABSPATH'))
    exit;

class FileSystem
{
    public static function delete_directory_contents($path)
    {
        $dir_handle = opendir($path);

        if ($dir_handle)
        {
            while (($filename = readdir($dir_handle)) !== false)
            {
                if (in_array($filename, [ '.', '..' ], true))
                    continue;

                $file_path = $path . '/' . $filename;

                if (is_dir($file_path))
                {
                    self::delete_directory_contents($file_path);

                    rmdir($file_path);
                }
                else
                {
                    unlink($file_path);
                }
            }
            
            closedir($dir_handle);
        }
    }

    public static function delete_directory($path)
    {
        self::delete_directory_contents($path);

        rmdir($path);
    }
}
