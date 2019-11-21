<?php

namespace whikloj\BagItTools;

/**
 * Abstract manifest class to hold common elements between Payload and Tag manifests.
 * @package whikloj\BagItTools
 */
abstract class AbstractManifest
{

    /**
     * The bag this manifest is part of.
     *
     * @var \whikloj\BagItTools\Bag
     */
    protected $bag;

    /**
     * The hash algorithm for this manifest.
     *
     * @var string
     */
    protected $algorithm;

    /**
     * Associative array where paths are keys and hashes are values.
     *
     * @var array
     */
    protected $hashes = [];

    /**
     * The filename for this manifest.
     *
     * @var string
     */
    protected $filename;

    /**
     * Array of files on disk to validate against.
     *
     * @var array
     */
    protected $filesOnDisk = [];

    /**
     * Errors while validating this manifest.
     *
     * @var array
     */
    protected $manifestErrors = [];

    /**
     * Warnings generated while validating this manifest.
     *
     * @var array
     */
    protected $manifestWarnings = [];

    /**
     * Errors generated while loading.
     * Because of the path key in the hash array if there are multiple entries for a path we need to track it during
     * load but present it at validate().
     *
     * @var array
     */
    protected $loadErrors = [];

    /**
     * Manifest constructor.
     *
     * @param \whikloj\BagItTools\Bag $bag
     *   The bag this manifest is part of.
     * @param string $algorithm
     *   The BagIt name of the hash algorithm.
     * @param string $filename
     *   The manifest filename.
     * @param boolean $load
     *   Whether we are loading an existing file
     */
    protected function __construct(Bag $bag, $algorithm, $filename, $load = false)
    {
        $this->bag = $bag;
        $this->algorithm = $algorithm;
        $this->filename = $filename;

        if ($load) {
            $this->loadFile();
        }
    }

    /**
     * Return the algorithm for this manifest.
     *
     * @return string
     */
    public function getAlgorithm()
    {
        return $this->algorithm;
    }

    /**
     * Return the filename of this manifest.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Return the array of errors.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->manifestErrors;
    }

    /**
     * Return the array of warnings.
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->manifestWarnings;
    }

    /**
     * Update the hashes for each path.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   Error writing the manifest file to disk.
     */
    public function update()
    {
        $newHashes = [];
        foreach ($this->hashes as $path => $hash) {
            $newHashes[$path] = $this->calculateHash($this->bag->makeAbsolute($path));
        }
        $this->hashes = $newHashes;
        $this->writeToDisk();
    }

    /**
     * Compare file hashes against what is on disk.
     */
    public function validate()
    {
        $this->manifestWarnings = [];
        $this->manifestErrors = [] + $this->loadErrors;
        foreach ($this->hashes as $path => $hash) {
            $fullPath = $this->bag->makeAbsolute($path);
            $this->validatePath($path, $fullPath);
            $calculatedHash = strtolower($this->calculateHash($fullPath));
            $hash = strtolower($hash);
            if ($hash !== $calculatedHash) {
                $this->addError("{$path} calculated hash ({$calculatedHash}) does not match manifest " .
                    "({$hash})");
            }
        }
    }

    /**
     * Return the payload and hashes as an associative array.
     *
     * @return array
     *   Array of paths => hashes
     */
    public function getHashes()
    {
        return $this->hashes;
    }

    /*
     * Protected functions.
     */

    protected function validatePath($path, $filepath)
    {
        $filepath = $this->cleanUpAbsPath($filepath);
        if ($filepath === false || !file_exists($filepath)) {
            $this->addError("{$path} does not exist.");
        } elseif ($this->bag->makeRelative($filepath) === "") {
            $this->addError("{$path} resolves to a path outside of the data/ directory.");
        }
    }

    /**
     * Load the paths and hashes from the file on disk, does not validate.
     */
    protected function loadFile()
    {
        $this->hashes = [];
        $this->loadErrors = [];
        $fullPath = $this->bag->makeAbsolute($this->filename);
        if (file_exists($fullPath)) {
            $fp = fopen($fullPath, "rb");
            while (!feof($fp)) {
                $line = fgets($fp);
                $line = $this->bag->decodeText($line);
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                if (preg_match("~^(\w+)\s+\*?(.*)$~", $line, $matches)) {
                    $path = $this->cleanUpRelPath($matches[2]);
                    if (array_key_exists($path, $this->hashes)) {
                        $this->addLoadError("Path {$matches[2]} appears more than once in manifest.");
                    } else {
                        $this->hashes[$path] = $matches[1];
                    }
                }
            }
            fclose($fp);
        }
    }

