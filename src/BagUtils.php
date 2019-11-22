<?php

namespace whikloj\BagItTools;

/**
 * Utility class to hold static functions.
 * @package whikloj\BagItTools
 */
class BagUtils
{

    /**
     * Valid character set MIME names from IANA.
     */
    const CHARACTER_SETS = [
        "utf-8" => "UTF-8",
        "us-ascii" => "US-ASCII",
        "iso-8859-1" => "ISO-8859-1",
        "iso-8859-2" => "ISO-8859-2",
        "iso-8859-3" => "ISO-8859-3",
        "iso-8859-4" => "ISO-8859-4",
        "iso-8859-5" => "ISO-8859-5",
        "iso-8859-6" => "ISO-8859-6",
        "iso-8859-7" => "ISO-8859-7",
        "iso-8859-8" => "ISO-8859-8",
        "iso-8859-9" => "ISO-8859-9",
        "iso-8859-10" => "ISO-8859-10",
        "shift_jis" => "Shift_JIS",
        "euc-jp" => "EUC-JP",
        "iso-2022-kr" => "ISO-2022-KR",
        "euc-kr" => "EUC-KR",
        "iso-2022-jp" => "ISO-2022-JP",
        "iso-2022-jp-2" => "ISO-2022-JP-2",
        "iso-8859-6-e" => "ISO-8859-6-E",
        "iso-8859-6-i" => "ISO-8859-6-I",
        "iso-8859-8-e" => "ISO-8859-8-E",
        "iso-8859-8-i" => "ISO-8859-8-I",
        "gb2312" => "GB2312",
        "big5" => "Big5",
        "koi8-r" => "KOI8-R",
    ];

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

    /**
     * Check the provided lower case name of a character set against our list. If we have it, return the proper MIME
     * name.
     * @param string $charset
     *   The trimmed lowercase version of the character set MIME name.
     * @return string|null
     *   The proper name or null if we don't have it.
     */
    public static function getValidCharset($charset)
    {
        if (in_array($charset, array_keys(self::CHARACTER_SETS))) {
            return self::CHARACTER_SETS[$charset];
        }
        return null;
    }

    /**
     * There is a method that deal with Sven Arduwie proposal https://www.php.net/manual/en/function.realpath.php#84012
     * And runeimp at gmail dot com proposal https://www.php.net/manual/en/function.realpath.php#112367
     * @author  moreau.marc.web@gmail.com
     * @param string $path
     * @return string
     */
    public static function getAbsolute(string $path)
    {
        // Cleaning path regarding OS
        $path = mb_ereg_replace('\\\\|/', DIRECTORY_SEPARATOR, $path, 'msr');
        // Check if path start with a separator (UNIX)
        $startWithSeparator = $path[0] === DIRECTORY_SEPARATOR;
        // Check if start with drive letter
        preg_match('/^[a-z]:/', $path, $matches);
        $startWithLetterDir = isset($matches[0]) ? $matches[0] : false;
        // Get and filter empty sub paths
        $subPaths = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'mb_strlen');

        $absolutes = [];
        foreach ($subPaths as $subPath) {
            if ('.' === $subPath) {
                continue;
            }
            // if $startWithSeparator is false
            // and $startWithLetterDir
            // and (absolutes is empty or all previous values are ..)
            // save absolute cause that's a relative and we can't deal with that and just forget that we want go up
            if ('..' === $subPath
                && !$startWithSeparator
                && !$startWithLetterDir
                && empty(array_filter($absolutes, function ($value) {
                    return !('..' === $value);
                }))
            ) {
                $absolutes[] = $subPath;
                continue;
            }
            if ('..' === $subPath) {
                array_pop($absolutes);
                continue;
            }
            $absolutes[] = $subPath;
        }

        return
            (($startWithSeparator ? DIRECTORY_SEPARATOR : $startWithLetterDir) ?
                $startWithLetterDir . DIRECTORY_SEPARATOR : ''
            ) . implode(DIRECTORY_SEPARATOR, $absolutes);
    }

    /**
     * Paths for new and existing files should not have these conditions.
     *
     * @param string $path
     *   The relative path from an existing bag file or as a destination for a new file.
     * @return bool
     *   True if invalid characters/character sequences exist.
     */
    public static function invalidPathCharacters($path)
    {
        $path = urldecode($path);
        return ($path[0] === DIRECTORY_SEPARATOR || strpos($path, "~") !== false ||
            substr($path, 0, 3) == "../");
    }
}
