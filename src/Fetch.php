<?php

declare(strict_types=1);

namespace whikloj\BagItTools;

use Normalizer;
use whikloj\BagItTools\Exceptions\BagItException;
use whikloj\BagItTools\Exceptions\FilesystemException;

/**
 * Class for holding and interacting with fetch.txt data.
 *
 * @package whikloj\BagItTools
 * @author whikloj
 * @since 1.0.0
 */
class Fetch
{
    use CurlInstance;

    /**
     * The fetch filename.
     */
    private const FILENAME = "fetch.txt";

    /**
     * The bag this fetch file is part of
     *
     * @var Bag
     */
    private Bag $bag;

    /**
     * The current absolute path to the fetch.txt file.
     *
     * @var string
     */
    private string $filename;

    /**
     * Information from the fetch.txt, array of arrays with keys 'uri', 'size', and 'destination'
     *
     * @var array
     */
    private array $files;

    /**
     * Errors
     *
     * @var array
     */
    private array $fetchErrors = [];

    /**
     * Urls and Files that validated and should be downloaded.
     *
     * @var array
     */
    private array $downloadQueue = [];

    /**
     * Fetch constructor.
     *
     * @param Bag $bag
     *   The bag this fetch is part of.
     * @param bool $load
     *   Whether to load a fetch.txt
     * @throws FilesystemException
     *   Unable to read fetch.txt for existing bag.
     */
    public function __construct(Bag $bag, bool $load = false)
    {
        $this->bag = $bag;
        $this->files = [];
        $this->filename = $this->bag->makeAbsolute(self::FILENAME);
        if ($load) {
            $this->loadFiles();
        }
    }

    /**
     * Return the array of file data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->files;
    }

    /**
     * Download the files.
     *
     * @throws BagItException
     *   Unable to open file handle to save to.
     */
    public function downloadAll(): void
    {
        if (count($this->getErrors()) > 0) {
            throw new BagItException("The fetch.txt file has errors, unable to download files.");
        }
        $this->resetErrors();
        $this->downloadQueue = [];
        foreach ($this->files as $file) {
            try {
                $this->validateData($file);
            } catch (BagItException $e) {
                $this->addError($e->getMessage());
                continue;
            }
            $this->downloadQueue[] = $file;
        }
        $this->downloadFiles();
    }

    /**
     * Validate fetch data.
     *
     * @param array $fetchData
     *   Array with mandatory keys 'uri' and 'destination' and optional key 'size'.
     *
     * @throws BagItException
     *   For all validation errors.
     */
    private function validateData(array $fetchData): void
    {
        $uri = $fetchData['uri'];
        $dest = BagUtils::baseInData($fetchData['destination']);
        if (!$this->validateUrl($uri)) {
            // skip invalid URLs or non-http URLs
            throw new BagItException("URL $uri does not seem to have a scheme or host");
        }
        if (!$this->internalValidateUrl($uri)) {
            throw new BagItException("This library only supports http/https URLs");
        }
        if (!$this->bag->pathInBagData($dest)) {
            throw new BagItException("Path $dest resolves outside the bag.");
        }
    }

    /**
     * Add a file to this fetch file.
     *
     * @param string $url
     *   The remote URL for the file.
     * @param string $destination
     *   The bag destination path for the file.
     * @param int|null $size
     *   The expected size of the file, or null for unknown.
     * @throws BagItException
     *   Errors with adding this file to your fetch file.
     */
    public function addFile(string $url, string $destination, ?int $size = null): void
    {
        $fetchData = [
            'uri' => $url,
            'destination' => $destination,
        ];
        if (is_int($size)) {
            $fetchData['size'] = $size;
        }
        // Download the file now to help with manifest handling, deleted when you package() or finalize().
        $this->download($fetchData);
    }

    /**
     * Download a single file as it is added to the fetch file so we can generate checksums.
     *
     * @param array $fetchData
     *   Array of data with keys 'uri', 'destination' and optionally 'size'.
     *
     * @throws BagItException
     *   Problems downloading the file.
     */
    public function download(array $fetchData): void
    {
        $this->validateData($fetchData);
        $uri = $fetchData['uri'];
        if ($this->urlExistsInFile($uri)) {
            throw new BagItException("This URL ($uri) is already in fetch.txt");
        }
        $dest = BagUtils::baseInData($fetchData['destination']);
        if ($this->destinationExistsInFile($dest)) {
            throw new BagItException("This destination ($dest) is already in the fetch.txt");
        }
        $fullDest = $this->bag->makeAbsolute($dest);
        $fullDest = Normalizer::normalize($fullDest);
        if (file_exists($fullDest)) {
            throw new BagItException("File already exists at the destination path $dest");
        }
        $size = $fetchData['size'] ?? null;
        $ch = $this->createCurl($uri, true, $size);
        $output = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if (!empty($error) || $output === false) {
            throw new BagItException("Error with download of $uri : $error");
        }
        $this->saveFileData($output, $dest);
        $this->files[] = [
            'uri' => $fetchData['uri'],
            'size' => (!empty($fetchData['size']) ? $fetchData['size'] : '-'),
            'destination' => $dest,
        ];
    }

