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
     * @throws \whikloj\BagItTools\BagItException
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
}
