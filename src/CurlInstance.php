<?php

declare(strict_types=1);

namespace whikloj\BagItTools;

use CurlHandle;
use CurlMultiHandle;
use whikloj\BagItTools\Exceptions\BagItException;

trait CurlInstance
{
    /**
     * Initiate a cUrl handler
     *
     * @param string $url
     *   The URL to download.
     * @param bool $single
     *   If this is a download() call versus a downloadAll() call.
     * @param int|null $size
     *   Expected download size or null if unknown
     * @return CurlHandle
     *   The cUrl resource.
     * @throws BagItException
     *   On cUrl error.
     */
    public static function createCurl(string $url, bool $single = false, ?int $size = null): CurlHandle
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new BagItException("Failed to initialize cUrl with URL ($url).");
        }
        $curlVersion = curl_version();
        $options = is_array($curlVersion) ? self::setupCurl($curlVersion['version']) : [];
        if ($single === true) {
            // If this is set during curl_multi_exec, it swallows error messages.
            $options[CURLOPT_FAILONERROR] = true;
        }
        if (is_int($size)) {
            $options[CURLOPT_NOPROGRESS] = 0;
            $options[CURLOPT_PROGRESSFUNCTION] = function ($a, $b, $c, $d, $e) use ($size) {
                // PROGRESSFUNCTION variables are
                // $a -> curl_handle
                // $b -> expected download size (bytes)
                // $c -> current download size (bytes)
                // $d -> expected upload size (bytes)
                // $e -> current upload size (bytes)
                return self::curlXferInfo($size, $c);
            };
        } else {
            $options[CURLOPT_NOPROGRESS] = 1;
        }
        curl_setopt_array($ch, $options);
        return $ch;
    }

    /**
     * Compares current download size versus expected for cUrl progress.
     * @param int $expectDl
     *   The expected download size (bytes).
     * @param int $currDl
     *   The current download size (bytes).
     * @return int
     *   1 if current download size is greater than 105% of the expected size.
     */
    private static function curlXferInfo(int $expectDl, int $currDl): int
    {
        // Allow a 5% variance in size.
        $variance = $expectDl * 1.05;
        return ($currDl > $variance ? 1 : 0);
    }

    /**
     * Create a cUrl multi handler.
     *
     * @return CurlMultiHandle
     *   The cUrl resource
     */
    public static function createMultiCurl(): CurlMultiHandle
    {
        $curlVersion = curl_version();
        $version = is_array($curlVersion) ? $curlVersion['version'] : false;
        $mh = curl_multi_init();
        if (
            $version &&
            version_compare('7.62.0', $version) > 0 &&
            version_compare('7.43.0', $version) <= 0
        ) {
            curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
        }
        if ($version && version_compare('7.30.0', $version) <= 0) {
            // Set a limit to how many connections can be opened.
            curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 10);
        }
        return $mh;
    }

    /**
     * Set general CURLOPTS based on the Curl version.
     * @return array<int, mixed> The options to set.
     */
    private static function setupCurl(string $curlVersion): array
    {
        if (!defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
            define('CURLMOPT_MAX_TOTAL_CONNECTIONS', 13);
        }
        if (!defined('CURLOPT_PIPEWAIT')) {
            define('CURLOPT_PIPEWAIT', 237);
        }
        $curlOptions = [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
        ];
        if (
            version_compare('7.0', PHP_VERSION, '<=') &&
            version_compare('7.43.0', $curlVersion, '<=')
        ) {
            $curlOptions[CURLOPT_PIPEWAIT] = true;
        }
        return $curlOptions;
    }
}
