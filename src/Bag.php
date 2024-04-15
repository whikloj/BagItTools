<?php

declare(strict_types=1);

namespace whikloj\BagItTools;

use Archive_Tar;
use Normalizer;
use whikloj\BagItTools\Exceptions\BagItException;
use whikloj\BagItTools\Exceptions\FilesystemException;
use ZipArchive;

/**
 * Bag class as normal interface for all actions and holder of supporting constructs.
 *
 * @package \whikloj\BagItTools
 * @author  whikloj
 * @since   1.0.0
 */
class Bag
{
    /**
     * The default algorithm to use if one is not specified.
     */
    private const DEFAULT_HASH_ALGORITHM = 'sha512';

    /**
     * The default file encoding if one is not specified.
     */
    private const DEFAULT_FILE_ENCODING = 'UTF-8';

    /**
     * The default bagit version.
     */
    private const DEFAULT_BAGIT_VERSION = [
        'major' => 1,
        'minor' => 0,
    ];

    /**
     * Bag-info fields that MUST not be repeated.
     */
    private const BAG_INFO_MUST_NOT_REPEAT = [
        'payload-oxum',
    ];

    /**
     * Bag-info fields that SHOULD NOT be repeated.
     */
    private const BAG_INFO_SHOULD_NOT_REPEAT = [
        'bagging-date',
        'bag-size',
        'bag-group-identifer',
        'bag-count',
    ];

