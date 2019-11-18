<?php

namespace whikloj\BagItTools;

/**
 * Utility class to hold static functions.
 * @package whikloj\BagItTools
 */
class BagUtils
{

    /**
     * BagUtils constructor.
     */
    private function __construct()
    {
        // This constructor left intentionally blank.
    }

    /**
     * Rebase the path in the data directory as payloads only deal in there.
     *
     * @param string $path
     *   The provided path.
     * @return string
     *   The (possibly) rebased path.
     */
    public static function baseInData($path)
    {
        if (substr($path, 0, 5) !== 'data/') {
            $path = "data/" . ltrim($path, "/");
        }
        return $path;
    }

    /**
     * Return all files that match the pattern, or an empty array.
     *
     * @param string $pattern
     *   The pattern to search for.
     *
     * @return array
     *   Array of matches.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   Error in matching pattern.
     */
    public static function findAllByPattern($pattern)
    {
        $matches=glob($pattern);
        if ($matches === false) {
            throw new BagItException("Error matching pattern {$pattern}");
        }
        return $matches;
    }
}
