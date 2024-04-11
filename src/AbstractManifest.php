<?php

declare(strict_types=1);

namespace whikloj\BagItTools;

use Normalizer;
use whikloj\BagItTools\Exceptions\FilesystemException;

/**
 * Abstract manifest class to hold common elements between Payload and Tag manifests.
 *
 * @package whikloj\BagItTools
 * @author whikloj
 * @since 1.0.0
 */
abstract class AbstractManifest
{
    /**
     * The bag this manifest is part of.
     *
     * @var Bag
     */
    protected Bag $bag;

    /**
     * The hash algorithm for this manifest.
     *
     * @var string
     */
    protected string $algorithm;

    /**
     * Associative array where paths are keys and hashes are values.
     *
     * @var array
     */
    protected array $hashes = [];

    /**
     * Array of the same paths as in $hashes but normalized for case and characters to check for duplication.
     *
     * @var array
     */
    protected array $normalizedPaths = [];

    /**
     * The filename for this manifest.
     *
     * @var string
     */
    protected string $filename;

    /**
     * Errors while validating this manifest.
     *
     * @var array
     */
    protected array $manifestErrors = [];

    /**
     * Warnings generated while validating this manifest.
     *
     * @var array
     */
    protected array $manifestWarnings = [];

    /**
     * Errors/Warnings generated while loading.
     * Because of the path key in the hash array if there are multiple entries for a path we need to track it during
     * load but present it at validate().
     *
     * @var array
     *   Array of arrays with keys 'error' and 'warning'
     * @see AbstractManifest::resetLoadIssues
     */
    protected array $loadIssues;

    /**
     * Manifest constructor.
     *
     * @param Bag $bag
     *   The bag this manifest is part of.
     * @param string $algorithm
     *   The BagIt name of the hash algorithm.
     * @param string $filename
     *   The manifest filename.
     * @param boolean $load
     *   Whether we are loading an existing file
     * @throws FilesystemException
     *   Unable to read manifest file.
     */
    protected function __construct(Bag $bag, string $algorithm, string $filename, bool $load = false)
    {
        $this->bag = $bag;
        $this->algorithm = $algorithm;
        $this->filename = $filename;
        $this->resetLoadIssues();

        if ($load) {
            $this->loadFile();
        }
    }

    /**
     * Return the algorithm for this manifest.
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Return the filename of this manifest.
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Return the array of errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->manifestErrors;
    }

    /**
     * Return the array of warnings.
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->manifestWarnings;
    }

    /**
     * Update the hashes for each path.
     *
     * @throws FilesystemException
     *   Error writing the manifest file to disk.
     */
    public function update(): void
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
    public function validate(): void
    {
        $this->manifestWarnings = [] + $this->loadIssues['warning'];
        $this->manifestErrors = [] + $this->loadIssues['error'];
        if ($this->algorithm == 'md5') {
            $this->addWarning("This manifest is MD5, you should use setAlgorithm('sha512') to upgrade.");
        }
        foreach ($this->hashes as $path => $hash) {
            $fullPath = $this->bag->makeAbsolute($path);
            if (file_exists($fullPath)) {
                $calculatedHash = strtolower($this->calculateHash($fullPath));
                $hash = strtolower($hash);
                if ($hash !== $calculatedHash) {
                    $this->addError("$path calculated hash ($calculatedHash) does not match manifest " .
                        "($hash)");
                }
            } else {
                $this->addError("$path does not exist.");
            }
        }
    }

    /**
     * Return the payload and hashes as an associative array.
     *
     * @return array
     *   Array of paths => hashes
     */
    public function getHashes(): array
    {
        return $this->hashes;
    }

    /*
     * Protected functions.
     */

    /**
     * Load the paths and hashes from the file on disk, does not validate.
     *
     * @throws FilesystemException
     *   Unable to read manifest file.
     */
    protected function loadFile(): void
    {
        $this->hashes = [];
        $this->resetLoadIssues();
        $fullPath = $this->bag->makeAbsolute($this->filename);
        if (!file_exists($fullPath)) {
            return;
        }
        $file_contents = file_get_contents($fullPath);
        if ($file_contents === false) {
            throw new FilesystemException("Unable to read file $fullPath");
        }
        $lineCount = 0;
        $lines = BagUtils::splitFileDataOnLineEndings($file_contents);
        foreach ($lines as $line) {
            $lineCount += 1;
            $line = $this->bag->decodeText($line);
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (preg_match("~^(\w+)\s+\*?(.*)$~", $line, $matches)) {
                $hash = $matches[1];
                $originalPath = $matches[2];
                $this->checkIncomingFilePath($originalPath, $lineCount);
                $path = $this->cleanUpRelPath($originalPath);
                // Normalized path in lowercase (for matching)
                $lowerNormalized = $this->normalizePath($path);
                if (array_key_exists($path, $this->hashes)) {
                    $this->addLoadError("Line $lineCount: Path $originalPath appears more than once in " .
                        "manifest.");
                } elseif ($this->matchNormalizedList($lowerNormalized)) {
                    $this->addLoadWarning("Line $lineCount: Path $originalPath matches another file when " .
                        "normalized for case and characters.");
                } elseif (empty($path)) {
                    $this->addLoadError("Line $lineCount: Path $originalPath resolves to a path outside of the " .
                        "data/ directory.");
                } else {
                    $this->hashes[$path] = $hash;
                    $this->addToNormalizedList($lowerNormalized);
                }
            } else {
                $this->addLoadError("Line $lineCount: Line is not of the form 'checksum path'");
            }
        }
    }

