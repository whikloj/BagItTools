<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;

/**
 * Class to setup a mock webserver for testing remote file downloads.
 * @package whikloj\BagItTools\Test
 * @since 5.0.0
 *
 * To use this abstract class, extend it and then implement the setupBeforeClass methods, define the webserver_files
 * variable and then call the parent::setUpBeforeClass() method.
 */
abstract class BagItWebserverFramework extends BagItTestFramework
{
    /**
     * Array of remote files defined in mock webserver.
     * Outside key is a unique identifier, keys for the inside array are:
     *  filename (string) - path to file with response contents
     *  headers (array) - headers to return in response
     *  status_code (int) - status code
     *  content (string) - string to return, used instead of filename
     *  path (path) - the path of the URL, used if filename not defined. Otherwise is basename(filename)
     * @var array<string, array<string, mixed>>
     */
    protected static array $webserver_files = [];

    /**
     * A mock webserver for some remote download tests.
     *
     * @var MockWebServer
     */
    protected static MockWebServer $webserver;

    /**
     * Array of file contents for use with comparing against requests against the same index in self::$remote_urls
     *
     * @var array<int, mixed>
     */
    protected static array $response_content = [];

    /**
     * Array of mock urls to get responses from. Match response bodies against matching key in self::$response_content
     *
     * @var array<int, string>
     */
    protected static array $remote_urls = [];

    /**
     * {@inheritdoc}
     * NOTE: You should override this in your class, define self::$webserver_files, then call parent::setUpBeforeClass
     */
    public static function setUpBeforeClass(): void
    {
        self::$webserver = new MockWebServer();
        self::$webserver->start();
        $counter = 0;
        foreach (self::$webserver_files as $file) {
            self::$response_content[$counter] = $file['content'] ?? file_get_contents($file['filename']);
            // Add custom headers if defined.
            $response_headers = [
                'Cache-Control' => 'no-cache',
            ] + ($file['headers'] ?? []);
            if (isset($file['content'])) {
                $response_headers['Content-Length'] = strlen($file['content']);
            } else {
                $stats = stat($file['filename']);
                if ($stats !== false) {
                    $response_headers['Content-Length'] = $stats['size'];
                }
            }
            // Use custom status code if defined.
            $status_code = $file['status_code'] ?? 200;
            self::$remote_urls[$counter] = self::$webserver->setResponseOfPath(
                "/" . ($file['path'] ?? "example/" . basename($file['filename'])),
                new Response(
                    self::$response_content[$counter],
                    $response_headers,
                    $status_code
                )
            );
            $counter += 1;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass(): void
    {
        self::$webserver->stop();
    }
}
