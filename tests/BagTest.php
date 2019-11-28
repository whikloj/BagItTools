<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\BagItException;
use whikloj\BagItTools\BagUtils;

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
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testConstructNewBag()
    {
        $this->assertFileNotExists($this->tmpdir);
        $bag = Bag::create($this->tmpdir);
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
   * @covers ::validate
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testOpenBag()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertCount(0, $bag->getErrors());
        $this->assertArrayHasKey('sha256', $bag->getPayloadManifests());
        $this->assertFalse($bag->isExtended());
        $this->assertTrue($bag->validate());
        $this->assertCount(0, $bag->getErrors());
        $this->assertCount(0, $bag->getWarnings());
    }

  /**
   * Test adding a file to a bag.
   * @group Bag
   * @covers ::addFile
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testAddFile()
    {
        $source_file = self::TEST_IMAGE['filename'];
        $bag = Bag::create($this->tmpdir);
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
        $bag = Bag::create($this->tmpdir);
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
        $bag = Bag::create($this->tmpdir);
        $bag->addFile($source_file, "data/../../../images/places/image.jpg");
    }

    /**
     * Test adding a file to a bag twice.
     * @group Bag
     * @covers ::addFile
     * @expectedException  \whikloj\BagItTools\BagItException
     */
    public function testAddFileTwice()
    {
        $source_file = self::TEST_IMAGE['filename'];
        $bag = Bag::create($this->tmpdir);
        $bag->addFile($source_file, "some/image.txt");
        $this->assertDirectoryExists($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some');
        $this->assertFileExists($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'image.txt');
        $bag->addFile($source_file, "some/image.txt");
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
        $bag = Bag::load($this->tmpdir);
        $this->assertFileExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'jekyll_and_hyde.txt');
        $bag->removeFile('jekyll_and_hyde.txt');
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'jekyll_and_hyde.txt');
    }

    /**
     * Test adding a string to a bag.
     * @group Bag
     * @covers ::addFile
     * @covers ::createFile
     * @throws  \whikloj\BagItTools\BagItException
     */
    public function testCreateFile()
    {
        $source = "Hi this is a test";
        $bag = Bag::create($this->tmpdir);
        $this->assertDirectoryNotExists($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some');
        $this->assertFileNotExists($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'text.txt');
        $bag->createFile($source, "some/text.txt");
        $this->assertDirectoryExists($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some');
        $this->assertFileExists($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'text.txt');
        $contents = file_get_contents($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'text.txt');
        $this->assertEquals($source, $contents);
    }

    /**
     * Test adding a string to a bag twice.
     * @group Bag
     * @covers ::addFile
     * @covers ::createFile
     * @expectedException  \whikloj\BagItTools\BagItException
     */
    public function testCreateFileTwice()
    {
        $source = "Hi this is a test";
        $bag = Bag::create($this->tmpdir);
        $bag->createFile($source, "some/text.txt");
        $contents = file_get_contents($bag->getDataDirectory() .
            DIRECTORY_SEPARATOR . 'some' . DIRECTORY_SEPARATOR . 'text.txt');
        $this->assertEquals($source, $contents);
        $source_two = "This is new stuff";
        $bag->createFile($source_two, "some/text.txt");
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

        $bag = Bag::load($this->tmpdir);

        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'another_picture.txt');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'some_more_data.txt');

        // Don't reference the correct path.
        $bag->removeFile('some_more_data.txt');
        $this->assertFileExists($picturesDir . DIRECTORY_SEPARATOR . 'some_more_data.txt');

        // Reference with data/ prefix
        $bag->removeFile('data/pictures/some_more_data.txt');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'some_more_data.txt');

        // Reference without data/ prefix
        $bag->removeFile('pictures/another_picture.txt');
        $this->assertFileNotExists($picturesDir . DIRECTORY_SEPARATOR . 'another_picture.txt');

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

        $bag = Bag::load($this->tmpdir);
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
        $bag = Bag::load($this->tmpdir);
        $manifest = $bag->getPayloadManifests()['sha256'];
        // File doesn't exist.
        $this->assertArrayNotHasKey('data/land.jpg', $manifest->getHashes());
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');

        // Add the file
        $bag->addFile(self::TEST_IMAGE['filename'], 'data/land.jpg');
        $manifest = $bag->getPayloadManifests()['sha256'];
        $this->assertArrayNotHasKey('data/land.jpg', $manifest->getHashes());
        $this->assertFileExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');
        // Update
        $bag->update();
        $manifest = $bag->getPayloadManifests()['sha256'];
        $this->assertArrayHasKey('data/land.jpg', $manifest->getHashes());

        // Remove it manually.
        unlink($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');
        $manifest = $bag->getPayloadManifests()['sha256'];
        // File is gone
        $this->assertFileNotExists($bag->getDataDirectory() . DIRECTORY_SEPARATOR . 'land.jpg');
        // Still exists in the manifest.
        $this->assertArrayHasKey('data/land.jpg', $manifest->getHashes());
        // Update BagIt files on disk.
        $bag->update();
        $manifest = $bag->getPayloadManifests()['sha256'];
        // Gone from the payload manifest.
        $this->assertArrayNotHasKey('data/land.jpg', $manifest->getHashes());
    }

    /**
     * Test setting the file encoding.
     * @group Bag
     * @covers ::setFileEncoding
     * @covers \whikloj\BagItTools\BagUtils::getValidCharset
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testSetFileEncodingSuccess()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertEquals('UTF-8', $bag->getFileEncoding());

        $bag->setFileEncoding('ISO-8859-1');
        $this->assertEquals('ISO-8859-1', $bag->getFileEncoding());
        $bag->update();
        $this->assertBagItFileEncoding($bag, 'ISO-8859-1');

        $bag->setFileEncoding('US-ASCII');
        $this->assertEquals('US-ASCII', $bag->getFileEncoding());
        $bag->update();
        $this->assertBagItFileEncoding($bag, 'US-ASCII');

        // Also assert that case is not relevant
        $bag->setFileEncoding('EUC-jp');
        $this->assertEquals('EUC-JP', $bag->getFileEncoding());
        $bag->update();
        $this->assertBagItFileEncoding($bag, 'EUC-JP');
    }

    /**
     * Test exception for invalid character set.
     * @group Bag
     * @covers ::setFileEncoding
     * @covers \whikloj\BagItTools\BagUtils::getValidCharset
     * @expectedException \whikloj\BagItTools\BagItException
     */
    public function testSetFileEncodingFailure()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertEquals('UTF-8', $bag->getFileEncoding());

        $bag->setFileEncoding('gb2312');
        $this->assertEquals('GB2312', $bag->getFileEncoding());
        $bag->update();
        $this->assertBagItFileEncoding($bag, 'GB2312');

        // Now try a wrong encoding.
        $bag->setFileEncoding('fake-encoding');
    }

    /**
     * Test getting, adding and removing valid algorithms using internal names.
     *
     * @group Bag
     * @covers ::addAlgorithm
     * @covers ::removeAlgorithm
     * @covers ::getAlgorithms
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testGetHashesNames()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        // Set one
        $bag->addAlgorithm('sha1');
        $this->assertArrayEquals(['sha512', 'sha1'], $bag->getAlgorithms());
        // Set again
        $bag->addAlgorithm('sha1');
        $this->assertArrayEquals(['sha512', 'sha1'], $bag->getAlgorithms());
        // Set a third
        $bag->addAlgorithm('md5');
        $this->assertArrayEquals(['md5', 'sha512', 'sha1'], $bag->getAlgorithms());
        // Remove one
        $bag->removeAlgorithm('sha512');
        $this->assertArrayEquals(['md5', 'sha1'], $bag->getAlgorithms());
    }

    /**
     * Test getting, adding and removing valid algorithms using common names.
     *
     * @group Bag
     * @covers ::addAlgorithm
     * @covers ::removePayloadManifest
     * @covers ::removeAlgorithm
     * @covers ::getAlgorithms
     * @covers ::hasAlgorithm
     * @covers ::hasHash
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testGetHashesCommon()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        // Set one
        $bag->addAlgorithm('SHA1');
        $this->assertArrayEquals(['sha512', 'sha1'], $bag->getAlgorithms());
        // Remove it
        $bag->removeAlgorithm('SHA1');
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        // Set again differently
        $bag->addAlgorithm('SHA-1');
        $this->assertTrue($bag->hasAlgorithm('sha1'));
        $this->assertTrue($bag->hasAlgorithm('sha-1'));
        $this->assertTrue($bag->hasAlgorithm('SHA1'));
        $this->assertTrue($bag->hasAlgorithm('SHA-1'));
        $this->assertArrayEquals(['sha512', 'sha1'], $bag->getAlgorithms());
        // Set a third
        $bag->addAlgorithm('SHA-224');
        $this->assertArrayEquals(['sha224', 'sha512', 'sha1'], $bag->getAlgorithms());
        // Remove one
        $bag->removeAlgorithm('SHA-512');
        $this->assertArrayEquals(['sha224', 'sha1'], $bag->getAlgorithms());
        // Remove one not set.
        $bag->removeAlgorithm('sha512');
        $this->assertArrayEquals(['sha224', 'sha1'], $bag->getAlgorithms());
        // Really remove it
        $bag->removeAlgorithm('sha224');
        $this->assertArrayEquals(['sha1'], $bag->getAlgorithms());
    }

    /**
     * Try to remove the last algorithm.
     *
     * @group Bag
     * @covers ::removeAlgorithm
     * @expectedException \whikloj\BagItTools\BagItException
     */
    public function testRemoveLastHash()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        $bag->removeAlgorithm('SHA-512');
    }

    /**
     * Test
     * @group Bag
     * @covers ::algorithmIsSupported
     * @covers ::hashIsSupported
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testIsSupportedHash()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertTrue($bag->algorithmIsSupported('sha-1'));
        $this->assertFalse($bag->algorithmIsSupported('bob'));
    }

    /**
     * Test setting a specific algorithm.
     * @group Bag
     * @covers ::setAlgorithm
     * @covers ::removeAllPayloadManifests
     * @covers ::removePayloadManifest
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testSetAlgorithm()
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addAlgorithm('sha1');
        $bag->addAlgorithm('SHA-224');
        $this->assertArrayEquals(['sha512', 'sha1', 'sha224'], $bag->getAlgorithms());
        $bag->setAlgorithm('md5');
        $this->assertArrayEquals(['md5'], $bag->getAlgorithms());
    }

    /**
     * Test that Windows reserved names are rejected as filenames.
     * @group Bag
     * @covers ::addFile
     * @covers ::reservedFilename
     * @expectedException  \whikloj\BagItTools\BagItException
     */
    public function testUseReservedFilename()
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addFile(self::TEST_TEXT['filename'], 'data/some/directory/com1');
    }

    /**
     * Test writing a file to an absolute location outside data
     * @group Bag
     * @covers ::create
     * @covers ::addFile
     * @expectedExceptionCode  \whikloj\BagItTools\BagItException
     */
    public function testAddFileAbsolutePath()
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addFile(self::TEST_TEXT['filename'], '/var/cache/etc');
    }

    /**
     * Test getting a warning when validating an MD5 bag.
     * @group Bag
     * @covers ::validate
     * @covers \whikloj\BagItTools\AbstractManifest::loadFile
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testWarningOnMd5()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->validate());
        $this->assertCount(0, $bag->getWarnings());
        $bag->setAlgorithm('md5');
        $bag->update();
        $newBag = Bag::load($this->tmpdir);
        $this->assertTrue($newBag->validate());
        $this->assertCount(1, $newBag->getWarnings());
    }

    /**
     * Test opening a non-existant compressed file.
     * @group Bag
     * @covers ::load
     * @covers ::getExtensions
     * @covers ::isCompressed
     * @expectedException  \whikloj\BagItTools\BagItException
     */
    public function testNonExistantCompressed()
    {
        $bag = Bag::load('/my/directory.tar');
    }


    /**
     * Test opening a tar gzip
     * @group Bag
     * @covers ::load
     * @covers ::isCompressed
     * @covers ::uncompressBag
     * @covers ::getExtensions
     * @covers ::untarBag
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testUncompressTarGz()
    {
        $bag = Bag::load(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'testtar.tgz');
        $this->assertTrue($bag->validate());
        $this->assertTrue($bag->hasAlgorithm('sha224'));
        $manifest = $bag->getPayloadManifests()['sha224'];
        foreach ($manifest->getHashes() as $path => $hash) {
            $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . BagUtils::baseInData($path));
        }
        $this->assertNotEquals(
            self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'testtar.tgz',
            $bag->getBagRoot()
        );
    }

    /**
     * Test opening a tar bzip2.
     * @group Bag
     * @covers ::load
     * @covers ::isCompressed
     * @covers ::uncompressBag
     * @covers ::getExtensions
     * @covers ::untarBag
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testUncompressTarBzip()
    {
        $bag = Bag::load(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'testtar.tar.bz2');
        $this->assertTrue($bag->validate());
        $this->assertTrue($bag->hasAlgorithm('sha224'));
        $manifest = $bag->getPayloadManifests()['sha224'];
        foreach ($manifest->getHashes() as $path => $hash) {
            $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . BagUtils::baseInData($path));
        }
        $this->assertNotEquals(
            self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'testtar.tar.bz2',
            $bag->getBagRoot()
        );
    }

    /**
     * Test opening a zip file.
     * @group Bag
     * @covers ::isCompressed
     * @covers ::uncompressBag
     * @covers ::getExtensions
     * @covers ::unzipBag
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testUncompressZip()
    {
        $bag = Bag::load(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'testzip.zip');
        $this->assertTrue($bag->validate());
        $this->assertTrue($bag->hasAlgorithm('sha224'));
        $manifest = $bag->getPayloadManifests()['sha224'];
        foreach ($manifest->getHashes() as $path => $hash) {
            $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . BagUtils::baseInData($path));
        }
        $this->assertNotEquals(
            self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'testzip.zip',
            $bag->getBagRoot()
        );
    }

    /**
     * Test generating a zip.
     *
     * TODO: Re-extract and compare against source bag.
     * @group Bag
     * @covers ::package
     * @covers ::makePackage
     * @covers ::makeZip
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testZipBag()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $archivefile = $this->getTempName();
        $archivefile .= ".zip";
        $this->assertFileNotExists($archivefile);
        $bag->package($archivefile);
        $this->assertFileExists($archivefile);

        $newbag = Bag::load($archivefile);
        $this->assertTrue($newbag->validate());

        $this->assertEquals(
            $bag->getPayloadManifests()['sha256']->getHashes(),
            $newbag->getPayloadManifests()['sha256']->getHashes()
        );
    }

    /**
     * Test generating a tar.
     *
     * TODO: Re-extract and compare against source bag.
     * @group Bag
     * @covers ::package
     * @covers ::makePackage
     * @covers ::makeTar
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testTarBag()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $archivefile = $this->getTempName();
        $archivefile .= ".tar";
        $this->assertFileNotExists($archivefile);
        $bag->package($archivefile);
        $this->assertFileExists($archivefile);

        $newbag = Bag::load($archivefile);
        $this->assertTrue($newbag->validate());

        $this->assertEquals(
            $bag->getPayloadManifests()['sha256']->getHashes(),
            $newbag->getPayloadManifests()['sha256']->getHashes()
        );
    }

    /**
     * Test generating a tar.
     *
     * TODO: Re-extract and compare against source bag.
     * @group Bag
     * @covers ::package
     * @covers ::makePackage
     * @covers ::makeTar
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testTarGzBag()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $archivefile = $this->getTempName();
        $archivefile .= ".tar.gz";
        $this->assertFileNotExists($archivefile);
        $bag->package($archivefile);
        $this->assertFileExists($archivefile);

        $newbag = Bag::load($archivefile);
        $this->assertTrue($newbag->validate());

        $this->assertEquals(
            $bag->getPayloadManifests()['sha256']->getHashes(),
            $newbag->getPayloadManifests()['sha256']->getHashes()
        );
    }

    /**
     * Test generating a tar.
     *
     * TODO: Re-extract and compare against source bag.
     * @group Bag
     * @covers ::package
     * @covers ::makePackage
     * @covers ::makeTar
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testTarBzipBag()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $archivefile = $this->getTempName();
        $archivefile .= ".tar.bz2";
        $this->assertFileNotExists($archivefile);
        $bag->package($archivefile);
        $this->assertFileExists($archivefile);

        $newbag = Bag::load($archivefile);
        $this->assertTrue($newbag->validate());

        $this->assertEquals(
            $bag->getPayloadManifests()['sha256']->getHashes(),
            $newbag->getPayloadManifests()['sha256']->getHashes()
        );
    }
}
