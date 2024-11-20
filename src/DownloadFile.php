<?php

namespace whikloj\BagItTools;

use whikloj\BagItTools\Exceptions\BagItException;

/**
 * Simple class to hold the specifics of a Fetch file download.
 * @package whikloj\BagItTools
 * @since 5.0.0
 * @author whikloj
 */
class DownloadFile
{
    private string $uri;
    private string $destination;
    private ?int $size;

    /**
     * Constructor
     * @param string $uri The URI to download.
     * @param string $destination The destination file path.
     * @param int|null $size The expected download size or null if unknown.
     */
    public function __construct(string $uri, string $destination, ?int $size = null)
    {
        $this->uri = $uri;
        $this->destination = $destination;
        $this->size = $size;
    }

    /**
     * Property getter for array_column usage.
     * @param string $prop The property to get.
     * @return string|int|null The value of the property.
     */
    public function __get(string $prop)
    {
        return $this->$prop;
    }

    /**
     * Property isset check for array_column usage.
     * @param string $prop The property to check.
     * @return bool True if the property is set.
     */
    public function __isset(string $prop): bool
    {
        return isset($this->$prop);
    }

    /**
     * @return string The destination file path.
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @return string The URL to download.
     */
    public function getUrl(): string
    {
        return $this->uri;
    }

    /**
     * @return int|null The expected download size or null if unknown.
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Get the size as a string.
     * @return string The size as a string.
     */
    public function getSizeString(): string
    {
        return $this->size === null ? '-' : (string) $this->size;
    }

    /**
     * Validate the download URL.
     * @throws BagItException If the URL is invalid.
     */
    public function validateDownload(): void
    {
        if (!$this->validateUrl($this->getUrl())) {
            // skip invalid URLs or non-http URLs
            throw new BagItException("URL {$this->getUrl()} does not seem to have a scheme or host");
        }
        if (!$this->internalValidateUrl($this->getUrl())) {
            throw new BagItException("This library only supports http/https URLs");
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
        if (
            is_array($parts) &&
            array_key_exists('scheme', $parts) &&
            $parts['scheme'] !== 'http' &&
            $parts['scheme'] !== 'https'
        ) {
            return false;
        }
        return true;
    }
}
