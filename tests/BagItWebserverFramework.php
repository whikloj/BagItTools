<?php

namespace whikloj\BagItTools\Test;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;

abstract class BagItWebserverFramework extends BagItTestFramework
{
    /**
     * Array of remote files defined in mock webserver.
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
     * @var string|array|false
     */
    protected static string|array|false $response_content = [];

    /**
     * Array of mock urls to get responses from. Match response bodies against matching key in self::$response_content
     *
     * @var string|array
     */
    protected static string|array $remote_urls = [];

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$webserver = new MockWebServer();
        self::$webserver->start();
        $counter = 0;
        foreach (self::$webserver_files as $file) {
            self::$response_content[$counter] = file_get_contents($file['filename']);
            // Add custom headers if defined.
            $response_headers = [ 'Cache-Control' => 'no-cache', 'Content-Length' => stat($file['filename'])['size']] +
                ($file['headers'] ?? []);
            // Use custom status code if defined.
            $status_code = $file['status_code'] ?? 200;
            self::$remote_urls[$counter] = self::$webserver->setResponseOfPath(
                "/example/" . basename($file['filename']),
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
