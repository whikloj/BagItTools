<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Exceptions\BagItException;

/**
 * Test of various classes for extended bag functions..
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\Bag
 */
class ExtendedBagTest extends BagItTestFramework
{
    /**
     * @var string The directory to the bag-info files.
     */
    private const BAG_INFO_DIR = self::TEST_RESOURCES . "/bag-infos";

    /**
     * @group Extended
     * @covers ::isValid
     * @covers ::getErrors
     * @covers \whikloj\BagItTools\AbstractManifest::getErrors
     * @covers ::getWarnings
     * @covers \whikloj\BagItTools\AbstractManifest::getWarnings
     */
    public function testValidateExtendedBag(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
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
     */
    public function testNoTagManifest(): void
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertFalse($bag->isExtended());
        $payloads = array_keys($bag->getPayloadManifests());
        $hash = reset($payloads);
        $manifests = $bag->getTagManifests();
        $this->assertCount(0, $manifests);

        $this->assertFileExists($bag->getBagRoot() . "/manifest-$hash.txt");
        $this->assertFileDoesNotExist($bag->getBagRoot() . "/tagmanifest-$hash.txt");
        // Make an extended bag
        $bag->setExtended(true);
        // Tag manifest not written.
        $this->assertFileDoesNotExist($bag->getBagRoot() . "/tagmanifest-$hash.txt");

        $bag->update();
        // Now it exists.
        $this->assertFileExists($bag->getBagRoot() . "/tagmanifest-$hash.txt");
        $manifests = $bag->getTagManifests();
        $this->assertNotEmpty($manifests);
        $this->assertArrayHasKey($hash, $manifests);
    }

