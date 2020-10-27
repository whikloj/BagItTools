<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\BagUtils;

/**
 * Class BagUtilsTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\BagUtils
 */
class BagUtilsTest extends BagItTestFramework
{

    /**
     * @covers ::isDotDir
     */
    public function testIsDotDir()
    {
        $this->assertTrue(BagUtils::isDotDir('.'));
        $this->assertTrue(BagUtils::isDotDir('..'));
        $this->assertFalse(BagUtils::isDotDir('.hidden'));
        $this->assertFalse(BagUtils::isDotDir('./upAdirectory'));
        $this->assertFalse(BagUtils::isDotDir('random.file'));
    }

    /**
     * @covers ::baseInData
     */
    public function testBaseInData()
    {
        $this->assertEquals('data/test.txt', BagUtils::baseInData('test.txt'));
        $this->assertEquals('data/test.txt', BagUtils::baseInData('data/test.txt'));
        $this->assertEquals('data/data/test.txt', BagUtils::baseInData('/data/test.txt'));
        $this->assertEquals('data/../../test.txt', BagUtils::baseInData('../../test.txt'));
    }

    /**
     * @covers ::findAllByPattern
     */
    public function testFindAllByPattern()
    {
        $txt_files = [
            self::TEST_BAG_DIR . '/bagit.txt',
            self::TEST_BAG_DIR . '/manifest-sha256.txt',
        ];
        $files = BagUtils::findAllByPattern(self::TEST_BAG_DIR . '/*\.txt');
        $this->assertArrayEquals($txt_files, $files);

        $manifest = [
            self::TEST_BAG_DIR . '/manifest-sha256.txt',
        ];
        $files = BagUtils::findAllByPattern(self::TEST_BAG_DIR . '/manifest*');
        $this->assertArrayEquals($manifest, $files);
    }

    /**
     * @covers ::getValidCharset
     */
    public function testGetValidCharset()
    {
        $this->assertEquals('UTF-8', BagUtils::getValidCharset('utf-8'));
        $this->assertEquals('EUC-JP', BagUtils::getValidCharset('euc-jp'));
        $this->assertNull(BagUtils::getValidCharset('mom'));
    }

    /**
     * @covers ::getAbsolute
     */
    public function testGetAbsolute()
    {
        $this->assertEquals('data/dir1/dir2', BagUtils::getAbsolute('data/./dir1//dir2'));
        $this->assertEquals('data/dir1/dir3', BagUtils::getAbsolute('data/dir1/dir2/../dir3'));
        $this->assertEquals('', BagUtils::getAbsolute('data/dir1/../../'));
        $this->assertEquals('..', BagUtils::getAbsolute('data/dir1/../../../'));
    }

    /**
     * @covers ::invalidPathCharacters
     */
    public function testInvalidPathCharacters()
    {
        $this->assertTrue(BagUtils::invalidPathCharacters('/some/directory'));
        $this->assertTrue(BagUtils::invalidPathCharacters('../some/other/directory'));
        $this->assertTrue(BagUtils::invalidPathCharacters('some/directory/~host/mine'));
        $this->assertFalse(BagUtils::invalidPathCharacters('data/something/../whatever/file.txt'));
    }

    /**
     * @covers ::getAllFiles
     */
    public function testGetAllFiles()
    {
        $files = BagUtils::getAllFiles(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'bag-infos');
        $this->assertCount(2, $files);

        $files = BagUtils::getAllFiles(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'fetchFiles');
        $this->assertCount(4, $files);

        $files = BagUtils::getAllFiles(self::TEST_EXTENDED_BAG_DIR);
        $this->assertCount(7, $files);

        $files = BagUtils::getAllFiles(self::TEST_EXTENDED_BAG_DIR, ['data']);
        $this->assertCount(5, $files);

        $files = BagUtils::getAllFiles(self::TEST_EXTENDED_BAG_DIR, ['data', 'alt_tags']);
        $this->assertCount(4, $files);
    }

    /**
     * @covers ::checkedUnlink
     * @expectedException \whikloj\BagItTools\Exceptions\FilesystemException
     */
    public function testCheckedUnlink()
    {
        // try to delete a non-existant file.
        BagUtils::checkedUnlink($this->tmpdir);
    }

    /**
     * @covers ::checkedMkdir
     * @expectedException  \whikloj\BagItTools\Exceptions\FilesystemException
     */
    public function testCheckedMkdir()
    {
        // Create a directory
        touch($this->tmpdir);
        // Try to create a directory with the same name.
        BagUtils::checkedMkdir($this->tmpdir);
    }

    /**
     * @covers ::checkedCopy
     * @expectedException  \whikloj\BagItTools\Exceptions\FilesystemException
     */
    public function testCheckedCopyNoSource()
    {
        $destFile = $this->getTempName();
        // Source file does not exist.
        BagUtils::checkedCopy($this->tmpdir, $destFile);
    }

    /**
     * @covers ::checkedCopy
     * @expectedException  \whikloj\BagItTools\Exceptions\FilesystemException
     */
    public function testCheckedCopyNoDest()
    {
        // Real source file
        $sourceFile = self::TEST_IMAGE['filename'];
        // Directory of destination does not exist.
        BagUtils::checkedCopy($sourceFile, $this->tmpdir . DIRECTORY_SEPARATOR . "someotherfile");
    }

    /**
     * @covers ::checkedFilePut
     * @expectedException \whikloj\BagItTools\Exceptions\FilesystemException
     */
    public function testCheckedFilePut()
    {
        BagUtils::checkedFilePut($this->tmpdir . DIRECTORY_SEPARATOR . "someotherfile", "some content");
    }

    /**
     * @covers ::checkedFwrite
     * @expectedException  \whikloj\BagItTools\Exceptions\FilesystemException
     */
    public function testCheckedFwrite()
    {
        // Open a pointer to a new file.
        $fp = fopen($this->tmpdir, "w+");
        if ($fp === false) {
            throw new \Exception("Couldn't open file ({$this->tmpdir}).");
        }
        // Close the file pointer.
        fclose($fp);
        // Write to the file.
        BagUtils::checkedFwrite($fp, "Some example text");
    }
}
