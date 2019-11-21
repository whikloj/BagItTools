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
    private const CHARACTER_SETS = [
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
}