    /**
     * Remove the URL (case-insensitive match) from the fetch file.
     *
     * @param string $url
     *   The url to remove.
     * @throws FilesystemException
     *   Issues removing the file from the filesystem.
     */
    public function removeFile(string $url): void
    {
        if ($this->urlExistsInFile($url)) {
            $newFiles = [];
            foreach ($this->files as $file) {
                if (strtolower($url) !== strtolower($file['uri'])) {
                    $newFiles[] = $file;
                } else {
                    $fullFile = $this->bag->makeAbsolute($file['destination']);
                    if (file_exists($fullFile)) {
                        BagUtils::checkedUnlink($fullFile);
                    }
                }
            }
            $this->files = $newFiles;
        }
    }

    /**
     * Update the fetch.txt on disk with the fetch file records.
     *
     * @throws FilesystemException
     *   If we can't write to disk.
     */
    public function update(): void
    {
        $this->writeToDisk();
    }

    /**
     * Remove any downloaded files referenced in fetch.txt. This is called before we package up the Bag or finalize the
     * directory.
     *
     * @throws FilesystemException
     *   Problems removing file from filesystem.
     */
    public function cleanup(): void
    {
        foreach ($this->files as $file) {
            $fullPath = $this->bag->makeAbsolute($file['destination']);
            if (file_exists($fullPath)) {
                // Remove the file because we are being packaged or finalized.
                BagUtils::checkedUnlink($fullPath);
                $this->bag->checkForEmptyDir($fullPath);
            }
        }
    }

    /**
     * Clean up any downloaded files and then wipe the internal data array.
     *
     * @throws FilesystemException
     *   Problems removing file from filesystem.
     */
    public function clearData(): void
    {
        $this->cleanup();
        $this->files = [];
        if (file_exists($this->filename)) {
            BagUtils::checkedUnlink($this->filename);
        }
    }

    /**
     * Return the errors.
     *
     * @return array
     *   Array of errors.
     */
    public function getErrors(): array
    {
        return $this->fetchErrors;
    }

    /**
     * Check if the destination is supposed to be used by a fetched url.
     *
     * @param string $dest
     *   The relative path to check.
     * @return bool
     *   True if the destination is in the fetch.txt
     */
    public function reservedPath(string $dest): bool
    {
        $dest = BagUtils::baseInData($dest);
        return $this->destinationExistsInFile($dest);
    }

    /*
     * Private functions
     */

    /**
     * Load an existing fetch.txt
     *
     * @throws FilesystemException
     *   Unable to read the fetch.txt file.
     */
    private function loadFiles(): void
    {
        $this->resetErrors();
        if (file_exists($this->filename)) {
            $file_contents = file_get_contents($this->filename);
            if ($file_contents === false) {
                throw new FilesystemException("Unable to read file $this->filename");
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
                if (preg_match("~^(\S+)\s+(\d+|\-)\s+(.*)$~", $line, $matches)) {
                    // We just store what you give us, we'll validate when you load the contents to validate the bag.
                    $uri = $matches[1];
                    $filesize = $matches[2];
                    if ($filesize != "-") {
                        $filesize = (int)$filesize;
                    }
                    $destination = BagUtils::baseInData($matches[3]);
                    if (BagUtils::checkUnencodedFilepath($destination)) {
                        $this->addError(
                            "Line $lineCount: Filepaths containing Line Feed (LF), Carriage Return (CR) or a " .
                            "percent sign (%) MUST be encoded, and only those characters can be encoded."
                        );
                    }
                    $destination = BagUtils::decodeFilepath($destination);
                    $this->files[] = [
                        'uri' => $uri,
                        'size' => $filesize,
                        'destination' => $destination,
                    ];
                } else {
                    $this->addError("Line $lineCount: This line is not valid.");
                }
            }
        }
    }

    /**
     * Write out data collected via curl to disk.
     *
     * @param string $content
     *   The content from curl.
     * @param string $destination
     *   The relative path to the final file.
     * @throws FilesystemException
     *   Trouble writing to disk.
     */
    private function saveFileData(string $content, string $destination): void
    {
        if (strlen($content) > 0) {
            $fullDest = $this->bag->makeAbsolute($destination);
            $fullDest = Normalizer::normalize($fullDest);
            $dirname = dirname($fullDest);
            if (str_starts_with($this->bag->makeRelative($dirname), "data/")) {
                // Create any missing directories inside data.
                if (!file_exists($dirname)) {
                    BagUtils::checkedMkdir($dirname, 0777, true);
                }
            }
            BagUtils::checkedFilePut($fullDest, $content, LOCK_EX);
        }
    }

