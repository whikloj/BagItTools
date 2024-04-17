<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test;

use Exception;
use whikloj\BagItTools\BagUtils;
use whikloj\BagItTools\Exceptions\BagItException;
use whikloj\BagItTools\Exceptions\FilesystemException;

/**
 * Class BagUtilsTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\BagUtils
 */
class BagUtilsTest extends BagItTestFramework
{
    /**
     * @covers ::baseInData
     */
    public function testBaseInData(): void
    {
        $this->assertEquals('data/test.txt', BagUtils::baseInData('test.txt'));
        $this->assertEquals('data/test.txt', BagUtils::baseInData('data/test.txt'));
        $this->assertEquals('data/data/test.txt', BagUtils::baseInData('/data/test.txt'));
        $this->assertEquals('data/../../test.txt', BagUtils::baseInData('../../test.txt'));
    }

    /**
     * @covers ::findAllByPattern
     */
    public function testFindAllByPattern(): void
    {
        $txt_files = [
            self::TEST_BAG_DIR . '/bagit.txt',
            self::TEST_BAG_DIR . '/manifest-sha256.txt',
        ];
        $files = BagUtils::findAllByPattern(self::TEST_BAG_DIR . '/*.txt');
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
    public function testGetValidCharset(): void
    {
        $this->assertEquals('UTF-8', BagUtils::getValidCharset('utf-8'));
        $this->assertEquals('EUC-JP', BagUtils::getValidCharset('euc-jp'));
        $this->assertNull(BagUtils::getValidCharset('mom'));
    }

    /**
     * @covers ::getAbsolute
     */
    public function testGetAbsolute(): void
    {
        $paths = [
            'data/./dir1//dir2' => 'data/dir1/dir2',
            'data/dir1/dir2/../dir3' => 'data/dir1/dir3',
            'data/dir1/../../' => '',
            'data/dir1/../../../../' => '../..',
            '/one/two/../two/./three/../../two' => '/one/two',
            '../one/two/../two/./three/../../two' => '../one/two',
            '../.././../one/two/../two/./three/../../two' => '../../../one/two',
            '../././../one/two/../two/./three/../../two' => '../../one/two',
            '/../one/two/../two/./three/../../two' => '/one/two',
            '/../../one/two/../two/./three/../../two' => '/one/two',
            'c:\.\..\one\two\..\two\.\three\..\..\two' => 'c:/one/two',
            '/path/to/test/.././..//..///..///../one/two/../three/filename' => '/one/three/filename',
        ];
        $line = 0;
        foreach ($paths as $starting => $expected) {
            $this->assertEquals(
                str_replace('\\', '/', $expected),
                BagUtils::getAbsolute($starting),
                "Test Case " . ++$line . " failed"
            );
        }
    }

    /**
     * @covers ::getAbsolute
     */
    public function testGetAbsoluteRelative(): void
    {
        mkdir($this->tmpdir);
        $current = getcwd();
        chdir($this->tmpdir);
        $bag_name = "new_bag_directory";
        $full_path = $this->tmpdir . '/' . $bag_name;
        $this->assertEquals($full_path, BagUtils::getAbsolute($bag_name, true));
        chdir($current);
    }

    /**
     * @covers ::getAllFiles
     */
    public function testGetAllFiles(): void
    {
        $files = BagUtils::getAllFiles(self::TEST_RESOURCES . '/bag-infos');
        $this->assertCount(2, $files);

        $files = BagUtils::getAllFiles(self::TEST_RESOURCES . '/fetchFiles');
        $this->assertCount(8, $files);

        $files = BagUtils::getAllFiles(self::TEST_EXTENDED_BAG_DIR);
        $this->assertCount(7, $files);

        $files = BagUtils::getAllFiles(self::TEST_EXTENDED_BAG_DIR, ['data']);
        $this->assertCount(5, $files);

        $files = BagUtils::getAllFiles(self::TEST_EXTENDED_BAG_DIR, ['data', 'alt_tags']);
        $this->assertCount(4, $files);
    }

    /**
     * @covers ::checkedUnlink
     */
    public function testCheckedUnlink(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Unable to delete path $this->tmpdir");

        // try to delete a non-existant file.
        BagUtils::checkedUnlink($this->tmpdir);
    }

    /**
     * @covers ::checkedMkdir
     */
    public function testCheckedMkdir(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Unable to create directory $this->tmpdir");

        // Create a directory
        touch($this->tmpdir);
        // Try to create a directory with the same name.
        BagUtils::checkedMkdir($this->tmpdir);
    }

    /**
     * @covers ::checkedCopy
     */
    public function testCheckedCopyNoSource(): void
    {
        $destFile = $this->getTempName();

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Unable to copy file ($this->tmpdir) to ($destFile)");

        // Source file does not exist.
        BagUtils::checkedCopy($this->tmpdir, $destFile);
    }

    /**
     * @covers ::checkedCopy
     */
    public function testCheckedCopyNoDest(): void
    {
        // Real source file
        $sourceFile = self::TEST_IMAGE['filename'];
        // Fake destination path
        $destFile = $this->tmpdir . "/someotherfile";

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Unable to copy file ($sourceFile) to ($destFile)");

        // Directory of destination does not exist.
        BagUtils::checkedCopy($sourceFile, $destFile);
    }

    /**
     * @covers ::checkedFilePut
     */
    public function testCheckedFilePut(): void
    {
        $destFile = $this->tmpdir . "/someotherfile";

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Unable to put contents to file $destFile");

        BagUtils::checkedFilePut($destFile, "some content");
    }

    /**
     * @covers ::checkedFwrite
     */
    public function testCheckedFwrite(): void
    {
        // Open a pointer to a new file.
        $fp = fopen($this->tmpdir, "w+");
        if ($fp === false) {
            throw new Exception("Couldn't open file ($this->tmpdir).");
        }
        // Close the file pointer.
        fclose($fp);

        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Error writing to file");

        // Write to the file.
        BagUtils::checkedFwrite($fp, "Some example text");
    }

    /**
     * @covers ::checkedRmDir
     */
    public function testCheckedRmDirFailure(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage("Unable to remove directory $this->tmpdir");

        // try to delete a non-existent file.
        BagUtils::checkedRmDir($this->tmpdir);
    }

    /**
     * @covers ::checkedRmDir
     */
    public function testCheckedRmDirSuccess(): void
    {
        mkdir($this->tmpdir);
        $this->assertDirectoryExists($this->tmpdir);
        BagUtils::checkedRmDir($this->tmpdir);
        $this->assertDirectoryDoesNotExist($this->tmpdir);
    }

    /**
     * @covers ::checkUnencodedFilepath
     */
    public function testCheckUnencodedFilepath(): void
    {
        $this->assertTrue(BagUtils::checkUnencodedFilepath("some/path/with%2ffake/slashes"));
        $this->assertTrue(BagUtils::checkUnencodedFilepath("some/path/with%2Ffake/slashes"));
        $this->assertFalse(BagUtils::checkUnencodedFilepath("some/path/with%252ffake/slashes"));
        $this->assertFalse(BagUtils::checkUnencodedFilepath("some/path/with%252Ffake/slashes"));
        $this->assertFalse(BagUtils::checkUnencodedFilepath("some/path/with%0Aencoded/newlines"));
        $this->assertFalse(BagUtils::checkUnencodedFilepath("some/path/with%0Dencoded/carriage/returns"));
        $this->assertTrue(BagUtils::checkUnencodedFilepath("some/path/with%22encoded/quotes"));
        $this->assertFalse(BagUtils::checkUnencodedFilepath("some/path/with/nothing"));
    }

    /**
     * @covers ::standardizePathSeparators
     */
    public function testStandardizeFilePaths(): void
    {
        // array of arrays with expected path and original input
        $data = [
            ["data/directory/for/file.txt", "data/directory\\for/file.txt"],
            ["/some/path/we/are/using", "/some/path\\we\\are\\using"],
            ["c:/windows/directories/are/weird", "c:\\windows\\directories\\are\\weird"],
        ];
        foreach ($data as $item) {
            $this->assertEquals($item[0], BagUtils::standardizePathSeparators($item[1]));
        }
    }

    /**
     * @covers ::deleteEmptyDirTree
     */
    public function testDeleteEmptyTreeOutsideRoot(): void
    {
        mkdir($this->tmpdir);
        $parent = dirname($this->tmpdir);
        $this->assertDirectoryExists($this->tmpdir);

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Path is not within the root directory.");

        BagUtils::deleteEmptyDirTree($parent, $this->tmpdir);
    }

    /**
     * @covers ::deleteEmptyDirTree
     */
    public function testDeleteMultipleLevels(): void
    {
        mkdir($this->tmpdir);
        $bottomRung = $this->tmpdir . "/level1/level2/level3";
        mkdir($bottomRung, 0777, true);
        $this->assertDirectoryExists($bottomRung);
        $this->assertDirectoryExists($this->tmpdir . "/level1/level2");
        $this->assertDirectoryExists($this->tmpdir . "/level1");
        BagUtils::deleteEmptyDirTree($bottomRung, $this->tmpdir);
        $this->assertDirectoryDoesNotExist($bottomRung);
        $this->assertDirectoryDoesNotExist($this->tmpdir . "/level1/level2");
        $this->assertDirectoryDoesNotExist($this->tmpdir . "/level1");
    }

    /**
     * @covers ::deleteEmptyDirTree
     */
    public function testDeleteMultipleLevelsStopInMiddle(): void
    {
        mkdir($this->tmpdir);
        $bottomRung = $this->tmpdir . "/level1/level2/level3";
        $someFile = $this->tmpdir . "/level1/level2/someFile.txt";
        mkdir($bottomRung, 0777, true);
        // Create a file in the middle level.
        touch($someFile);

        $this->assertDirectoryExists($bottomRung);
        $this->assertDirectoryExists($this->tmpdir . "/level1/level2");
        $this->assertDirectoryExists($this->tmpdir . "/level1");
        BagUtils::deleteEmptyDirTree($bottomRung, $this->tmpdir);

        $this->assertDirectoryDoesNotExist($bottomRung);
        // Middle level should still exist.
        $this->assertDirectoryExists($this->tmpdir . "/level1/level2");
        $this->assertDirectoryExists($this->tmpdir . "/level1");
        // Remove the file.
        unlink($someFile);

        BagUtils::deleteEmptyDirTree($bottomRung, $this->tmpdir);
        // Because we specified a non-existent directory, we didn't traverse anything.
        $this->assertDirectoryDoesNotExist($bottomRung);
        $this->assertDirectoryExists($this->tmpdir . "/level1/level2");
        $this->assertDirectoryExists($this->tmpdir . "/level1");

        BagUtils::deleteEmptyDirTree($this->tmpdir . "/level1/level2", $this->tmpdir);
        // Now all the directories should be removed.
        $this->assertDirectoryDoesNotExist($bottomRung);
        $this->assertDirectoryDoesNotExist($this->tmpdir . "/level1/level2");
        $this->assertDirectoryDoesNotExist($this->tmpdir . "/level1");
    }
}