    /**
     * Fields you can't set because we generate them on $bag->update().
     */
    private const BAG_INFO_GENERATED_ELEMENTS = [
        'payload-oxum',
        'bag-size',
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
    private const HASH_ALGORITHMS = array(
        'md5' => 'md5',
        'sha1' => 'sha1',
        'sha224' => 'sha224',
        'sha256' => 'sha256',
        'sha384' => 'sha384',
        'sha512' => 'sha512',
        'sha3224' => 'sha3-224',
        'sha3256' => 'sha3-256',
        'sha3384' => 'sha3-384',
        'sha3512' => 'sha3-512',
    );

    /**
     * File names that are not allowed on windows, should be disallowed in bags for interoperability.
     */
    private const WINDOWS_RESERVED_NAMES = [
        'CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1',
        'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
    ];

    /**
     * Extensions which map to a tar file.
     */
    private const TAR_EXTENSIONS = [
        '.tar',
        '.tgz',
        '.tar.gz',
        '.tar.bz2',
    ];

    /**
     * Extensions which map to a zip file.
     */
    private const ZIP_EXTENSIONS = [
        '.zip',
    ];

    /**
     * Length we start trying to wrap at.
     */
    private const BAGINFO_AUTOWRAP_START = 77;

    /**
     * The length of a line over which we assume the line has been auto-wrapped.
     */
    private const BAGINFO_AUTOWRAP_GUESS_LENGTH = 70;

    /**
     * A map of some supported extensions to their serialization MIME types.
     */
    private const SERIALIZATION_MAPPING = [
        '.bz' => 'application/x-bzip',
        '.bz2' => 'application/x-bzip2',
        '.gz' => 'application/gzip',
        '.tgz' => 'application/gzip',
        '.tar' => 'application/x-tar',
        '.zip' => 'application/zip',
    ];

    /**
     * All the extensions in one array.
     *
     * @var array
     */
    private array $packageExtensions;

    /**
     * Array of current bag version with keys 'major' and 'minor'.
     *
     * @var array
     */
    private array $currentVersion = self::DEFAULT_BAGIT_VERSION;

    /**
     * Current bag file encoding.
     *
     * @var string
     */
    private string $currentFileEncoding = self::DEFAULT_FILE_ENCODING;

    /**
     * Array of payload manifests.
     *
     * @var array
     */
    private array $payloadManifests;

    /**
     * Array of tag manifests.
     *
     * @var array
     */
    private array $tagManifests;

    /**
     * List of relative file paths for all files.
     *
     * @var array
     */
    private array $payloadFiles;

    /**
     * Reference to a Fetch file or null if not used.
     *
     * @var Fetch|null
     */
    private ?Fetch $fetchFile = null;

    /**
     * The absolute path to the root of the bag, all other file paths are
     * relative to this. This path is stored with / as directory separator
     * regardless of the OS.
     *
     * @var string
     */
    private string $bagRoot;

    /**
     * Is this an extended bag?
     *
     * @var boolean
     */
    private bool $isExtended = false;

    /**
     * The valid algorithms from the current version of PHP filtered to those
     * supported by the BagIt specification. Stored to avoid extraneous calls
     * to hash_algos().
     *
     * @var array
     */
    private array $validHashAlgorithms;

    /**
     * Errors when validating a bag.
     *
     * @var array
     */
    private array $bagErrors;

    /**
     * Warnings when validating a bag.
     *
     * @var array
     */
    private array $bagWarnings;

    /**
     * Have we changed the bag and not written it to disk?
     *
     * @var boolean
     */
    private bool $changed = false;

    /**
     * Bag Info data.
     *
     * @var array
     */
    private array $bagInfoData = [];

    /**
     * Unique array of all Bag info tags/values. Tags are stored once in lower case with an array of all instances
     * of values. This index does not save order.
     *
     * @var array
     */
    private array $bagInfoTagIndex = [];

    /**
     * Did we load this from disk?
     *
     * @var boolean
     */
    private bool $loaded;

    /**
     * The serialization mime-type of the bag or null if not serialized.
     * @var string|null
     */
    private ?string $serialization = null;

    /**
     * Bag constructor.
     *
     * @param string  $rootPath
     *   The path of the root of the new or existing bag.
     * @param boolean $new
     *   Are we making a new bag?
     * @param string|null $extension
     *   The extension of the bag (if it was serialized) or null if not.
     *
     * @throws FilesystemException
     *   Problems accessing a file.
     * @throws BagItException
     *   Bag directory exists for new bag or various issues for loading an existing bag.
     */
    private function __construct(string $rootPath, bool $new = true, ?string $extension = null)
    {
        $this->packageExtensions = array_merge(self::TAR_EXTENSIONS, self::ZIP_EXTENSIONS);
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
        $rootPath = BagUtils::standardizePathSeparators($rootPath);
        $this->bagRoot = BagUtils::getAbsolute($rootPath, true);
        $this->loaded = (!$new);
        if ($new) {
            $this->createNewBag();
        } else {
            $this->serialization = is_null($extension) ? null : $this->determineSerializationMimetype($extension);
            $this->loadBag();
        }
    }

    /**
     * Static function to create a new Bag
     *
     * @param  string $rootPath
     *   Path to the new bag, must not exist
     * @return Bag
     *   The bag.
     * @throws BagItException
     *   If we can't create the directory.
     */
    public static function create(string $rootPath): Bag
    {
        return new Bag($rootPath, true);
    }

    /**
     * Static constructor to load an existing bag.
     *
     * @param  string $rootPath
     *   Path to the existing bag.
     * @return Bag
     *   The bag object.
     * @throws BagItException
     *   If we can't read files in the bag.
     */
    public static function load(string $rootPath): Bag
    {
        $rootPath = BagUtils::getAbsolute($rootPath, true);
        $extension = null;
        if (is_file($rootPath)) {
            $extension = self::getExtension($rootPath);
            if (self::hasExtension($extension, array_merge(self::ZIP_EXTENSIONS, self::TAR_EXTENSIONS))) {
                $rootPath = self::uncompressBag($rootPath, $extension);
            }
        }
        return new Bag($rootPath, false, $extension);
    }

    /**
     * Is the bag valid as it appears on disk.
     *
     * @return boolean
     *   True if the bag is valid
     * @throws BagItException
     *   Problems writing to disk.
     */
    public function isValid(): bool
    {
        if (!$this->loaded || $this->changed) {
            // If we created this bag or have changed stuff we need to write it.
            $this->update();
        }
        // Reload the bag from disk.
        $this->loadBag();
        if ($this->hasFetchFile()) {
            $this->fetchFile->downloadAll();
            $this->mergeErrors($this->fetchFile->getErrors());
        }
        $manifests = array_values($this->payloadManifests);
        if (count($this->tagManifests) > 0) {
            // merge in the tag manifests so we can do them all at once.
            $manifests = array_merge($manifests, array_values($this->tagManifests));
        }
        foreach ($manifests as $manifest) {
            $manifest->validate();
            $this->mergeErrors($manifest->getErrors());
            $this->mergeWarnings($manifest->getWarnings());
        }
        return (count($this->bagErrors) == 0);
    }

    /**
     * Write the updated BagIt files to disk.
     *
     * @throws FilesystemException
     *   Errors with writing files to disk.
     */
    public function update(): void
    {
        if (!file_exists($this->makeAbsolute("data"))) {
            BagUtils::checkedMkdir($this->makeAbsolute("data"));
        }
        $this->updateBagIt();
        $this->updatePayloadManifests();
        $this->updateFetch();

        if ($this->isExtended) {
            $this->updateBagInfo();
            $this->updateTagManifests();
        } else {
            $this->removeBagInfo();
            $this->clearTagManifests();
        }
        $this->changed = false;
    }

    /**
     * This does cleanup functions related to packaging, for example deleting downloaded files referenced in fetch.txt
     *
     * @throws FilesystemException
     *   Issues deleting files from filesystem.
     */
    public function finalize(): void
    {
        // Update files to ensure they are correct.
        $this->update();
        if ($this->hasFetchFile()) {
            // Clean up fetch files downloaded to generate checksums.
            $this->fetchFile->cleanup();
        }
    }

    /**
     * Package a bag up into an archive.
     *
     * @param  string $filepath
     *   The full path to create the archive at.
     * @throws BagItException
     *   Problems creating the archive.
     */
    public function package(string $filepath): void
    {
        if (!self::hasExtension(self::getExtension($filepath), $this->packageExtensions)) {
            throw new BagItException(
                "Unknown archive type ($filepath), the file extension must be one of (" .
                implode(", ", $this->packageExtensions) . ")"
            );
        }
        $this->finalize();
        $this->makePackage($filepath);
    }

    /**
     * Add a file to the bag.
     *
     * @param string $source
     *   Full path to the source file.
     * @param string $dest
     *   Relative path for the destination.
     *
     * @throws BagItException
     *   Source file does not exist or the destination is outside the data directory.
     * @throws FilesystemException
     *   Issues writing to the filesystem.
     */
    public function addFile(string $source, string $dest): void
    {
        if (!file_exists($source)) {
            throw new BagItException("$source does not exist");
        }
        $dest = BagUtils::baseInData($dest);
        if (!$this->pathInBagData($dest)) {
            throw new BagItException("Path $dest resolves outside the bag.");
        }
        if ($this->reservedFilename($dest)) {
            throw new BagItException("The filename requested is reserved on Windows OSes.");
        }
        if (isset($this->fetchFile) && $this->fetchFile->reservedPath($dest)) {
            throw new BagItException("The path ($dest) is used in the fetch.txt file.");
        }
        $fullDest = Normalizer::normalize($this->makeAbsolute($dest));
        if (file_exists($fullDest)) {
            throw new BagItException("File $dest already exists in the bag.");
        }
        $dirname = dirname($fullDest);
        if (str_starts_with($this->makeRelative($dirname), "data/")) {
            // Create any missing directories inside data.
            if (!file_exists($dirname)) {
                BagUtils::checkedMkdir($dirname, 0777, true);
            }
        }
        BagUtils::checkedCopy($source, $fullDest);
        $this->changed = true;
    }

    /**
     * Remove a payload file.
     *
     * @param string $dest
     *   The relative path of the file.
     * @throws FilesystemException
     *   Issues deleting the file.
     */
    public function removeFile(string $dest): void
    {
        $dest = BagUtils::baseInData($dest);
        if (!$this->pathInBagData($dest)) {
            return;
        }
        $fullPath = $this->makeAbsolute($dest);
        if (!is_file($fullPath)) {
            return;
        }
        BagUtils::checkedUnlink($fullPath);
        $this->checkForEmptyDir($fullPath);
        $this->changed = true;
    }

    /**
     * Add a string as a file to the bag.
     *
     * @param  string $string
     *   The contents of the file.
     * @param  string $dest
     *   The name of the file in the bag.
     * @throws BagItException
     *   Generated source file does not exist or the destination is outside the data directory. Should not occur.
     * @throws FilesystemException
     *   Issues creating/deleting temporary file.
     */
    public function createFile(string $string, string $dest): void
    {
        $tempname = BagUtils::checkedTempnam("", "bagit_");
        BagUtils::checkedUnlink($tempname);
        BagUtils::checkedFilePut($tempname, $string);
        try {
            $this->addFile($tempname, $dest);
        } finally {
            BagUtils::checkedUnlink($tempname);
        }
    }

    /**
     * Add the bag root to the front of a relative bag path and return with
     * OS directory separator.
     *
     * @param  string $path
     *   The relative path.
     * @return string
     *   The absolute path.
     */
    public function makeAbsolute(string $path): string
    {
        $length = strlen(trim($this->bagRoot));
        $path = BagUtils::getAbsolute($path);
        if (substr($path, 0, $length) === $this->bagRoot) {
            return $path;
        }
        $components = array_filter(explode("/", $path));
        $rootComponents = array_filter(explode("/", $this->bagRoot));
        $components = array_merge($rootComponents, $components);
        $prefix = (preg_match('/^[a-z]:/i', $rootComponents[0] ?? '', $matches) ? '' : '/');
        return $prefix . implode('/', $components);
    }

    /**
     * Remove all the extraneous path information and make relative to bag root.
     *
     * @param  string $path
     *   The absolute path to process
     * @return string
     *   The shortened path or blank if it is outside bag root.
     */
    public function makeRelative(string $path): string
    {
        $path = BagUtils::getAbsolute($path);
        $rootLength = strlen($this->bagRoot);
        if (substr($path, 0, $rootLength) !== $this->bagRoot) {
            // We are not in bag root so return nothing.
            return '';
        }
        return substr($path, strlen($this->bagRoot) + 1);
    }

    /**
     * Return raw bag info data.
     *
     * @return array
     *   Bag Info data.
     */
    public function getBagInfoData(): array
    {
        return $this->bagInfoData;
    }

    /**
     * Case-insensitive search of bag-info tags.
     *
     * @param  string $tag
     *   Bag info tag to locate
     * @return bool
     *   Does the tag exist.
     */
    public function hasBagInfoTag(string $tag): bool
    {
        $tag = BagUtils::trimLower($tag);
        return $this->bagInfoTagExists($tag);
    }

    /**
     * Find all instances of tag and return an array of values.
     *
     * @param  string $tag
     *   Bag info tag to locate
     * @return array
     *   Array of values for the tag.
     */
    public function getBagInfoByTag(string $tag): array
    {
        $tag = BagUtils::trimLower($tag);
        return $this->bagInfoTagExists($tag) ? $this->bagInfoTagIndex[$tag] : [];
    }

    /**
     * Remove ALL instances of tag.
     *
     * @param string $tag
     *   The tag to remove.
     */
    public function removeBagInfoTag(string $tag): void
    {
        $tag = BagUtils::trimLower($tag);
        if (!$this->bagInfoTagExists($tag)) {
            return;
        }
        $compare_fn = function ($o) use ($tag) {
            return strcmp($tag, strtolower($o["tag"])) !== 0;
        };
        // array_values fixes numeric indexes for individual tag arrays after filtering
        $this->bagInfoData = array_values(array_filter($this->bagInfoData, $compare_fn));
        $this->updateBagInfoIndex();
        $this->changed = true;
    }

    /**
     * Removes a specific entry for a tag by the array index. This can be determined using the index in the array
     * returned by getBagInfoByKey().
     *
     * @param string $tag
     *   The tag to remove.
     * @param int    $index
     *   The index of the value to remove.
     */
    public function removeBagInfoTagIndex(string $tag, int $index): void
    {
        if ($index < 0) {
            return;
        }
        $tag = BagUtils::trimLower($tag);
        if (!$this->bagInfoTagExists($tag)) {
            return;
        }
        $values = $this->getBagInfoByTag($tag);
        if ($index >= count($values)) {
            return;
        }
        $newInfo = [];
        $tagCount = 0;
        foreach ($this->bagInfoData as $row) {
            $rowTag = BagUtils::trimLower($row['tag']);
            if ($rowTag !== $tag || $tagCount !== $index) {
                $newInfo[] = $row;
            }
            if ($rowTag == $tag) {
                $tagCount += 1;
            }
        }
        $this->bagInfoData = $newInfo;
        $this->updateBagInfoIndex();
        $this->changed = true;
    }

    /**
     * Remove a specific entry for a tag by the tag value.
     *
     * @param string $tag
     *   The tag we are removing a value of.
     * @param string $value
     *   The value to remove from the above tag.
     * @param bool $case_sensitive
     *   Whether to perform a case-sensitive match.
     */
    public function removeBagInfoTagValue(string $tag, string $value, bool $case_sensitive = true): void
    {
        if (empty($tag) || empty($value)) {
            return;
        }
        $tag = BagUtils::trimLower($tag);
        if (!$this->hasBagInfoTag($tag)) {
            return;
        }
        $compare_value = ($case_sensitive ? "strcmp" : "strcasecmp");
        $compare_fn = function ($o) use ($tag, $value, $compare_value) {
            return (strcasecmp($tag, $o["tag"]) !== 0 || $compare_value($value, $o["value"]) !== 0);
        };
        // array_values fixes numeric indexes for individual tag arrays after filtering
        $this->bagInfoData = array_values(array_filter($this->bagInfoData, $compare_fn));
        $this->updateBagInfoIndex();
    }

    /**
     * Add tag and value to bag-info.
     *
     * @param  string $tag
     *   The tag to add.
     * @param  string $value
     *   The value to add.
     * @throws BagItException
     *   When you try to set an auto-generated tag value.
     */
    public function addBagInfoTag(string $tag, string $value): void
    {
        $this->setExtended(true);
        $internal_tag = BagUtils::trimLower($tag);
        if (in_array($internal_tag, self::BAG_INFO_GENERATED_ELEMENTS)) {
            throw new BagItException("Field $tag is auto-generated and cannot be manually set.");
        }
        $this->addBagInfoTagsInternal([$tag => $value]);
    }

    /**
     * Add multiple bag info tags from an array.
     *
     * @param array $tags
     *   Associative array of tag => value
     * @throws BagItException
     *   When you try to set an auto-generated tag value.
     */
    public function addBagInfoTags(array $tags): void
    {
        $this->setExtended(true);
        $normalized_keys = array_keys($tags);
        $normalized_keys = array_map('whikloj\BagItTools\BagUtils::trimLower', $normalized_keys);
        $overlap = array_intersect($normalized_keys, self::BAG_INFO_GENERATED_ELEMENTS);
        if (count($overlap) !== 0) {
            throw new BagItException(
                "The field(s) " . implode(", ", $overlap) . " are auto-generated and cannot be manually set."
            );
        }
        $this->addBagInfoTagsInternal($tags);
    }

    /**
     * Internal function adding the values to the various tag arrays.
     *
     * @param array $tags
     *   Associative array of tag => value
     */
    private function addBagInfoTagsInternal(array $tags): void
    {
        foreach ($tags as $key => $value) {
            $internal_key = BagUtils::trimLower($key);
            if (!$this->bagInfoTagExists($internal_key)) {
                $this->bagInfoTagIndex[$internal_key] = [];
            }
            if (!is_array($value)) {
                // Make value an array
                $value = [$value];
            }
            foreach ($value as $val) {
                $this->bagInfoTagIndex[$internal_key][] = $val;
                $this->bagInfoData[] = [
                    'tag' => trim($key),
                    'value' => trim($val),
                ];
            }
        }
        $this->changed = true;
    }

    /**
     * Set the file encoding.
     *
     * @param  string $encoding
     *   The MIME name of the character set to encode with.
     * @throws BagItException
     *   If we don't support the requested character set.
     */
    public function setFileEncoding(string $encoding): void
    {
        $encoding = BagUtils::trimLower($encoding);
        $charset = BagUtils::getValidCharset($encoding);
        if (is_null($charset)) {
            throw new BagItException("Character set $encoding is not supported.");
        }
        if (strcasecmp($encoding, $this->currentFileEncoding) !== 0) {
            $this->currentFileEncoding = $charset;
            $this->changed = true;
        }
    }

    /**
     * Get current file encoding or default if not specified.
     *
     * @return string
     *   Current file encoding.
     */
    public function getFileEncoding(): string
    {
        return $this->currentFileEncoding;
    }

    /**
     * Get the currently active payload (and tag) manifests.
     *
     * @return array
     *   Internal hash names for current manifests.
     */
    public function getAlgorithms(): array
    {
        return array_keys($this->payloadManifests);
    }

    /**
     * Do we have this hash algorithm already?
     *
     * @param string $hashAlgorithm
     *   The requested hash algorithms.
     *
     * @return boolean Do we already have this payload manifest.
     */
    public function hasAlgorithm(string $hashAlgorithm): bool
    {
        $internal_name = $this->getHashName($hashAlgorithm);
        return $this->hashIsSupported($internal_name) && $this->hasHash($internal_name);
    }

    /**
     * The algorithm is supported.
     *
     * @param  string $algorithm
     *   The requested hash algorithm
     * @return boolean
     *   Whether it is supported by our PHP.
     */
    public function algorithmIsSupported(string $algorithm): bool
    {
        $internal_name = $this->getHashName($algorithm);
        return $this->hashIsSupported($internal_name);
    }

    /**
     * Add a hash algorithm to the bag.
     *
     * @param  string $algorithm
     *   Algorithm to add.
     * @throws BagItException
     *   Asking for an unsupported algorithm.
     */
    public function addAlgorithm(string $algorithm): void
    {
        $internal_name = $this->getHashName($algorithm);
        if (!$this->hashIsSupported($internal_name)) {
            throw new BagItException("Algorithm $algorithm is not supported.");
        }
        if (!array_key_exists($internal_name, $this->payloadManifests)) {
            $this->payloadManifests[$internal_name] = new PayloadManifest($this, $internal_name);
        }
        if ($this->isExtended) {
            $this->ensureTagManifests();
            if (!array_key_exists($internal_name, $this->tagManifests)) {
                $this->tagManifests[$internal_name] = new TagManifest($this, $internal_name);
            }
        }
        $this->changed = true;
    }

    /**
     * Remove a hash algorithm from the bag.
     *
     * @param  string $algorithm
     *   Algorithm to remove
     * @throws BagItException
     *   Trying to remove the last algorithm or asking for an unsupported algorithm.
     */
    public function removeAlgorithm(string $algorithm): void
    {
        $internal_name = $this->getHashName($algorithm);
        if (!$this->hashIsSupported($internal_name)) {
            throw new BagItException("Algorithm $algorithm is not supported.");
        }
        if (array_key_exists($internal_name, $this->payloadManifests)) {
            if (count($this->payloadManifests) == 1) {
                throw new BagItException("Cannot remove last payload algorithm, add one before removing this one");
            }
            $this->removePayloadManifest($internal_name);
        }
        if (
            $this->isExtended && isset($this->tagManifests)
            && array_key_exists($internal_name, $this->tagManifests)
        ) {
            if (count($this->tagManifests) == 1) {
                throw new BagItException("Cannot remove last tag algorithm, add one before removing this one");
            }
            $this->removeTagManifest($internal_name);
        }
        $this->changed = true;
    }

    /**
     * Replaces any existing hash algorithms with the one requested.
     *
     * @param  string $algorithm
     *   Algorithm to use.
     * @throws FilesystemException
     *   Problems removing/reading/
     * @throws BagItException
     *   Asking for an unsupported algorithm.
     */
    public function setAlgorithm(string $algorithm): void
    {
        $internal_name = $this->getHashName($algorithm);
        if (!$this->hashIsSupported($internal_name)) {
            throw new BagItException("Algorithm $algorithm is not supported.");
        }
        $this->setAlgorithmsInternal([$internal_name]);
    }

    /**
     * Replaces any existing hash algorithms with the ones requested.
     *
     * @param array $algorithms
     *   Array of algorithms to use.
     * @throws FilesystemException
     *   Problems removing/reading/creating the new payload/tag manifest files.
     * @throws BagItException
     *   If an unsupported algorithm is provided.
     */
    public function setAlgorithms(array $algorithms): void
    {
        $internal_names = array_map(self::class . '::getHashName', $algorithms);
        $valid_algorithms = array_filter($internal_names, [$this, 'hashIsSupported']);
        if (count($valid_algorithms) !== count($algorithms)) {
            throw new BagItException("One or more of the algorithms provided are NOT supported.");
        }
        $this->setAlgorithmsInternal($valid_algorithms);
    }

    /**
     * Internal utility to remove all algorithms not specified and add any missing.
     *
     * @param array $algorithms
     *   Array of algorithms using their internal names.
     * @throws FilesystemException
     *   Errors
     */
    private function setAlgorithmsInternal(array $algorithms): void
    {
        $this->removeAllPayloadManifests($algorithms);
        if ($this->isExtended) {
            $this->removeAllTagManifests($algorithms);
            $this->ensureTagManifests();
        }
        foreach ($algorithms as $algorithm) {
            if (!array_key_exists($algorithm, $this->payloadManifests)) {
                $this->payloadManifests[$algorithm] = new PayloadManifest($this, $algorithm);
            }
            if ($this->isExtended) {
                if (!array_key_exists($algorithm, $this->tagManifests)) {
                    $this->tagManifests[$algorithm] = new TagManifest($this, $algorithm);
                }
            }
        }
        $this->changed = true;
    }

    /**
     * Add a file to your fetch file.
     *
     * @param  string       $url
     *   The source URL.
     * @param  string       $destination
     *   The destination path in the bag.
     * @param  null|int $size
     *   Size of the file to be stored in the fetch file, if desired.
     * @throws BagItException
     *   On errors adding the file.
     */
    public function addFetchFile(string $url, string $destination, int $size = null): void
    {
        if (!$this->hasFetchFile()) {
            $this->fetchFile = new Fetch($this, false);
        }
        $this->fetchFile->addFile($url, $destination, $size);
        $this->setExtended(true);
        $this->changed = true;
    }

    /**
     * Return the fetch file data, an array of arrays with keys 'url', 'destination' and (optionally) 'size'.
     *
     * @return array
     */
    public function listFetchFiles(): array
    {
        return (!$this->hasFetchFile() ? [] : $this->fetchFile->getData());
    }

    /**
     * Wipe the fetch file data
     *
     * @throws FilesystemException
     *   Issues deleting files/directories from the filesystem.
     */
    public function clearFetch(): void
    {
        if ($this->hasFetchFile()) {
            $this->fetchFile->clearData();
            unset($this->fetchFile);
            $this->changed = true;
        }
    }

    /**
     * Delete a line from the fetch file.
     *
     * @param string $url
     *   The url to delete.
     * @throws FilesystemException
     *   Problems deleting the file.
     */
    public function removeFetchFile(string $url): void
    {
        if ($this->hasFetchFile()) {
            $this->fetchFile->removeFile($url);
            $this->changed = true;
        }
    }

    /**
     * @return bool Does the bag have a fetch file?
     */
    public function hasFetchFile(): bool
    {
        return isset($this->fetchFile);
    }

    /**
     * Get the current version array or default if not specified.
     *
     * @return array
     *   Current version.
     */
    public function getVersion(): array
    {
        return $this->currentVersion;
    }

    /**
     * Get current version as string.
     *
     * @return string
     *   Current version in M.N format.
     */
    public function getVersionString(): string
    {
        $version = $this->getVersion();
        return $version['major'] . "." . $version['minor'];
    }

    /**
     * Return path to the bag root.
     *
     * @return string
     *   The bag root path.
     */
    public function getBagRoot(): string
    {
        return BagUtils::getAbsolute($this->bagRoot);
    }

    /**
     * Return path to the data directory.
     *
     * @return string
     *   The bag data directory path.
     */
    public function getDataDirectory(): string
    {
        return $this->makeAbsolute("data");
    }

    /**
     * Check the bag's extended status.
     *
     * @return boolean
     *   Does the bag use extended features?
     */
    public function isExtended(): bool
    {
        return $this->isExtended;
    }

    /**
     * Turn extended bag features on or off.
     *
     * @param boolean $extBag
     *   Whether the bag should be extended or not.
     */
    public function setExtended(bool $extBag): void
    {
        if ($this->isExtended !== $extBag) {
            $this->isExtended = $extBag;
            $this->changed = true;
        }
    }

    /**
     * Get errors on the bag.
     *
     * @return array
     *   The errors.
     */
    public function getErrors(): array
    {
        return $this->bagErrors;
    }

    /**
     * Get any warnings related to the bag.
     *
     * @return array
     *   The warnings.
     */
    public function getWarnings(): array
    {
        return $this->bagWarnings;
    }

    /**
     * Get the payload manifests as an associative array with hash algorithm as key.
     *
     * @return array
     *   hash algorithm => Payload manifests
     */
    public function getPayloadManifests(): array
    {
        return $this->payloadManifests;
    }

    /**
     * Get the tag manifests as an associative array with hash algorithm as key.
     *
     * @return array|null
     *   hash algorithm => Tag manifests or null if not an extended bag.
     */
    public function getTagManifests(): ?array
    {
        return $this->tagManifests ?? null;
    }

    /**
     * Utility function to convert text to UTF-8
     *
     * @param  string $text
     *   The source text.
     * @return string
     *   The converted text.
     */
    public function decodeText(string $text): string
    {
        return mb_convert_encoding($text, self::DEFAULT_FILE_ENCODING, $this->getFileEncoding());
    }

    /**
     * Utility function to convert text back to the encoding for the file.
     *
     * @param  string $text
     *   The source text.
     * @return string
     *   The converted text.
     */
    public function encodeText(string $text): string
    {
        return mb_convert_encoding($text, $this->getFileEncoding(), self::DEFAULT_FILE_ENCODING);
    }

    /**
     * Is the path inside the payload directory?
     *
     * @param  string $filepath
     *   The internal path.
     * @return boolean
     *   Path is inside the data/ directory.
     */
    public function pathInBagData(string $filepath): bool
    {
        $external = $this->makeAbsolute($filepath);
        $relative = $this->makeRelative($external);
        return $relative !== "" && str_starts_with($relative, "data/");
    }


    /**
     * Check the directory we just deleted a file from, if empty we should remove
     * it too.
     *
     * @param string $path
     *   The file just deleted.
     * @throws FilesystemException If we can't delete the directory.
     * @throws BagItException If the directory is outside the data directory.
     */
    public function checkForEmptyDir(string $path): void
    {
        $parentPath = dirname($path);
        if (str_starts_with($this->makeRelative($parentPath), "data/")) {
            BagUtils::deleteEmptyDirTree($parentPath, $this->getDataDirectory());
        }
    }

    /**
     * Upgrade an older bag to comply with the 1.0 specification.
     *
     * @throws BagItException
     *   If the bag cannot be upgraded for some reason.
     * @throws FilesystemException
     *   Issues writing files to the filesystem.
     */
    public function upgrade(): void
    {
        if (!$this->loaded) {
            throw new BagItException("You can only upgrade loaded bags.");
        }
        if ($this->getVersion() == self::DEFAULT_BAGIT_VERSION) {
            throw new BagItException("Bag is already at version {$this->getVersionString()}");
        }
        if (!$this->isValid()) {
            throw new BagItException("This bag is not valid, we cannot automatically upgrade it.");
        }
        // We can upgrade.
        $hashes = array_keys($this->getPayloadManifests());
        if (count($hashes) == 1 && $hashes[0] == 'md5') {
            $this->setAlgorithm(self::DEFAULT_HASH_ALGORITHM);
        }
        $this->currentVersion = self::DEFAULT_BAGIT_VERSION;
        $this->update();
    }

    /**
     * Add a special tag file to the bag.
     * @param string $source Full path to the tag file.
     * @param string $dest Relative path for the destination.
     *
     * @throws BagItException Various errors related to the source and destination locations and access.
     * @throws FilesystemException Issues reading from or writing to the filesystem.
     */
    public function addTagFile(string $source, string $dest): void
    {
        if (!file_exists($source) || !is_file($source) || !is_readable($source)) {
            throw new BagItException("$source does not exist, is not a file or is not readable.");
        }
        $this->checkTagFileConstraints($dest);
        $external = $this->makeAbsolute($dest);
        if (file_exists($external)) {
            throw new BagItException("Tag file ($dest) already exists in the bag.");
        }
        $this->setExtended(true);
        $parentDirs = dirname($external);
        if ($parentDirs !== $this->getBagRoot() && !file_exists($parentDirs)) {
            // Create any missing tag file directories.
            BagUtils::checkedMkdir($parentDirs, 0777, true);
        }
        BagUtils::checkedCopy($source, $external);
        $this->changed = true;
    }

    /**
     * Remove a tag file and any empty directories it leaves behind.
     * @param string $dest The relative path to the tag file.
     * @return void
     * @throws BagItException If the file does not exist, is not inside the bag root or is a reserved file.
     * @throws FilesystemException If there are issues deleting the file or directories.
     */
    public function removeTagFile(string $dest): void
    {
        $this->checkTagFileConstraints($dest);
        $external = $this->makeAbsolute($dest);
        if (!file_exists($external)) {
            throw new BagItException("Tag file ($dest) does not exist in the bag.");
        }
        BagUtils::checkedUnlink($external);
        BagUtils::deleteEmptyDirTree(dirname($external), $this->getBagRoot());
        $this->changed = true;
    }

    /**
     * @return string|null The serialization format if the bag was loaded from a serialized format or null.
     */
    public function getSerializationMimeType(): ?string
    {
        return $this->serialization;
    }

    /*
     *  XXX: Private functions
     */

    /**
     * Common checks for interactions with custom tag files.
     * @param string $tagFilePath The relative path to the tag file.
     * @return void
     * @throws BagItException If the tag file is not in the bag root, is in the data directory, or is a reserved file.
     */
    private function checkTagFileConstraints(string $tagFilePath): void
    {
        $external = $this->makeAbsolute($tagFilePath);
        $relativePath = $this->makeRelative($external);
        if ($relativePath === "") {
            throw new BagItException("Tag files must be inside the bag root.");
        }
        if (str_starts_with(strtolower($relativePath), "data/")) {
            throw new BagItException("Tag files must be in the bag root or a tag file directory, " .
                "use ->addFile() to add data files.");
        }
        if (in_array(strtolower($relativePath), ['bagit.txt', 'bag-info.txt', 'fetch.txt'])) {
            throw new BagItException("You cannot alter reserved file ($tagFilePath) file with your own tag file.");
        } elseif (
            str_starts_with(strtolower($relativePath), 'tagmanifest-') ||
            str_starts_with(strtolower($relativePath), 'manifest-')
        ) {
            throw new BagItException("You cannot alter a manifest or tag manifest file with your own tag file.");
        }
    }

    /**
     * Load a bag from disk.
     *
     * @throws BagItException
     *   If a file cannot be read.
     */
    private function loadBag(): void
    {
        $root = $this->getBagRoot();
        if (!file_exists($root)) {
            throw new BagItException("Path $root does not exist, could not load Bag.");
        }
        $this->resetErrorsAndWarnings();
        // Reset these or we end up with double manifests in a validate() situation.
        $this->payloadManifests = [];
        unset($this->tagManifests);
        $this->bagInfoData = [];
        $this->loadBagIt();
        $this->loadPayloadManifests();
        $bagInfo = $this->loadBagInfo();
        $tagManifest = $this->loadTagManifests();
        $this->loadFetch();
        $this->isExtended = ($bagInfo || $tagManifest);
    }

    /**
     * Create a new bag and output the default parts.
     *
     * @throws BagItException
     *   If the bag root directory already exists.
     * @throws FilesystemException
     *   Problems writing to the filesystem.
     */
    private function createNewBag(): void
    {
        $this->resetErrorsAndWarnings();
        $root = $this->getBagRoot();
        if (file_exists($root)) {
            throw new BagItException("New bag directory $root exists");
        }
        BagUtils::checkedMkdir($root . "/data", 0777, true);
        $this->updateBagIt();
        $this->payloadManifests = [
            self::DEFAULT_HASH_ALGORITHM => new PayloadManifest($this, self::DEFAULT_HASH_ALGORITHM)
        ];
    }

    /**
     * On new bag or load bag or update we need to refresh these containers.
     */
    private function resetErrorsAndWarnings(): void
    {
        $this->bagErrors = [];
        $this->bagWarnings = [];
    }

    /**
     * Update a fetch.txt if it exists.
     *
     * @throws FilesystemException
     */
    private function updateFetch(): void
    {
        if (isset($this->fetchFile)) {
            $this->fetchFile->update();
        }
    }

    /**
     * Read in the bag-info.txt file.
     *
     * To support newlines in bag-info.txt any line that is less than 70 characters will have the newline at the end
     * maintained. Otherwise, the newline is considered an auto-wrap and is removed to not interfere with the text.
     *
     * @return bool
     *   Does bag-info.txt exists.
     *
     * @throws FilesystemException
     *   Unable to read bag-info.txt
     */
    private function loadBagInfo(): bool
    {
        $info_file = 'bag-info.txt';
        $fullPath = $this->makeAbsolute($info_file);
        if (!file_exists($fullPath)) {
            return false;
        }
        $file_contents = file_get_contents($fullPath);
        if ($file_contents === false) {
            throw new FilesystemException("Unable to access $info_file");
        }
        $bagData = [];
        $lineCount = 0;
        $lines = BagUtils::splitFileDataOnLineEndings($file_contents);
        foreach ($lines as $line) {
            $lineCount += 1;
            if (empty(trim($line))) {
                continue;
            }
            $line = $this->decodeText($line) . PHP_EOL;
            $lineLength = strlen($line);
            if (str_starts_with($line, "  ") || $line[0] == "\t") {
                // Continuation of a line
                if (count($bagData) > 0) {
                    $previousValue = $bagData[count($bagData) - 1]['value'];
                    // Add a space only if the previous character was not a line break.
                    $lastChar = substr($previousValue, -1);
                    if ($lineLength >= Bag::BAGINFO_AUTOWRAP_GUESS_LENGTH) {
                        // Line is max length or longer, should be autowrapped
                        $previousValue = rtrim($previousValue, "\r\n");
                    }
                    $previousValue .= ($lastChar != "\r" && $lastChar != "\n" ? " " : "");
                    $previousValue .= Bag::trimSpacesOnly($line);
                    $bagData[count($bagData) - 1]['value'] = $previousValue;
                } else {
                    $this->addBagError(
                        $info_file,
                        "Line $lineCount: Appears to be continuation but there is no preceding tag."
                    );
                }
            } elseif (preg_match("~^(\s+)?([^:]+?)(\s+)?:([^\r\n]*)~", $line, $matches)) {
                // First line
                $current_tag = $matches[2];
                if (self::mustNotRepeatBagInfoExists($current_tag, $bagData)) {
                    $this->addBagError(
                        $info_file,
                        "Line $lineCount: Tag $current_tag MUST not be repeated."
                    );
                } elseif (self::shouldNotRepeatBagInfoExists($current_tag, $bagData)) {
                    $this->addBagWarning(
                        $info_file,
                        "Line $lineCount: Tag $current_tag SHOULD NOT be repeated."
                    );
                }
                if (($this->compareVersion('1.0') <= 0) && (!empty($matches[1]) || !empty($matches[3]))) {
                    $this->addBagError(
                        $info_file,
                        "Line $lineCount: Labels cannot begin or end with a whitespace."
                    );
                }
                $value = $matches[4];
                if ($lineLength < Bag::BAGINFO_AUTOWRAP_GUESS_LENGTH) {
                    // Shorter line, re-add the newline removed by the preg_match.
                    $value .= PHP_EOL;
                }
                $bagData[] = [
                    'tag' => $current_tag,
                    'value' => Bag::trimSpacesOnly($value),
                ];
            } else {
                $this->addBagError($info_file, "Invalid tag.");
            }
        }
        // We left newlines on the end of each tag value, those can be stripped.
        array_walk($bagData, function (&$item) {
            $item['value'] = rtrim($item['value']);
        });
        $this->bagInfoData = $bagData;

        $this->updateBagInfoIndex();
        return true;
    }

    /**
     * Just trim spaces NOT newlines and carriage returns.
     * @param string $text
     *   The original text
     * @return string
     *   The text with surrounding spaces trimmed away.
     */
    private static function trimSpacesOnly(string $text): string
    {
        return trim($text, " \t\0\x0B");
    }

    /**
     * Generate a faster index of Bag-Info tags.
     */
    private function updateBagInfoIndex(): void
    {
        $tags = [];
        foreach ($this->bagInfoData as $row) {
            $tagName = BagUtils::trimLower($row['tag']);
            if (!array_key_exists($tagName, $tags)) {
                $tags[$tagName] = [];
            }
            $tags[$tagName][] = $row['value'];
        }
        $this->bagInfoTagIndex = $tags;
    }

    /**
     * Internal case-insensitive search of bag info.
     *
     * @param  string $internal_tag
     *   Trimmed and lowercase tag.
     * @return bool
     *   Does it exist in the index.
     */
    private function bagInfoTagExists(string $internal_tag): bool
    {
        return array_key_exists($internal_tag, $this->bagInfoTagIndex);
    }

    /**
     * Write the contents of the bag-info array to disk.
     *
     * @throws FilesystemException
     *   Issues writing the file to disk.
     */
    private function updateBagInfo(): void
    {
        $fullPath = $this->makeAbsolute("bag-info.txt");
        $fp = fopen($fullPath, 'wb');
        if ($fp === false) {
            throw new FilesystemException("Could not write bag-info.txt");
        }
        $this->updateCalculateBagInfoFields();
        $this->updateBagInfoIndex();
        foreach ($this->bagInfoData as $bag_info_datum) {
            $tag = $bag_info_datum['tag'];
            $value = $bag_info_datum['value'];
            // We don't guarantee newlines remain once you edit a bag.
            $value = str_replace(["\r\n", "\n"], " ", $value);
            $data = self::wrapBagInfoText("$tag: $value");
            foreach ($data as $line) {
                $line = $this->encodeText($line);
                BagUtils::checkedFwrite($fp, $line . PHP_EOL);
            }
        }
        fclose($fp);
    }

    /**
     * Update the calculated bag-info fields
     */
    private function updateCalculateBagInfoFields(): void
    {
        $newInfo = [];
        foreach ($this->bagInfoData as $row) {
            if (in_array(BagUtils::trimLower($row['tag']), self::BAG_INFO_GENERATED_ELEMENTS)) {
                continue;
            }
            $newInfo[] = $row;
        }
        $calculated = $this->calculateTotalFileSizeAndAmountOfFiles();
        if (!empty($calculated)) {
            $newInfo[] = [
                'tag' => 'Payload-Oxum',
                'value' => $calculated['totalFileSize'] . '.' . $calculated['totalFiles'],
            ];
            $newInfo[] = [
                'tag' => 'Bag-Size',
                'value' => $this->convertToHumanReadable($calculated['totalFileSize']),
            ];
        }
        $newInfo[] = [
            'tag' => 'Bagging-Date',
            'value' => date('Y-m-d', time()),
        ];
        $this->bagInfoData = $newInfo;
    }

    /**
     * Convert given byte value to a human readable value.
     *
     * @param int $bytes
     *   Value in bytes
     * @return string
     */
    private function convertToHumanReadable(int $bytes): string
    {
        $symbols = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        if ($bytes > 0) {
            $bytesInFloat = floatval($bytes);
            $exp = floor(log($bytesInFloat) / log(1024));
            $result =  sprintf('%.2f ' . $symbols[$exp], ($bytesInFloat / pow(1024, floor($exp))));
        } else {
            $result = '0 B';
        }
        return $result;
    }

    /**
     * Remove the bag-info.txt file and data.
     *
     * @throws FilesystemException
     *   Unable to delete the bag-info.txt file.
     */
    private function removeBagInfo(): void
    {
        $fullPath = $this->makeAbsolute('bag-info.txt');
        if (file_exists($fullPath)) {
            BagUtils::checkedUnlink($fullPath);
        }
        $this->bagInfoData = [];
    }

    /**
     * Calculate the total file size and amount of files of all payload files.
     *
     * @return array|null
     *   The total file size and amount of all files or
     *   null if we couldn't read all the file sizes.
     */
    private function calculateTotalFileSizeAndAmountOfFiles(): ?array
    {
        $total_size = 0;
        $total_files = 0;
        foreach ($this->payloadFiles as $file) {
            $fullPath = $this->makeAbsolute($file);
            if (file_exists($fullPath) && is_file($fullPath)) {
                $info = stat($fullPath);
                if (!isset($info[7])) {
                    return null;
                }
                $total_size += (int) $info[7];
                $total_files += 1;
            }
        }
        return [
            'totalFileSize' => $total_size,
            'totalFiles' => $total_files
        ];
    }

    /**
     * Wrap bagInfo lines to 79 characters if possible
     *
     * @param  string $text
     *   The whole tag and value as one.
     * @return array
     *   The text as an array.
     */
    private static function wrapBagInfoText(string $text): array
    {
        // Start short of 79 for some leeway.
        $length = Bag::BAGINFO_AUTOWRAP_START;
        do {
            $rows = self::wrapAtLength($text, $length);
            $too_long = array_filter(
                $rows,
                function ($o) {
                    return strlen($o) > Bag::BAGINFO_AUTOWRAP_START;
                }
            );
            $length -= 1;
            $num_too_long = count($too_long);
        } while ($length > 0 && $num_too_long > 0);
        if ($num_too_long > 0) {
            // No matter the size we couldn't get it to fit in 79 characters. So we give up.
            $rows = self::wrapAtLength($text, Bag::BAGINFO_AUTOWRAP_START);
        }
        $row_count = count($rows);
        for ($foo = 1; $foo < $row_count; $foo += 1) {
            $rows[$foo] = "  " . $rows[$foo];
        }
        return $rows;
    }

    /**
     * Utility to remove newline characters, wrap the string and return an array of the rows.
     *
     * @param  string $text
     *   The text to wrap.
     * @param  int    $length
     *   The length to wrap at.
     * @return array
     *   Rows of text.
     */
    private static function wrapAtLength(string $text, int $length): array
    {
        $text = str_replace("\n", "", $text);
        $wrapped = wordwrap($text, $length);
        return explode("\n", $wrapped);
    }

    /**
     * Load all tag manifests (if any).
     *
     * @return boolean
     *   Are there any tag manifest files.
     *
     * @throws FilesystemException
     *   Problems with glob() pattern or loading manifest.
     */
    private function loadTagManifests(): bool
    {
        $this->ensureTagManifests();
        $tagManifests = [];
        $pattern = $this->getBagRoot() . "/tagmanifest-*.txt";
        $files = BagUtils::findAllByPattern($pattern);
        if (count($files) < 1) {
            return false;
        }
        foreach ($files as $file) {
            $hash = self::determineHashFromFilename($file);
            if (isset($tagManifests[$hash])) {
                $this->addBagError(
                    $this->makeRelative($file),
                    "More than one tag manifest for hash ($hash) found."
                );
            } else {
                $tagManifests[$hash] = new TagManifest($this, $hash, true);
            }
        }
        $this->tagManifests = $tagManifests;
        return true;
    }

    /**
     * Utility to setup tag manifests.
     */
    private function ensureTagManifests(): void
    {
        if (!isset($this->tagManifests)) {
            $this->tagManifests = [];
        }
    }

    /**
     * Run update against the tag manifests.
     *
     * @throws FilesystemException
     *   Issues deleting the tag manifest files.
     */
    private function updateTagManifests(): void
    {
        if (!$this->isExtended) {
            return;
        }
        $this->clearTagManifests();
        $this->ensureTagManifests();
        $hashes = $this->payloadManifests ?? [self::DEFAULT_HASH_ALGORITHM => ""];
        $hashes = array_diff_key($hashes, $this->tagManifests);
        foreach (array_keys($hashes) as $hash) {
            $this->tagManifests[$hash] = new TagManifest($this, $hash);
        }
        foreach ($this->tagManifests as $manifest) {
            $manifest->update();
        }
    }

    /**
     * Remove all tagmanifest files.
     *
     * @throws FilesystemException
     *    Errors with glob() pattern or deleting files from filesystem.
     */
    private function clearTagManifests(): void
    {
        $pattern = $this->getBagRoot() . "/tagmanifest-*.txt";
        $this->clearFilesOfPattern($pattern);
        unset($this->tagManifests);
    }

    /**
     * Remove tag manifests.
     *
     * @param array $exclusions
     *   Hash algorithm names of manifests to preserve.
     * @throws FilesystemException
     *   Issues deleting files from the filesystem.
     */
    private function removeAllTagManifests(array $exclusions = []): void
    {
        if (!isset($this->tagManifests)) {
            return;
        }
        foreach ($this->tagManifests as $hash => $manifest) {
            if (!in_array($hash, $exclusions)) {
                $this->removeTagManifest($hash);
            }
        }
    }

    /**
     * Remove a single tag manifest.
     *
     * @param string $internal_name
     *   The hash name to remove.
     * @throws FilesystemException
     *   Problems deleting the tag manifest file.
     */
    private function removeTagManifest(string $internal_name): void
    {
        $manifest = $this->tagManifests[$internal_name];
        $filename = $manifest->getFilename();
        if (file_exists($filename)) {
            BagUtils::checkedUnlink($this->makeAbsolute($filename));
        }
        unset($this->tagManifests[$internal_name]);
    }

    /**
     * Load all payload manifests found on disk.
     *
     * @throws FilesystemException
     *   Problems with glob() pattern
     * @throws BagItException
     *   Invalid algorithm detected.
     */
    private function loadPayloadManifests(): void
    {
        $pattern = $this->getBagRoot() . "/manifest-*.txt";
        $manifests = BagUtils::findAllByPattern($pattern);
        if (count($manifests) == 0) {
            $this->addBagError('manifest-ALG.txt', 'No payload manifest files found.');
            return;
        }
        $files = [];
        foreach ($manifests as $manifest) {
            $hash = self::determineHashFromFilename($manifest);
            $relative_filename = $this->makeRelative($manifest);
            if (!is_null($hash) && !in_array($hash, array_keys(self::HASH_ALGORITHMS))) {
                throw new BagItException("We do not support the algorithm $hash");
            } elseif (is_null($hash)) {
                $this->addBagError(
                    $relative_filename,
                    "Payload manifest MUST have a name in the form of manifest-ALG.txt"
                );
            } elseif (isset($this->payloadManifests[$hash])) {
                $this->addBagError(
                    $relative_filename,
                    "More than one payload manifest for hash ($hash) found."
                );
            } else {
                $temp = new PayloadManifest($this, $hash, true);
                $this->payloadManifests[$hash] = $temp;
                $files = array_merge($files, array_keys($temp->getHashes()));
            }
        }
        $this->payloadFiles = array_unique($files);
    }

    /**
     * Run update against the payload manifests.
     *
     * @throws FilesystemException
     *   Issues deleting payload manifest files.
     */
    private function updatePayloadManifests(): void
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
        $this->payloadFiles = array_unique($files);
    }

