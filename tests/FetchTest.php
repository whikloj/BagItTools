<?php

namespace whikloj\BagItTools\Test;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use whikloj\BagItTools\Bag;
use whikloj\BagItTools\BagUtils;
use whikloj\BagItTools\Fetch;

/**
 * Class FetchTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Fetch
 */
class FetchTest extends BagItTestFramework
{

    const FETCH_FILES = self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'fetchFiles';

    const WEBSERVER_FILES_DIR = self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'webserver_responses';

    const WEBSERVER_FILES = [
        'remote_file1.txt' => [
            'sha512' => 'fd7c6f2a22f5dffac90c4483c9d623206a237a523b8e5a6f291ac0678fb6a3b5d68bb09a779c1809a15d8ef8c7d4' .
                'e16a6d18d50c9b7f9639fd0d8fcf2b7ef46a',
        ],
        'remote_file2.txt' => [
            'sha512' => '29ad87ff27417de3e1526517e1b8583034c9f3a47e3c1f9ff216025229f9a04c85e8bdd5551d8df6838e46271732' .
                'b98400170f8fd246d47de9312df2bdde3ca9',
        ],
        'remote_file3.txt' => [
            'sha512' => '3dccc8db74e74ba8f0d926987e6daf93f78d9d344a0babfaac5d64dd614215c5358014c830706be5f00c920a9ce2' .
                'fec0949fababfa65f3c6b7de8a3c27ac6f96',
        ],
        'remote_file4.txt' => [
            'sha512' => '6b8c5673861b4578c441cd2fe5af209d6684abdfbaea06cbafe39e9fb1c6882b790c294d19b1d61c7504a5f3a916' .
                'bd4266334e7f1557a3ab0ae114b0068a8c10',
        ],
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
    public static function setUpBeforeClass()
    {
        self::$webserver = new MockWebServer();
        self::$webserver->start();
        for ($foo = 0; $foo < 4; $foo += 1) {
            $f_num = $foo + 1;
            self::$response_content[$foo] = file_get_contents(self::WEBSERVER_FILES_DIR . DIRECTORY_SEPARATOR .
                "remote_file{$f_num}.txt");
            self::$remote_urls[$foo] = self::$webserver->setResponseOfPath(
                "/example/remote_file{$f_num}.txt",
                new Response(
                    self::$response_content[$foo],
                    [ 'Cache-Control' => 'no-cache' ],
                    200
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass()
    {
        self::$webserver->stop();
    }

    /**
     * Utility to make a bag with a specific fetch file and return the fetch.
     * @param string $fetchFile
     *   The name of the file in the FETCH_FILES directory.
     * @return \whikloj\BagItTools\Fetch
     *   The Fetch object.
     * @throws \whikloj\BagItTools\BagItException
     *   If we can't read the fetch.txt
     */
    private function setupBag($fetchFile)
    {
        $bag = Bag::create($this->tmpdir);
        copy(
            self::FETCH_FILES . DIRECTORY_SEPARATOR . $fetchFile,
            $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'fetch.txt'
        );
        $fetch = new Fetch($bag, true);
        return $fetch;
    }

    /**
     * Test destinations that resolve outside the data directory.
     * @group Fetch
     * @covers ::loadFiles
     * @covers ::downloadAll
     * @covers  ::downloadFiles
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testDestinationOutsideData()
    {
        $fetch = $this->setupBag('path-outside-data.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->downloadAll();
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test destinations that have percent encoded characters other than
     * @group Fetch
     * @covers ::loadFiles
     * @covers ::downloadAll
     * @covers  ::downloadFiles
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testDestinationOtherEncodedCharacters()
    {
        $fetch = $this->setupBag('other-encoded-characters.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->downloadAll();
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test if the fetch.txt url does not have a http or https scheme.
     * @group Fetch
     * @covers ::loadFiles
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testNotHttpUrl()
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
    public function testLettersInSize()
    {
        $fetch = $this->setupBag('letters-in-size.txt');
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test adding a new fetch file and that it does download the file to update the payload manifest.
     * @group Fetch
     * @covers \whikloj\BagItTools\Bag::addFetch
     * @covers ::download
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testAddFetch()
    {
        $file_one_dest = 'data/dir1/dir2/first_text.txt';
        $bag = Bag::create($this->tmpdir);
        $this->assertFileNotExists($bag->makeAbsolute($file_one_dest));
        $bag->addFetch(self::$remote_urls[0], $file_one_dest);
        $this->assertFileExists($bag->makeAbsolute($file_one_dest));
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertFalse(array_key_exists($file_one_dest, $manifest->getHashes()));
        $bag->update();
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertTrue(array_key_exists($file_one_dest, $manifest->getHashes()));
        $contents = file_get_contents($bag->makeAbsolute($file_one_dest));
        $this->assertEquals(self::$response_content[0], $contents);
    }

    /**
     * Test downloading multiple files at once defined in the fetch.txt
     * @group Fetch
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @covers ::saveFileData
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testMultipleDownloadsSuccess()
    {
        $hashes = [];
        $destinations = [
            'data/dir1/dir2/first.txt',
            'data/dir1/dir3/dir4/second.txt',
            'dir1/third.txt',
            'dir1/dir2/../dir3/fourth.txt',
        ];
        for ($foo = 0; $foo < 4; $foo += 1) {
            $f_num = $foo + 1;
            $hashes[] = self::WEBSERVER_FILES["remote_file{$f_num}.txt"]['sha512'] . " " .
                BagUtils::baseInData($destinations[$foo]);
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
            $this->assertFileNotExists($newbag->makeAbsolute($dest));
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
            $this->assertFileNotExists($newbag->makeAbsolute($dest));
        }
    }

    /**
     * Test exception when adding a file we can't access.
     * @group Fetch
     * @covers ::download
     * @expectedException \whikloj\BagItTools\BagItException
     */
    public function testRemoteFailure()
    {
        $url = self::$webserver->setResponseOfPath(
            '/example/failure',
            new Response('', [], 500)
        );
        $bag = Bag::create($this->tmpdir);
        $bag->addFetch($url, 'data/myfile.txt');
    }

    /**
     * Test multiple downloads where one fails and others succeed.
     * @group Fetch
     * @covers ::downloadAll
     * @covers ::downloadFiles
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testMultiDownloadPartialFailure()
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
            $hashes[] = self::WEBSERVER_FILES["remote_file{$f_num}.txt"]['sha512'] . " " .
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
            $this->assertFileNotExists($newbag->makeAbsolute($dest));
        }
        $this->assertFalse($newbag->validate());
        $this->assertCount(1, $newbag->getErrors());
        $hashes = $newbag->getPayloadManifests()['sha512']->getHashes();
        for ($foo = 0; $foo < 2; $foo += 1) {
            $dest = $destinations[$foo];
            $dest = BagUtils::getAbsolute(BagUtils::baseInData($dest));
            $this->assertArrayHasKey($dest, $hashes);
            if ($foo == 0 || $foo == 2) {
                // First and third URLs succeed
                $this->assertFileExists($newbag->makeAbsolute($dest));
            } else {
                $this->assertFileNotExists($newbag->makeAbsolute($dest));
            }
        }
    }
}
