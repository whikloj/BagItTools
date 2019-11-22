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
}
