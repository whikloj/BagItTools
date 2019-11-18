<?php

namespace whikloj\BagItTools;

/**
 * Class BagFactory
 * @package whikloj\BagItTools
 * @author whikloj
 * @since 1.0.0
 */
class Bag
{

  /**
   * The default algorithm to use if one is not specified.
   */
    const DEFAULT_HASH_ALGORITHM = 'sha512';

  /**
   * The default file encoding if one is not specified.
   */
    const DEFAULT_FILE_ENCODING = 'UTF-8';

  /**
   * The default bagit version.
   */
    const DEFAULT_BAGIT_VERSION = array(
    'major' => 1,
    'minor' => 0,
    );

  /**
   * Bag-info fields that MUST not be repeated (in lowercase).
   */
    const BAG_INFO_MUST_NOT_REPEAT = array(
    'payload-oxum'
    );

  /**
   * Reserved element names for Bag-info fields.
   */
    const BAG_INFO_RESERVED_ELEMENTS = array(
    'source-organization',
    'organization-address',
    'contact-name',
    'contact-phone',
    'contact-email',
    'external-description',
    'bagging-date',
    'external-identifier',
    'payload-oxum',
    'bag-size',
    'bag-group-identifier',
    'bag-count',
    'internal-sender-identifier',
    'internal-sender-description',
    );

  /**
   * Array of BagIt approved names of hash algorithms to the PHP names of
   * those hash algorithms for use with hash_file().
   *
   * @see https://tools.ietf.org/html/rfc8493#section-2.4
   *
   * @var array
   */
    const HASH_ALGORITHMS = array(
    'md5' => 'md5',
    'sha1' => 'sha1',
    'sha256' => 'sha256',
    'sha384' => 'sha384',
    'sha512' => 'sha512',
    'sha3224' => 'sha3-224',
    'sha3256' => 'sha3-256',
    'sha3384' => 'sha3-384',
    'sha3512' => 'sha3-512',
    );

  /**
   * Array of current bag version with keys 'major' and 'minor'.
   *
   * @var array
   */
    private $currentVersion;

  /**
   * Current bag file encoding.
   *
   * @var string
   */
    private $currentFileEncoding;

  /**
   * Array of payload manifests.
   *
   * @var array
   */
    private $payloadManifests;

  /**
   * Array of tag manifests.
   *
   * @var array
   */
    private $tagManifests;

  /**
   * List of relative file paths for all files.
   *
   * @var array
   */
    private $payloadFiles;

  /**
   * The absolute path to the root of the bag, all other file paths are
   * relative to this. This path is stored with / as directory separator
   * regardless of the OS.
   *
   * @var string
   */
    private $bagRoot;

  /**
   * Is this an extended bag?
   *
   * @var boolean
   */
    private $isExtended;

  /**
   * The valid algorithms from the current version of PHP filtered to those
   * supported by the BagIt specification. Stored to avoid extraneous calls
   * to hash_algos().
   *
   * @var array
   */
    private $validHashAlgorithms;

  /**
   * Errors when validating a bag.
   *
   * @var array
   */
    private $bagErrors;

  /**
   * Errors while initially loading a bag.
   *
   * @var array
   */
    private $loadErrors;

  /**
   * Have we changed the bag and not written it to disk?
   *
   * @var boolean
   */
    private $changed = false;

  /**
   * Bag Info data.
   *
   * @var array
   */
    private $bagInfoData = [];

  /**
   * Did we load this from disk.
   *
   * @var boolean
   */
    private $loaded = false;

  /**
   * BagFactory constructor.
   *
   * @param string $rootPath
   *   The path of the root of the new or existing bag.
   * @param boolean $new
   *   Are we making a new bag?
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Problems accessing a file.
   */
    public function __construct($rootPath, $new = true)
    {
        // Define valid hash algorithms our PHP supports.
        $this->validHashAlgorithms = array_filter(
            hash_algos(),
            array($this, 'filterPhpHashAlgorithms')
        );
        // Alter the algorithm name to the sanitize version.
        array_walk(
            $this->validHashAlgorithms,
            array($this, 'normalizeHashAlgorithmName')
        );
        $this->bagRoot = $this->internalPath($rootPath);
        $this->loaded = (!$new);
        if ($new) {
            $this->createNewBag();
        } else {
            $this->loadBag();
        }
    }

