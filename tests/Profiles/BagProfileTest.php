<?php

namespace whikloj\BagItTools\Test\Profiles;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Exceptions\ProfileException;
use whikloj\BagItTools\Profiles\BagItProfile;
use whikloj\BagItTools\Test\BagItTestFramework;

/**
 * Test BagItProfile against the specifications foo
 * @package Profiles
 * @coversDefaultClass \whikloj\BagItTools\Profiles\BagItProfile
 */
class BagProfileTest extends BagItTestFramework
{
    private static $profiles = self::TEST_RESOURCES . '/profiles';

    /**
     * Try to validate a bag.
     * @group Profiles
     * @covers ::validateBag
     */
    public function testValidateBag1(): void
    {
        $profile = BagItProfile::fromJson(file_get_contents(self::$profiles . "/bagProfileFoo.json"));
        $this->assertTrue($profile->isValid());
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
        $this->expectException(ProfileException::class);
        $profile->validateBag($bag);
    }

    /**
     * Try to load a bad profile.
     * @group Profiles
     * @covers ::setBagInfoTags
     */
    public function testInvalidBagInfoTags(): void
    {
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Invalid tag options for Source-Organization");
        BagItProfile::fromJson(file_get_contents(self::$profiles . "/test_profiles/invalid_bag_info_tag_options.json"));
    }

    /**
     * Try to load a profile with the BagIt-Profile-Identifier specified in the Bag-Info tags.
     * @group Profiles
     * @covers ::setBagInfoTags
     */
    public function testBagItProfileIdentifierInTags(): void
    {
        $profile = BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/profile_identifier_bag_info_tag.json"
        ));
        $this->assertArrayEquals(
            [
            "The tag BagIt-Profile-Identifier is always required, but SHOULD NOT be listed under Bag-Info in " .
            "the Profile."
            ],
            $profile->getWarnings()
        );
    }

    /**
     * @group Profiles
     * @covers ::setManifestsAllowed
     */
    public function testSetManifestAllowed(): void
    {
        $profile = BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/set_manifest_allowed_valid.json"
        ));
        $this->assertTrue($profile->isValid());
    }

    /**
     * @group Profiles
     * @covers ::setManifestsAllowed
     */
    public function testSetManifestAllowedInvalid(): void
    {
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Manifests-Allowed must include all entries from Manifests-Required");
        BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/set_manifest_allowed_invalid.json"
        ));
    }

    /**
     * @group Profiles
     * @covers ::setAllowFetchTxt
     */
    public function testAllowFetchInvalid(): void
    {
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Allow-Fetch.txt cannot be false if Require-Fetch.txt is true");
        BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/allow_fetch_invalid.json"
        ));
    }

    /**
     * @group Profiles
     * @covers ::setRequireFetchTxt
     */
    public function testAllowFetchInvalid2(): void
    {
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Allow-Fetch.txt cannot be false if Require-Fetch.txt is true");
        BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/allow_fetch_invalid_2.json"
        ));
    }

    /**
     * @group Profiles
     * @covers ::setDataEmpty
     * @covers ::validateBag
     */
    public function testDataEmpty(): void
    {
        $profile = BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/data_empty.json"
        ));
        $this->assertTrue($profile->isValid());
        $bag = Bag::create($this->tmpdir);
        $bag->update();
        // Empty data is valid.
        $this->assertTrue($profile->validateBag($bag));

        $bag->addFile(self::TEST_RESOURCES . "/text/empty.txt", "empty.txt");
        $bag->update();
        // A single zero byte file is valid.
        $this->assertTrue($profile->validateBag($bag));

        $bag->addFile(self::TEST_RESOURCES . "/text/empty.txt", "empty2.txt");
        $bag->update();
        // Two zero byte files is not valid.
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Profile requires /data directory to be empty or contain a single 0 " .
            "byte file but it contains 2 files");
        $profile->validateBag($bag);

        $bag->removeFile("empty2.txt");
        $bag->removeFile("empty.txt");
        $bag->update();
        // Empty data directory is valid.
        $this->assertTrue($profile->validateBag($bag));

        $bag->addFile(self::TEST_RESOURCES . "/images/scenic-landscape.jpg", "scenic-landscape.jpg");
        $bag->update();
        // A single non-zero byte file is not valid.
        $this->assertFalse($profile->validateBag($bag));
    }

    /**
     * @group Profiles
     * @covers ::setDataEmpty
     * @covers ::validateBag
     */
    public function testDataEmpty2(): void
    {
        $profile = BagItProfile::fromJson(file_get_contents(
            self::$profiles . "/test_profiles/data_empty.json"
        ));
        $this->assertTrue($profile->isValid());
        $bag = Bag::create($this->tmpdir);
        $bag->update();
        // Empty data is valid.
        $this->assertTrue($profile->validateBag($bag));

        $bag->addFile(self::TEST_RESOURCES . "/images/scenic-landscape.jpg", "scenic-landscape.jpg");
        $bag->update();
        // A single non-zero byte file is not valid.
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Profile requires /data directory to be empty or contain a single 0 byte " .
            "file but it contains a single file of size 398246");
        $profile->validateBag($bag);
    }
}
