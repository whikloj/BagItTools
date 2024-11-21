<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test\Profiles;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Exceptions\ProfileException;
use whikloj\BagItTools\Profiles\BagItProfile;
use whikloj\BagItTools\Profiles\ProfileFactory;
use whikloj\BagItTools\Test\BagItWebserverFramework;

/**
 * Tests of the BagItProfile and ProfileFactory class.
 * @coversDefaultClass \whikloj\BagItTools\Profiles\BagItProfile
 */
abstract class ProfileTestFramework extends BagItWebserverFramework
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
            'profile_btr.json' => [
                'filename' => self::PROFILE_DIR . '/btrProfile.json',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        ];
        parent::setUpBeforeClass();
    }

    /**
     * @var BagItProfile The profile loaded from a URI.
     */
    protected BagItProfile $uriProfile;

    /**
     * @var BagItProfile The profile loaded from a JSON file.
     */
    protected BagItProfile $jsonProfile;

    /**
     * @var array<string, mixed> An array of the test values to match against the profile.
     */
    protected array $profileValues = [];

    public function setUp(): void
    {
        parent::setUp();

        $json = file_get_contents($this->getProfileFilename());
        if ($json === false) {
            throw new \Exception("Failed to read profile file.");
        }
        $this->jsonProfile = BagItProfile::fromJson($json);
        $this->assertTrue($this->jsonProfile->isValid());

        $this->uriProfile = ProfileFactory::generateProfileFromUri($this->getProfileUri());
        $this->assertTrue($this->uriProfile->isValid());

        $this->profileValues = $this->getProfileValues();
    }

    /**
     * @return string The path to the profile JSON file.
     */
    abstract protected function getProfileFilename(): string;

    /**
     * @return string The URI to the profile.
     */
    abstract protected function getProfileUri(): string;

    /**
     * @return array<string, mixed> The expected values of the profile.
     */
    abstract protected function getProfileValues(): array;

    /**
     * @group Profiles
     * @covers ::fromJson
     * @covers \whikloj\BagItTools\Profiles\ProfileFactory::generateProfileFromUri
     * @covers ::isValid
     * @covers ::setProfileIdentifier
     * @covers ::getProfileIdentifier
     */
    public function testProfileIdentifier(): void
    {
        $this->assertEquals(
            $this->profileValues['identifier'],
            $this->jsonProfile->getProfileIdentifier()
        );
        $this->assertEquals(
            $this->profileValues['identifier'],
            $this->uriProfile->getProfileIdentifier()
        );
    }

    /**
     * @group Profiles
     * @covers ::setProfileSpecVersion
     * @covers ::getProfileSpecVersion
     */
    public function testProfileSpecVersion(): void
    {
        $this->assertEquals(
            $this->profileValues['spec_version'],
            $this->jsonProfile->getProfileSpecVersion()
        );
        $this->assertEquals(
            $this->profileValues['spec_version'],
            $this->uriProfile->getProfileSpecVersion()
        );
    }

    /**
     * @group Profiles
     * @covers ::setVersion
     * @covers ::getVersion
     */
    public function testProfileVersion(): void
    {
        $this->assertEquals(
            $this->profileValues['version'],
            $this->jsonProfile->getVersion()
        );
        $this->assertEquals(
            $this->profileValues['version'],
            $this->uriProfile->getVersion()
        );
    }

    /**
     * @group Profiles
     * @covers ::setSourceOrganization
     * @covers ::getSourceOrganization
     */
    public function testGetSourceOrganization(): void
    {
        $this->assertEquals(
            $this->profileValues['source_organization'],
            $this->jsonProfile->getSourceOrganization()
        );
        $this->assertEquals(
            $this->profileValues['source_organization'],
            $this->uriProfile->getSourceOrganization()
        );
    }

    /**
     * @group Profiles
     * @covers ::setExternalDescription
     * @covers ::getExternalDescription
     */
    public function testGetExternalDescription(): void
    {
        $this->assertEquals(
            $this->profileValues['external_description'],
            $this->jsonProfile->getExternalDescription()
        );
        $this->assertEquals(
            $this->profileValues['external_description'],
            $this->uriProfile->getExternalDescription()
        );
    }

    /**
     * @group Profiles
     * @covers ::setContactName
     * @covers ::getContactName
     */
    public function testGetContactName(): void
    {
        $this->assertEquals(
            $this->profileValues['contact_name'],
            $this->jsonProfile->getContactName()
        );
        $this->assertEquals(
            $this->profileValues['contact_name'],
            $this->uriProfile->getContactName()
        );
    }

    /**
     * @group Profiles
     * @covers ::setContactEmail
     * @covers ::getContactEmail
     */
    public function testGetContactEmail(): void
    {
        $this->assertEquals(
            $this->profileValues['contact_email'],
            $this->jsonProfile->getContactEmail()
        );
        $this->assertEquals(
            $this->profileValues['contact_email'],
            $this->uriProfile->getContactEmail()
        );
    }

    /**
     * @group Profiles
     * @covers ::setContactPhone
     * @covers ::getContactPhone
     */
    public function testGetContactPhone(): void
    {
        $this->assertEquals(
            $this->profileValues['contact_phone'],
            $this->jsonProfile->getContactPhone()
        );
        $this->assertEquals(
            $this->profileValues['contact_phone'],
            $this->uriProfile->getContactPhone()
        );
    }

    /**
     * @group Profiles
     * @covers ::setBagInfoTags
     * @covers ::getBagInfoTags
     */
    public function testGetBagInfoTags(): void
    {
        $this->assertProfileBagInfoTags($this->profileValues['bag_info_tags'], $this->jsonProfile);
        $this->assertProfileBagInfoTags($this->profileValues['bag_info_tags'], $this->uriProfile);
    }

    /**
     * @group Profiles
     * @covers ::setManifestsRequired
     * @covers ::getManifestsRequired
     */
    public function testManifestRequired(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['manifests_required'],
            $this->jsonProfile->getManifestsRequired()
        );
        $this->assertArrayEquals(
            $this->profileValues['manifests_required'],
            $this->uriProfile->getManifestsRequired()
        );
    }


    /**
     * @group Profiles
     * @covers ::setManifestsAllowed
     * @covers ::getManifestsAllowed
     */
    public function testManifestAllowed(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['manifests_allowed'],
            $this->jsonProfile->getManifestsAllowed()
        );
        $this->assertArrayEquals(
            $this->profileValues['manifests_allowed'],
            $this->uriProfile->getManifestsAllowed()
        );
    }

    /**
     * @group Profiles
     * @covers ::setAllowFetchTxt
     * @covers ::isAllowFetchTxt
     */
    public function testAllowFetchTxt(): void
    {
        $this->assertEquals(
            $this->profileValues['allow_fetch_txt'],
            $this->jsonProfile->isAllowFetchTxt()
        );
        $this->assertEquals(
            $this->profileValues['allow_fetch_txt'],
            $this->uriProfile->isAllowFetchTxt()
        );
    }

    /**
     * @group Profiles
     * @covers ::setRequireFetchTxt
     * @covers ::isRequireFetchTxt
     */
    public function testRequireFetchTxt(): void
    {
        $this->assertEquals(
            $this->profileValues['require_fetch_txt'],
            $this->jsonProfile->isRequireFetchTxt()
        );
        $this->assertEquals(
            $this->profileValues['require_fetch_txt'],
            $this->uriProfile->isRequireFetchTxt()
        );
    }

    /**
     * @group Profiles
     * @covers ::setDataEmpty
     * @covers ::isDataEmpty
     */
    public function testDataEmpty(): void
    {
        $this->assertEquals(
            $this->profileValues['data_empty'],
            $this->jsonProfile->isDataEmpty()
        );
        $this->assertEquals(
            $this->profileValues['data_empty'],
            $this->uriProfile->isDataEmpty()
        );
    }

    /**
     * @group Profiles
     * @covers ::setSerialization
     * @covers ::getSerialization
     */
    public function testSerialization(): void
    {
        $this->assertEquals(
            $this->profileValues['serialization'],
            $this->jsonProfile->getSerialization()
        );
        $this->assertEquals(
            $this->profileValues['serialization'],
            $this->uriProfile->getSerialization()
        );
    }

    /**
     * @group Profiles
     * @covers ::setAcceptSerialization
     * @covers ::getAcceptSerialization
     */
    public function testAcceptSerialization(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['accept_serialization'],
            $this->jsonProfile->getAcceptSerialization()
        );
        $this->assertArrayEquals(
            $this->profileValues['accept_serialization'],
            $this->uriProfile->getAcceptSerialization()
        );
    }

    /**
     * @group Profiles
     * @covers ::setAcceptBagItVersion
     * @covers ::getAcceptBagItVersion
     */
    public function testAcceptBagItVersion(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['accept_bagit_version'],
            $this->jsonProfile->getAcceptBagItVersion()
        );
        $this->assertArrayEquals(
            $this->profileValues['accept_bagit_version'],
            $this->uriProfile->getAcceptBagItVersion()
        );
    }

    /**
     * @group Profiles
     * @covers ::setTagManifestsRequired
     * @covers ::getTagManifestsRequired
     */
    public function testTagManifestsRequired(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['tag_manifests_required'],
            $this->jsonProfile->getTagManifestsRequired()
        );
        $this->assertArrayEquals(
            $this->profileValues['tag_manifests_required'],
            $this->uriProfile->getTagManifestsRequired()
        );
    }

    /**
     * @group Profiles
     * @covers ::setTagManifestsAllowed
     * @covers ::getTagManifestsAllowed
     */
    public function testTagManifestsAllowed(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['tag_manifests_allowed'],
            $this->jsonProfile->getTagManifestsAllowed()
        );
        $this->assertArrayEquals(
            $this->profileValues['tag_manifests_allowed'],
            $this->uriProfile->getTagManifestsAllowed()
        );
    }

    /**
     * @group Profiles
     * @covers ::setTagFilesRequired
     * @covers ::getTagFilesRequired
     */
    public function testTagFilesRequired(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['tag_files_required'],
            $this->jsonProfile->getTagFilesRequired()
        );
        $this->assertArrayEquals(
            $this->profileValues['tag_files_required'],
            $this->uriProfile->getTagFilesRequired()
        );
    }

    /**
     * @group Profiles
     * @covers ::setTagFilesAllowed
     * @covers ::getTagFilesAllowed
     */
    public function testTagFilesAllowed(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['tag_files_allowed'],
            $this->jsonProfile->getTagFilesAllowed()
        );
        $this->assertArrayEquals(
            $this->profileValues['tag_files_allowed'],
            $this->uriProfile->getTagFilesAllowed()
        );
    }

    /**
     * @group Profiles
     * @covers ::setPayloadFilesRequired
     * @covers ::getPayloadFilesRequired
     */
    public function testPayloadFilesRequired(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['payload_files_required'],
            $this->jsonProfile->getPayloadFilesRequired()
        );
        $this->assertArrayEquals(
            $this->profileValues['payload_files_required'],
            $this->uriProfile->getPayloadFilesRequired()
        );
    }

    /**
     * @group Profiles
     * @covers ::setPayloadFilesAllowed
     * @covers ::getPayloadFilesAllowed
     */
    public function testPayloadFilesAllowed(): void
    {
        $this->assertArrayEquals(
            $this->profileValues['payload_files_allowed'],
            $this->jsonProfile->getPayloadFilesAllowed()
        );
        $this->assertArrayEquals(
            $this->profileValues['payload_files_allowed'],
            $this->uriProfile->getPayloadFilesAllowed()
        );
    }

    /**
     * Assert the bag-info tags are as expected.
     * @param array<string, string|bool|array<string>|mixed> $expected The expected tags.
     * @param BagItProfile $profile The profile to check.
     */
    protected function assertProfileBagInfoTags(array $expected, BagItProfile $profile): void
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
