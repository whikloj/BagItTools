<?php

namespace whikloj\BagItTools\Test;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use whikloj\BagItTools\Bag;
use whikloj\BagItTools\BagUtils;
use whikloj\BagItTools\Exceptions\BagItException;
use whikloj\BagItTools\Fetch;

/**
 * Class FetchTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Fetch
 */
class FetchTest extends BagItTestFramework
{
    /**
     * Location of fetch file test resources.
     */
    private const FETCH_FILES = self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'fetchFiles';

    /**
     * Location of webserver response files.
     */
    private const WEBSERVER_FILES_DIR = self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'webserver_responses';

    /**
     * Array of remote files defined in mock webserver.
     */
    private const WEBSERVER_FILES = [
        'remote_file1.txt' => [
            'filename' => self::WEBSERVER_FILES_DIR . DIRECTORY_SEPARATOR . 'remote_file1.txt',
            'checksums' => [
                'sha512' => 'fd7c6f2a22f5dffac90c4483c9d623206a237a523b8e5a6f291ac0678fb6a3b5d68bb09a779c1809a15d8ef' .
                    '8c7d4e16a6d18d50c9b7f9639fd0d8fcf2b7ef46a',
            ],
        ],
        'remote_file2.txt' => [
            'filename' => self::WEBSERVER_FILES_DIR . DIRECTORY_SEPARATOR . 'remote_file2.txt',
            'checksums' => [
                'sha512' => '29ad87ff27417de3e1526517e1b8583034c9f3a47e3c1f9ff216025229f9a04c85e8bdd5551d8df6838e462' .
                    '71732b98400170f8fd246d47de9312df2bdde3ca9',
            ],
        ],
        'remote_file3.txt' => [
            'filename' => self::WEBSERVER_FILES_DIR . DIRECTORY_SEPARATOR . 'remote_file3.txt',
            'checksums' => [
                'sha512' => '3dccc8db74e74ba8f0d926987e6daf93f78d9d344a0babfaac5d64dd614215c5358014c830706be5f00c920' .
                    'a9ce2fec0949fababfa65f3c6b7de8a3c27ac6f96',
            ],
        ],
        'remote_file4.txt' => [
            'filename' => self::WEBSERVER_FILES_DIR . DIRECTORY_SEPARATOR . 'remote_file4.txt',
            'checksums' => [
                'sha512' => '6b8c5673861b4578c441cd2fe5af209d6684abdfbaea06cbafe39e9fb1c6882b790c294d19b1d61c7504a5f' .
                    '3a916bd4266334e7f1557a3ab0ae114b0068a8c10',
            ],
        ],
        'remote_image.jpg' => self::TEST_IMAGE,
    ];

    /**
     * A mock webserver for some remote download tests.
     *
     * @var \donatj\MockWebServer\MockWebServer
     */
    private static $webserver;

    /**
     * Array of file contents for use with comparing against requests against the same index in self::$remote_urls
     *
     * @var array
     */
    private static $response_content = [];

