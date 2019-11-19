<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Test of TagManifest class.
 * @package whikloj\BagItTools\Test
 */
class ExtendedBagTest extends BagItTestFramework
{

    /**
     * Test a non-extended bag has no tag manifest.
     * @group Extended
     * @covers \whikloj\BagItTools\TagManifest::calculateHash
     * @covers \whikloj\BagItTools\TagManifest::update
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testNoTagManifest()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = new Bag($this->tmpdir, false);
        $this->assertFalse($bag->isExtended());
        $payloads = array_keys($bag->getPayloadManifests());
        $hash = reset($payloads);
        $manifests = $bag->getTagManifests();
        $this->assertNull($manifests);

        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . "manifest-{$hash}.txt");
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-{$hash}.txt");
        // Make an extended bag
        $bag->setExtended(true);
        // Tag manifest not written.
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-{$hash}.txt");

        $bag->update();
        // Now it exists.
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . "tagmanifest-{$hash}.txt");
        $manifests = $bag->getTagManifests();
        $this->assertNotEmpty($manifests);
        $this->assertArrayHasKey($hash, $manifests);
    }

    /**
     * Test loading an extended bag properly and adding payload-oxum
     * @group Extended
     * @covers \whikloj\BagItTools\Bag::loadBagInfo
     * @covers \whikloj\BagItTools\Bag::updateBagInfo
     * @covers \whikloj\BagItTools\Bag::calculateOxum
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testLoadExtendedBag()
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = new Bag($this->tmpdir, false);
        $this->assertTrue($bag->isExtended());
        $payloads = $bag->getPayloadManifests();
        $tags = $bag->getTagManifests();
        $this->assertCount(1, $payloads);
        $this->assertCount(1, $tags);
        $this->assertArrayHasKey('sha1', $payloads);
        $this->assertArrayHasKey('sha1', $tags);
        $this->assertCount(2, $payloads['sha1']->getHashes());
        $this->assertCount(4, $tags['sha1']->getHashes());
        $this->assertArrayHasKey('bagit.txt', $tags['sha1']->getHashes());
        $this->assertArrayHasKey('bag-info.txt', $tags['sha1']->getHashes());
        $this->assertArrayHasKey('manifest-sha1.txt', $tags['sha1']->getHashes());
        $this->assertArrayHasKey('alt_tags/random_tags.txt', $tags['sha1']->getHashes());

        $this->assertTrue($bag->hasBagInfoDataTag('contact-phone'));

        $this->assertFalse($bag->hasBagInfoDataTag('payload-oxum'));
        $bag->update();
        $this->assertTrue($bag->hasBagInfoDataTag('payload-oxum'));
        $this->assertTrue($bag ->hasBagInfoDataTag('bagging-date'));
        $oxums = $bag->getBagInfoDataByTag('payload-oxum');
        $this->assertCount(1, $oxums);
        $this->assertEquals('408183.2', $oxums[0]);
    }
}
