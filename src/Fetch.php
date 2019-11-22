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
    public function download()
    {
        $this->resetErrors();
        $this->downloadQueue = [];
        foreach ($this->files as $file) {
            $uri = $file['uri'];
            $dest = BagUtils::baseInData($file['destination']);
            if (!$this->validateUrl($uri)) {
                // skip invalid URLs or non-http URLs
                continue;
            }
            if (!$this->validPath($dest)) {
                // Skip destinations with %xx other than %0A, %0D and %25
                continue;
            }
            if (!$this->bag->pathInBagData($dest)) {
                $this->addError("Path {$dest} resolves outside the bag.");
                continue;
            }
            $this->downloadQueue[] = $file;
        }
        $this->downloadFiles();
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
                    $destination = $matches[3];
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
            $file_handles = [];
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
                        $file_handles[$key] = fopen($fullPath, 'wb');
                        if ($file_handles[$key] === false) {
                            throw new BagItException("Unable to open output file to {$fullPath}");
                        }
                        $curl_handles[$key] = curl_init($download['url']);
                        curl_setopt_array($curl_handles[$key], $this->curlOptions);
                        curl_setopt($curl_handles[$key], CURLOPT_FILE, $file_handles[$key]);
                        curl_multi_add_handle($mh, $curl_handles[$key]);
                    }
                }
                $running = null;
                do {
                    curl_multi_exec($mh, $running);
                } while ($running);
                foreach ($file_handles as $fh) {
                    fclose($fh);
                }
                foreach ($curl_handles as $ch) {
                    $error = curl_error($ch);
                    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                    if (!empty($error)) {
                        $this->addError("Failed to fetch URL ({$url})");
                    }
                    curl_multi_remove_handle($mh, $ch);
                }
                curl_multi_close($mh);
            }
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
            $this->addError("URL {$url} does not seem to have a scheme or host");
            return false;
        }
        if ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https') {
            $this->addError("This library only supports http/https URLs, {$parts['scheme']} found");
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
    private function validPath($dest)
    {
        // You can't have any encoded characters in the destination string except LF, CR, CRLF and % itself.
        if (strpos($dest, '%') !== false) {
            $parts = explode('%', $dest);
            foreach ($parts as $part) {
                $char = substr($part, 0, 2);
                $char = strtolower($char);
                if (!($char == '0a' || $char == '0d' || $char == '25')) {
                    $this->addError("Destination paths can't have any percent encoded characters except CR, LF, & %");
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