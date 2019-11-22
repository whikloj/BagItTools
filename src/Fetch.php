<?php

namespace whikloj\BagItTools;

/**
 * Fetch.txt class
 * @package whikloj\BagItTools
 */
class Fetch
{

    private const FILENAME = "fetch.txt";

    /**
     * The bag this fetch file is part of
     *
     * @var \whikloj\BagItTools\Bag
     */
    private $bag;

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
     * Warnings
     *
     * @var array
     */
    private $fetchWarnings = [];

    /**
     * Urls and Files that validated and should be downloaded.
     *
     * @var array
     */
    private $downloadQueue = [];

    /**
     * Curl version array
     *
     * @var array
     */
    private $curlVersion;

    /**
     * Standard curl options to use.
     * @var array
     */
    private $curlOptions = [
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FAILONERROR => true,
    ];

    /**
     * Fetch constructor.
     * @param \whikloj\BagItTools\Bag $bag
     *   The bag this fetch is part of.
     * @param bool $load
     *   Whether to load a fetch.txt
     */
    public function __construct(Bag $bag, $load = false)
    {
        $this->bag = $bag;
        $this->files = [];
        $this->curlVersion = curl_version();
        $this->setupCurl();
        if ($load) {
            $this->loadFiles();
        }
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
     * @param array $fetchData
     *   Array with mandatory keys 'uri' and 'destination' and optional key 'size'.
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
     * @param $fetchData
     * @throws \whikloj\BagItTools\BagItException
     */
    public function download($fetchData)
    {
        $this->validateData($fetchData);
        $uri = $fetchData['uri'];
        $dest = BagUtils::baseInData($fetchData['destination']);
        $ch = $this->createCurl($uri);
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
     * Return the errors.
     *
     * @return array
     *   Array of errors.
     */
    public function getErrors()
    {
        return $this->fetchErrors;
    }

    /**
     * Return the warnings.
     *
     * @return array
     *   Array of warnings.
     */
    public function getWarnings()
    {
        return $this->fetchWarnings;
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
        $fullPath = $this->bag->makeAbsolute(self::FILENAME);
        if (file_exists($fullPath)) {
            $fp = fopen($fullPath, "rb");
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
     * @return false|resource
     *   False on error, otherwise a resource.
     */
    private function createCurl($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->curlOptions);
        //curl_setopt($ch, CURLOPT_FILE, $filestream);
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
                if (version_compare($this->curlVersion['version_number'], '7.62.0') < 0) {
                    // Try enabling HTTP/1.1 pipelining and HTTP/2 multiplexing.
                    curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX);
                }
                curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 10);
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
        $fullPath = $this->bag->makeAbsolute(self::FILENAME);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        if (count($this->files) > 0) {
            $fp = fopen(addslashes($fullPath), "wb");
            if ($fp === false) {
                throw new BagItException("Unable to write {$fullPath}");
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
    private function validateUrl($url)
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme']) || !isset($parts['host'])) {
            return false;
        }
        return true;
    }

    /**
     * Library specific (non-spec) requirements for URLs.
     *
     * @param string $url
     *   The URL.
     * @return bool
     *   True if we can process it.
     */
    private function internalValidateUrl($url)
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
    private function validatePath($dest)
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
     * Set general CURLOPTS based on the Curl version.
     */
    private function setupCurl()
    {
        if (version_compare($this->curlVersion['version_number'], '7.43.0') >= 0) {
            $this->curlOptions[CURLOPT_PIPEWAIT] = true;
        }
    }

    /**
     * Reset the error and warning logs.
     */
    private function resetErrors()
    {
        $this->fetchErrors = [];
        $this->fetchWarnings = [];
    }

    /**
     * Add a warning for the fetch file.
     *
     * @param string $message
     *   The message.
     */
    private function addWarning($message)
    {
        $this->fetchWarnings[] = [
            'file' => self::FILENAME,
            'message' => $message,
        ];
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
