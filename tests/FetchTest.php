<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Fetch;

/**
 * Class FetchTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Fetch
 */
class FetchTest extends BagItTestFramework
{

    private const FETCH_FILES = self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'fetchFiles';

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
        copy(self::FETCH_FILES . DIRECTORY_SEPARATOR . $fetchFile,
            $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'fetch.txt');
        $fetch = new Fetch($bag, true);
        return $fetch;
    }

    /**
     * Test destinations that resolve outside the data directory.
     * @covers ::loadFiles
     * @covers ::download
     * @covers  ::downloadFiles
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testDestinationOutsideData()
    {
        $fetch = $this->setupBag('path-outside-data.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->download();
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test destinations that have percent encoded characters other than
     * @covers ::loadFiles
     * @covers ::download
     * @covers  ::downloadFiles
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testDestinationOtherEncodedCharacters()
    {
        $fetch = $this->setupBag('other-encoded-characters.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->download();
        $this->assertCount(1, $fetch->getErrors());
    }

    public function testNotHttpUrl()
    {
        $fetch = $this->setupBag('not-http-urls.txt');
        $this->assertCount(0, $fetch->getErrors());
        $fetch->download();
        $this->assertCount(1, $fetch->getErrors());
    }

    /**
     * Test
     */
    public function testLettersInSize()
    {
        $fetch = $this->setupBag('letters-in-size.txt');
        $this->assertCount(1, $fetch->getErrors());
    }
}