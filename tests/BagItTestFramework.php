<?php
/**
 * Created by PhpStorm.
 * User: whikloj
 * Date: 2019-11-17
 * Time: 13:58
 */

namespace whikloj\BagItTools\Test;

use PHPUnit\Framework\TestCase;

class BagItTestFramework extends TestCase
{

  /**
   * Location of the Test Bag
   */
    const TEST_BAG_DIR = __DIR__ . DIRECTORY_SEPARATOR . "resources/TestBag1";

  /**
   * Location and hashes of the test image.
   */
    const TEST_IMAGE = [
    'filename' => __DIR__ . DIRECTORY_SEPARATOR . "resources/images/scenic-landscape.jpg",
    'checksums' => [
      'md5' => 'f181491b485c45ecaefdc3393da4aea6',
      'sha1' => '0cc9a4a7e02edf70650a5a8bb972224657bb48bb',
      'sha256' => 'ac1b6ed49d381ccc9c1be3654d0964018e9a75954985d57d27146a221c16e8fd',
      'sha512' => '1e56314a6f46c194b77e24309392c0039a7f0a7351a807924cc870fae26b81f77ab02db240ae382088ff0a46e821bbce1b' .
        'b6bfbe158ae9245a22fcfee3be0bee',
    ],
    ];

  /**
   * Path to a directory that will be cleaned up after test. Use this if
   * the test throws an exception to ensure it gets deleted.
   * @var string
   */
    protected $tmpdir;

  /**
   * {@inheritdoc}
   */
    public function setUp()
    {
        parent::setUp();
        $this->tmpdir = $this->getTempName();
    }

  /**
   * {@inheritdoc}
   */
    public function tearDown()
    {
        parent::tearDown();
        if (isset($this->tmpdir) && file_exists($this->tmpdir)) {
            self::deleteDirAndContents($this->tmpdir);
        }
    }

  /**
   * Get a temporary filename.
   *
   * @return bool|string
   *   The filename.
   */
    protected function getTempName()
    {
        $tempname = tempnam("", "bagit_");
        unlink($tempname);
        return $tempname;
    }

  /**
   * Recursively delete a directory.
   *
   * @param string $path
   *   The directory to delete.
   */
    protected static function deleteDirAndContents($path)
    {
        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file == "." || $file == "..") {
                    continue;
                }
                $currentFile = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($currentFile)) {
                    self::deleteDirAndContents($currentFile);
                } elseif (is_file($currentFile)) {
                    unlink($currentFile);
                }
            }
            rmdir($path);
        }
    }

  /**
   * Copy the TestBag directory to a temporary directory so we can destroy it after each test.
   *
   * @return string The temporary directory with the copy of the test bag.
   */
    protected function prepareTestBagDirectory()
    {
        $tmp = $this->getTempName();
        mkdir($tmp);
        self::copyDir(self::TEST_BAG_DIR, $tmp);
        return $tmp;
    }

  /**
   * Compare two arrays have all the same elements, does not compare order.
   *
   * @param array $expected The expected array.
   * @param array $testing The array to test.
   */
    protected function assertArrayEquals(array $expected, array $testing)
    {
        // They have the same number of elements
        $this->assertCount(count($expected), $testing);
        // All the elements in $expected exist in $testing
        $this->assertCount(0, array_diff($expected, $testing));
        // All the elements in $testing exist in $expected (possibly overkill)
        $this->assertCount(0, array_diff($testing, $expected));
    }

  /**
   * Recursively copy the directory from src to dest
   *
   * @param string $src The original directory.
   * @param string $dest The destination directory.
   */
    private static function copyDir($src, $dest)
    {
        foreach (scandir($src) as $item) {
            if ($item == "." || $item == "..") {
                continue;
            }
            if (is_dir("{$src}/{$item}")) {
                if (!is_dir("{$dest}/{$item}")) {
                    mkdir("{$dest}/{$item}");
                }
                self::copyDir("{$src}/{$item}", "{$dest}/{$item}");
            } else {
                copy("{$src}/{$item}", "{$dest}/{$item}");
            }
        }
    }

  /**
   * Get a private or protected method to test it directly.
   *
   * @param string $class
   *   Class to refect.
   * @param string $method
   *   Method to get.
   *
   * @return mixed
   *   Reflection of the method.
   *
   * @throws \ReflectionException
   */
    protected static function getReflectionMethod($class, $method)
    {
        $class = new \ReflectionClass($class);
        $methodCall = $class->getMethod($method);
        $methodCall->setAccessible(true);
        return $methodCall;
    }
}
