<?php

namespace whikloj\BagItTools;

/**
 * Class for holding and interacting with fetch.txt data.
 *
 * @package whikloj\BagItTools
 * @author whikloj
 * @since 1.0.0
 */
class Fetch
{

    /**
     * The fetch filename.
     */
    private const FILENAME = "fetch.txt";

    /**
     * The bag this fetch file is part of
     *
     * @var \whikloj\BagItTools\Bag
     */
    private $bag;

    /**
     * The current absolute path to the fetch.txt file.
     *
     * @var string
     */
    private $filename;

    /**
     * Information from the fetch.txt, array of arrays with keys 'uri', 'size', and 'destination'
     *
     * @var array
     */
    private $files;

    /**
     * Errors
     *
     * @var array
     */
    private $fetchErrors = [];

    /**
     * Urls and Files that validated and should be downloaded.
     *
     * @var array
     */
    private $downloadQueue = [];

    /**
     * Curl version number string.
     *
     * @var string
     */
    private $curlVersion;

    /**
     * Standard curl options to use.
     *
     * @var array
     */
    private $curlOptions = [
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
    ];

    /**
     * Fetch constructor.
     *
     * @param \whikloj\BagItTools\Bag $bag
     *   The bag this fetch is part of.
     * @param bool $load
     *   Whether to load a fetch.txt
     */
    public function __construct(Bag $bag, $load = false)
    {
        $this->bag = $bag;
        $this->files = [];
        $this->curlVersion = curl_version()['version_number'];
        $this->filename = $this->bag->makeAbsolute(self::FILENAME);
        $this->setupCurl();
        if ($load) {
            $this->loadFiles();
        }
    }

    /**
     * Return the array of file data.
     *
     * @return array
     */
    public function getData() : array
    {
        return $this->files;
    }

