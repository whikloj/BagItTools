<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Class BagItTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Bag
 */
class BagItTest extends BagItTestFramework
{

  /**
   * @group Bag
   * @covers ::__construct
   * @covers ::createNewBag
   * @covers ::updateBagIt
   */
    public function testConstructNewBag()
    {
        $this->assertFileNotExists($this->tmpdir);
        $bag = new Bag($this->tmpdir, true);
        $this->assertFileExists($this->tmpdir . DIRECTORY_SEPARATOR . "bagit.txt");
        $this->assertTrue(is_file($this->tmpdir . DIRECTORY_SEPARATOR . "bagit.txt"));
        $this->assertFileExists($this->tmpdir . DIRECTORY_SEPARATOR . "data");
        $this->assertTrue(is_dir($this->tmpdir . DIRECTORY_SEPARATOR . "data"));
    }

  /**
   * @group Bag
   * @covers ::__construct
   * @covers ::loadBag
   * @covers ::loadBagIt
   * @covers ::loadPayloadManifests
   * @covers ::loadBagInfo
   * @covers ::loadTagManifests
   * @covers ::isExtended
   */
    public function testOpenBag()
    {
        $tmpdir = $this->prepareTestBagDirectory();
        $bag = new Bag($tmpdir, false);
        $this->assertCount(0, $bag->getErrors());
        $this->assertArrayHasKey('sha256', $bag->getPayloadManifests());
        $this->assertFalse($bag->isExtended());
        $this->deleteDirAndContents($tmpdir);
    }

  /**
   * Test adding a file to a bag.
   * @group Bag
   * @covers ::addFile
   */
    public function testAddFile()
    {
        $source_file = self::TEST_IMAGE['filename'];
        $bag = new Bag($this->tmpdir, true);
        $bag->addFile($source_file, "some/image.txt");
        $this->assertDirectoryExists($bag->getDataDirectory() .
        DIRECTORY_SEPARATOR . 'some');
        $this->assertFileExists($bag->getDataDirectory() .
        DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'image.txt');
    }

  /**
   * Test adding a file that doesn't exist.
   * @group Bag
   * @covers ::addFile
   * @expectedException \whikloj\BagItTools\BagItException
   */
    public function testAddFileNoSource()
    {
        $source_file = "some/fake/image.txt";
        $bag = new Bag($this->tmpdir, true);
        $bag->addFile($source_file, "some/image.txt");
    }

  /**
   * Test adding a file with an invalid destination directory.
   * @group Bag
   * @covers ::addFile
   * @expectedException \whikloj\BagItTools\BagItException
   */
    public function testAddFileInvalidDestination()
    {
        $source_file = self::TEST_IMAGE['filename'];
        $bag = new Bag($this->tmpdir, true);
        $bag->addFile($source_file, "data/../../../images/places/image.jpg");
    }

    /**
     * Test removing a file from a bag.
     * @group Bag
     * @covers ::removeFile
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testRemoveFile()
    {
        $tmpdir = $this->prepareTestBagDirectory();
        $bag = new Bag($tmpdir, false);
        $this->assertFileExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'jekyll_and_hyde.txt');
        $bag->removeFile('jekyll_and_hyde.txt');
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'jekyll_and_hyde.txt');
        $this->deleteDirAndContents($tmpdir);
    }

    /**
     * Ensure empty directories are removed as files are removed.
     * @group Bag
     * @covers ::removeFile
     * @covers ::checkForEmptyDir
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testRemoveEmptyDirectories()
    {
        $tmpdir = $this->prepareTestBagDirectory();

        $picturesDir = implode(DIRECTORY_SEPARATOR, [
            $tmpdir,
            'data',
            'pictures',
        ]);

        $bag = new Bag($tmpdir, false);

        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'background-with-flower-and-butterfl.jpg');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'houses-of-parliament.jpg');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'tower-bridge-at-night.jpg');

        // Don't reference the correct path.
        $bag->removeFile('houses-of-parliament.jpg');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'houses-of-parliament.jpg');

        // Reference with data/ prefix
        $bag->removeFile('data/pictures/houses-of-parliament.jpg');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'houses-of-parliament.jpg');

        // Reference without data/ prefix
        $bag->removeFile('pictures/tower-bridge-at-night.jpg');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'tower-bridge-at-night.jpg');

        $bag->removeFile('pictures/background-with-flower-and-butterfl.jpg');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'background-with-flower-and-butterfl.jpg');

        // All files are gone so directory data/pictures should have been removed.
        $this->assertDirectoryNotExists($picturesDir);

        $this->deleteDirAndContents($tmpdir);
    }
}