    /**
     * Test loading an extended bag properly and adding payload-oxum
     * @group Extended
     * @covers ::calculateTotalFileSizeAndAmountOfFiles
     * @covers ::convertToHumanReadable
     * @covers ::loadBagInfo
     * @covers ::updateBagInfo
     * @covers ::updateCalculateBagInfoFields
     * @covers ::update
     * @covers \whikloj\BagItTools\AbstractManifest::loadFile
     */
    public function testLoadExtendedBag(): void
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
        $this->assertFalse($bag->hasBagInfoTag('bag-size'));
        $bag->update();
        $this->assertTrue($bag->hasBagInfoTag('payload-oxum'));
        $this->assertTrue($bag ->hasBagInfoTag('bagging-date'));
        $oxums = $bag->getBagInfoByTag('payload-oxum');
        $this->assertCount(1, $oxums);
        $this->assertEquals('408183.2', $oxums[0]);
        $bagSize = $bag->getBagInfoByTag('bag-size');
        $this->assertCount(1, $bagSize);
        $this->assertEquals('398.62 KB', $bagSize[0]);
    }

    /**
     * Test getting bag info by key
     * @group Extended
     * @covers ::hasBagInfoTag
     * @covers ::getBagInfoByTag
     * @covers ::bagInfoTagExists
     */
    public function testGetBagInfoByKey(): void
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
     */
    public function testRemoveBagInfoByTag(): void
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
     */
    public function testRemoveBagInfoByTagIndex(): void
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
        $this->assertTrue($bag->isValid());
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
     * @covers ::removeBagInfoTagValue
     */
    public function testRemoveBagInfoByTagValue(): void
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
        $this->assertTrue($bag->isValid());
        $this->assertTrue($bag->isExtended());
        $this->assertCount(7, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(3, $bag->getBagInfoByTag('CONTACT-name'));
        $this->assertArrayEquals($original, $bag->getBagInfoByTag('CONTACT-name'));
        // remove by value is case-sensitive
        $bag->removeBagInfoTagValue('Contact-NAME', "RANDY MOSS");
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(3, $bag->getBagInfoByTag('ConTAct-NamE'));
        $this->assertArrayEquals($original, $bag->getBagInfoByTag('contact-name'));
        // So you have to be exact, even spaces matter
        $bag->removeBagInfoTagValue('Contact-NAME', "Randy Moss ");
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(3, $bag->getBagInfoByTag('ConTAct-NamE'));
        $this->assertArrayEquals($original, $bag->getBagInfoByTag('contact-name'));
        // Be careful
        $bag->removeBagInfoTagValue('Contact-NAME', "Randy Moss");
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(2, $bag->getBagInfoByTag('ConTAct-NamE'));
        $this->assertArrayEquals($final, $bag->getBagInfoByTag('contact-name'));
    }

    /**
     * @covers ::removeBagInfoTagValue
     */
    public function testRemoveBagInfoByTagValueCase(): void
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
        $this->assertTrue($bag->isValid());
        $this->assertTrue($bag->isExtended());
        $this->assertCount(7, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('CONTACT-NAME'));
        $this->assertCount(3, $bag->getBagInfoByTag('CONTACT-name'));
        $this->assertArrayEquals($original, $bag->getBagInfoByTag('CONTACT-name'));
        // remove by value can also be case-insensitive
        $bag->removeBagInfoTagValue('Contact-NAME', "RANDY MOSS", false);
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
     */
    public function testGetHashesCommon(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha512.txt');

        $bag->update();
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha512.txt');

        $bag->setExtended(true);
        $bag->update();
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha512.txt');

        // Set one
        $bag->addAlgorithm('SHA1');
        // Remove it
        $bag->removeAlgorithm('SHA1');

        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha1.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha1.txt');

        // Set again differently
        $bag->addAlgorithm('SHA-1');
        // Set a third
        $bag->addAlgorithm('SHA-224');

        $bag->update();

        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha1.txt');

        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha224.txt');

        // Remove one
        $bag->removeAlgorithm('SHA-512');

        $bag->update();
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha512.txt');

        $this->assertArrayEquals(['sha224', 'sha1'], $bag->getAlgorithms());
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha1.txt');

        $bag->setExtended(false);
        $bag->update();
        // tag manifests are gone.
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha224.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha1.txt');
        // but payload remain
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha1.txt');
    }

    /**
     * Test setting a specific algorithm.
     * @group Bag
     * @covers ::setAlgorithm
     * @covers ::setAlgorithmsInternal
     * @covers ::removeAllPayloadManifests
     * @covers ::removePayloadManifest
     * @covers ::removeAllTagManifests
     * @covers ::removeTagManifest
     */
    public function testSetAlgorithm(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        $bag->addAlgorithm('sha1');
        $bag->addAlgorithm('SHA-224');
        $this->assertArrayEquals(['sha512', 'sha1', 'sha224'], $bag->getAlgorithms());
        $bag->update();

        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha224.txt');

        $bag->setAlgorithm('md5');
        $this->assertArrayEquals(['md5'], $bag->getAlgorithms());
        // Still the old manifests exist
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha224.txt');
        // And the new one doesn't
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-md5.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-md5.txt');

        $bag->update();

        // Now the old manifests don't exist
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha1.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha224.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha1.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha512.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha224.txt');
        // And the new one does
        $this->assertFileExists($bag->getBagRoot() . '/manifest-md5.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-md5.txt');
    }

    /**
     * Test setting multiple algorithms in one call.
     * @group Extended
     * @covers ::setAlgorithms
     * @covers ::setAlgorithmsInternal
     * @covers ::removeAllPayloadManifests
     * @covers ::removeAllTagManifests
     */
    public function testSetAlgorithms(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        $bag->update();
        $this->assertArrayEquals(['sha512'], $bag->getAlgorithms());
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha512.txt');

        $bag->setAlgorithms(['sha1', 'SHA-224']);
        $bag->update();
        $this->assertArrayEquals(['sha1', 'sha224'], $bag->getAlgorithms());
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/manifest-sha512.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/tagmanifest-sha512.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha1.txt');
        $this->assertFileExists($bag->getBagRoot() . '/manifest-sha224.txt');
        $this->assertFileExists($bag->getBagRoot() . '/tagmanifest-sha224.txt');
    }

    /**
     * Test setting a bag info tag.
     * @group Extended
     * @covers ::addBagInfoTag
     * @covers ::bagInfoTagExists
     * @covers ::addBagInfoTagsInternal
     */
    public function testSetBagInfoElement(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $this->assertCount(1, $bag->getBagInfoData());
        $this->assertTrue($bag->hasBagInfoTag('contact-name'));
        $tags = $bag->getBagInfoByTag('CONTACT-NAME');
        $this->assertArrayEquals(['Monty Hall'], $tags);
        $baginfo = $bag->getBagRoot() . '/bag-info.txt';
        $this->assertFileDoesNotExist($baginfo);
        $bag->update();
        $this->assertFileExists($baginfo);
        $expected = 'Contact-NAME: Monty Hall' . PHP_EOL . 'Payload-Oxum: 0.0' . PHP_EOL . 'Bag-Size: 0 B' .
            PHP_EOL . 'Bagging-Date: ' . date('Y-m-d', time()) . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($baginfo));

        $bag->addBagInfoTag('contact-nAME', 'Bob Barker');
        $tags = $bag->getBagInfoByTag('CONTACT-NAME');
        $this->assertArrayEquals(['Monty Hall', 'Bob Barker'], $tags);

        $bag->update();
        $expected = 'Contact-NAME: Monty Hall' . PHP_EOL . 'contact-nAME: Bob Barker' . PHP_EOL . 'Payload-Oxum: 0.0' .
            PHP_EOL . 'Bag-Size: 0 B' . PHP_EOL . 'Bagging-Date: ' . date('Y-m-d', time()) . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($baginfo));
    }

    /**
     * Test the exception when trying to set a generated field.
     * @group Extended
     * @covers ::addBagInfoTag
     * @covers ::addBagInfoTagsInternal
     * @covers ::setExtended
     */
    public function testSetGeneratedField(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Source-organization', 'Planet Earth');
        // Doesn't match due to underscore instead of hyphen.
        $bag->addBagInfoTag('PAYLOAD_OXUM', '123456.12');

        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Field payload-oxum is auto-generated and cannot be manually set.");

        // Now we explode.
        $bag->addBagInfoTag('payload-oxum', '123');
    }

    /**
     * Test that we can add multiple tags at once.
     * @group Extended
     * @covers ::setExtended
     * @covers ::addBagInfoTags
     * @covers ::addBagInfoTagsInternal
     */
    public function testAddBagInfoTags(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals([], $bag->getBagInfoData());
        $baginfo = $bag->getBagRoot() . '/bag-info.txt';

        $inputTags = [
            'Source-organization' => 'The Pyramid',
            'CONTACT-NAME' => 'Bob Barker',
            'Contact-Name' => 'Monty Hall',
        ];
        $bag->addBagInfoTags($inputTags);

        $tags = $bag->getBagInfoByTag('CONTACT-NAME');
        $this->assertArrayEquals(['Monty Hall', 'Bob Barker'], $tags);

        $bag->update();
        $expected = 'Source-organization: The Pyramid' . PHP_EOL . 'CONTACT-NAME: Bob Barker' . PHP_EOL .
            'Contact-Name: Monty Hall' . PHP_EOL . 'Payload-Oxum: 0.0' . PHP_EOL . 'Bag-Size: 0 B' . PHP_EOL .
            'Bagging-Date: ' . date('Y-m-d', time()) . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($baginfo));
    }

    /**
     * Test setting multiple values for a single bag-info tag.
     * @group Extended
     * @covers ::addBagInfoTags
     * @covers ::addBagInfoTagsInternal
     */
    public function testAddBagInfoTagsMultiple(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals([], $bag->getBagInfoData());
        $baginfo = $bag->getBagRoot() . '/bag-info.txt';

        $inputTags = [
            'Source-organization' => 'The Pyramid',
            'CONTACT-NAME' => [
                'Bob Barker',
                'Monty Hall',
            ],
        ];
        $bag->addBagInfoTags($inputTags);

        $tags = $bag->getBagInfoByTag('Contact-name');
        $this->assertArrayEquals(['Monty Hall', 'Bob Barker'], $tags);

        $bag->update();
        $expected = 'Source-organization: The Pyramid' . PHP_EOL . 'CONTACT-NAME: Bob Barker' . PHP_EOL .
            'CONTACT-NAME: Monty Hall' . PHP_EOL . 'Payload-Oxum: 0.0' . PHP_EOL . 'Bag-Size: 0 B' . PHP_EOL .
            'Bagging-Date: ' . date('Y-m-d', time()) . PHP_EOL;
        $this->assertEquals($expected, file_get_contents($baginfo));
    }

    /**
     * Test trying to set multiple tags where one is an auto-generated tag.
     * @group Extended
     * @covers ::addBagInfoTags
     * @covers ::addBagInfoTagsInternal
     */
    public function testAddBagInfoTagsGenerated(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertArrayEquals([], $bag->getBagInfoData());

        $inputTags = [
            'Source-organization' => 'The Pyramid',
            'CONTACT-NAME' => 'Bob Barker',
            'Contact-Name' => 'Monty Hall',
            'payload-OXUM' => '123',
        ];
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("The field(s) payload-oxum are auto-generated and cannot be manually set.");
        $bag->addBagInfoTags($inputTags);
    }

    /**
     * Test that addBagInfoTags() no longer throws an exception if the bag is not extended.
     * @group Extended
     * @covers ::addBagInfoTags
     */
    public function testAddBagInfoTagsNotExtended(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertFalse($bag->isExtended());
        $inputTags = [
            'Source-organization' => 'The Pyramid',
            'CONTACT-NAME' => [
                'Bob Barker',
                'Monty Hall',
            ],
        ];
        $bag->addBagInfoTags($inputTags);
        $this->assertTrue($bag->isExtended());
        $this->assertTrue($bag->hasBagInfoTag('source-organization'));
        $this->assertTrue($bag->hasBagInfoTag('contact-name'));
        $this->assertArrayEquals(['The Pyramid'], $bag->getBagInfoByTag('source-organization'));
        $this->assertArrayEquals(['Monty Hall', 'Bob Barker'], $bag->getBagInfoByTag('contact-name'));
    }

    /**
     * Test that for a v1.0 bag you CAN'T have spaces at the start or end of a tag.
     * @group Extended
     * @covers ::loadBagInfo
     * @covers ::compareVersion
     */
    public function testInvalidBagInfov1(): void
    {
        $bag = Bag::create($this->tmpdir);
        copy(
            self::BAG_INFO_DIR . '/invalid-leading-spaces.txt',
            $bag->getBagRoot() . '/bag-info.txt'
        );
        touch($bag->getBagRoot() . '/manifest-md5.txt');
        $testbag = Bag::load($this->tmpdir);
        $this->assertCount(2, $testbag->getErrors());
    }

    /**
     * Test that for a v0.97 bag you CAN have spaces at the start or end of a tag.
     * @group Extended
     * @covers ::loadBagInfo
     * @covers ::compareVersion
     */
    public function testInvalidBagInfov097(): void
    {
        $bag = Bag::create($this->tmpdir);
        copy(
            self::BAG_INFO_DIR . '/invalid-leading-spaces.txt',
            $bag->getBagRoot() . '/bag-info.txt'
        );
        file_put_contents(
            $bag->getBagRoot() . '/bagit.txt',
            "BagIt-Version: 0.97" . PHP_EOL . "Tag-File-Character-Encoding: UTF-8" . PHP_EOL
        );
        touch($bag->getBagRoot() . '/manifest-md5.txt');
        $testbag = Bag::load($this->tmpdir);
        $this->assertCount(0, $testbag->getErrors());
    }

    /**
     * Test getting manifests for basic bag.
     * @group Extended
     * @covers ::load
     * @covers ::getPayloadManifests
     * @covers ::getTagManifests
     */
    public function testGetManifests(): void
    {
        $this->tmpdir = $this->prepareBasicTestBag();
        $bag = Bag::load($this->tmpdir);
        $payloads = $bag->getPayloadManifests();
        $this->assertCount(1, $payloads);
        $tags = $bag->getTagManifests();
        $this->assertCount(0, $tags);
    }

    /**
     * Test getting manifests for extended bag.
     * @group Extended
     * @covers ::load
     * @covers ::getPayloadManifests
     * @covers ::getTagManifests
     */
    public function testGetManifestsExtended(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $payloads = $bag->getPayloadManifests();
        $this->assertCount(1, $payloads);
        $tags = $bag->getTagManifests();
        $this->assertTrue(is_array($tags));
        $this->assertCount(1, $tags);
    }

    /**
     * Test payload-oxum calculation is only done once independent of how
     * many hash algorithm are used.
     * @group Extended
     * @covers ::calculateTotalFileSizeAndAmountOfFiles
     * @covers ::getBagInfoByTag
     * @covers ::update
     */
    public function testOxumCalculationForManyHashAlogrithm(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
        $this->assertTrue($bag->isExtended());
        $bag->addAlgorithm('SHA-224');
        $bag->update();

        $oxums = $bag->getBagInfoByTag('payload-oxum');
        $this->assertCount(1, $oxums);
        $this->assertEquals('408183.2', $oxums[0]);
    }

    /**
     * Test setting of bag-size tag.
     * @group Extended
     * @covers ::calculateTotalFileSizeAndAmountOfFiles
     * @covers ::convertToHumanReadable
     * @covers ::getBagInfoByTag
     * @covers ::update
     */
    public function testCalculatedBagSize(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);

        $bag->addFile(self::TEST_IMAGE['filename'], 'data/image1.jpg');
        $bag->update();
        $bagsize = $bag->getBagInfoByTag('bag-size');
        $this->assertCount(1, $bagsize);
        $this->assertEquals('787.53 KB', $bagsize[0]);
        $oxums = $bag->getBagInfoByTag('payload-oxum');
        $this->assertCount(1, $oxums);
        $this->assertEquals('806429.3', $oxums[0]);

        $bag->addFile(self::TEST_IMAGE['filename'], 'data/subdir/image1.jpg');
        $bag->addFile(self::TEST_IMAGE['filename'], 'data/subdir/image2.jpg');
        $bag->update();
        $bagsize = $bag->getBagInfoByTag('bag-size');
        $this->assertCount(1, $bagsize);
        $this->assertEquals('1.53 MB', $bagsize[0]);
        $oxums = $bag->getBagInfoByTag('payload-oxum');
        $this->assertCount(1, $oxums);
        $this->assertEquals('1602921.5', $oxums[0]);
    }

    /**
     * Test that long tag lines might contain colons and should still validate if
     * @group Extended
     * @covers ::loadBagInfo
     * @covers ::trimSpacesOnly
     */
    public function testLongBagInfoLinesWrap(): void
    {
        $bag = Bag::create($this->tmpdir);
        $title = 'A really long long long long long long long long long long long title'
            . ' with a colon : between and more information are on the way';
        $bag->addBagInfoTag('Title', $title);
        $bag->update();
        $testbag = Bag::load($this->tmpdir);
        $this->assertTrue($testbag->isValid());
        $this->assertEquals($title, $testbag->getBagInfoByTag('Title')[0]);
    }

    /**
     * Test loading long lines with internal newlines from a bag-info.txt
     * @group Extended
     * @covers ::loadBagInfo
     */
    public function testLoadWrappedLines(): void
    {
        $bag = Bag::create($this->tmpdir);
        copy(
            self::BAG_INFO_DIR . '/long-lines-and-line-returns.txt',
            $bag->getBagRoot() . '/bag-info.txt'
        );
        touch($bag->getBagRoot() . '/manifest-sha512.txt');

        // Load tag values as they exist on disk. Long lines (over 70 characters) get the newline removed
        $testbag = Bag::load($this->tmpdir);
        $this->assertCount(0, $testbag->getErrors());
        $this->assertEquals("This is some crazy information about a new way of searching for : the stuff. " .
            "Why do this?" . PHP_EOL . "Because we can.", $testbag->getBagInfoByTag('External-Description')[0]);
        $testbag->update();

        // We wrote the bag info again, so now it is stripped of newlines
        $testbag2 = Bag::load($this->tmpdir);
        $this->assertCount(0, $testbag2->getErrors());
        $this->assertEquals("This is some crazy information about a new way of searching for : the stuff. " .
            "Why do this? Because we can.", $testbag2->getBagInfoByTag('External-Description')[0]);
    }

    /**
     * Repeat the reading of a bag but with CR instead of LF endings.
     *
     * @covers \whikloj\BagItTools\AbstractManifest::loadFile
     *
     * @see \whikloj\BagItTools\Test\ExtendedBagTest::testLoadExtendedBag()
     */
    public function testLoadExtendedCRLineEndings(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $this->switchLineEndingsTo("\r");

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
        $this->assertFalse($bag->hasBagInfoTag('bag-size'));
    }

    /**
     * Repeat the reading of a bag but with CRLF instead of just LF endings.
     *
     * @covers \whikloj\BagItTools\AbstractManifest::loadFile
     *
     * @see \whikloj\BagItTools\Test\ExtendedBagTest::testLoadExtendedBag()
     */
    public function testLoadExtendedCRLFLineEndings(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        $this->switchLineEndingsTo("\r\n");

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
        $this->assertFalse($bag->hasBagInfoTag('bag-size'));
    }

    /**
     * Switch the line endings of the test extended bag from \r to
     *
     * @param string $newEnding
     *   What to switch the line endings to.
     */
    private function switchLineEndingsTo(string $newEnding): void
    {
        $files = [
            "bagit.txt",
            "bag-info.txt",
            "manifest-sha1.txt",
            "tagmanifest-sha1.txt",
        ];
        foreach ($files as $file) {
            $path = $this->tmpdir . '/' . $file;
            file_put_contents(
                $path,
                str_replace("\n", $newEnding, file_get_contents($path))
            );
        }
    }

    /**
     * Ensure that a bag-info that starts with a continuation is listed as an error.
     * @covers ::loadBagInfo
     */
    public function testBagInfoStartWithContinuation(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        // Alter the bag-info.txt
        file_put_contents(
            $this->tmpdir . '/bag-info.txt',
            "  the next line.\nExternal-Description: This is the start of a very long information that" .
            " is expected to wrap on to\n"
        );
        // Update the hash for bag-info.txt
        file_put_contents(
            $this->tmpdir . '/tagmanifest-sha1.txt',
            "1d9349f1fe77430540a75b996220b41d8ae571cf  bag-info.txt\n8010d7758f1793d0221c529fef818ff988dda141  " .
            "bagit.txt\nfdead00cc124f82eef20c051e699518c43adc561  manifest-sha1.txt\n" .
            "e939f78371e07a59c7a91e113618fd70cfa1e7ca  alt_tags/random_tags.txt\n"
        );
        $bag = Bag::load($this->tmpdir);
        $this->assertFalse($bag->isValid());
        $this->assertCount(1, $bag->getErrors());
        $this->assertEquals(
            "bag-info.txt",
            $bag->getErrors()[0]["file"]
        );
        $this->assertEquals(
            "Line 1: Appears to be continuation but there is no preceding tag.",
            $bag->getErrors()[0]["message"]
        );
    }

    /**
     * Ensure that a bag-info that has lines over 77 characters get autowrapped.
     * @covers ::loadBagInfo
     */
    public function testBagInfoWithLongLines(): void
    {
        $expected = [
            "tag" => "External-Description",
            "value" => "This is the start of a very long information that" .
            " is expected to wrap on to the next line eventually. This action will cause the line that comes" .
            " next to be placed directly in line with above line, no newline."
        ];
        $this->tmpdir = $this->prepareExtendedTestBag();
        // Alter the bag-info.txt
        file_put_contents(
            $this->tmpdir . '/bag-info.txt',
            "External-Description: This is the start of a very long information that" .
            " is expected to wrap on to the next line eventually. This action will cause the line that comes\n" .
            "\tnext to be placed directly in line with above line, no newline."
        );
        // Update the hash for bag-info.txt
        file_put_contents(
            $this->tmpdir . '/tagmanifest-sha1.txt',
            "bec1f727e714a0821610c4a2b28f9b0ef48e086b  bag-info.txt\n8010d7758f1793d0221c529fef818ff988dda141  " .
            "bagit.txt\nfdead00cc124f82eef20c051e699518c43adc561  manifest-sha1.txt\n" .
            "e939f78371e07a59c7a91e113618fd70cfa1e7ca  alt_tags/random_tags.txt\n"
        );
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
        $this->assertCount(0, $bag->getErrors());
        $this->assertCount(1, $bag->getBagInfoData());
        $this->assertArrayEquals($expected, $bag->getBagInfoData()[0]);
    }

    /**
     * Ensure that MUST not repeat fields are flagged as errors on load
     * @covers ::loadBagInfo
     * @covers ::mustNotRepeatBagInfoExists
     */
    public function testMustNotRepeatBagInfoTags(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        file_put_contents(
            $this->tmpdir . "/bag-info.txt",
            "Payload-Oxum: 19845.3\nSource-Organization: Museum of Arts and Crafts\nPayload-Oxum: 19845.3\n" .
            "External-Description: This file will fail because you can only have one payload-oxum line.\n"
        );
        file_put_contents(
            $this->tmpdir . '/tagmanifest-sha1.txt',
            "39546af37cf32cfce71b8d88a4aa4829105e11e6  bag-info.txt\n8010d7758f1793d0221c529fef818ff988dda141  " .
            "bagit.txt\nfdead00cc124f82eef20c051e699518c43adc561  manifest-sha1.txt\n" .
            "e939f78371e07a59c7a91e113618fd70cfa1e7ca  alt_tags/random_tags.txt\n"
        );
        $bag = Bag::load($this->tmpdir);
        $this->assertFalse($bag->isValid());
        $this->assertCount(1, $bag->getErrors());
        $expected = [
            "file" => "bag-info.txt",
            "message" => "Line 3: Tag Payload-Oxum MUST not be repeated."
        ];
        $this->assertArrayEquals($expected, $bag->getErrors()[0]);
    }

    /**
     * Ensure that SHOULD not repeat fields are flagged as warnings on load
     * @covers ::loadBagInfo
     * @covers ::shouldNotRepeatBagInfoExists
     */
    public function testShouldNotRepeatBagInfoTags(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        file_put_contents(
            $this->tmpdir . "/bag-info.txt",
            "Payload-Oxum: 19845.3\nBag-Size: 2MB\nSource-Organization: Museum of Arts and Crafts\n" .
            "External-Description: This file will fail because you can only have one payload-oxum line.\n" .
            "Bag-Size: 2MB\n"
        );
        file_put_contents(
            $this->tmpdir . '/tagmanifest-sha1.txt',
            "1a8c17edfec75662a05fad0276a08212af53c656  bag-info.txt\n8010d7758f1793d0221c529fef818ff988dda141  " .
            "bagit.txt\nfdead00cc124f82eef20c051e699518c43adc561  manifest-sha1.txt\n" .
            "e939f78371e07a59c7a91e113618fd70cfa1e7ca  alt_tags/random_tags.txt\n"
        );
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
        $this->assertCount(0, $bag->getErrors());
        $this->assertCount(1, $bag->getWarnings());
        $expected = [
            "file" => "bag-info.txt",
            "message" => "Line 5: Tag Bag-Size SHOULD NOT be repeated."
        ];
        $this->assertArrayEquals($expected, $bag->getWarnings()[0]);
    }

    /**
     * Ensure a bag with a bag-info and no tagmanifest-*.txt still validates.
     */
    public function testExtendedBagWithoutTagManifest(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        # Remove extra tag files and tagmanifests
        unlink($this->tmpdir . "/alt_tags/random_tags.txt");
        rmdir($this->tmpdir . '/alt_tags');
        unlink($this->tmpdir . "/tagmanifest-sha1.txt");
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
    }

    /**
     * Ensure a bag with a tagmanifest-*.txt and no bag-info still validates.
     */
    public function testExtendedBagWithoutBagInfo(): void
    {
        $this->tmpdir = $this->prepareExtendedTestBag();
        # Remove bag-info file and update tagmanifest
        unlink($this->tmpdir . "/bag-info.txt");
        file_put_contents(
            $this->tmpdir . '/tagmanifest-sha1.txt',
            "8010d7758f1793d0221c529fef818ff988dda141  bagit.txt\n" .
            "fdead00cc124f82eef20c051e699518c43adc561  manifest-sha1.txt\n" .
            "e939f78371e07a59c7a91e113618fd70cfa1e7ca  alt_tags/random_tags.txt"
        );
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
    }

    /**
     * Test trying to add a tag file with a non-existant file.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagFileFileDoesNotExist(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("/path/to/nowhere.txt does not exist, is not a file or is not readable.");
        $bag->addTagFile('/path/to/nowhere.txt', 'nowhere.txt');
    }

    /**
     * Test trying to add a tag file over the bag-info.txt file.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagFileCannotOverwriteBagInfo(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter reserved file (bag-info.txt) file with your own tag file.");
        $bag->addTagFile($special_file, 'bag-info.txt');
    }

    /**
     * Test trying to add a tag file over the bagit.txt file.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagCannotOverwriteBagIt(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter reserved file (bagit.txt) file with your own tag file.");
        $bag->addTagFile($special_file, 'bagit.txt');
    }

    /**
     * Test trying to add a tag file over a payload manifest file.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagCannotOverwritePayloadManifest(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $bag->addAlgorithm('sha256');
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter a manifest or tag manifest file with your own tag file.");
        $bag->addTagFile($special_file, 'manifest-sha256.txt');
    }

    /**
     * Test trying to add a tag file over a tag manifest file.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagCannotOverwriteTagManifest(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->setExtended(true);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $bag->addAlgorithm('sha256');
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter a manifest or tag manifest file with your own tag file.");
        $bag->addTagFile($special_file, 'tagmanifest-sha256.txt');
    }

    /**
     * Test trying to add a tag file to the data directory.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagCannotBeInDataDirectory(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Tag files must be in the bag root or a tag file directory");
        $bag->addTagFile($special_file, 'data/special.txt');
    }

    /**
     * Test trying to add a tag file to the data directory.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagCannotBeInDataDirectory2(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Tag files must be in the bag root or a tag file directory");
        $bag->addTagFile($special_file, './data/special.txt');
    }

    /**
     * Test adding a tag file to the bag.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagNotOverwrite(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addTagFile($special_file, 'special.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special.txt');
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Tag file (special.txt) already exists in the bag.");
        $bag->addTagFile($special_file, 'special.txt');
    }

    /**
     * Test adding a tag file to the bag.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagFileSuccess(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addTagFile($special_file, 'special.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special.txt');
    }

    /**
     * Test adding tag files in a subdirectory
     * @group Extended
     * @covers ::addTagFile
     */
    public function testAddTagFileInSubDir(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addTagFile($special_file, 'special/special.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special/special.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special');
        $bag->addTagFile($special_file, 'special/special2.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special/special2.txt');
    }

    /**
     * Test trying to add a tag file with a non-existant file.
     * @group Extended
     * @covers ::removeTagFile
     */
    public function testRemoveTagFileFileDoesNotExist(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Tag file (nowhere.txt) does not exist in the bag.");
        $bag->removeTagFile('nowhere.txt');
    }

    /**
     * Test trying to remove the bag-info.txt file using removeTagFile.
     * @group Extended
     * @covers ::removeTagFile
     */
    public function testRemoveTagFileCannotOverwriteBagInfo(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $bag->update(); // Update to create the bag-info.txt on disk.
        $this->assertTrue(file_exists($bag->getBagRoot() . '/bag-info.txt'));
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter reserved file (bag-info.txt) file with your own tag file.");
        $bag->removeTagFile('bag-info.txt');
        $this->assertTrue(file_exists($bag->getBagRoot() . '/bag-info.txt'));
    }

    /**
     * Test trying to remove the bagit.txt file using removeTagFile.
     * @group Extended
     * @covers ::addTagFile
     */
    public function testRemoveTagCannotOverwriteBagIt(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertTrue(file_exists($bag->getBagRoot() . '/bagit.txt'));
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter reserved file (bagit.txt) file with your own tag file.");
        $bag->removeTagFile('bagit.txt');
        $this->assertTrue(file_exists($bag->getBagRoot() . '/bagit.txt'));
    }

    /**
     * Test trying to remove a payload manifest file using removeTagFile.
     * @group Extended
     * @covers ::removeTagFile
     */
    public function testRemoveTagCannotOverwritePayloadManifest(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $bag->setAlgorithm('sha256');
        $bag->update();
        $this->assertTrue(file_exists($bag->getBagRoot() . '/manifest-sha256.txt'));
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter a manifest or tag manifest file with your own tag file.");
        $bag->removeTagFile('manifest-sha256.txt');
        $this->assertTrue(file_exists($bag->getBagRoot() . '/manifest-sha256.txt'));
    }

    /**
     * Test trying to remove a tag manifest file using removeTagFile.
     * @group Extended
     * @covers ::removeTagFile
     */
    public function testRemoveTagCannotOverwriteTagManifest(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('Contact-NAME', 'Monty Hall');
        $bag->setAlgorithm('sha256');
        $bag->update();
        $this->assertTrue(file_exists($bag->getBagRoot() . '/tagmanifest-sha256.txt'));
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("You cannot alter a manifest or tag manifest file with your own tag file.");
        $bag->removeTagFile('tagmanifest-sha256.txt');
        $this->assertTrue(file_exists($bag->getBagRoot() . '/tagmanifest-sha256.txt'));
    }

    /**
     * Test trying to remove a file from the data directory using removeTagFile.
     * @group Extended
     * @covers ::removeTagFile
     */
    public function testRemoveTagCannotBeInDataDirectory(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addFile(self::TEST_RESOURCES . '/text/empty.txt', 'data/empty.txt');
        $this->assertTrue(file_exists($bag->getDataDirectory() . '/empty.txt'));
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Tag files must be in the bag root or a tag file directory");
        $bag->removeTagFile('data/special.txt');
        $this->assertTrue(file_exists($bag->getDataDirectory() . '/empty.txt'));
    }

    /**
     * Test trying to remove a file from the data directory using removeTagFile.
     * @group Extended
     * @covers ::removeTagFile
     */
    public function testRemoveTagCannotBeInDataDirectory2(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addFile(self::TEST_RESOURCES . '/text/empty.txt', 'data/empty.txt');
        $this->assertTrue(file_exists($bag->getDataDirectory() . '/empty.txt'));
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Tag files must be in the bag root or a tag file directory");
        $bag->removeTagFile('./data/special.txt');
        $this->assertTrue(file_exists($bag->getDataDirectory() . '/empty.txt'));
    }

    /**
     * Test adding and removing multiple tag files in a tag file subdirectory.
     * @group Extended
     * @covers ::addTagFile
     * @covers ::removeTagFile
     */
    public function testAddAndRemoveTagFileInSubDir(): void
    {
        $special_file = self::TEST_RESOURCES . '/tagFiles/SomeSpecialTags.txt';
        $bag = Bag::create($this->tmpdir);
        $bag->addTagFile($special_file, 'special/special.txt');
        $bag->addTagFile($special_file, 'special/special2.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special/special.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special/special2.txt');
        $bag->removeTagFile('special/special2.txt');
        $this->assertFileExists($bag->getBagRoot() . '/special/special.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/special/special2.txt');
        $bag->removeTagFile('special/special.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/special/special.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/special/special2.txt');
        $this->assertFileDoesNotExist($bag->getBagRoot() . '/special');
    }
}