    /**
     * Download the files.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   Unable to open file handle to save to.
     */
    public function downloadAll()
    {
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
     * @throws \whikloj\BagItTools\BagItException
     *   For all validation errors.
     */
    private function validateData(array $fetchData)
    {
        $uri = $fetchData['uri'];
        $dest = BagUtils::baseInData($fetchData['destination']);
        if (!$this->validateUrl($uri)) {
            // skip invalid URLs or non-http URLs
            throw new BagItException("URL {$uri} does not seem to have a scheme or host");
        }
        if (!$this->internalValidateUrl($uri)) {
            throw new BagItException("This library only supports http/https URLs");
        }
        if (!$this->validatePath($dest)) {
            // Skip destinations with %xx other than %0A, %0D and %25
            throw new BagItException("Destination paths can't have any percent encoded characters except CR, LF, & %");
        }
        if (!$this->bag->pathInBagData($dest)) {
            throw new BagItException("Path {$dest} resolves outside the bag.");
        }
    }

    /**
     * Download a single file as it is added to the fetch file so we can generate checksums.
     *
     * @param array $fetchData
     *   Array of data with keys 'uri', 'destination' and optionally 'size'.
     *
     * @throws \whikloj\BagItTools\BagItException
     */
    public function download($fetchData)
    {
        $this->validateData($fetchData);
        $uri = $fetchData['uri'];
        if ($this->urlExistsInFile($uri)) {
            throw new BagItException("This URL ({$uri}) is already in fetch.txt");
        }
        $dest = BagUtils::baseInData($fetchData['destination']);
        if ($this->destinationExistsInFile($dest)) {
            throw new BagItException("This destination ({$dest}) is already in the fetch.txt");
        }
        $fullDest = $this->bag->makeAbsolute($dest);
        $fullDest = \Normalizer::normalize($fullDest);
        if (file_exists($fullDest)) {
            throw new BagItException("File already exists at the destination path {$dest}");
        }
        $ch = $this->createCurl($uri, true);
        $output = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if (!empty($error)) {
            throw new BagItException("Error with download of {$uri} : {$error}");
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
     */
    public function removeFile($url)
    {
        if ($this->urlExistsInFile($url)) {
            $newFiles = [];
            foreach ($this->files as $file) {
                if (strtolower($url) !== strtolower($file['uri'])) {
                    $newFiles[] = $file;
                } else {
                    $fullFile = $this->bag->makeAbsolute($file['destination']);
                    if (file_exists($fullFile)) {
                        unlink($fullFile);
                    }
                }
            }
            $this->files = $newFiles;
        }
    }

    /**
     * Update the fetch.txt on disk with the fetch file records.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   If we can't write to disk.
     */
    public function update()
    {
        $this->writeToDisk();
    }

    /**
     * Remove any downloaded files referenced in fetch.txt. This is called before we package up the Bag or finalize the
     * directory.
     */
    public function cleanup()
    {
        foreach ($this->files as $file) {
            $fullPath = BagUtils::getAbsolute($this->bag->makeAbsolute($file['destination']));
            if (file_exists($fullPath)) {
                // Remove the file because we are being packaged or finalized.
                unlink($fullPath);
                $this->bag->checkForEmptyDir($fullPath);
            }
        }
    }

    /**
     * Clean up any downloaded files and then wipe the internal data array.
     */
    public function clearData()
    {
        $this->cleanup();
        $this->files = [];
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
    }

    /**
     * Return the errors.
     *
     * @return array
     *   Array of errors.
     */
    public function getErrors() : array
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
    public function reservedPath($dest) : bool
    {
        $dest = BagUtils::baseInData($dest);
        return $this->destinationExistsInFile($dest);
    }

    /*
     * Private functions
     */

    /**
     * Load an existing fetch.txt
     */
    private function loadFiles()
    {
        $this->resetErrors();
        if (file_exists($this->filename)) {
            $fp = fopen($this->filename, "rb");
            $lineCount = 0;
            while (!feof($fp)) {
                $lineCount += 1;
                $line = fgets($fp);
                $line = $this->bag->decodeText($line);
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                if (preg_match("~^([^\s]+)\s+(\d+|\-)\s+(.*)$~", $line, $matches)) {
                    // We just store what you give us, we'll validate when you load the contents to validate the bag.
                    $uri = $matches[1];
                    $filesize = $matches[2];
                    $destination = BagUtils::baseInData($matches[3]);
                    $this->files[] = [
                        'uri' => $uri,
                        'size' => $filesize,
                        'destination' => $destination,
                    ];
                } else {
                    $this->addError("Line {$lineCount} : This line is not valid.");
                }
            }
        }
    }

    /**
     * Write out data collected via curl to disk.
     *
     * @param mixed $content
     *   The content from curl.
     * @param string $destination
     *   The relative path to the final file.
     * @throws \whikloj\BagItTools\BagItException
     *   Trouble writing to disk.
     */
    private function saveFileData($content, $destination)
    {
        if (strlen($content) > 0) {
            $fullDest = $this->bag->makeAbsolute($destination);
            $fullDest = \Normalizer::normalize($fullDest);
            $dirname = dirname($fullDest);
            if (substr($this->bag->makeRelative($dirname), 0, 5) == "data/") {
                // Create any missing missing directories inside data.
                if (!file_exists($dirname)) {
                    mkdir($dirname, 0777, true);
                }
            }
            $res = file_put_contents($fullDest, $content, LOCK_EX);
            if ($res === false) {
                throw new BagItException("Unable to write to file {$fullDest}");
            }
        }
    }

    /**
     * Initiate a curl handler
     *
     * @param string $url
     *   The URL to download.
     * @param bool $single
     *   If this is a download() call versus a downloadAll() call.
     * @return false|resource
     *   False on error, otherwise a Curl resource.
     */
    private function createCurl($url, $single = false)
    {
        $ch = curl_init($url);
        $options = $this->curlOptions;
        if ($single === true) {
            // If this is set during curl_multi_exec, it swallows error messages.
            $options[CURLOPT_FAILONERROR] = true;
        }
        curl_setopt_array($ch, $options);
        return $ch;
    }

    /**
     * Download files using Curl.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   Unable to open a file handle to download to.
     */
    private function downloadFiles()
    {
        if (count($this->downloadQueue) > 0) {
            $mh = curl_multi_init();
            $curl_handles = [];
            $destinations = [];
            if ($mh !== false) {
                if (version_compare($this->curlVersion, '7.62.0') <= 0) {
                    // Try enabling HTTP/1.1 pipelining and HTTP/2 multiplexing.
                    curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX);
                }
                if (version_compare($this->curlVersion, '7.30.0') <= 0) {
                    curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 10);
                }
                foreach ($this->downloadQueue as $key => $download) {
                    $fullPath = $this->bag->makeAbsolute($download['destination']);
                    // Don't download again.
                    if (!file_exists($fullPath)) {
                        $destinations[$key] = $fullPath;
                        $curl_handles[$key] = $this->createCurl($download['uri']);
                        curl_multi_add_handle($mh, $curl_handles[$key]);
                    }
                }
                $running = null;
                do {
                    curl_multi_exec($mh, $running);
                } while ($running);
                for ($x = 0; $x < count($curl_handles); $x += 1) {
                    $error = curl_error($curl_handles[$x]);
                    $url = curl_getinfo($curl_handles[$x], CURLINFO_EFFECTIVE_URL);
                    if (!empty($error)) {
                        $this->addError("Failed to fetch URL ({$url})");
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
    }

    /**
     * Utility to recreate the fetch file using the currently stored files.
     *
     * @throws \whikloj\BagItTools\BagItException
     *   If we can't write the fetch file.
     */
    private function writeToDisk()
    {
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
        if (count($this->files) > 0) {
            $fp = fopen($this->filename, "wb");
            if ($fp === false) {
                throw new BagItException("Unable to write {$this->filename}");
            }
            foreach ($this->files as $fileData) {
                $line = "{$fileData['uri']} {$fileData['size']} {$fileData['destination']}" . PHP_EOL;
                $line = $this->bag->encodeText($line);
                fwrite($fp, $line);
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
    private function validateUrl($url) : bool
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
    private function internalValidateUrl($url) : bool
    {
        $parts = parse_url($url);
        if ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https') {
            return false;
        }
        return true;
    }

    /**
     * Validate the path for fetch files.
     *
     * @param string $dest
     *   The destination file path.
     * @return bool
     *   True if it is valid.
     */
    private function validatePath($dest) : bool
    {
        // You can't have any encoded characters in the destination string except LF, CR, CRLF and % itself.
        if (strpos($dest, '%') !== false) {
            $parts = explode('%', $dest);
            foreach ($parts as $part) {
                $char = substr($part, 0, 2);
                $char = strtolower($char);
                if (!($char == '0a' || $char == '0d' || $char == '25')) {
                    return false;
                }
            }
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
    private function urlExistsInFile($url) : bool
    {
        $uris = array_column($this->files, 'uri');
        array_walk($uris, function (&$item) {
            $item = strtolower($item);
        });
        return (in_array(strtolower($url), $uris));
    }

    /**
     * Check if the destination path is already in the file.
     *
     * @param string $dest
     *   Relative path to the destination file.
     * @return bool
     *   True if a duplicate.
     */
    private function destinationExistsInFile($dest) : bool
    {
        $paths = array_column($this->files, 'destination');
        array_walk($paths, function (&$item) {
            $item = strtolower($item);
        });
        return (in_array(strtolower($dest), $paths));
    }

    /**
     * Set general CURLOPTS based on the Curl version.
     */
    private function setupCurl()
    {
        if (!defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
            define('CURLMOPT_MAX_TOTAL_CONNECTIONS', 13);
        }
        if (!defined('CURL_PIPEWAIT')) {
            define('CURL_PIPEWAIT', 237);
        }
        if (version_compare('7.0', PHP_VERSION) >= 0 &&
            version_compare($this->curlVersion, '7.43.0') >= 0) {
            // Add CURL_PIPEWAIT if we can, using the integer to avoid problems in PHP 5.6
            $this->curlOptions[CURL_PIPEWAIT] = true;
        }
    }

    /**
     * Reset the error and warning logs.
     */
    private function resetErrors()
    {
        $this->fetchErrors = [];
    }

    /**
     * Add an error for the fetch file.
     *
     * @param string $message
     *   The message.
     */
    private function addError($message)
    {
        $this->fetchErrors[] = [
            'file' => self::FILENAME,
            'message' => $message,
        ];
    }
}