    /**
     * Remove all manifest files.
     *
     * @throws FilesystemException
     *    Errors with glob() pattern.
     */
    private function clearPayloadManifests(): void
    {
        $pattern = $this->getBagRoot() . "/manifest-*.txt";
        $this->clearFilesOfPattern($pattern);
    }

    /**
     * Remove payload manifests.
     *
     * @param array $exclusions
     *   Hash algorithm names of manifests to preserve.
     * @throws FilesystemException
     *   Issues deleting a file.
     */
    private function removeAllPayloadManifests(array $exclusions = []): void
    {
        foreach ($this->payloadManifests as $hash => $manifest) {
            if (!in_array($hash, $exclusions)) {
                $this->removePayloadManifest($hash);
            }
        }
    }

    /**
     * Remove a single payload manifest.
     *
     * @param string $internal_name
     *   The hash name to remove.
     * @throws FilesystemException
     *   Issues deleting the payload manifest file.
     */
    private function removePayloadManifest(string $internal_name): void
    {
        $manifest = $this->payloadManifests[$internal_name];
        $filename = $manifest->getFilename();
        if (file_exists($filename)) {
            BagUtils::checkedUnlink($this->makeAbsolute($filename));
        }
        unset($this->payloadManifests[$internal_name]);
    }

    /**
     * Load the bagit.txt on disk.
     *
     * @throws FilesystemException
     *   Can't read the file on disk.
     */
    private function loadBagIt(): void
    {
        $fullPath = $this->makeAbsolute("bagit.txt");
        if (!file_exists($fullPath)) {
            $this->addBagError(
                'bagit.txt',
                'Required file missing.'
            );
            return;
        }
        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            throw new FilesystemException("Unable to read $fullPath");
        }
        $lines = BagUtils::splitFileDataOnLineEndings($contents);
        // remove blank lines.
        $lines = array_filter($lines);
        array_walk(
            $lines,
            function (&$item) {
                $item = trim($item);
            }
        );
        if (count($lines) !== 2) {
            $this->addBagError(
                'bagit.txt',
                sprintf(
                    "File MUST contain exactly 2 lines, found %b",
                    count($lines)
                )
            );
            return;
        }
        if (
            !preg_match(
                "~^BagIt\-Version: (\d+)\.(\d+)$~",
                $lines[0],
                $match
            )
        ) {
            $this->addBagError(
                'bagit.txt',
                'First line should have pattern BagIt-Version: M.N'
            );
        } else {
            $this->currentVersion = [
                'major' => $match[1],
                'minor' => $match[2],
            ];
        }
        if (
            !preg_match(
                "~^Tag\-File\-Character\-Encoding: (.*)$~",
                $lines[1],
                $match
            )
        ) {
            $this->addBagError(
                'bagit.txt',
                'Second line should have pattern ' .
                    'Tag-File-Character-Encoding: ENCODING'
            );
        } else {
            $this->currentFileEncoding = $match[1];
        }
    }

    /**
     * Update the bagit.txt on disk.
     *
     * @throws FilesystemException
     *   Issues putting contents to the file.
     */
    private function updateBagIt(): void
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

        BagUtils::checkedFilePut(
            $this->makeAbsolute("bagit.txt"),
            $output
        );
    }

    /**
     * Load a fetch.txt if it exists.
     *
     * @throws FilesystemException
     *   Unable to read fetch.txt for existing bag.
     */
    private function loadFetch(): void
    {
        $fullPath = $this->makeAbsolute('fetch.txt');
        if (file_exists($fullPath)) {
            $this->fetchFile = new Fetch($this, true);
            $this->mergeErrors($this->fetchFile->getErrors());
        }
    }

    /**
     * Create an archive file of the current bag.
     *
     * @param  string $filename
     *   The archive filename.
     * @throws FilesystemException
     *   Problems creating the archive.
     * @throws BagItException
     *   Unable to determine archive format.
     */
    private function makePackage(string $filename): void
    {
        $extension = self::getExtension($filename);
        if (self::hasExtension($extension, self::ZIP_EXTENSIONS)) {
            $this->makeZip($filename);
        } elseif (self::hasExtension($extension, self::TAR_EXTENSIONS)) {
            $this->makeTar($filename);
        } else {
            throw new BagItException("Unable to determine archive format.");
        }
    }

    /**
     * Create a Zip archive.
     *
     * @param  string $filename
     *   The archive filename.
     * @throws FilesystemException
     *   Problems creating the archive.
     */
    private function makeZip(string $filename): void
    {
        $zip = new ZipArchive();
        $res = $zip->open($filename, ZipArchive::CREATE);
        if ($res !== true) {
            throw new FilesystemException("Unable to create zip file");
        }
        $files = BagUtils::getAllFiles($this->bagRoot);
        $parentPrefix = basename($this->bagRoot);
        foreach ($files as $file) {
            $relative = $this->makeRelative($file);
            $zip->addFile($file, "$parentPrefix/$relative");
        }
        $zip->close();
    }

    /**
     * Create a Tar archive.
     *
     * @param  string $filename
     *   The archive filename.
     * @throws FilesystemException
     *   Problems creating the archive.
     */
    private function makeTar(string $filename): void
    {
        $compression = self::extensionTarCompression($filename);
        $tar = new Archive_Tar($filename, $compression);
        $parent = $this->getParentDir();
        $files = BagUtils::getAllFiles($this->bagRoot);
        if (!$tar->createModify($files, "", $parent)) {
            throw new FilesystemException("Error adding files to $filename.");
        }
    }

    /**
     * Get the parent directory of the current Bag.
     *
     * @return string
     *   The parent directory.
     */
    private function getParentDir(): string
    {
        return dirname($this->bagRoot);
    }

    /**
     * Uncompress a BagIt archive file.
     *
     * @param string $filepath
     *   The full path to the archive file.
     * @param string $extension
     *   The extension to the archive file.
     * @return string
     *   The full path to extracted bag.
     * @throws FilesystemException
     *   Problems accessing and/or uncompressing files on filesystem.
     * @throws BagItException
     *   Unable to determine correct archive format or file does not exist.
     */
    private static function uncompressBag(string $filepath, string $extension): string
    {
        if (!file_exists($filepath)) {
            throw new BagItException("File $filepath does not exist.");
        }
        if (self::hasExtension($extension, self::ZIP_EXTENSIONS)) {
            $directory = self::unzipBag($filepath);
        } elseif (self::hasExtension($extension, self::TAR_EXTENSIONS)) {
            $directory = self::untarBag($filepath);
        } else {
            throw new BagItException("Unable to determine archive format.");
        }
        // $directory contains the directory with the bag, so find it.
        return self::getDirectory($directory);
    }

    /**
     * Unzip a zip file.
     *
     * @param  string $filename
     *   The full path to the zip file.
     * @return string
     *   The path the archive file was extracted to.
     * @throws FilesystemException
     *   Problems extracting the zip file.
     */
    private static function unzipBag(string $filename): string
    {
        $zip = new ZipArchive();
        $res = $zip->open($filename);
        if ($res !== true) {
            throw new FilesystemException("Unable to unzip $filename");
        }
        $directory = self::extractDir();
        $zip->extractTo($directory);
        $zip->close();
        return $directory;
    }

    /**
     * Untar a tar file.
     *
     * @param  string $filename
     *   The fullpath to the tar file.
     * @return string
     *   The path the archive file was extracted to.
     * @throws FilesystemException
     *   Problems extracting the zip file.
     */
    private static function untarBag(string $filename): string
    {
        $compression = self::extensionTarCompression($filename);
        $directory = self::extractDir();
        $tar = new Archive_Tar($filename, $compression);
        $res = $tar->extract($directory);
        if ($res === false) {
            throw new FilesystemException("Unable to untar $filename : " . $tar->error_object->getMessage());
        }
        return $directory;
    }

    /**
     * Determine the correct compression (if any) from the extension.
     *
     * @param  string $filename
     *   The filename.
     * @return string|null
     *   The compression string or null for no compression.
     */
    private static function extensionTarCompression(string $filename): ?string
    {
        $filename = strtolower(basename($filename));
        return (str_ends_with($filename, '.bz2') ? 'bz2' : (str_ends_with($filename, 'gz') ? 'gz' : null));
    }

    /**
     * Generate a temporary directory name.
     *
     * @return string
     *   The path to a new temporary directory.
     * @throws FilesystemException
     *   Issues creating/deleting files on filesystem.
     */
    private static function extractDir(): string
    {
        $temp = BagUtils::checkedTempnam();
        BagUtils::checkedUnlink($temp);
        BagUtils::checkedMkdir($temp);
        return $temp;
    }

    /**
     * Determine whether the given file extensions ends with an accepted extensions
     *
     * @param  string|null $file_extension
     *   The file extensions.
     * @param  array $extensions
     *   The list of extensions to check.
     * @return bool
     *   The list of extensions or an empty array.
     */
    private static function hasExtension(?string $file_extension, array $extensions): bool
    {
        if (is_null($file_extension)) {
            return false;
        }
        foreach ($extensions as $extension) {
            // Need to loop and check each to avoid failing on foo.tmp.tar.gz or bar.old.zip
            if (str_ends_with($file_extension, $extension)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve all the extensions of the given filepath.
     *
     * @param  string $filepath
     *   The full file path.
     * @return string|null
     *   The extension or null if not found.
     */
    private static function getExtension(string $filepath): ?string
    {
        $filename = strtolower(basename($filepath));
        $pathinfo = pathinfo($filename);
        $extensions = [];
        $extensions[] = $pathinfo['extension'] ?? null;
        while (strpos($pathinfo['filename'], ".") > -1) {
            $pathinfo = pathinfo($pathinfo['filename']);
            $extensions[] = $pathinfo['extension'] ?? null;
        }
        $extensions = array_filter($extensions);
        if (count($extensions) > 0) {
            return "." . ltrim(implode(".", array_reverse($extensions)), ".\ \n\r\t\v\0");
        }
        return null;
    }

    /**
     * Locate the extracted bag directory from inside our temporary directory.
     *
     * @param  string $filepath
     *   The temporary directory.
     * @return string
     *   The bag directory.
     * @throws BagItException
     *   Find more or less than one directory (not including . and ..)
     */
    private static function getDirectory(string $filepath): string
    {
        $files = array_diff(scandir($filepath), [".", ".."]);
        $dirs = [];
        if (count($files) > 0) {
            foreach ($files as $file) {
                $fullpath = $filepath . '/' . $file;
                if (is_dir($fullpath)) {
                    $dirs[] = $fullpath;
                }
            }
        }
        if (count($dirs) !== 1) {
            throw new BagItException("Found multiple root level directories inside archive file.");
        }
        return reset($dirs);
    }

    /**
     * Utility to remove files using a pattern.
     *
     * @param  string $filePattern
     *   The file pattern.
     * @throws FilesystemException
     *   Problems matching or deleting files.
     */
    private function clearFilesOfPattern(string $filePattern): void
    {
        $files = BagUtils::findAllByPattern($filePattern);
        if (count($files) < 1) {
            return;
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                BagUtils::checkedUnlink($file);
            }
        }
    }

    /**
     * Utility function to add bag error.
     *
     * @param string $filename
     *   The file the error was detected in.
     * @param string $message
     *   The message.
     */
    private function addBagError(string $filename, string $message): void
    {
        $this->bagErrors[] = [
            'file' => $filename,
            'message' => $message
        ];
    }

    /**
     * Utility function to add bag error.
     *
     * @param string $filename
     *   The file the error was detected in.
     * @param string $message
     *   The message.
     */
    private function addBagWarning(string $filename, string $message): void
    {
        $this->bagWarnings[] = [
            'file' => $filename,
            'message' => $message
        ];
    }

    /**
     * Normalize a PHP hash algorithm to a BagIt specification name. Used to alter the incoming $item.
     *
     * @param string $item
     *   The hash algorithm name.
     */
    private static function normalizeHashAlgorithmName(string &$item): void
    {
        $item = array_flip(self::HASH_ALGORITHMS)[$item];
    }

    /**
     * Check if the algorithm PHP has is allowed by the specification.
     *
     * @param string $item
     *   A hash algorithm name.
     *
     * @return boolean
     *   True if allowed by the specification.
     */
    private static function filterPhpHashAlgorithms(string $item): bool
    {
        return in_array($item, array_values(self::HASH_ALGORITHMS));
    }

    /**
     * Return the BagIt sanitized algorithm name.
     *
     * @param  string $algorithm
     *   A algorithm name
     * @return string
     *   The sanitized version of algorithm or an empty string if invalid.
     */
    public static function getHashName(string $algorithm): string
    {
        $algorithm = BagUtils::trimLower($algorithm);
        $algorithm = preg_replace("/[^a-z0-9]/", "", $algorithm);
        return in_array($algorithm, array_keys(Bag::HASH_ALGORITHMS)) ? $algorithm : "";
    }

    /**
     * Do we have a payload manifest with this internal hash name. Internal use only to avoid getHashName()
     *
     * @param  string $internal_name
     *   Internal name from getHashName.
     * @return boolean
     *   Already have this algorithm.
     */
    private function hasHash(string $internal_name): bool
    {
        return (in_array($internal_name, array_keys($this->payloadManifests)));
    }

    /**
     * Is the internal named hash supported by our PHP. Internal use only to avoid getHashName()
     *
     * @param  string $internal_name
     *   Output of getHashName
     * @return boolean
     *   Do we support the algorithm
     * @see Bag::getHashName
     */
    private function hashIsSupported(string $internal_name): bool
    {
        return ($internal_name != null && in_array($internal_name, $this->validHashAlgorithms));
    }

    /**
     * Case-insensitive version of array_key_exists
     *
     * @param  string     $search The key to look for.
     * @param  string|int $key    The associative or numeric key to look in.
     * @param  array      $map    The associative array to search.
     * @return boolean True if the key exists regardless of case.
     */
    private static function arrayKeyExistsNoCase(string $search, $key, array $map): bool
    {
        $keys = array_column($map, $key);
        array_walk(
            $keys,
            function (&$item) {
                $item = strtolower($item);
            }
        );
        return in_array(strtolower($search), $keys);
    }

    /**
     * Check that the key is not non-repeatable and already in the bagInfo.
     *
     * @param string $key The key being added.
     * @param array $bagData The current bag data.
     *
     * @return boolean
     *   True if the key is non-repeatable and already in the
     */
    private static function mustNotRepeatBagInfoExists(string $key, array $bagData): bool
    {
        return (in_array(strtolower($key), self::BAG_INFO_MUST_NOT_REPEAT) &&
            self::arrayKeyExistsNoCase($key, 'tag', $bagData));
    }

    /**
     * Check that the key is not non-repeatable and already in the bagInfo.
     *
     * @param string $key The key being added.
     * @param array $bagData The current bag data.
     *
     * @return boolean
     *   True if the key is non-repeatable and already in the
     */
    private static function shouldNotRepeatBagInfoExists(string $key, array $bagData): bool
    {
        return (in_array(strtolower($key), self::BAG_INFO_SHOULD_NOT_REPEAT) &&
            self::arrayKeyExistsNoCase($key, 'tag', $bagData));
    }

    /**
     * Parse manifest/tagmanifest file names to determine hash algorithm.
     *
     * @param string $filepath the filename.
     *
     * @return string|null the hash or null.
     */
    private static function determineHashFromFilename(string $filepath): ?string
    {
        $filename = basename($filepath);
        return preg_match('~-([a-z0-9]+)\.txt$~', $filename, $matches) ? $matches[1] : null;
    }


    /**
     * Is the requested destination filename reserved on Windows?
     *
     * @param  string $filepath
     *   The relative filepath.
     * @return boolean
     *   True if a reserved filename.
     */
    private function reservedFilename(string $filepath): bool
    {
        $filename = substr($filepath, strrpos($filepath, '/') + 1);
        return (in_array(strtoupper($filename), self::WINDOWS_RESERVED_NAMES));
    }

    /**
     * Compare the provided version against the current one.
     *
     * @param  string $version
     *   The version to compare against.
     * @return int
     *   returns -1 $version < current, 0  $version == current, and 1 $version > current.
     */
    private function compareVersion(string $version): int
    {
        return version_compare($version, $this->getVersionString());
    }

    /**
     * Utility to merge manifest and fetch errors into the bag errors.
     *
     * @param array $newErrors
     *   The new errors to be added.
     */
    private function mergeErrors(array $newErrors): void
    {
        $this->bagErrors = array_merge($this->bagErrors, $newErrors);
    }

    /**
     * Utility to merge manifest and fetch warnings into the bag warnings.
     *
     * @param array $newWarnings
     *   The new warnings to be added.
     */
    private function mergeWarnings(array $newWarnings): void
    {
        $this->bagWarnings = array_merge($this->bagWarnings, $newWarnings);
    }

    /**
     * Determine the serialization mimetype from the extension.
     * @param string $extension The extension.
     * @return string The serialization mimetype.
     * @throws BagItException If the serialization mimetype cannot be determined.
     */
    private function determineSerializationMimetype(string $extension): string
    {
        foreach (self::SERIALIZATION_MAPPING as $extension => $mimetype) {
            if (str_ends_with($extension, $extension)) {
                return $mimetype;
            }
        }
        throw new BagItException("Unable to determine serialization mimetype for extension ($extension).");
    }
}
