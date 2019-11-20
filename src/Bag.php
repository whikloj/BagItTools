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
    const DEFAULT_BAGIT_VERSION = [
        'major' => 1,
        'minor' => 0,
    ];

    /**
     * Bag-info fields that MUST not be repeated (in lowercase).
     */
    const BAG_INFO_MUST_NOT_REPEAT = [
        'payload-oxum',
    ];

    /**
     * Reserved element names for Bag-info fields.
     */
    const BAG_INFO_RESERVED_ELEMENTS = [
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
    ];

    /**
     * Fields you can't set because we generate them on $bag->update().
     */
    const BAG_INFO_GENERATED_ELEMENTS = [
        'payload-oxum',
        'bagging-date',
    ];

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
     * Unique array of all Bag info tags/values. Tags are stored once in lower case with an array of all instances
     * of values. This index does not save order.
     *
     * @var array
     */
    private $bagInfoTagIndex = [];

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
            $this->update();
        }
        // TODO: Need to validate.
    }

    /**
     * Write the updated BagIt files to disk.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   Errors with writing files to disk.
     */
    public function update()
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
            $this->clearTagManifests();
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
                $this->changed = true;
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
                $this->changed = true;
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
     * Return raw bag info data.
     *
     * @return array
     *   Bag Info data.
     */
    public function getBagInfoData()
    {
        return $this->bagInfoData;
    }

    /**
     * Case-insensitive search of bag-info tags.
     * @param string $tag
     *   Bag info tag to locate
     * @return bool
     *   Does the tag exist.
     */
    public function hasBagInfoTag($tag)
    {
        $tag = self::trimLower($tag);
        return $this->bagInfoTagExists($tag);
    }

    /**
     * Find all instances of tag and return an array of values.
     *
     * @param $tag
     *   Bag info tag to locate
     * @return array
     *   Array of values for the tag.
     */
    public function getBagInfoByTag($tag)
    {
        $tag = self::trimLower($tag);
        if ($this->bagInfoTagExists($tag)) {
            return $this->bagInfoTagIndex[$tag];
        }
        return [];
    }

    /**
     * Remove ALL instances of tag.
     *
     * @param string $tag
     *   The tag to remove.
     */
    public function removeBagInfoTag($tag)
    {
        $tag = self::trimLower($tag);
        if ($this->bagInfoTagExists($tag)) {
            $newInfo = [];
            foreach ($this->bagInfoData as $row) {
                $rowTag = self::trimLower($row['tag']);
                if ($rowTag !== $tag) {
                    $newInfo[] = $row;
                }
            }
            $this->bagInfoData = $newInfo;
            $this->updateBagInfoIndex();
        }
    }

    /**
     * Removes a specific entry for a tag by the array index. This can be determined using the index in the array
     * returned by getBagInfoByKey().
     *
     * @param string $tag
     *   The tag to remove.
     * @param int $index
     *   The index of the value to remove.
     */
    public function removeBagInfoTagIndex($tag, $index)
    {
        if (is_int($index) && $index > -1) {
            $tag = self::trimLower($tag);
            if ($this->bagInfoTagExists($tag)) {
                $values = $this->getBagInfoByTag($tag);
                if ($index < count($values)) {
                    $newInfo = [];
                    $tagCount = 0;
                    foreach ($this->bagInfoData as $row) {
                        $rowTag = self::trimLower($row['tag']);
                        if ($rowTag !== $tag || $tagCount !== $index) {
                            $newInfo[] = $row;
                        }
                        if ($rowTag == $tag) {
                            $tagCount += 1;
                        }
                    }
                    $this->bagInfoData = $newInfo;
                    $this->updateBagInfoIndex();
                }
            }
        }
    }

    /**
     * Add tag and value to bag-info.
     *
     * @param string $tag
     *   The tag to add.
     * @param string $value
     *   The value to add.
     * @throws \whikloj\BagItTools\BagItException
     *   When you try to set an auto-generated tag value.
     */
    public function setBagInfoTag($tag, $value)
    {
        $internal_tag = self::trimLower($tag);
        if (in_array($internal_tag, self::BAG_INFO_GENERATED_ELEMENTS)) {
            throw new BagItException("Field {$tag} is auto-generated and cannot be manually set.");
        }
        if (!$this->bagInfoTagExists($internal_tag)) {
            $this->bagInfoTagIndex[$internal_tag] = [];
        }
        $this->bagInfoTagIndex[$internal_tag][] = $value;
        $this->bagInfoData[] = [
            'tag' => trim($tag),
            'value' => trim($value),
        ];
    }

    /**
     * Set the file encoding.
     *
     * @param string $encoding
     *   The MIME name of the character set to encode with.
     * @throws \whikloj\BagItTools\BagItException
     *   If we don't support the requested character set.
     */
    public function setFileEncoding($encoding)
    {
        $encoding = self::trimLower($encoding);
        if ($encoding == self::trimLower(self::DEFAULT_FILE_ENCODING)) {
            // go back to default.
            unset($this->currentFileEncoding);
        } else {
            $charset = BagUtils::getValidCharset($encoding);
            if (is_null($charset)) {
                throw new BagItException("Character set {$encoding} is not supported.");
            } else {
                $this->currentFileEncoding = $charset;
            }
        }
    }

    /**
     * Get current file encoding or default if not specified.
     *
     * @return string
     *   Current file encoding.
     */
    public function getFileEncoding()
    {
        if (isset($this->currentFileEncoding)) {
            return $this->currentFileEncoding;
        }
        return self::DEFAULT_FILE_ENCODING;
    }

    /**
     * Get the currently active payload (and tag) manifests.
     *
     * @return array
     *   Internal hash names for current manifests.
     */
    public function getAlgorithms()
    {
        return array_keys($this->payloadManifests);
    }

    /**
     * Do we have this hash algorithm already?
     *
     * @param string $hashAlgorithm
     *   The requested hash algorithms.
     *
     * @return bool Do we already have this payload manifest.
     */
    public function hasAlgorithm($hashAlgorithm)
    {
        $internal_name = $this->getHashName($hashAlgorithm);
        if ($this->hashIsSupported($internal_name)) {
            return $this->hasHash($internal_name);
        }
        return false;
    }

    /**
     * The algorithm is supported.
     *
     * @param string $algorithm
     *   The requested hash algorithm
     * @return bool
     *   Whether it is supported by our PHP.
     */
    public function algorithmIsSupported($algorithm)
    {
        $internal_name = $this->getHashName($algorithm);
        return $this->hashIsSupported($internal_name);
    }

    /**
     * Add a hash algorithm to the bag.
     *
     * @param string $algorithm
     *   Algorithm to add.
     * @throws \whikloj\BagItTools\BagItException
     *   Asking for an unsupported algorithm.
     */
    public function addAlgorithm($algorithm)
    {
        $internal_name = $this->getHashName($algorithm);
        if ($this->hashIsSupported($internal_name)) {
            if (!array_key_exists($internal_name, $this->payloadManifests)) {
                $this->payloadManifests[$internal_name] = new PayloadManifest($this, $internal_name);
            }
            if ($this->isExtended) {
                $this->ensureTagManifests();
                if (!array_key_exists($internal_name, $this->tagManifests)) {
                    $this->tagManifests[$internal_name] = new TagManifest($this, $internal_name);
                }
            }
        } else {
            throw new BagItException("Algorithm {$algorithm} is not supported.");
        }
    }

    /**
     * Remove a hash algorithm from the bag.
     *
     * @param string $algorithm
     *   Algorithm to remove
     * @throws \whikloj\BagItTools\BagItException
     *   Trying to remove the last algorithm or asking for an unsupported algorithm.
     */
    public function removeAlgorithm($algorithm)
    {
        $internal_name = $this->getHashName($algorithm);
        if ($this->hashIsSupported($internal_name)) {
            if (array_key_exists($internal_name, $this->payloadManifests)) {
                if (count($this->payloadManifests) == 1) {
                    throw new BagItException("Cannot remove last payload algorithm, add one before removing this one");
                }
                $this->removePayloadManifest($internal_name);
            }
            if ($this->isExtended && isset($this->tagManifests) &&
                array_key_exists($internal_name, $this->tagManifests)) {
                if (count($this->tagManifests) == 1) {
                    throw new BagItException("Cannot remove last tag algorithm, add one before removing this one");
                }
                $this->removeTagManifest($internal_name);
            }
        } else {
            throw new BagItException("Algorithm {$algorithm} is not supported.");
        }
    }

    /**
     * Replaces any existing hash algorithms with the one requested.
     *
     * @param string $algorithm
     *   Algorithm to use.
     * @throws \whikloj\BagItTools\BagItException
     *   Asking for an unsupported algorithm.
     */
    public function setAlgorithm($algorithm)
    {
        $internal_name = $this->getHashName($algorithm);
        if ($this->hashIsSupported($internal_name)) {
            $this->removeAllPayloadManifests([$internal_name]);
            if (count($this->payloadManifests) == 0) {
                $this->payloadManifests[$internal_name] = new PayloadManifest($this, $internal_name);
            }
            $this->removeAllTagManifests([$internal_name]);
            if ($this->isExtended) {
                $this->ensureTagManifests();
                if (count($this->tagManifests) == 0) {
                    $this->tagManifests[$internal_name] = new TagManifest($this, $internal_name);
                }
            }
        } else {
            throw new BagItException("Algorithm {$algorithm} is not supported.");
        }
    }

    /**
     * Get the current version or default if not specified.
     *
     * @return array
     *   Current version.
     */
    public function getVersion()
    {
        if (isset($this->currentVersion)) {
            return $this->currentVersion;
        }
        return self::DEFAULT_BAGIT_VERSION;
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
        $this->bagErrors = [];
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
            $bagData = [];
            while (!feof($fp)) {
                $line = fgets($fp);
                $line = trim($line);
                if ($line == "") {
                    continue;
                }
                if (!mb_check_encoding($line, $this->getFileEncoding())) {
                    $this->bagErrors[] = [
                        'file' => 'bag-info.txt',
                        'message' => sprintf(
                            "Expected encoding %s, found %s",
                            $this->getFileEncoding(),
                            mb_detect_encoding($line)
                        ),
                    ];
                }
                $line = $this->decodeText($line);

                if (preg_match("~^([^:]+)\s*:\s+(.*)$~", $line, $matches)) {
                    // First line
                    $current_tag = $matches[1];
                    if ($this->nonRepeatableBagInfoFieldExists($current_tag)) {
                        $this->bagErrors[] = [
                            'file' => 'bag-info.txt',
                            'message' => "Tag {$current_tag} MUST not be repeated.",
                        ];
                    }
                    $bagData[] = [
                        'tag' => $current_tag,
                        'value' => trim($matches[2]),
                    ];
                } elseif (!empty($line) && ($line[0] == " " || $line[0] == "\t")) {
                    if (count($bagData) > 0) {
                        $bagData[count($bagData)]['value'] .= " " . trim($line);
                    }
                }
            }
            fclose($fp);
            $this->bagInfoData = $bagData;
            $this->updateBagInfoIndex();
            return true;
        }
        return false;
    }

    /**
     * Return a trimmed and lowercase version of text.
     * @param string $text
     *   The original text.
     * @return string
     *   The lowercase trimmed text.
     */
    private static function trimLower($text)
    {
        $text = strtolower($text);
        return trim($text);
    }

    /**
     * Generate a faster index of Bag-Info tags.
     */
    private function updateBagInfoIndex()
    {
        $tags = [];
        foreach ($this->bagInfoData as $row) {
            $tagName = self::trimLower($row['tag']);
            if (!array_key_exists($tagName, $tags)) {
                $tags[$tagName] = [];
            }
            $tags[$tagName][] = $row['value'];
        }
        $this->bagInfoTagIndex = $tags;
    }

    /**
     * Internal case insensitive search of bag info.
     *
     * @param string $internal_tag
     *   Trimmed and lowercase tag.
     * @return bool
     *   Does it exist in the index.
     */
    private function bagInfoTagExists($internal_tag)
    {
        return array_key_exists($internal_tag, $this->bagInfoTagIndex);
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
        $this->updateCalculateBagInfoFields();
        $this->updateBagInfoIndex();
        foreach ($this->bagInfoData as $bag_info_datum) {
            $tag = $bag_info_datum['tag'];
            $value = $bag_info_datum['value'];
            $data = self::wrapBagInfoText("{$tag}: {$value}");
            foreach ($data as $line) {
                $line = $this->encodeText($line);
                fwrite($fp, $line . PHP_EOL);
            }
        }
        fclose($fp);
    }

    /**
     * Update the calculated bag-info fields
     */
    private function updateCalculateBagInfoFields()
    {
        $newInfo = [];
        foreach ($this->bagInfoData as $row) {
            if (in_array(self::trimLower($row['tag']), self::BAG_INFO_GENERATED_ELEMENTS)) {
                continue;
            }
            $newInfo[] = $row;
        }
        $oxum = $this->calculateOxum();
        if (!is_null($oxum)) {
            $newInfo[] = [
                'tag' => 'Payload-Oxum',
                'value' => $oxum,
            ];
        }
        $newInfo[] = [
            'tag' => 'Bagging-Date',
            'value' => date('Y-m-d', time()),
        ];
        $this->bagInfoData = $newInfo;
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
        $this->bagInfoData = [];
    }

    /**
     * Calculate the payload-oxum value for all payload files.
     *
     * @return string|null
     *   The payload-oxum or null if we couldn't read all the file sizes.
     */
    private function calculateOxum()
    {
        $total_size = 0;
        $total_files = 0;
        foreach ($this->payloadFiles as $file) {
            $fullPath = $this->makeAbsolute($file);
            if (file_exists($fullPath) && is_file($fullPath)) {
                $info = stat($fullPath);
                if (isset($info[7])) {
                    $total_size += (int) $info[7];
                } else {
                    return null;
                }
                $total_files += 1;
            }
        }
        return "{$total_size}.{$total_files}";
    }

    /**
     * Wrap bagInfo lines to 79 characters if possible
     *
     * @param $text
     *   The whole tag and value as one.
     * @return array
     *   The text as an array.
     */
    private static function wrapBagInfoText($text)
    {
        // Start at 78 for some leeway.
        $length = 78;
        do {
            $rows = self::wrapAtLength($text, $length);
            $too_long = array_filter($rows, function ($o) {
                return strlen($o) > 78;
            });
            $length -= 1;
        } while ($length > 0 && count($too_long) > 0);
        if (count($too_long) > 0) {
            // No matter the size we couldn't get it to fit in 79 characters. So we give up.
            $rows = self::wrapAtLength($text, 78);
        }
        for ($foo = 1; $foo < count($rows); $foo += 1) {
            $rows[$foo] = "  " . $rows[$foo];
        }
        return $rows;
    }

    /**
     * Utility to remove newline characters, wrap the string and return an array of the rows.
     * @param $text
     *   The text to wrap.
     * @param $length
     *   The length to wrap at.
     * @return array
     *   Rows of text.
     */
    private static function wrapAtLength($text, $length)
    {
        $text = str_replace("\n", "", $text);
        $wrapped = wordwrap($text, $length, "\n");
        return explode("\n", $wrapped);
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
        $tagManifests = [];
        $pattern = $this->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-*.txt";
        $files = BagUtils::findAllByPattern($pattern);
        if (count($files) > 0) {
            foreach ($files as $file) {
                $hash = self::determineHashFromFilename($file);
                if (isset($tagManifests[$hash])) {
                    $this->bagErrors[] = [
                        'file' => $this->makeRelative($file),
                        'message' => "More than one tag manifest for hash ({$hash}) found.",
                    ];
                } else {
                    $tagManifests[$hash] = new TagManifest($this, $hash, true);
                }
            }
            $this->tagManifests = $tagManifests;
            return true;
        }
        return false;
    }

    /**
     * Utility to setup tag manifests.
     */
    private function ensureTagManifests()
    {
        if (!isset($this->tagManifests)) {
            $this->tagManifests = [];
        }
    }

    /**
     * Run update against the tag manifests.
     */
    private function updateTagManifests()
    {
        if ($this->isExtended) {
            $this->clearTagManifests();
            $this->ensureTagManifests();
            $hashes = (is_array($this->payloadManifests) ? $this->payloadManifests :
                [self::DEFAULT_HASH_ALGORITHM => ""]);
            $hashes = array_diff_key($hashes, $this->tagManifests);
            foreach (array_keys($hashes) as $hash) {
                $this->tagManifests[$hash] = new TagManifest($this, $hash);
            }
            foreach ($this->tagManifests as $manifest) {
                $manifest->update();
            }
        }
    }

    /**
     * Remove all tagmanifest files.
     *
     * @throws \whikloj\BagItTools\BagItException
     *    Errors with glob() pattern.
     */
    private function clearTagManifests()
    {
        $pattern = $this->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-*.txt";
        $this->clearFilesOfPattern($pattern);
        unset($this->tagManifests);
    }

    /**
     * Remove tag manifests.
     *
     * @param array $exclusions
     *   Hash algorithm names of manifests to preserve.
     */
    private function removeAllTagManifests($exclusions = [])
    {
        if (isset($this->tagManifests)) {
            foreach ($this->tagManifests as $hash => $manifest) {
                if (in_array($hash, $exclusions)) {
                    continue;
                }
                $this->removeTagManifest($hash);
            }
        }
    }

    /**
     * Remove a single tag manifest.
     *
     * @param string $internal_name
     *   The hash name to remove.
     */
    private function removeTagManifest($internal_name)
    {
        $manifest = $this->tagManifests[$internal_name];
        $filename = $manifest->getFilename();
        if (file_exists($filename)) {
            unlink($this->makeAbsolute($filename));
        }
        unset($this->tagManifests[$internal_name]);
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
            $this->bagErrors[] = [
                'file' => 'manifest-ALG.txt',
                'message' => 'No payload manifest files found.',
            ];
        } else {
            foreach ($files as $file) {
                $hash = self::determineHashFromFilename($file);
                if (isset($this->payloadManifests[$hash])) {
                    $this->bagErrors[] = [
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
            $this->payloadManifests = [self::DEFAULT_HASH_ALGORITHM => $manifest];
        }
        // Delete all manifest files, before we update the current manifests.
        $this->clearPayloadManifests();
        $files = [];
        foreach ($this->payloadManifests as $manifest) {
            $manifest->update();
            $files = array_merge($files, array_keys($manifest->getHashes()));
        }
        $this->payloadFiles = $files;
    }

    /**
     * Remove all manifest files.
     *
     * @throws \whikloj\BagItTools\BagItException
     *    Errors with glob() pattern.
     */
    private function clearPayloadManifests()
    {
        $pattern = $this->getBagRoot() . DIRECTORY_SEPARATOR . "manifest-*.txt";
        $this->clearFilesOfPattern($pattern);
    }

    /**
     * Remove payload manifests.
     *
     * @param array $exclusions
     *   Hash algorithm names of manifests to preserve.
     */
    private function removeAllPayloadManifests($exclusions = [])
    {
        foreach ($this->payloadManifests as $hash => $manifest) {
            if (in_array($hash, $exclusions)) {
                continue;
            }
            $this->removePayloadManifest($hash);
        }
    }

    /**
     * Remove a single payload manifest.
     *
     * @param string $internal_name
     *   The hash name to remove.
     */
    private function removePayloadManifest($internal_name)
    {
        $manifest = $this->payloadManifests[$internal_name];
        $filename = $manifest->getFilename();
        if (file_exists($filename)) {
            unlink($this->makeAbsolute($filename));
        }
        unset($this->payloadManifests[$internal_name]);
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
            $this->bagErrors[] = [
                'file' => 'bagit.txt',
                'message' => 'Required file missing.',
            ];
        } else {
            $contents = file_get_contents($fullPath);
            if ($contents === false) {
                throw new BagItException("Unable to read {$fullPath}");
            }
            $contents = $this->decodeText($contents);
            $lines = explode(PHP_EOL, $contents);
            //$lines = preg_split("~[\r\n]+~", $contents, null, PREG_SPLIT_NO_EMPTY);
            // remove blank lines.
            $lines = array_filter($lines);
            if (count($lines) !== 2) {
                $this->bagErrors[] = [
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
                    $this->bagErrors[] = [
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
                    $this->bagErrors[] = [
                        'file' => 'bagit.txt',
                        'message' => 'Second line should have pattern ' .
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
            "BagIt-Version: %d.%d" . PHP_EOL .
            "Tag-File-Character-Encoding: %s" . PHP_EOL,
            $version['major'],
            $version['minor'],
            $this->getFileEncoding()
        );

        // We don't use encodeText because this must always be UTF-8.
        $output = mb_convert_encoding($output, self::DEFAULT_FILE_ENCODING);

        file_put_contents(
            $this->makeAbsolute("bagit.txt"),
            $output
        );
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
     * Utility to remove files using a pattern.
     *
     * @param string $filePattern
     *   The file pattern.
     * @throws \whikloj\BagItTools\BagItException
     *   Problems deleting files.
     */
    private function clearFilesOfPattern($filePattern)
    {
        $files = BagUtils::findAllByPattern($filePattern);
        if (count($files) > 0) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
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
     * @param string $item
     *   The hash algorithm name.
     */
    private static function normalizeHashAlgorithmName(&$item)
    {
        $item = array_flip(self::HASH_ALGORITHMS)[$item];
    }

    /**
     * Check if the algorithm PHP has is allowed by the specification.
     *
     * @param string $item
     *   A hash algorithm name.
     *
     * @return bool
     *   True if allowed by the specification.
     */
    private static function filterPhpHashAlgorithms($item)
    {
        return in_array($item, array_values(self::HASH_ALGORITHMS));
    }

    /**
     * Return the BagIt sanitized algorithm name.
     * @param string $algorithm
     *   A algorithm name
     * @return string|null
     *   The sanitized version of algorithm or null if invalid.
     */
    private function getHashName($algorithm)
    {
        $algorithm = self::trimLower($algorithm);
        $algorithm = preg_replace("/[^a-z0-9]/", "", $algorithm);
        if (in_array($algorithm, array_keys(self::HASH_ALGORITHMS))) {
            return $algorithm;
        }
        return "";
    }

    /**
     * Do we have a payload manifest with this internal hash name. Internal use only to avoid getHashName()
     *
     * @param string $internal_name
     *   Internal name from getHashName.
     * @return bool
     *   Already have this algorithm.
     */
    private function hasHash($internal_name)
    {
        return (in_array($internal_name, array_keys($this->manifest)));
    }

    /**
     * Is the internal named hash supported by our PHP. Internal use only to avoid getHashName()
     *
     * @param string $internal_name
     *   Output of getHashName
     * @return bool
     *   Do we support the algorithm
     */
    private function hashIsSupported($internal_name)
    {
        return ($internal_name != null && in_array($internal_name, $this->validHashAlgorithms));
    }

    /**
     * Case-insensitive version of array_key_exists
     *
     * @param string $search The key to look for.
     * @param string|int $key The associative or numeric key to look in.
     * @param array $map The associative array to search.
     * @return bool True if the key exists regardless of case.
     */
    private static function arrayKeyExistsNoCase($search, $key, array $map)
    {
        $keys = array_column($map, $key);
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
            self::arrayKeyExistsNoCase($key, 'tag', $this->bagInfoData));
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
     * Utility function to convert text to UTF-8
     * @param string $text
     *   The source text.
     * @return string
     *   The converted text.
     */
    private function decodeText($text)
    {
        return mb_convert_encoding($text, self::DEFAULT_FILE_ENCODING);
    }

    /**
     * Utility function to convert text back to the encoding for the file.
     *
     * @param string $text
     *   The source text.
     * @return string
     *   The converted text.
     */
    private function encodeText($text)
    {
        return mb_convert_encoding($text, $this->getFileEncoding());
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
                $startWithLetterDir . DIRECTORY_SEPARATOR : ''
            ) . implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}
