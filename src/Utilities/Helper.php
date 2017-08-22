<?php namespace Fulfillment\DHL\Api\Utilities;

class Helper
{
    /**
     * Get the path to the storage folder.
     *
     * @param  string $path
     *
     * @return string
     */
    public static function getStoragePath($path = '')
    {
        if(function_exists('storage_path')){
            return storage_path($path);
        }
            return (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '../storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}