    /**
     * Utility to recreate the manifest file using the currently stored hashes.
     *
     * @throws FilesystemException
     *   If we can't write the manifest files.
     */
    protected function writeToDisk(): void
    {
        $fullPath = $this->bag->makeAbsolute($this->filename);
        if (file_exists($fullPath)) {
            BagUtils::checkedUnlink($fullPath);
        }
        $fp = fopen(addslashes($fullPath), "w");
        if ($fp === false) {
            throw new FilesystemException("Unable to write $fullPath");
        }
        foreach ($this->hashes as $path => $hash) {
            $path = BagUtils::encodeFilepath($path);
            $line = "$hash $path" . PHP_EOL;
            $line = $this->bag->encodeText($line);
            BagUtils::checkedFwrite($fp, $line);
        }
        fclose($fp);
    }

    /**
     * Does validation on incoming file paths.
     *
     * @param string $filepath
     *   The file path to be checked.
     * @param int $lineCount
     *   The line of the manifest we are currently checking.
     */
    private function checkIncomingFilePath(string $filepath, int $lineCount): void
    {
        if (str_starts_with($filepath, "./")) {
            $this->addLoadWarning("Line $lineCount : Paths SHOULD not be relative");
        }
        if (BagUtils::checkUnencodedFilepath($filepath)) {
            $this->addLoadError(
                "Line $lineCount: File paths containing Line Feed (LF), Carriage Return (CR) or a percent sign (%) " .
                "MUST be encoded, and only those characters can be encoded."
            );
        }
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
    protected function calculateHash(string $file): string
    {
        return hash_file($this->getPhpHashName(), $file);
    }

    /**
     * Add an error using the current filename.
     *
     * @param string $message
     *   The error text.
     */
    protected function addError(string $message): void
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
    protected function addWarning(string $message): void
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
    protected function getPhpHashName(): string
    {
        return Bag::getHashName($this->algorithm);
    }

    /*
     * Private functions
     */

    /**
     * Add a path to the list of normalized paths.
     *
     * @param string $path
     *   The normalized path.
     */
    private function addToNormalizedList(string $path): void
    {
        $this->normalizedPaths[] = $path;
    }

    /**
     * Compare a path against a list of normalized paths and look for matches.
     *
     * @param string $path
     *   The normalized path to look for.
     * @return bool
     *   True if there is a match.
     */
    private function matchNormalizedList(string $path): bool
    {
        return (in_array($this->normalizePath($path), $this->normalizedPaths));
    }

    /**
     * Normalize a path for character representation and case.
     *
     * @param string $path
     *   The path.
     * @return string
     *   The normalized path.
     */
    private function normalizePath(string $path): string
    {
        $path = urldecode($path);
        $path = strtolower($path);
        if (!Normalizer::isNormalized($path)) {
            $path = Normalizer::normalize($path);
        }
        return $path;
    }

    /**
     * Clean up file paths to remove extraneous periods, double periods and slashes
     *
     * @param string $filepath
     *   The relative file path.
     * @return string
     *   The cleaned up relative file path or blank if not in the bag Root.
     */
    private function cleanUpRelPath(string $filepath): string
    {
        $filepath = $this->bag->makeAbsolute($filepath);
        $filepath = BagUtils::decodeFilepath($filepath);
        return $this->bag->makeRelative($filepath);
    }

    /**
     * Add a load error using the current filename. This is only erased on a new load.
     *
     * @param string $message
     *   The error text.
     */
    private function addLoadError(string $message): void
    {
        $this->loadIssues['error'][] = [
            'file' => $this->filename,
            'message' => $message,
        ];
    }

    /**
     * Add a load warning using the current filename. This is only erased on a new load.
     *
     * @param string $message
     *   The error text.
     */
    private function addLoadWarning(string $message): void
    {
        $this->loadIssues['warning'][] = [
            'file' => $this->filename,
            'message' => $message,
        ];
    }

    /**
     * Utility to reset the load issues construct.
     */
    private function resetLoadIssues(): void
    {
        $this->loadIssues = [
            'error' => [],
            'warning' => [],
        ];
    }
}
