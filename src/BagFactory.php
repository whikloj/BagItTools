<?php

namespace whikloj\BagItTools;

/**
 * Class BagFactory
 * @package whikloj\BagItTools
 */
class BagFactory
{

    /**
     * Generate a new Bag at path.
     *
     * @param string $rootPath
     *   The path to create the bag at, it must not already exist.
     * @return \whikloj\BagItTools\Bag
     *   The Bag.
     * @throws \whikloj\BagItTools\BagItException
     *   If there is problems creating the Bag.
     */
    public static function create($rootPath)
    {
        if (file_exists($rootPath)) {
            throw new BagItException("Path {$rootPath} already exists, cannot create a new bag there.");
        }
        if (strpos($rootPath, DIRECTORY_SEPARATOR) >= 0) {
            $components = explode($rootPath, DIRECTORY_SEPARATOR);
            $last_component = array_pop($components);
            if (strtolower($last_component) == "data") {
                throw new BagItException("Path cannot end with a directory called \"data\".");
            }
        }
        return new Bag($rootPath, true);
    }
}
