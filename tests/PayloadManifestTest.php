<?php
/**
 * Created by PhpStorm.
 * User: whikloj
 * Date: 2019-11-17
 * Time: 16:16
 */

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Class PayloadManifestTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\PayloadManifest
 */
class PayloadManifestTest extends BagItTestFramework
{

  /**
   * Test creation of default payload manifest with construction.
   * @group PayloadManifest
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testCreateManifest()
    {
        $bag = new Bag($this->tmpdir, true);
        $manifests = $bag->getPayloadManifests();
        $this->assertArrayHasKey('sha512', $manifests);
        $manifest = $manifests['sha512'];
        $this->assertEquals("manifest-sha512.txt", $manifest->getFilename());
        $this->assertEquals('sha512', $manifest->getAlgorithm());
    }

  /**
   * Test adding a file to the manifest.
   * @group PayloadManifest
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testAddFile()
    {
        $source_file = self::TEST_IMAGE['filename'];
        $checksum = self::TEST_IMAGE['checksums']['sha512'];
        $destination = "land.jpg";
        $expected_internal = "data/" . str_replace(DIRECTORY_SEPARATOR, "/", $destination);

        $bag = new Bag($this->tmpdir, true);
        $manifests = $bag->getPayloadManifests();
        $this->assertArrayHasKey('sha512', $manifests);
        $manifest = $manifests['sha512'];
        copy($source_file, $bag->getDataDirectory() . DIRECTORY_SEPARATOR . $destination);
        $manifest->addFile("data/{$destination}");
        $payloads = $manifest->getHashes();
        $this->assertArrayHasKey($expected_internal, $payloads);
        $this->assertEquals($checksum, $payloads[$expected_internal]);
    }

    public function testRemoveFile()
    {
    }
}
