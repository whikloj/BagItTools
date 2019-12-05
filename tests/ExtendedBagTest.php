<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Test of various classes for extended bag functions..
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Bag
 */
class ExtendedBagTest extends BagItTestFramework
{

    /**
     * @group Extended
     * @covers ::validate
     * @covers ::getErrors
     * @covers \whikloj\BagItTools\AbstractManifest::getErrors
     * @covers ::getWarnings
     * @covers \whikloj\BagItTools\AbstractManifest::getWarnings
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testValidateExtendedBag()
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->validate());
        $this->assertCount(0, $bag->getErrors());
        $this->assertCount(0, $bag->getWarnings());
    }

    /**
     * Test a non-extended bag has no tag manifest.
     * @group Extended
     * @covers \whikloj\BagItTools\AbstractManifest::calculateHash
     * @covers \whikloj\BagItTools\TagManifest::update
     * @covers \whikloj\BagItTools\AbstractManifest::update
     * @covers \whikloj\BagItTools\AbstractManifest::writeToDisk
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testNoTagManifest()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
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
     * @covers ::loadBagInfo
     * @covers ::updateBagInfo
     * @covers ::calculateOxum
     * @covers ::updateCalculateBagInfoFields
     * @covers ::update
     * @covers \whikloj\BagItTools\AbstractManifest::loadFile
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testLoadExtendedBag()
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
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

        $this->assertTrue($bag->hasBagInfoTag('contact-phone'));

        $this->assertFalse($bag->hasBagInfoTag('payload-oxum'));
        $bag->update();
        $this->assertTrue($bag->hasBagInfoTag('payload-oxum'));
        $this->assertTrue($bag ->hasBagInfoTag('bagging-date'));
        $oxums = $bag->getBagInfoByTag('payload-oxum');
        $this->assertCount(1, $oxums);
        $this->assertEquals('408183.2', $oxums[0]);
    }

    /**
     * Test getting bag info by key
     * @group Extended
     * @covers ::hasBagInfoTag
     * @covers ::getBagInfoByTag
     * @covers ::bagInfoTagExists
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testGetBagInfoByKey()
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isExtended());
        $this->assertCount(7, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $contacts = $bag->getBagInfoByTag('CONTACT-name');
        $this->assertCount(3, $contacts);
        $this->assertTrue(in_array('Cris Carter', $contacts));
        $this->assertTrue(in_array('Randy Moss', $contacts));
        $this->assertTrue(in_array('Robert Smith', $contacts));
        $this->assertFalse(in_array('cris carter', $contacts));
    }

    /**
     * Test removing all entries for a tag.
     * @group Extended
     * @covers ::hasBagInfoTag
     * @covers ::getBagInfoByTag
     * @covers ::removeBagInfoTag
     * @covers ::bagInfoTagExists
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testRemoveBagInfoByTag()
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isExtended());
        $this->assertCount(7, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(3, $bag->getBagInfoByTag('CONTACT-name'));
        $bag->removeBagInfoTag('Contact-NAME');
        $this->assertFalse($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(0, $bag->getBagInfoByTag('ConTAct-NamE'));
    }

    /**
     * Test removing all entries for a tag.
     * @group Extended
     * @covers ::hasBagInfoTag
     * @covers ::getBagInfoByTag
     * @covers ::removeBagInfoTagIndex
     * @covers ::bagInfoTagExists
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testRemoveBagInfoByTagIndex()
    {
        $original = [
            'Robert Smith',
            'Randy Moss',
            'Cris Carter',
        ];
        $final = [
            'Robert Smith',
            'Cris Carter',
        ];
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->validate());
        $this->assertTrue($bag->isExtended());
        $this->assertCount(7, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(3, $bag->getBagInfoByTag('CONTACT-name'));
        $this->assertArrayEquals($original, $bag->getBagInfoByTag('CONTACT-name'));
        $bag->removeBagInfoTagIndex('Contact-NAME', 1);
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(2, $bag->getBagInfoByTag('ConTAct-NamE'));
        $this->assertArrayEquals($final, $bag->getBagInfoByTag('contact-name'));
    }

    /**
     * Test getting, adding and removing valid algorithms using common names.
     *
     * @group Bag
     * @covers ::addAlgorithm
     * @covers ::removeTagManifest
     * @covers ::clearTagManifests
     * @covers ::removeAlgorithm
     * @covers ::getAlgorithms
     * @covers ::updateTagManifests
     * @covers ::updatePayloadManifests
     * @covers ::ensureTagManifests
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testGetHashesCommon()
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');

        $bag->update();
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha512.txt');

        $bag->setExtended(true);
        $bag->update();
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha512.txt');

        // Set one
        $bag->addAlgorithm('SHA1');
        // Remove it
        $bag->removeAlgorithm('SHA1');

        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha1.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');

        // Set again differently
        $bag->addAlgorithm('SHA-1');
        // Set a third
        $bag->addAlgorithm('SHA-224');

        $bag->update();

        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');

        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha224.txt');

        // Remove one
        $bag->removeAlgorithm('SHA-512');

        $bag->update();
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha512.txt');

        $this->assertArrayEquals(['sha224', 'sha1'], $bag->getAlgorithms());
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');

        $bag->setExtended(false);
        $bag->update();
        // tag manifests are gone.
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha224.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');
        // but payload remain
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha1.txt');
    }

    /**
     * Test setting a specific algorithm.
     * @group Bag
     * @covers ::setAlgorithm
     * @covers ::removeAllPayloadManifests
     * @covers ::removePayloadManifest
     * @covers ::removeAllTagManifests
     * @covers ::removeTagManifest
     */
    public function testSetAlgorithm()
    {
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        $bag->addAlgorithm('sha1');
        $bag->addAlgorithm('SHA-224');
        $this->assertArrayEquals(['sha512', 'sha1', 'sha224'], $bag->getAlgorithms());
        $bag->update();

        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha224.txt');

        $bag->setAlgorithm('md5');
        $this->assertArrayEquals(['md5'], $bag->getAlgorithms());
        // Still the old manifests exist
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha224.txt');
        // And the new one doesn't
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-md5.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-md5.txt');

        $bag->update();

        // Now the old manifests don't exist
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha1.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha512.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-sha224.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha1.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha512.txt');
        $this->assertFileNotExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-sha224.txt');
        // And the new one does
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-md5.txt');
        $this->assertFileExists($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'tagmanifest-md5.txt');
    }

