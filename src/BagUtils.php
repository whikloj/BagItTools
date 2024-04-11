<?php

declare(strict_types=1);

namespace whikloj\BagItTools;

use TypeError;
use whikloj\BagItTools\Exceptions\BagItException;
use whikloj\BagItTools\Exceptions\FilesystemException;

/**
 * Utility class to hold static functions.
 *
 * @package whikloj\BagItTools
 * @author whikloj
 * @since 1.0.0
 */
class BagUtils
{
    /**
     * Valid character set MIME names from IANA.
     */
    private const CHARACTER_SETS = [
        "utf-8" => "UTF-8",
        "utf-16" => "UTF-16",
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
    public static function baseInData(string $path): string
    {
        if (!str_starts_with($path, 'data/')) {
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
     * @throws FilesystemException
     *   Error in matching pattern.
     */
    public static function findAllByPattern(string $pattern): array
    {
        $matches = glob($pattern);
        if ($matches === false) {
            throw new FilesystemException("Error matching pattern $pattern");
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
    public static function getValidCharset(string $charset): ?string
    {
        return self::CHARACTER_SETS[$charset] ?? null;
    }

    /**
     * There is a method that deal with Sven Arduwie proposal https://www.php.net/manual/en/function.realpath.php#84012
     * And runeimp at gmail dot com proposal https://www.php.net/manual/en/function.realpath.php#112367
     * @author  moreau.marc.web@gmail.com
     * @param string $path
     *   The path to decode.
     * @param bool $add_absolute
     *   Whether to prepend the current working directory if the path is relative.
     * @return string
     */
    public static function getAbsolute(string $path, bool $add_absolute = false): string
    {
        $path = self::standardizePathSeparators($path);
        // Check if path start with a separator (UNIX)
        $startWithSeparator = str_starts_with($path, '/');
        // Check if start with drive letter
        preg_match('/^[a-z]:/i', $path, $matches);
        $startWithLetterDir = $matches[0] ?? false;
        if ($startWithLetterDir) {
            // Remove the drive letter (it will be restored later on)
            $path = substr($path, strlen($startWithLetterDir));
        }

        // whikloj - 2021-07-05 : Make sure we are using an absolute path.
        if (!($startWithLetterDir || $startWithSeparator) && $add_absolute) {
            // This was relative to start with, prepend the current working directory.
            $current_dir = getcwd();
            return BagUtils::getAbsolute(rtrim($current_dir, '/') . '/' .
                ltrim($path, '/'));
        }

        // Get and filter empty sub paths
        $subPaths = array_filter(explode('/', $path), 'mb_strlen');

        $absolutes = [];
        foreach ($subPaths as $subPath) {
            if ('.' === $subPath) {
                continue;
            }
            $actual_absolutes = array_filter($absolutes, function ($value) {
                return !('..' === $value);
            });
            // if $subPath == '..'
            // and $startWithSeparator is false
            // and $startWithLetterDir is false
            // and absolutes is empty or only contains '..' subpaths.
            // save absolute cause that's a relative and we can't deal with that and just forget that we want go up
            if (
                '..' === $subPath
                && !$startWithSeparator
                && !$startWithLetterDir
                && empty($actual_absolutes)
            ) {
                $absolutes[] = $subPath;
            } elseif ('..' === $subPath) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $subPath;
            }
        }
        $prefix = ($startWithSeparator ? '/' : $startWithLetterDir)
            ? $startWithLetterDir . '/'
            : "";
        return $prefix . implode('/', $absolutes);
    }

    /**
     * Recursively list all files in a directory, except dot files.
     *
     * @param string $directory
     *   The starting full path.
     * @param array $exclusions
     *   Array with directory names to skip.
     * @return array
     *   List of files with absolute path.
     */
    public static function getAllFiles(string $directory, array $exclusions = []): array
    {
        $paths = [$directory];
        $found_files = [];

        while ($currentPath = array_shift($paths)) {
            $files = array_diff(scandir($currentPath), [".", ".."]);
            foreach ($files as $file) {
                $fullPath = $currentPath . '/' . $file;
                if (is_dir($fullPath) && !in_array($file, $exclusions)) {
                    $paths[] = $fullPath;
                } elseif (is_file($fullPath)) {
                    $found_files[] = $fullPath;
                }
            }
        }
        return $found_files;
    }

    /**
     * Copy a file and check that the copy succeeded.
     *
     * @param string $sourceFile
     *   The source path.
     * @param string $destFile
     *   The destination path.
     * @throws FilesystemException
     *   If the copy() call fails.
     * @see \copy()
     */
    public static function checkedCopy(string $sourceFile, string $destFile): void
    {
        if (!@copy($sourceFile, $destFile)) {
            throw new FilesystemException("Unable to copy file ($sourceFile) to ($destFile)");
        }
    }

    /**
     * Make a directory (or directories) and check it succeeds.
     *
     * @param string $path
     *   The path to create.
     * @param int $mode
     *   The permissions on the new directories.
     * @param bool $recursive
     *   Whether to create intermediate directories automatically.
     * @throws FilesystemException
     *   If the mkdir() call fails.
     * @see \mkdir()
     */
    public static function checkedMkdir(string $path, int $mode = 0777, bool $recursive = false): void
    {
        if (!@mkdir($path, $mode, $recursive)) {
            throw new FilesystemException("Unable to create directory $path");
        }
    }

    /**
     * Put contents to a file and check it succeeded.
     *
     * @param string $path
     *   The path of the file.
     * @param string $contents
     *   The contents to put
     * @param int $flags
     *   Flags to pass on to file_put_contents.
     * @return int
     *   Number of bytes written to the file.
     * @throws FilesystemException
     *   On any error putting the contents to the file.
     * @see \file_put_contents()
     */
    public static function checkedFilePut(string $path, string $contents, int $flags = 0): int
    {
        $res = @file_put_contents($path, $contents, $flags);
        if ($res === false) {
            throw new FilesystemException("Unable to put contents to file $path");
        }
        return $res;
    }

    /**
     * Delete a file/directory and check it succeeded.
     *
     * @param string $path
     *   The path to remove.
     * @throws FilesystemException
     *   If the call to unlink() fails.
     * @see \unlink()
     */
    public static function checkedUnlink(string $path): void
    {
        if (!@unlink($path)) {
            throw new FilesystemException("Unable to delete path $path");
        }
    }

    /**
     * Create a temporary file and check it succeeded.
     *
     * @param string $directory
     *   The directory to create the file in.
     * @param string $prefix
     *   The prefix to the file.
     * @return string
     *   The path to the temporary filename.
     * @throws FilesystemException
     *   Issues creating the file.
     * @see \tempnam()
     */
    public static function checkedTempnam(string $directory = "", string $prefix = ""): string
    {
        $res = @tempnam($directory, $prefix);
        if ($res === false) {
            throw new FilesystemException("Unable to create a temporary file with directory $directory, prefix" .
            " $prefix");
        }
        return self::standardizePathSeparators($res);
    }

    /**
     * Write to a file resource and check it succeeded.
     *
     * @param Resource $fp
     *   The file pointer.
     * @param string $content
     *   The content to write.
     * @throws FilesystemException
     *   Problem writing to file.
     */
    public static function checkedFwrite($fp, string $content): void
    {
        try {
            $res = @fwrite($fp, $content);
            if ($res === false) {
                throw new FilesystemException("Error writing to file");
            }
        } catch (TypeError $e) {
            throw new FilesystemException("Error writing to file", $e->getCode(), $e);
        }
    }

    /**
     * Remove a directory and check if it succeeded.
     * @param string $path The path to remove.
     * @throws FilesystemException If the call to rmdir() fails.
     */
    public static function checkedRmDir(string $path): void
    {
        if (!@rmdir($path)) {
            throw new FilesystemException("Unable to remove directory $path");
        }
    }

    /**
     * Decode a file path according to the special rules of the spec.
     *
     * RFC 8943 - sections 2.1.3 & 2.2.3
     * If _filename_ includes an LF, a CR, a CRLF, or a percent sign (%), those characters (and only those) MUST be
     * percent-encoded as described in [RFC3986].
     *
     * @param string $line
     *   The original filepath from the manifest file.
     * @return string
     *   The filepath with the special characters decoded.
     */
    public static function decodeFilepath(string $line): string
    {
        // Strip newlines from the right.
        $decoded = rtrim($line, "\r\n");
        return str_replace(
            ["%0A", "%0D", "%25"],
            ["\n", "\r", "%"],
            $decoded
        );
    }

    /**
     * Encode a file path according to the special rules of the spec.
     *
     * RFC 8943 - sections 2.1.3 & 2.2.3
     * If _filename_ includes an LF, a CR, a CRLF, or a percent sign (%), those characters (and only those) MUST be
     * percent-encoded as described in [RFC3986].
     *
     * @param string $line
     *   The original file path.
     * @return string
     *   The file path with the special manifest characters encoded.
     */
    public static function encodeFilepath(string $line): string
    {
        // Strip newlines from the right.
        $encoded = rtrim($line, "\r\n");
        return str_replace(
            ["%", "\n", "\r"],
            ["%25", "%0A", "%0D"],
            $encoded
        );
    }

    /**
     * Check for unencoded newlines, carriage returns or % symbols in a file path.
     *
     * @param string $filepath
     *   The file path to check
     * @return bool
     *   True if there are un-encoded characters
     * @see \whikloj\BagItTools\BagUtils::encodeFilepath()
     * @see \whikloj\BagItTools\BagUtils::decodeFilepath()
     */
    public static function checkUnencodedFilepath(string $filepath): bool
    {
        return (bool) preg_match_all("/%(?!(25|0A|0D))/", $filepath);
    }

    /**
     * Split the file data on any of the allowed line endings.
     *
     * @param string $data
     *   The file data as a single string.
     * @return array
     *   Array split on \r\n, \r, and \n
     */
    public static function splitFileDataOnLineEndings(string $data): array
    {
        return preg_split("/(\r\n|\r|\n)/", $data);
    }

    /**
     * Try using only forward slashes internally to avoid the extraneous checks for \ and or /
     *
     * @param string $path
     *   The original path.
     * @return string
     *   The corrected path using /
     */
    public static function standardizePathSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Utility function to trim and lowercase a string.
     * @param string $string The string to standardize.
     * @return string The standardized string.
     */
    public static function trimLower(string $string): string
    {
        return strtolower(trim($string));
    }

    /**
     * Walk up a path as far as the rootDir and delete empty directories.
     * @param string $path The path to check.
     * @param string $rootDir The root to not remove .
     *
     * @throws BagItException If the path is not within the bag root.
     * @throws FilesystemException If we can't remove a directory
     */
    public static function deleteEmptyDirTree(string $path, string $rootDir): void
    {
        if (rtrim(strtolower($path), '/') === rtrim(strtolower($rootDir), '/')) {
            return;
        }
        if (!str_starts_with($path, $rootDir)) {
            throw new BagItException("Path is not within the root directory.");
        }
        if (file_exists($path) && is_dir($path)) {
            $parent = dirname($path);
            $files = array_diff(scandir($path), [".", ".."]);
            if (count($files) === 0) {
                self::checkedRmDir($path);
            }
            if ($parent !== $rootDir) {
                self::deleteEmptyDirTree($parent, $rootDir);
            }
        }
    }
}
