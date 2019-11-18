<?php

namespace whikloj\BagItTools\Test;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
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
}