    /**
     * Test setting a bag info tag.
     * @group Extended
     * @covers ::addBagInfoTag
     * @covers ::bagInfoTagExists
     */
    public function testSetBagInfoElement()
    {
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $this->assertCount(1, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('contact-name'));
        $tags = $bag->getBagInfoByTag('CONTACT-NAME');
        $this->assertArrayEquals(['Monty Hall'], $tags);
        $baginfo = $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'bag-info.txt';
        $this->assertFileNotExists($baginfo);
        $bag->update();
        $this->assertFileExists($baginfo);
        $expected = 'Contact-NAME: Monty Hall' . PHP_EOL . 'Payload-Oxum: 0.0' . PHP_EOL . 'Bagging-Date: ' .
            date('Y-m-d', time()) . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($baginfo));

        $bag->addBagInfoTag('contact-nAME', 'Bob Barker');
        $tags = $bag->getBagInfoByTag('CONTACT-NAME');
        $this->assertArrayEquals(['Monty Hall', 'Bob Barker'], $tags);

        $bag->update();
        $expected = 'Contact-NAME: Monty Hall' . PHP_EOL . 'contact-nAME: Bob Barker' . PHP_EOL . 'Payload-Oxum: 0.0' .
            PHP_EOL . 'Bagging-Date: ' . date('Y-m-d', time()) . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($baginfo));
    }

    /**
     * Test the exception when trying to set a generated field.
     * @group Extended
     * @covers ::addBagInfoTag
     * @covers ::setExtended
     * @expectedException  \whikloj\BagItTools\BagItException
     */
    public function testSetGeneratedField()
    {
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        $bag->addBagInfoTag('Source-organization', 'Planet Earth');
        // Doesn't match due to underscore instead of hyphen.
        $bag->addBagInfoTag('PAYLOAD_OXUM', '123456.12');
        // Now we explode.
        $bag->addBagInfoTag('payload-oxum', '123');
    }

    /**
     * Test that for a v1.0 bag you CAN'T have spaces at the start or end of a tag.
     * @group Extended
     * @covers ::loadBagInfo
     * @covers ::compareVersion
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testInvalidBagInfov1()
    {
        $bag = Bag::create($this->tmpdir);
        copy(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'bag-infos' . DIRECTORY_SEPARATOR .
            'invalid-leading-spaces.txt', $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'bag-info.txt');
        touch($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-md5.txt');
        $testbag = Bag::load($this->tmpdir);
        $this->assertCount(2, $testbag->getErrors());
    }

    /**
     * Test that for a v0.97 bag you CAN have spaces at the start or end of a tag.
     * @group Extended
     * @covers ::loadBagInfo
     * @covers ::compareVersion
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testInvalidBagInfov097()
    {
        $bag = Bag::create($this->tmpdir);
        copy(self::TEST_RESOURCES . DIRECTORY_SEPARATOR . 'bag-infos' . DIRECTORY_SEPARATOR .
            'invalid-leading-spaces.txt', $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'bag-info.txt');
        file_put_contents(
            $bag->getBagRoot() . DIRECTORY_SEPARATOR . 'bagit.txt',
            "BagIt-Version: 0.97" . PHP_EOL . "Tag-File-Character-Encoding: UTF-8" . PHP_EOL
        );
        touch($bag->getBagRoot() . DIRECTORY_SEPARATOR . 'manifest-md5.txt');
        $testbag = Bag::load($this->tmpdir);
        $this->assertCount(0, $testbag->getErrors());
    }

    /**
     * Test getting manifests for basic bag.
     * @group Extended
     * @covers ::load
     * @covers ::getPayloadManifests
     * @covers ::getTagManifests
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testGetManifests()
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $payloads = $bag->getPayloadManifests();
        $this->assertCount(1, $payloads);
        $tags = $bag->getTagManifests();
        $this->assertNull($tags);
    }

    /**
     * Test getting manifests for extended bag.
     * @group Extended
     * @covers ::load
     * @covers ::getPayloadManifests
     * @covers ::getTagManifests
     * @throws \whikloj\BagItTools\BagItException
     */
    public function testGetManifestsExtended()
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $payloads = $bag->getPayloadManifests();
        $this->assertCount(1, $payloads);
        $tags = $bag->getTagManifests();
        $this->assertCount(1, $tags);
    }
}
