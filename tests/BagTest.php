<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Class BagItTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Bag
 */
class BagTest extends BagItTestFramework
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
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = new Bag($this->tmpdir, false);
        $this->assertCount(0, $bag->getErrors());
        $this->assertArrayHasKey('sha256', $bag->getPayloadManifests());
        $this->assertFalse($bag->isExtended());
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
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = new Bag($this->tmpdir, false);
        $this->assertFileExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'jekyll_and_hyde.txt');
        $bag->removeFile('jekyll_and_hyde.txt');
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'jekyll_and_hyde.txt');
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
        $this->tmpdir = $this->prepareBasicTestBag();

        $picturesDir = implode(DIRECTORY_SEPARATOR, [
            $this->tmpdir,
            'data',
            'pictures',
        ]);

        $bag = new Bag($this->tmpdir, false);

        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'background-with-flower-and-butterfl.jpg');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'tower-bridge-at-night.jpg');

        // Don't reference the correct path.
        $bag->removeFile('tower-bridge-at-night.jpg');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'tower-bridge-at-night.jpg');

        // Reference with data/ prefix
        $bag->removeFile('data/pictures/tower-bridge-at-night.jpg');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'tower-bridge-at-night.jpg');

        // Reference without data/ prefix
        $bag->removeFile('pictures/background-with-flower-and-butterfl.jpg');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'background-with-flower-and-butterfl.jpg');

        // All files are gone so directory data/pictures should have been removed.
        $this->assertDirectoryNotExists($picturesDir);
    }

    /**
     * Ensure a directory is not removed if there are hidden files inside it.
     * @group Bag
     * @covers ::removeFile
     * @covers ::checkForEmptyDir
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testKeepDirectoryWithHiddenFile()
    {
        $this->tmpdir = $this->prepareBasicTestBag();

        $bag = new Bag($this->tmpdir, false);
        // Directory doesn't exist.
        $this->assertDirectoryNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'empty');
        // Add files.
        $bag->addFile(self::TEST_IMAGE['filename'], 'data/empty/test.jpg');
        $bag->addFile(self::TEST_TEXT['filename'], 'data/empty/.hidden');
        // Directory does exist.
        $this->assertDirectoryExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'empty');
        // Remove the image but leave a hidden file.
        $bag->removeFile('data/empty/test.jpg');
        // Directory does exist.
        $this->assertDirectoryExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'empty');
        // Remove the hidden file.
        $bag->removeFile('data/empty/.hidden');
        // Directory is removed too.
        $this->assertDirectoryNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'empty');
    }

    /**
     * Test that changes made outside the API still are noticed.
     * @group Bag
     * @covers ::update
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testUpdateOnDisk()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = new Bag($this->tmpdir, true);
        $manifest = $bag->getPayloadManifests()['sha512'];
        // File doesn't exist.
        $this->assertArrayNotHasKey('data/land.jpg', $manifest->getHashes());
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');

        // Add the file
        $bag->addFile(self::TEST_IMAGE['filename'], 'data/land.jpg');
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertArrayNotHasKey('data/land.jpg', $manifest->getHashes());
        $this->assertFileExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');
        // Update
        $bag->update();
        $manifest = $bag->getPayloadManifests()['sha512'];
        $this->assertArrayHasKey('data/land.jpg', $manifest->getHashes());

        // Remove it manually.
        unlink($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');
        $manifest = $bag->getPayloadManifests()['sha512'];
        // File is gone
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');
        // Still exists in the manifest.
        $this->assertArrayHasKey('data/land.jpg', $manifest->getHashes());
        // Update BagIt files on disk.
        $bag->update();
        $manifest = $bag->getPayloadManifests()['sha512'];
        // Gone from the payload manifest.
        $this->assertArrayNotHasKey('data/land.jpg', $manifest->getHashes());
    }

}