    /**
     * Utility to recreate the manifest file using the currently stored hashes.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   If we can't write the manifest files.
     */
    protected function writeToDisk()
    {
        $fullPath = $this->bag->makeAbsolute($this->filename);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $fp = fopen(addslashes($fullPath), "w");
        if ($fp === false) {
            throw new BagItException("Unable to write {$fullPath}");
        }
        foreach ($this->hashes as $path => $hash) {
            $line = "{$hash} {$path}" . PHP_EOL;
            $line = $this->bag->encodeText($line);
            fwrite($fp, $line);
        }
        fclose($fp);
    }

    /**
     * Calculate the hash of the file.
     *
     * @param string $file
     *   Absolute path to the file.
     *
     * @return string
     *   The hash.
     */
    protected function calculateHash($file)
    {
        return hash_file($this->getPhpHashName(), $file);
    }

    /**
     * Add an error using the current filename.
     *
     * @param string $message
     *   The error text.
     */
    protected function addError($message)
    {
        $this->manifestErrors[] = [
            'file' => $this->filename,
            'message' => $message,
        ];
    }

    /**
     * Add a warning using the current filename.
     *
     * @param string $message
     *   The error text.
     */
    protected function addWarning($message)
    {
        $this->manifestWarnings[] = [
            'file' => $this->filename,
            'message' => $message,
        ];
    }

    /**
     * Needed to account for differences in PHP hash to BagIt hash naming.
     *
     * i.e. BagIt sha3512 -> PHP sha3-512
     *
     * @return string the PHP hash name for the internal hash encoding.
     */
    protected function getPhpHashName()
    {
        return Bag::HASH_ALGORITHMS[$this->algorithm];
    }

    /**
     * Recursively list all files in a directory, except files starting with .
     *
     * @param string $directory
     *   The starting full path.
     * @param array $exclusions
     *   Array with directory names to skip.
     * @return array
     *   List of files with absolute path.
     */
    protected function getAllFiles($directory, $exclusions = [])
    {
        $paths = [$directory];
        $found_files = [];

        while (count($paths) > 0) {
            $currentPath = array_shift($paths);
            $files = scandir($currentPath);
            foreach ($files as $file) {
                if ($file == "." || $file == "..") {
                    continue;
                }
                $fullPath = $currentPath . DIRECTORY_SEPARATOR . $file;
                if (is_dir($fullPath) && !in_array($file, $exclusions)) {
                    $paths[] = $fullPath;
                } elseif (is_file($fullPath)) {
                    $found_files[] = $fullPath;
                }
            }
        }
        return $found_files;
    }

    /*
     * Private functions
     */

    /**
     * Clean up file paths to remove extraneous period, double period and slashes
     *
     * @param string $filepath
     *   The absolute file path
     * @return bool|string
     *   The cleaned up absolute file path or false if file doesn't exist.
     */
    private function cleanUpAbsPath($filepath)
    {
        $filepath = trim($filepath);
        return Bag::getAbsolute($filepath);
    }

    /**
     * Clean up file paths to remove extraneous periods, double periods and slashes
     *
     * @param string $filepath
     *   The relative file path.
     * @return bool|string
     *   The cleaned up relative file path or false if the file doesn't exist.
     */
    private function cleanUpRelPath($filepath)
    {
        $filepath = $this->bag->makeAbsolute($filepath);
        $filepath = $this->cleanUpAbsPath($filepath);
        return ($filepath === false ? false : $this->bag->makeRelative($filepath));
    }

    /**
     * Add a load error using the current filename. This is only erased on a new load.
     *
     * @param string $message
     *   The error text.
     */
    private function addLoadError($message)
    {
        $this->loadErrors[] = [
            'file' => $this->filename,
            'message' => $message,
        ];
    }
}
