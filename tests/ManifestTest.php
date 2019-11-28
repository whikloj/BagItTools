<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Class ManifestTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\PayloadManifest
 */
class ManifestTest extends BagItTestFramework
{

  /**
   * Test creation of default payload manifest with construction.
   * @group PayloadManifest
   * @covers ::__construct
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testCreateManifest()
    {
        $bag = Bag::create($this->tmpdir);
        $manifests = $bag->getPayloadManifests();
        $this->assertArrayHasKey('sha512', $manifests);
        $manifest = $manifests['sha512'];
        $this->assertEquals("manifest-sha512.txt", $manifest->getFilename());
        $this->assertEquals('sha512', $manifest->getAlgorithm());
    }

    /**
     * Test that manifests files are appropriately filled out.
     * @covers ::update
     * @covers \whikloj\BagItTools\TagManifest::update
     * @covers \whikloj\BagItTools\PayloadManifest::update
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testCheckManifests()
    {
        $bag = Bag::create($this->tmpdir);
        $test_files = [
            'baginfo' => $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'bag-info.txt',
            'payload' => $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha256.txt',
            'tag' => $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha256.txt',
        ];
        $bag->setExtended(true);
        $bag->addBagInfoTag('Contact-name', 'Jared Whiklo');
        $bag->setAlgorithm('sha256');
        $bag->createFile("This is some sample text", 'some/directory/file.txt');

        foreach ($test_files as $file) {
            $this->assertFileNotExists($file);
        }

        $bag->update();

        foreach ($test_files as $file) {
            $this->assertFileExists($file);
        }

        $fp = fopen($test_files['payload'], 'rb');
        $line = self::getLine($fp, $bag->getFileEncoding());
        $expected_filepath = 'data/some/directory/file.txt';
        $constraint1 = self::stringEndsWith($expected_filepath);
        $this->assertTrue($constraint1->evaluate($line, '', true));
        fclose($fp);

        $fp = fopen($test_files['tag'], 'rb');
        $constraints = self::logicalOr(
            self::stringEndsWith('bagit.txt'),
            self::stringEndsWith('bag-info.txt'),
            self::stringEndsWith('manifest-sha256.txt')
        );
        while (feof($fp)) {
            $line = $this->getLine($fp, $bag->getFileEncoding());
            $this->assertTrue($constraints->evaluate($line, '', true));
        }
        fclose($fp);
    }

    /**
     * Get a line from a Bagit file using the provided encoding.
     *
     * @param resource $fp
     *   The file resource
     * @param string $file_encoding
     *   The file encoding
     * @return string
     *   The line from the file decoded to UTF-8.
     */
    private static function getLine($fp, $file_encoding)
    {
        $line = fgets($fp);
        $line = mb_convert_encoding($line, 'UTF-8', $file_encoding);
        return trim($line);
    }
}