  /**
   * Validate the bag as it appears on disk.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Problems writing to disk.
   */
    public function validate()
    {
        if ($this->changed) {
            $this->writeToDisk();
        }
        // TODO: Need to validate.
    }

  /**
   * Write the updated BagIt files to disk.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Errors with writing files to disk.
   */
    public function writeToDisk()
    {
        if (!file_exists($this->makeAbsolute("data"))) {
            mkdir($this->makeAbsolute("data"), 0777);
        }
        $this->updateBagIt();
        $this->updatePayloadManifests();
        if ($this->isExtended) {
            $this->updateTagManifests();
            $this->updateBagInfo();
        } else {
            $this->removeTagManifests();
            $this->removeBagInfo();
        }
        $this->changed = false;
    }

  /**
   * Add a file to the bag.
   *
   * @param string $source
   *   Full path to the source file.
   * @param $dest
   *   Relative path for the destination.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Source file does not exist or the destination is outside the data directory.
   */
    public function addFile($source, $dest)
    {
        if (file_exists($source)) {
            $dest = BagUtils::baseInData($dest);
            if ($this->pathInBagData($dest)) {
                $fullDest = $this->makeAbsolute($dest);
                $dirname = dirname($fullDest);
                if (substr($this->makeRelative($dirname), 0, 5) == "data/") {
                    // Create any missing missing directories inside data.
                    if (!file_exists($dirname)) {
                        mkdir($dirname, 0777, true);
                    }
                }
                copy($source, $fullDest);
                foreach ($this->payloadManifests as $hash => $manifest) {
                    $manifest->addFile($dest);
                }
            } else {
                throw new BagItException("Path {$dest} resolves outside the bag.");
            }
        } else {
            throw new BagItException("{$source} does not exist");
        }
    }

  /**
   * Remove a payload file.
   *
   * @param $dest
   *   The relative path of the file.
   */
    public function removeFile($dest)
    {
        $dest = BagUtils::baseInData($dest);
        if ($this->pathInBagData($dest)) {
            $fullPath = $this->makeAbsolute($dest);
            if (file_exists($fullPath) && is_file($fullPath)) {
                unlink($fullPath);
                $this->checkForEmptyDir($fullPath);
            }
        }
    }

