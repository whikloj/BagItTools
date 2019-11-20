<?php

namespace whikloj\BagItTools;

/**
 * Abstract manifest class to hold common elements between Payload and Tag manifests.
 * @package whikloj\BagItTools
 */
abstract class AbstractManifest
{

  /**
   * @var \whikloj\BagItTools\Bag
   */
    protected $bag;

    protected $algorithm;

    protected $hashes = [];

    protected $filename;

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
   *
   * @return array
   *   Any errors occurred.
   */
    public function validate()
    {
        $errors = [];
        foreach ($this->hashes as $path => $hash) {
            $fullPath = $this->bag->makeAbsolute($path);
            $errors = $this->validatePath($path, $fullPath, $errors);
            $calculatedHash = strtolower($this->calculateHash($fullPath));
            $hash = strtolower($hash);
            if ($hash !== $calculatedHash) {
                $errors[] = [
                'file' => $this->filename,
                'message' => "{$path} calculated hash ({$calculatedHash}) does not match manifest ({$hash})",
                ];
            }
        }
        return $errors;
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

    protected function validatePath($path, $filepath, array $errors)
    {
        $filepath = trim($filepath);
        $filepath = realpath($filepath);
        if ($this->bag->makeRelative($filepath) === "") {
            $errors[] = [
            'file' => $this->filename,
            'message' => "{$path} resolves to a path outside of the data/ directory.",
            ];
        } elseif (!file_exists($filepath)) {
            $errors[] = [
            'file' => $this->filename,
            'message' => "{$path} does not exist.",
            ];
        }
        return $errors;
    }

  /**
   * Load the paths and hashes from the file on disk, does not validate.
   */
    protected function loadFile()
    {
        $this->hashes = [];
        $fullPath = $this->bag->makeAbsolute($this->filename);
        if (file_exists($fullPath)) {
            $fp = fopen($fullPath, "rb");
            while (!feof($fp)) {
                $line = fgets($fp);
                $line = mb_convert_encoding($line, Bag::DEFAULT_FILE_ENCODING);
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                if (preg_match("~^(\w+)\s+(.*)$~", $line, $matches)) {
                    $this->hashes[$matches[2]] = $matches[1];
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
            $line = mb_convert_encoding($line, $this->bag->getFileEncoding());
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
                if ($file[0] != ".") {
                    $fullPath = $currentPath . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($fullPath) && !in_array($file, $exclusions)) {
                        $paths[] = $fullPath;
                    } elseif (is_file($fullPath)) {
                        $found_files[] = $fullPath;
                    }
                }
            }
        }
        return $found_files;
    }
}