    /**
     * Download files using Curl.
     *
     * @throws FilesystemException
     *   Unable to open a file handle to download to.
     */
    private function downloadFiles(): void
    {
        if (count($this->downloadQueue) > 0) {
            $mh = $this->createMultiCurl();
            $curl_handles = [];
            $destinations = [];
            foreach ($this->downloadQueue as $key => $download) {
                $fullPath = $this->bag->makeAbsolute($download['destination']);
                // Don't download again.
                if (!file_exists($fullPath)) {
                    $destinations[$key] = $fullPath;
                    $size = is_int($download['size']) ? $download['size'] : null;
                    $curl_handles[$key] = $this->createCurl($download['uri'], false, $size);
                    curl_multi_add_handle($mh, $curl_handles[$key]);
                }
            }
            $running = null;
            do {
                $status = curl_multi_exec($mh, $running);
                while (false !== curl_multi_info_read($mh)) {
                    // Need to read the information or we lose any callback aborted messages.
                }
            } while ($running && $status == CURLM_OK);
            if ($status != CURLM_OK) {
                $this->addError("Problems with multifile download.");
            }
            $handle_count = count($curl_handles);
            for ($x = 0; $x < $handle_count; $x += 1) {
                $error = curl_error($curl_handles[$x]);
                $url = curl_getinfo($curl_handles[$x], CURLINFO_EFFECTIVE_URL);
                if (!empty($error)) {
                    $this->addError("Failed to fetch URL ($url) : $error");
                } else {
                    $content = curl_multi_getcontent($curl_handles[$x]);
                    $this->saveFileData($content, $destinations[$x]);
                }
                curl_multi_remove_handle($mh, $curl_handles[$x]);
                curl_close($curl_handles[$x]);
            }
            curl_multi_close($mh);
        }
    }

    /**
     * Utility to recreate the fetch file using the currently stored files.
     *
     * @throws FilesystemException
     *   If we can't write the fetch file.
     */
    private function writeToDisk(): void
    {
        if (file_exists($this->filename)) {
            BagUtils::checkedUnlink($this->filename);
        }
        if (count($this->files) > 0) {
            $fp = fopen($this->filename, "wb");
            if ($fp === false) {
                throw new FilesystemException("Unable to write $this->filename");
            }
            foreach ($this->files as $fileData) {
                $destination = BagUtils::encodeFilepath($fileData['destination']);
                $line = "{$fileData['uri']} {$fileData['size']} $destination" . PHP_EOL;
                $line = $this->bag->encodeText($line);
                BagUtils::checkedFwrite($fp, $line);
            }
            fclose($fp);
        }
    }

    /**
     * Validate URLs can be processed by this library.
     *
     * @param string $url
     *   The URL.
     * @return bool
     *   True if we can process it.
     */
    private function validateUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme']) || !isset($parts['host'])) {
            return false;
        }
        return true;
    }

    /**
     * BagItTools specific (non-spec) requirements for URLs.
     *
     * @param string $url
     *   The URL.
     * @return bool
     *   True if we can process it.
     */
    private function internalValidateUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https') {
            return false;
        }
        return true;
    }

    /**
     * Check if the url is already in the file.
     *
     * @param string $url
     *   The url to check
     * @return bool
     *   True if a duplicate.
     */
    private function urlExistsInFile(string $url): bool
    {
        return $this->existsInFile($url, 'uri');
    }

    /**
     * Check if the destination path is already in the file.
     *
     * @param string $dest
     *   Relative path to the destination file.
     * @return bool
     *   True if a duplicate.
     */
    private function destinationExistsInFile(string $dest): bool
    {
        return $this->existsInFile($dest, 'destination');
    }

    /**
     * Check multi-dimensional array for a specific value in a specific field.
     *
     * @param string $arg
     *   The value to look for.
     * @param string $key
     *   The key in the $this->files array to check in.
     * @return bool
     *   True if the value is located in the specified field.
     */
    private function existsInFile(string $arg, string $key): bool
    {
        $values = array_column($this->files, $key);
        $values = array_map('strtolower', $values);
        return (in_array(strtolower($arg), $values));
    }

    /**
     * Reset the error and warning logs.
     */
    private function resetErrors(): void
    {
        $this->fetchErrors = [];
    }

    /**
     * Add an error for the fetch file.
     *
     * @param string $message
     *   The message.
     */
    private function addError(string $message): void
    {
        $this->fetchErrors[] = [
            'file' => self::FILENAME,
            'message' => $message,
        ];
    }
}