  /**
   * Add the bag root to the front of a relative bag path and return with
   * OS directory separator.
   *
   * @param $path
   *   The relative path.
   * @return string
   *   The absolute path.
   */
    public function makeAbsolute($path)
    {
        $length = strlen($this->bagRoot);
        $path = $this->internalPath($path);
        if (substr($path, 0, $length) == $this->bagRoot) {
            return $path;
        }
        $components = array_filter(explode("/", $path));
        $rootComponents = array_filter(explode("/", $this->bagRoot));
        $components = array_merge($rootComponents, $components);
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $components);
    }

  /**
   * Remove all the extraneous path information and make relative to bag root.
   *
   * @param $path
   *   The path to process
   * @return bool|string
   *   The shortened string.
   */
    public function makeRelative($path)
    {
        $path = $this->internalPath($path);
        $path = self::getAbsolute($path);
        if (substr($path, 0, strlen($this->bagRoot)) !== $this->bagRoot) {
            return '';
        }
        $relative = substr($path, strlen($this->bagRoot) + 1);
        if ($relative === false) {
            return '';
        }
        return $relative;
    }

  /**
   * Return path to the bag root.
   *
   * @return string
   *   The bag root path.
   */
    public function getBagRoot()
    {
        return $this->bagRoot;
    }

  /**
   * Return path to the data directory.
   *
   * @return string
   *   The bag data directory path.
   */
    public function getDataDirectory()
    {
        return $this->makeAbsolute("data");
    }

  /**
   * Check the bag's extended status.
   *
   * @return boolean
   *   Does the bag use extended features?
   */
    public function isExtended()
    {
        return $this->isExtended;
    }

  /**
   * Turn extended bag features on or off.
   *
   * @param boolean $extBag
   *   Whether the bag should be extended or not.
   */
    public function setExtended($extBag)
    {
        $extBag = (bool) $extBag;
        $this->isExtended = $extBag;
    }

  /**
   * Get errors on the bag.
   *
   * @return array
   *   The errors.
   */
    public function getErrors()
    {
        if ($this->loaded) {
            return $this->loadErrors;
        }
        return $this->bagErrors;
    }

  /**
   * Get the payload manifests as an associative array with hash algorithm as key.
   *
   * @return array
   *   hash algorithm => Payload manifests
   */
    public function getPayloadManifests()
    {
        return $this->payloadManifests;
    }

  /**
   * Get the tag manifests as an associative array with hash algorithm as key.
   *
   * @return array
   *   hash algorithm => Tag manifests
   */
    public function getTagManifests()
    {
        return $this->tagManifests;
    }

  /*
   *  XXX: Private functions
   */

  /**
   * Load a bag from disk.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   If a file cannot be read.
   */
    private function loadBag()
    {
        $this->loadErrors = [];
        $this->loadBagIt();
        $this->loadPayloadManifests();
        $bagInfo = $this->loadBagInfo();
        $tagManifest = $this->loadTagManifests();
        $this->isExtended = ($bagInfo || $tagManifest);
    }

  /**
   * Create a new bag and output the default parts.
   */
    private function createNewBag()
    {
        $this->bagErrors = [];
        if (!file_exists($this->bagRoot)) {
            mkdir($this->bagRoot . DIRECTORY_SEPARATOR . "data", 0777, true);
        }
        $this->updateBagIt();
        $this->payloadManifests = [
        self::DEFAULT_HASH_ALGORITHM => new PayloadManifest($this, self::DEFAULT_HASH_ALGORITHM)
        ];
    }

  /**
   * Read in the bag-info.txt file.
   *
   * @return boolean
   *   Does bag-info.txt exists.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Unable to read bag-info.txt
   */
    private function loadBagInfo()
    {
        $fullPath = $this->makeAbsolute("bag-info.txt");
        if (file_exists($fullPath)) {
            $fp = fopen($fullPath, 'rb');
            if ($fp === false) {
                throw new BagItException("Unable to access bag-info.txt");
            }
            $this->bagInfoData = [];
            while (feof($fp)) {
                $line = fgets($fp);
                $line = trim($line);
                if (preg_match("~^([^:]+)\s*:\s+(.*)$~", $line, $matches)) {
                    // First line
                    $current_tag = $matches[1];
                    if ($this->checkForNonRepeatableBagInfoFields($current_tag)) {
                        $this->loadErrors[] = [
                        'file' => 'bag-info.txt',
                        'message' => "Tag {$current_tag} MUST not be repeated.",
                        ];
                    }
                    $this->bagInfoData[$current_tag] = [
                    $matches[2],
                    ];
                } elseif ($line[0] == " " || $line[0] == "\t") {
                    $last_row_count = count($this->bagInfoData[$current_tag]);
                    $last_value = &$this->bagInfoData[$current_tag][$last_row_count];
                    $last_value .= " " . trim($line);
                }
            }
            fclose($fp);
            return true;
        }
        return false;
    }

  /**
   * Write the contents of the bag-info array to disk.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Can't write the file to disk.
   */
    private function updateBagInfo()
    {
        $fullPath = $this->makeAbsolute("bag-info.txt");
        $fp = fopen($fullPath, 'wb');
        if ($fp === false) {
            throw new BagItException("Could not write bag-info.txt");
        }
        foreach ($this->bagInfoData as $tag => $tag_values) {
            foreach ($tag_values as $tag_value) {
                $wrappedText = str_split($tag_value, 79);
                $value = array_shift($wrappedText);
                fwrite($fp, "$tag: $value\r\n");
                foreach ($wrappedText as $value) {
                    fwrite($fp, " $value\r\n");
                }
            }
        }
        fclose($fp);
    }

  /**
   * Remove the bag-info.txt file and data.
   */
    private function removeBagInfo()
    {
        $fullPath = $this->makeAbsolute('bag-info.txt');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        unset($this->bagInfoData);
    }

  /**
   * Load all tag manifests (if any).
   *
   * @return boolean
   *   Are there any tag manifest files.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Problems with glob() pattern or loading manifest.
   */
    private function loadTagManifests()
    {
        $this->tagManifests = [];
        $pattern = $this->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-*.txt";
        $files = BagUtils::findAllByPattern($pattern);
        if (count($files) > 0) {
            foreach ($files as $file) {
                $hash = self::determineHashFromFilename($file);
                if (isset($this->tagManifests[$hash])) {
                    $this->loadErrors[] = [
                    'file' => $this->makeRelative($file),
                    'message' => "More than one tag manifest for hash ({$hash}) found.",
                    ];
                } else {
                    $this->tagManifests[$hash] = new TagManifest($this, $hash, true);
                }
            }
            return true;
        }
        return false;
    }

  /**
   * Run update against the tag manifests.
   */
    private function updateTagManifests()
    {
        if (!isset($this->tagManifests)) {
            $manifest = new TagManifest($this, self::DEFAULT_HASH_ALGORITHM);
            $this->tagManifests = [$manifest];
        }
        foreach ($this->tagManifests as $manifest) {
            $manifest->update();
        }
    }

  /**
   * Remove all tagmanifest files.
   *
   * @throws \whikloj\BagItTools\BagItException
   *    Errors with glob() pattern.
   */
    private function removeTagManifests()
    {
        $pattern = $this->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-*.txt";
        $files = BagUtils::findAllByPattern($pattern);
        if (count($files) > 0) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        unset($this->tagManifests);
    }

  /**
   * Load all payload manifests found on disk.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Problems with glob() pattern or loading manifest.
   */
    private function loadPayloadManifests()
    {
        $pattern = $this->getBagRoot() . DIRECTORY_SEPARATOR . "manifest-*.txt";
        $files = BagUtils::findAllByPattern($pattern);
        if (count($files) == 0) {
            $this->loadErrors[] = [
            'file' => 'manifest-ALG.txt',
            'message' => 'No payload manifest files found.',
            ];
        } else {
            foreach ($files as $file) {
                $hash = self::determineHashFromFilename($file);
                if (isset($this->payloadManifests[$hash])) {
                    $this->loadErrors[] = [
                    'file' => $this->makeRelative($file),
                    'message' => "More than one payload manifest for hash ({$hash}) found.",
                    ];
                } else {
                    $this->payloadManifests[$hash] = new PayloadManifest($this, $hash, true);
                }
            }
        }
    }

  /**
   * Run update against the payload manifests.
   */
    private function updatePayloadManifests()
    {
        if (!isset($this->payloadManifests)) {
            $manifest = new PayloadManifest($this, self::DEFAULT_HASH_ALGORITHM);
            $this->payloadManifests = [$manifest];
        }
        foreach ($this->payloadManifests as $manifest) {
            $manifest->update();
        }
    }

  /**
   * Load the bagit.txt on disk.
   *
   * @throws \whikloj\BagItTools\BagItException
   *   Can't read the file on disk.
   */
    private function loadBagIt()
    {
        $fullPath = $this->makeAbsolute("bagit.txt");
        if (!file_exists($fullPath)) {
            $this->loadErrors[] = [
            'file' => 'bagit.txt',
            'message' => 'Required file missing.',
            ];
        } else {
            $contents = file_get_contents($fullPath);
            if ($contents === false) {
                throw new BagItException("Unable to read {$fullPath}");
            }
            $lines = preg_split("~[\r\n]+~", $contents, null, PREG_SPLIT_NO_EMPTY);
            if (count($lines) !== 2) {
                $this->loadErrors[] = [
                'file' => 'bagit.txt',
                'message' => sprintf(
                    "File should contain exactly 2 lines found %b",
                    count($lines)
                ),
                ];
            } else {
                if (!preg_match(
                    "~^BagIt\-Version: (\d+)\.(\d+)$~",
                    $lines[0],
                    $match
                )) {
                    $this->loadErrors[] = [
                    'file' => 'bagit.txt',
                    'message' => 'First line should have pattern BagIt-Version: M.N',
                    ];
                } else {
                    $this->currentVersion = [
                    'major' => $match[1],
                    'minor' => $match[2],
                    ];
                }
                if (!preg_match(
                    "~^Tag\-File\-Character\-Encoding: (.*)$~",
                    $lines[1],
                    $match
                )) {
                    $this->loadErrors[] = [
                    'file' => 'bagit.txt',
                    'message' => 'Second line should have pattern '.
                      'Tag-File-Character-Encoding: ENCODING',
                    ];
                } else {
                    $this->currentFileEncoding = $match[1];
                }
            }
        }
    }

  /**
   * Update the bagit.txt on disk.
   */
    private function updateBagIt()
    {
        $version = $this->getVersion();

        $output = sprintf(
            "BagIt-Version: %d.%d\n" .
            "Tag-File-Character-Encoding: %s\n",
            $version['major'],
            $version['minor'],
            $this->getFileEncoding()
        );

        file_put_contents(
            $this->makeAbsolute("bagit.txt"),
            $output
        );
    }

  /**
   * Get current file encoding or default if not specified.
   *
   * @return string
   *   Current file encoding.
   */
    private function getFileEncoding()
    {
        if (isset($this->currentFileEncoding)) {
            return $this->currentFileEncoding;
        }
        return self::DEFAULT_FILE_ENCODING;
    }

  /**
   * Get the current version or default if not specified.
   *
   * @return array
   *   Current version.
   */
    private function getVersion()
    {
        if (isset($this->currentVersion)) {
            return $this->currentVersion;
        }
        return self::DEFAULT_BAGIT_VERSION;
    }

  /**
   * Check the directory we just deleted a file from, if empty we should remove
   * it too.
   *
   * @param string $path
   *   The file just deleted.
   */
    private function checkForEmptyDir($path)
    {
        $parentPath = dirname($path);
        if (substr($this->makeRelative($parentPath), 0, 5) == "data/") {
            $files = scandir($parentPath);
            $payload = array_filter($files, function ($o) {
                // Don't count directory specifiers.
                return ($o !== "." && $o !== "..");
            });
            if (count($payload) == 0) {
                  rmdir($parentPath);
            }
        }
    }

  /**
   * Convert paths from using the OS directory separator to using /.
   *
   * @param string $path
   *   The external path.
   * @return string
   *   The modified path.
   */
    private function internalPath($path)
    {
        return str_replace(DIRECTORY_SEPARATOR, "/", $path);
    }

  /**
   * Normalize a PHP hash algorithm to a BagIt specification name. Used to alter the incoming $item.
   *
   * @param string $item The hash algorithm name.
   * @author Jared Whiklo <jwhiklo@gmail.com>
   */
    private static function normalizeHashAlgorithmName(&$item)
    {
        $item = array_flip(self::HASH_ALGORITHMS)[$item];
    }

  /**
   * Check if the algorithm PHP has is allowed by the specification.
   *
   * @param string $item A hash algorithm name.
   *
   * @return bool True if allowed by the specification.
   * @author Jared Whiklo <jwhiklo@gmail.com>
   */
    private static function filterPhpHashAlgorithms($item)
    {
        return in_array($item, array_values(self::HASH_ALGORITHMS));
    }

  /**
   * Case-insensitive version of array_key_exists
   *
   * @param string $search The key to look for.
   * @param array $map The associative array to search.
   * @return bool True if the key exists regardless of case.
   */
    private static function arrayKeyExistsNoCase($search, array $map)
    {
        $keys = array_column($map, 'tag');
        array_walk($keys, function (&$item) {
            $item = strtolower($item);
        });
        return in_array(strtolower($search), $keys);
    }

  /**
   * Check that the key is not non-repeatable and already in the bagInfo.
   *
   * @param string $key The key being added.
   *
   * @return boolean
   *   True if the key is non-repeatable and already in the
   */
    private function nonRepeatableBagInfoFieldExists($key)
    {
        return (in_array(strtolower($key), self::BAG_INFO_MUST_NOT_REPEAT) &&
        self::arrayKeyExistsNoCase($key, $this->bagInfoData));
    }

  /**
   * Parse manifest/tagmanifest file names to determine hash algorithm.
   *
   * @param string $filepath the filename.
   *
   * @return string|null the hash or null.
   */
    private static function determineHashFromFilename($filepath)
    {
        $filename = basename($filepath);
        if (preg_match('~\-(\w+)\.txt$~', $filename, $matches)) {
            return $matches[1];
        }
        return null;
    }

  /**
   * Is the path inside the payload directory?
   *
   * @param string $filepath
   *   The internal path.
   * @return boolean
   *   Path is inside the data/ directory.
   */
    private function pathInBagData($filepath)
    {
        $external = $this->makeAbsolute($filepath);
        $external = trim($external);
        $external = self::getAbsolute($external);
        $relative = $this->makeRelative($external);
        return ($relative !== "" && substr($relative, 0, 5) === "data/");
    }

  /**
   * There is a method that deal with Sven Arduwie proposal https://www.php.net/manual/en/function.realpath.php#84012
   * And runeimp at gmail dot com proposal https://www.php.net/manual/en/function.realpath.php#112367
   * @author  moreau.marc.web@gmail.com
   * @param string $path
   * @return string
   */
    public static function getAbsolute(string $path): string
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
        $startWithLetterDir.DIRECTORY_SEPARATOR : ''
        ).implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}