    /**
     * Array of mock urls to get responses from. Match response bodies against matching key in self::$response_content
     *
     * @var array
     */
    private static $remote_urls = [];

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$webserver = new MockWebServer();
        self::$webserver->start();
        $counter = 0;
        foreach (self::WEBSERVER_FILES as $file) {
            self::$response_content[$counter] = file_get_contents($file['filename']);
            self::$remote_urls[$counter] = self::$webserver->setResponseOfPath(
                "/example/" . basename($file['filename']),
                new Response(
                    self::$response_content[$counter],
                    [ 'Cache-Control' => 'no-cache', 'Content-Length' => stat($file['filename'])[7]],
                    200
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

    /**
     * Utility to make a bag with a specific fetch file and return the fetch.
     * @param string $fetchFile
     *   The name of the file in the FETCH_FILES directory.
     * @return \whikloj\BagItTools\Fetch
     *   The Fetch object.
     * @throws \whikloj\BagItTools\Exceptions\BagItException
     *   If we can't read the fetch.txt
     */
    private function setupBag(string $fetchFile): Fetch
    {
        $bag = Bag::create($this->tmpdir);
        copy(
            self::FETCH_FILES . DIRECTORY_SEPARATOR . $fetchFile,
            $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'fetch.txt'
        );
        return new Fetch($bag, true);
    }

    /**
     * Test destinations that resolve outside the data directory.
     * @group Fetch
     * @covers ::__construct
     * @covers ::loadFiles
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @covers ::validateData
     */
    public function testDestinationOutsideData(): void
    {
        $fetch = $this->setupBag('path-outside-data.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->downloadAll();
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test destinations that have incorrect character encoded lines.
     * @group Fetch
     * @covers ::__construct
     * @covers ::loadFiles
     * @covers ::downloadAll
     */
    public function testDestinationOtherEncodedCharacters(): void
    {
        $fetch = $this->setupBag('bad-encoding.txt');
        $this->assertCount(1, $fetch->getErrors());
        $this->expectException(BagItException::class);
        $fetch->downloadAll();
    }

    /**
     * @group Fetch
     * @covers ::loadFiles
     */
    public function testFetchFileCorrectlyEncodedCharacters(): void
    {
        $fetch = $this->setupBag('good-encoded-file-paths.txt');
        $this->assertCount(0, $fetch->getErrors());
    }

    /**
     * Test if the fetch.txt url does not have a http or https scheme.
     * @group Fetch
     * @covers ::loadFiles
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @covers ::validateData
     * @covers ::internalValidateUrl
     */
    public function testNotHttpUrl(): void
    {
        $fetch = $this->setupBag('not-http-urls.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->downloadAll();
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test if there are alphabetic characters in the file size position of the fetch.txt
     * @group Fetch
     * @covers ::loadFiles
     */
    public function testLettersInSize(): void
    {
        $fetch = $this->setupBag('letters-in-size.txt');
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test adding a new fetch file and that it does download the file to update the payload manifest.
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetchFile
     * @covers ::download
     * @covers ::update
     */
    public function testAddFetchFile(): void
    {
        $file_one_dest = 'data/dir1/dir2/first_text.txt';
        $bag = Bag::create($this->tmpdir);
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
        $bag->addFetchFile(self::$remote_urls[0], $file_one_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertArrayNotHasKey($file_one_dest, $manifest->getHashes());
        $bag->update();
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertArrayHasKey($file_one_dest, $manifest->getHashes());
        $contents = file_get_contents($bag->makeAbsolute($file_one_dest));
        $this->assertEquals(self::$response_content[0], $contents);
    }

    /**
     * Test adding a new fetch file twice with the same url.
     *
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetchFile
     * @covers ::addFile
     * @covers ::download
     * @covers ::urlExistsInFile
     */
    public function testAddFetchUrlTwice(): void
    {
        $file_one_dest = 'data/dir1/dir2/first_text.txt';
        $file_two_dest = 'data/dir1/dir2/second_text.txt';
        $bag = Bag::create($this->tmpdir);
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
        $bag->addFetchFile(self::$remote_urls[0], $file_one_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("This URL (" . self::$remote_urls[0] . ") is already in fetch.txt");

        $bag->addFetchFile(self::$remote_urls[0], $file_two_dest);
    }

    /**
     * Test adding a new fetch file twice with the same destination.
     *
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetchFile
     * @covers ::addFile
     * @covers ::download
     * @covers ::destinationExistsInFile
     */
    public function testAddFetchDestTwice(): void
    {
        $destination = 'data/specialplace.txt';

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("This destination ($destination) is already in the fetch.txt");

        $bag = Bag::create($this->tmpdir);
        $bag->addFetchFile(self::$remote_urls[0], $destination);
        $bag->addFetchFile(self::$remote_urls[1], $destination);
    }

    /**
     * Ensure you can't add a fetch file with the same destination as an existing file.
     *
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetchFile
     * @covers ::addFile
     * @covers ::download
     * @covers ::createCurl
     */
    public function testDownloadToExistingPath(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addFile(self::TEST_IMAGE['filename'], 'pretty.jpg');
        $this->assertFileExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'pretty.jpg');

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("File already exists at the destination path data/pretty.jpg");

        $bag->addFetchFile(self::$remote_urls[0], 'pretty.jpg');
    }

    /**
     * Test you can't add a file to the bag where a fetch file will eventually exist.
     *
     * @group Fetch
     * @covers ::reservedPath
     * @covers \whikloj\BagItTools\Bag::addFile
     */
    public function testAddBagFileWithDestOfFetchFile(): void
    {
        $destination = "data/myplace.txt";
        $bag = Bag::create($this->tmpdir);
        $bag->addFetchFile(self::$remote_urls[0], $destination);

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("The path ($destination) is used in the fetch.txt file.");

        $bag->addFile(self::TEST_IMAGE['filename'], $destination);
    }

    /**
     * Test removing a fetch file.
     *
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetchFile
     * @covers \whikloj\BagItTools\Bag::removeFetchFile
     * @covers \whikloj\BagItTools\Bag::listFetchFiles
     * @covers ::addFile
     * @covers ::download
     * @covers ::removeFile
     * @covers ::getData
     */
    public function testRemoveFetchFile(): void
    {
        $file_one_dest = 'data/dir1/dir2/first_text.txt';
        $file_two_dest = 'data/dir1/dir2/second_text.txt';
        $expected_with_both = [
            [
                'uri' => self::$remote_urls[0],
                'size' => '-',
                'destination' => $file_one_dest,
            ],
            [
                'uri' => self::$remote_urls[1],
                'size' => '-',
                'destination' => $file_two_dest,
            ],
        ];
        $expected_with_one = [
            [
                'uri' => self::$remote_urls[1],
                'size' => '-',
                'destination' => $file_two_dest,
            ],
        ];
        $bag = Bag::create($this->tmpdir);
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_two_dest));
        $bag->addFetchFile(self::$remote_urls[0], $file_one_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $bag->addFetchFile(self::$remote_urls[1], $file_two_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $this->assertEquals($expected_with_both, $bag->listFetchFiles());
        // Url doesn't exist, nothing happens
        $bag->removeFetchFile('http://example.org/not/real');
        // Now really remove it.
        $bag->removeFetchFile(self::$remote_urls[0]);
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
        $this->assertEquals($expected_with_one, $bag->listFetchFiles());
    }

    /**
     * Test removing all fetch files.
     *
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetchFile
     * @covers \whikloj\BagItTools\Bag::clearFetch
     * @covers \whikloj\BagItTools\Bag::listFetchFiles
     * @covers ::addFile
     * @covers ::download
     * @covers ::clearData
     * @covers ::cleanup
     * @covers ::getData
     */
    public function testClearFetch(): void
    {
        $file_one_dest = 'data/dir1/dir2/first_text.txt';
        $file_two_dest = 'data/dir1/dir2/second_text.txt';
        $expected_with_both = [
            [
                'uri' => self::$remote_urls[0],
                'size' => '-',
                'destination' => $file_one_dest,
            ],
            [
                'uri' => self::$remote_urls[1],
                'size' => '-',
                'destination' => $file_two_dest,
            ],
        ];
        $bag = Bag::create($this->tmpdir);
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_two_dest));
        $bag->addFetchFile(self::$remote_urls[0], $file_one_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $bag->addFetchFile(self::$remote_urls[1], $file_two_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $this->assertEquals($expected_with_both, $bag->listFetchFiles());
        $bag->clearFetch();
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_two_dest));
        $this->assertEquals([], $bag->listFetchFiles());
    }

    /**
     * @covers ::cleanup
     * @covers ::clearData
     * @covers ::update
     * @covers ::writeToDisk

     */
    public function testClearFetchFile(): void
    {
        $file_one_dest = 'data/dir1/dir2/first_text.txt';
        $expected_with_both = [
            [
                'uri' => self::$remote_urls[0],
                'size' => '-',
                'destination' => $file_one_dest,
            ],
        ];
        $bag = Bag::create($this->tmpdir);
        $bag->addFetchFile(self::$remote_urls[0], $file_one_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $this->assertEquals($expected_with_both, $bag->listFetchFiles());
        $bag->update();
        $this->assertFileExists($bag->makeAbsolute("fetch.txt"));
        $bag->clearFetch();
        $this->assertFileDoesNotExist($bag->makeAbsolute("fetch.txt"));
        $this->assertFileDoesNotExist($bag->makeAbsolute($file_one_dest));
    }

    /**
     * Test downloading multiple files at once defined in the fetch.txt
     * @group Fetch
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @covers ::createMultiCurl
     * @covers ::saveFileData
     * @covers \whikloj\BagItTools\Bag::finalize
     * @covers \whikloj\BagItTools\Bag::loadFetch
     */
    public function testMultipleDownloadsSuccess(): void
    {
        $hashes = [];
        $destinations = [
            'data/dir1/dir2/first.txt',
            'data/dir1/dir3/dir4/second.txt',
            'dir1/third.txt',
            'dir1/dir2/../dir3/fourth.txt',
            'dir4/image.jpg',
        ];
        $count = 0;
        foreach (self::WEBSERVER_FILES as $file) {
            $hashes[] = $file['checksums']['sha512'] . " " .  BagUtils::baseInData($destinations[$count]);
            $count += 1;
        }

        $fetch_content = "";
        for ($foo = 0; $foo < count(self::$remote_urls); $foo += 1) {
            $fetch_content .= sprintf("%s - %s" . PHP_EOL, self::$remote_urls[$foo], $destinations[$foo]);
        }
        $manifest_content = implode(PHP_EOL, $hashes);

        $bag = Bag::create($this->tmpdir);
        file_put_contents($bag->makeAbsolute('fetch.txt'), $fetch_content);
        file_put_contents($bag->makeAbsolute('manifest-sha512.txt'), $manifest_content);
        $newbag = Bag::load($this->tmpdir);
        $this->assertCount(0, $newbag->getErrors());
        $manifest = $newbag->getPayloadManifests()['sha512'];
        $hashes = $manifest->getHashes();
        foreach ($destinations as $dest) {
            $dest = BagUtils::getAbsolute(BagUtils::baseInData($dest));
            $this->assertArrayHasKey($dest, $hashes);
            $this->assertFileDoesNotExist($newbag->makeAbsolute($dest));
        }
        $this->assertTrue($newbag->validate());
        foreach ($destinations as $dest) {
            $dest = BagUtils::getAbsolute(BagUtils::baseInData($dest));
            $this->assertArrayHasKey($dest, $hashes);
            $this->assertFileExists($newbag->makeAbsolute($dest));
        }
        // Remove the downloaded files so we can package the Bag.
        $newbag->finalize();
        foreach ($destinations as $dest) {
            $dest = BagUtils::getAbsolute(BagUtils::baseInData($dest));
            $this->assertArrayHasKey($dest, $hashes);
            $this->assertFileDoesNotExist($newbag->makeAbsolute($dest));
        }
    }

    /**
     * Test exception when adding a file we can't access.
     * @group Fetch
     * @covers ::download
     */
    public function testRemoteFailure(): void
    {
        $url = self::$webserver->setResponseOfPath(
            '/example/failure',
            new Response('', [], 500)
        );
        $bag = Bag::create($this->tmpdir);

        $this->expectException(BagItException::class);
        $this->expectExceptionMessageMatches("~^Error with download of (.*?)/example/failure : The "
        . "requested URL returned error: 500~");

        $bag->addFetchFile($url, 'data/myfile.txt');
    }

    /**
     * Test multiple downloads where one fails and others succeed.
     * @group Fetch
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @covers ::createMultiCurl
     * @covers ::createCurl
     */
    public function testMultiDownloadPartialFailure(): void
    {
        $url = [
            self::$webserver->setResponseOfPath(
                '/example/failure',
                new Response('', [], 500)
            ),
        ];
        $local_urls = array_slice(self::$remote_urls, 0, 2);
        // Splice the error in between 2 requests that will succeed.
        array_splice($local_urls, 1, 0, $url);
        $hashes = [];
        $destinations = [
            'data/dir1/dir2/first.txt',
            'data/dir1/dir3/dir4/second.txt',
        ];
        for ($foo = 0; $foo < 2; $foo += 1) {
            $f_num = $foo + 1;
            $hashes[] = self::WEBSERVER_FILES["remote_file$f_num.txt"]['checksums']['sha512'] . " " .
                BagUtils::baseInData($destinations[$foo]);
        }
        array_splice($hashes, 1, 0, [
            hash('sha512', 'blah blah blah') . " " . BagUtils::baseInData('dir1/third.txt'),
        ]);
        array_splice($destinations, 1, 0, [
            'dir1/third.txt',
        ]);

        $fetch_content = "";
        for ($foo = 0; $foo < count($local_urls); $foo += 1) {
            $fetch_content .= sprintf(
                "%s - %s" . PHP_EOL,
                $local_urls[$foo],
                BagUtils::baseInData($destinations[$foo])
            );
        }
        $manifest_content = implode(PHP_EOL, $hashes);

        $bag = Bag::create($this->tmpdir);
        file_put_contents($bag->makeAbsolute('fetch.txt'), $fetch_content);
        file_put_contents($bag->makeAbsolute('manifest-sha512.txt'), $manifest_content);
        $newbag = Bag::load($this->tmpdir);
        $this->assertCount(0, $newbag->getErrors());
        $hashes = $newbag->getPayloadManifests()['sha512']->getHashes();
        foreach ($destinations as $dest) {
            $dest = BagUtils::getAbsolute(BagUtils::baseInData($dest));
            $this->assertArrayHasKey($dest, $hashes);
            $this->assertFileDoesNotExist($newbag->makeAbsolute($dest));
        }
        $this->assertFalse($newbag->validate());
        $this->assertCount(1, $newbag->getErrors());
        $hashes = $newbag->getPayloadManifests()['sha512']->getHashes();
        for ($foo = 0; $foo < 2; $foo += 1) {
            $dest = $destinations[$foo];
            $dest = BagUtils::getAbsolute(BagUtils::baseInData($dest));
            $this->assertArrayHasKey($dest, $hashes);
            if ($foo == 1) {
                // First and third URLs succeed
                $this->assertFileDoesNotExist($newbag->makeAbsolute($dest));
            } else {
                $this->assertFileExists($newbag->makeAbsolute($dest));
            }
        }
    }

    /**
     * Test validate a URI without a scheme
     * @group Fetch
     * @covers ::validateUrl
     * @covers ::validateData
     * @throws \ReflectionException
     */
    public function testUriNoScheme(): void
    {
        $reflection = $this->getReflectionMethod('whikloj\BagItTools\Fetch', 'validateData');
        $bag = Bag::create($this->tmpdir);
        $fetch = new Fetch($bag);
        $good_data = [
            'uri' => 'http://example.org',
            'destination' => 'somewhere',
        ];
        $reflection->invokeArgs($fetch, [$good_data]);

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("URL somewhere.com does not seem to have a scheme or host");

        $bad_data = [
            'uri' => 'somewhere.com',
            'destination' => 'somewhere',
        ];
        $reflection->invokeArgs($fetch, [$bad_data]);
    }

    /**
     * Test validate a URI without a host
     * @group Fetch
     * @covers ::validateUrl
     * @covers ::validateData
     */
    public function testUriNoHost(): void
    {
        $reflection = $this->getReflectionMethod('whikloj\BagItTools\Fetch', 'validateData');
        $bag = Bag::create($this->tmpdir);
        $fetch = new Fetch($bag);

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("URL http:// does not seem to have a scheme or host");

        $data = [
            'uri' => 'http://',
            'destination' => 'somewhere',
        ];
        $reflection->invokeArgs($fetch, [$data]);
    }

    /**
     * Test validate a URI with a scheme we don't support.
     * @group Fetch
     * @covers ::validateData
     * @covers ::internalValidateUrl
     */
    public function testUriInvalidScheme(): void
    {
        $reflection = $this->getReflectionMethod('whikloj\BagItTools\Fetch', 'validateData');
        $bag = Bag::create($this->tmpdir);
        $fetch = new Fetch($bag);
        $good_data = [
            'uri' => 'http://somewhere.com',
            'destination' => 'somewhere',
        ];
        $reflection->invokeArgs($fetch, [$good_data]);

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("This library only supports http/https URLs");

        $bad_data = [
            'uri' => 'ftp://somewhere.com',
            'destination' => 'somewhere',
        ];
        $reflection->invokeArgs($fetch, [$bad_data]);
    }

    /**
     * @group Fetch
     * @covers ::createCurl
     * @covers ::createMultiCurl
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @covers ::curlXferInfo
     */
    public function testFetchTooLarge(): void
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        file_put_contents(
            $this->tmpdir . DIRECTORY_SEPARATOR . "fetch.txt",
            self::$remote_urls[4] . " 2 data/download1.jpg\n"
        );
        $bag = Bag::load($this->tmpdir);
        $this->assertFalse($bag->validate());
        $this->assertFileDoesNotExist($bag->makeAbsolute('data/download1.jpg'));
        $expected = [
            'file' => 'fetch.txt',
            'message' => "Failed to fetch URL (" . self::$remote_urls[4] . ") : Callback aborted",
        ];
        $this->assertEquals($expected, $bag->getErrors()[0]);
    }

    /**
     * Ensure fetch files are written to disk encoded, but kept in memory as un-encoded.
     * @group Fetch
     * @covers ::writeToDisk
     * @covers \whikloj\BagItTools\BagUtils::encodeFilepath
     */
    public function testCreateFetchWithEncodedCharacters(): void
    {
        $expected_on_disk = [
            "data/file-with%0Anewline.txt",
            "data/directory%0Dcarriage%0Dreturn/empty.txt",
            "data/image-with-%25-character.jpg",
            "data/already-encoded-%2525-double-it.txt",
            "data/directory%0Dcarriage%0Dreturn/directory%0Aline%0Abreak/image-with-%2525.jpg",
        ];
        $expected_in_memory = [
            "data/file-with\nnewline.txt",
            "data/directory\rcarriage\rreturn/empty.txt",
            "data/image-with-%-character.jpg",
            "data/already-encoded-%25-double-it.txt",
            "data/directory\rcarriage\rreturn/directory\nline\nbreak/image-with-%25.jpg",
        ];
        // Create a bag with fetch file destinations that are weird.
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        for ($foo = 0; $foo < count($expected_in_memory); $foo += 1) {
            $bag->addFetchFile(self::$remote_urls[$foo], $expected_in_memory[$foo]);
        }
        $bag->update();
        // Read the fetch.txt file from disk.
        $fetch = explode("\n", file_get_contents($bag->getBagRoot() . DIRECTORY_SEPARATOR . "fetch.txt"));
        $fetch = array_filter($fetch);
        array_walk($fetch, function (&$o) {
            $o = trim(explode(" ", $o)[2]);
        });
        // Verify the on disk fetch.txt file paths are as expected.
        $this->assertArrayEquals($expected_on_disk, $fetch);
        // Verify that the filepaths in memory remain decoded.
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertArrayEquals($expected_in_memory, array_keys($manifest->getHashes()));
    }

    public function testReadCRLineEndings(): void
    {
        $fetch = $this->setupBag('fetch-CR.txt');
        $this->assertCount(0, $fetch->getErrors());
        $cr_data = $fetch->getData();
        $this->assertCount(2, $cr_data);
        $this->assertTrue(in_array('http://example.org/some/file/1', array_column($cr_data, 'uri')));
        $this->assertTrue(in_array('http://example.org/some/file/2', array_column($cr_data, 'uri')));
    }

    public function testReadLFLineEndings(): void
    {
        $fetch = $this->setupBag('fetch-LF.txt');
        $this->assertCount(0, $fetch->getErrors());
        $cr_data = $fetch->getData();
        $this->assertCount(2, $cr_data);
        $this->assertTrue(in_array('http://example.org/some/file/1', array_column($cr_data, 'uri')));
        $this->assertTrue(in_array('http://example.org/some/file/2', array_column($cr_data, 'uri')));
    }

    public function testReadCRLFLineEndings(): void
    {
        $fetch = $this->setupBag('fetch-CRLF.txt');
        $this->assertCount(0, $fetch->getErrors());
        $cr_data = $fetch->getData();
        $this->assertCount(2, $cr_data);
        $this->assertTrue(in_array('http://example.org/some/file/1', array_column($cr_data, 'uri')));
        $this->assertTrue(in_array('http://example.org/some/file/2', array_column($cr_data, 'uri')));
    }
}
