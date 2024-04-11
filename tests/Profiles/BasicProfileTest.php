<?php

namespace whikloj\BagItTools\Test\Profiles;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Exceptions\ProfileException;
use whikloj\BagItTools\Profiles\BagItProfile;
use whikloj\BagItTools\Profiles\ProfileFactory;
use whikloj\BagItTools\Test\BagItWebserverFramework;

/**
 * Tests of the BagItProfile and ProfileFactory class.
 */
class BasicProfileTest extends BagItWebserverFramework
{
    private const PROFILE_DIR = self::TEST_RESOURCES . '/profiles';

    public static function setUpBeforeClass(): void
    {
        self::$webserver_files = [
            'profile_foo.json' => [
                'filename' => self::PROFILE_DIR . '/bagProfileFoo.json',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
            'profile_bar.json' => [
                'filename' => self::PROFILE_DIR . '/bagProfileBar.json',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
        ];
        parent::setUpBeforeClass();
    }

    /**
     * Test the first example profile from the specification.
     * @throws ProfileException
     * @covers \whikloj\BagItTools\Profiles\BagItProfile::fromJson
     */
    public function testSpecProfileFoo(): void
    {
        $json = file_get_contents(self::TEST_RESOURCES . '/profiles/bagProfileFoo.json');
        $profile = BagItProfile::fromJson($json);
        $this->assertTrue($profile->isValid());
        $this->assertExampleProfileFoo($profile);
    }

    /**
     * Test the second example profile from the specification.
     * @throws ProfileException
     * @covers \whikloj\BagItTools\Profiles\BagItProfile::fromJson
     */
    public function testSpecProfileBar(): void
    {
        $json = file_get_contents(self::TEST_RESOURCES . '/profiles/bagProfileBar.json');
        $profile = BagItProfile::fromJson($json);
        $this->assertTrue($profile->isValid());
        $this->assertExampleProfileBar($profile);
    }

    /**
     * Test the first example profile retrieved from webserver.
     * @throws ProfileException
     * @covers \whikloj\BagItTools\Profiles\ProfileFactory::generateProfileFromUri
     */
    public function testFactoryFoo(): void
    {
        $profile = ProfileFactory::generateProfileFromUri(self::$remote_urls[0]);
        $this->assertTrue($profile->isValid());
        $this->assertExampleProfileFoo($profile);
    }

    /**
     * Test the second example profile retrieved from webserver.
     * @throws ProfileException
     * @covers \whikloj\BagItTools\Profiles\ProfileFactory::generateProfileFromUri
     */
    public function testFactoryBar(): void
    {
        $profile = ProfileFactory::generateProfileFromUri(self::$remote_urls[1]);
        $this->assertTrue($profile->isValid());
        $this->assertExampleProfileBar($profile);
    }

    /**
     * Validate the BagItProfile specification example bagProfileFoo
     * @param BagItProfile $profile The profile to check.
     */
    private function assertExampleProfileFoo(BagItProfile $profile): void
    {
        $this->assertEquals(
            'http://www.library.yale.edu/mssa/bagitprofiles/disk_images.json',
            $profile->getProfileIdentifier()
        );
        $this->assertEquals('1.1.0', $profile->getProfileSpecVersion());
        $this->assertEquals('0.3', $profile->getVersion());
        $this->assertEquals('Yale University', $profile->getSourceOrganization());
        $this->assertEquals('BagIt Profile for packaging disk images', $profile->getExternalDescription());
        $this->assertEquals('Mark Matienzo', $profile->getContactName());
        $this->assertNull($profile->getContactEmail());
        $this->assertNull($profile->getContactPhone());
        $expected_tags = [
            'Bagging-Date' => [
                'required' => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            'Source-Organization' => [
                'required' => true,
                'values' => [
                    'Simon Fraser University',
                    'York University',
                ],
                'repeatable' => true,
                'description' => '',
            ],
        ];
        $this->assertProfileBagInfoTags($expected_tags, $profile);
        $this->assertArrayEquals(['md5'], $profile->getManifestsRequired());
        $this->assertArrayEquals([], $profile->getManifestsAllowed());
        $this->assertArrayEquals([], $profile->getTagManifestsRequired());
        $this->assertArrayEquals([], $profile->getTagManifestsAllowed());
        $this->assertArrayEquals([], $profile->getTagFilesRequired());
        $this->assertArrayEquals([], $profile->getTagFilesAllowed());
        $this->assertFalse($profile->isAllowFetchTxt());
        $this->assertFalse($profile->isRequireFetchTxt());
        $this->assertFalse($profile->isDataEmpty());
        $this->assertEquals("required", $profile->getSerialization());
        $this->assertArrayEquals(
            [
                "application/zip",
                "application/tar"
            ],
            $profile->getAcceptSerialization()
        );
        $this->assertArrayEquals(
            [
                "0.96",
                "0.97",
            ],
            $profile->getAcceptBagItVersion()
        );
        $this->assertArrayEquals([], $profile->getPayloadFilesRequired());
        $this->assertArrayEquals([], $profile->getPayloadFilesAllowed());
    }

    /**
     * Validate the BagItProfile specification example bagProfileBar
     * @param BagItProfile $profile The profile to check.
     */
    private function assertExampleProfileBar(BagItProfile $profile): void
    {
        $this->assertEquals(
            'http://canadiana.org/standards/bagit/tdr_ingest.json',
            $profile->getProfileIdentifier()
        );
        $this->assertEquals('1.2.0', $profile->getProfileSpecVersion());
        $this->assertEquals('1.2', $profile->getVersion());
        $this->assertEquals('Candiana.org', $profile->getSourceOrganization());
        $this->assertEquals(
            'BagIt Profile for ingesting content into the C.O. TDR loading dock.',
            $profile->getExternalDescription()
        );
        $this->assertEquals('William Wueppelmann', $profile->getContactName());
        $this->assertEquals('tdr@canadiana.com', $profile->getContactEmail());
        $this->assertNull($profile->getContactPhone());
        $expected_tags = [
            "Source-Organization" => [
                "required" => true,
                "values" => [
                    "Simon Fraser University",
                    "York University"
                ],
                'repeatable' => true,
                'description' => '',
            ],
            "Organization-Address" => [
                "required" => true,
                "values" => [
                    "8888 University Drive Burnaby, B.C. V5A 1S6 Canada",
                    "4700 Keele Street Toronto, Ontario M3J 1P3 Canada"
                ],
                'repeatable' => true,
                'description' => '',
            ],
            "Contact-Name" => [
                "required" => true,
                "values" => [
                    "Mark Jordan",
                    "Nick Ruest"
                ],
                'repeatable' => true,
                'description' => '',
            ],
            "Contact-Phone" => [
                "required" => false,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Contact-Email" => [
                "required" => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "External-Description" => [
                "required" => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "External-Identifier" => [
                "required" => false,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Bag-Size" => [
                "required" => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Bag-Group-Identifier" => [
                "required" => false,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Bag-Count" => [
                "required" => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Internal-Sender-Identifier" => [
                "required" => false,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Internal-Sender-Description" => [
                "required" => false,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Bagging-Date" => [
                "required" => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
            "Payload-Oxum" => [
                "required" => true,
                'values' => [],
                'repeatable' => true,
                'description' => '',
            ],
        ];
        $this->assertProfileBagInfoTags($expected_tags, $profile);
        $this->assertArrayEquals(['md5'], $profile->getManifestsRequired());
        $this->assertArrayEquals([], $profile->getManifestsAllowed());
        $this->assertArrayEquals(['md5'], $profile->getTagManifestsRequired());
        $this->assertArrayEquals([], $profile->getTagManifestsAllowed());
        $this->assertArrayEquals(
            [
                "DPN/dpnFirstNode.txt",
                "DPN/dpnRegistry"
            ],
            $profile->getTagFilesRequired()
        );
        $this->assertArrayEquals([], $profile->getTagFilesAllowed());
        $this->assertFalse($profile->isAllowFetchTxt());
        $this->assertFalse($profile->isRequireFetchTxt());
        $this->assertFalse($profile->isDataEmpty());
        $this->assertEquals("optional", $profile->getSerialization());
        $this->assertArrayEquals(
            [
                "application/zip",
            ],
            $profile->getAcceptSerialization()
        );
        $this->assertArrayEquals(
            [
                "0.96",
            ],
            $profile->getAcceptBagItVersion()
        );
        $this->assertArrayEquals([], $profile->getPayloadFilesRequired());
        $this->assertArrayEquals([], $profile->getPayloadFilesAllowed());
    }

    public function testValidateBag1(): void
    {
        $profile = ProfileFactory::generateProfileFromUri(self::$remote_urls[0]);
        $this->assertTrue($profile->isValid());
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
        $this->expectException(ProfileException::class);
        $profile->validateBag($bag);
    }

    /**
     * Assert the bag-info tags are as expected.
     * @param array $expected The expected tags.
     * @param BagItProfile $profile The profile to check.
     */
    private function assertProfileBagInfoTags(array $expected, BagItProfile $profile): void
    {
        foreach ($expected as $tag => $value) {
            $this->assertArrayHasKey(strtolower($tag), $profile->getBagInfoTags());
            $profileTag = $profile->getBagInfoTags()[strtolower($tag)];
            $this->assertEquals($value['required'], $profileTag->isRequired());
            $this->assertEquals($value['repeatable'], $profileTag->isRepeatable());
            $this->assertArrayEquals($value['values'], $profileTag->getValues());
            $this->assertEquals($value['description'], $profileTag->getDescription());
        }
    }
}
