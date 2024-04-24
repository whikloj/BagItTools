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
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Bag-Info":{
    "Source-Organization":{
      "required":true,
      "values":[
        "Simon Fraser University",
        "York University"
      ],
      "help": "This is the organization that originally created the bag."
    }
  },
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Invalid tag options for Source-Organization");
        BagItProfile::fromJson($profileJson);
    }

    /**
     * Try to load a profile with the BagIt-Profile-Identifier specified in the Bag-Info tags.
     * @group Profiles
     * @covers ::setBagInfoTags
     */
    public function testBagItProfileIdentifierInTags(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Bag-Info":{
    "BagIt-Profile-Identifier":{
      "required":true
    }
  },
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
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
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Manifests-Allowed": [
    "md5",
    "sha256"
  ],
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
        $this->assertTrue($profile->isValid());
    }

    /**
     * @group Profiles
     * @covers ::setManifestsAllowed
     */
    public function testSetManifestAllowedInvalid(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Manifests-Required": [
    "md5",
    "sha512"
  ],
  "Manifests-Allowed": [
    "md5",
    "sha256"
  ]
}
JSON;
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Manifests-Allowed must include all entries from Manifests-Required");
        BagItProfile::fromJson($profileJson);
    }

    /**
     * @group Profiles
     * @covers ::setAllowFetchTxt
     * @covers ::setRequireFetchTxt
     */
    public function testAllowFetchInvalid(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Require-Fetch.txt": true,
  "Allow-Fetch.txt": false,
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $this->expectException(ProfileException::class);
        $this->expectExceptionMessage("Allow-Fetch.txt cannot be false if Require-Fetch.txt is true");
        BagItProfile::fromJson($profileJson);
    }

    /**
     * @group Profiles
     * @covers ::setRequireFetchTxt
     * @covers ::setAllowFetchTxt
     * @covers ::isRequireFetchTxt
     */
    public function testRequireFetchValid(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Require-Fetch.txt": true,
  "Allow-Fetch.txt": true,
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
        $this->assertTrue($profile->isValid());
        $this->assertTrue($profile->isRequireFetchTxt());
    }

    /**
     * @group Profiles
     * @covers ::setDataEmpty
     * @covers ::validateBag
     */
    public function testDataEmpty(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
"Manifests-Allowed": [
"md5",
"sha512"
],
"Data-Empty": true,
"Serialization": "forbidden",
"Accept-BagIt-Version":[
"1.0"
]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
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
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
"Manifests-Allowed": [
"md5",
"sha512"
],
"Data-Empty": true,
"Serialization": "forbidden",
"Accept-BagIt-Version":[
"1.0"
]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
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

    /**
     * @group Profiles
     * @covers ::setTagManifestsAllowed
     * @covers ::getTagManifestsAllowed
     */
    public function testTagManifestAllowed(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing bad bag info tag options",
    "Version":"0.3"
  },
  "Tag-Manifests-Allowed": [
    "md5"
  ],
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
        $this->assertTrue($profile->isValid());
        $this->assertArrayEquals(["md5"], $profile->getTagManifestsAllowed());
    }

    /**
     * @group Profiles
     * @covers ::setTagFilesAllowed
     * @covers ::getTagFilesRequired
     */
    public function testTagFilesMissingFromAllowed(): void
    {
        $profileJson = <<< JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://somewhere.org/my/profile.json",
    "BagIt-Profile-Version": "0.1",
    "Source-Organization":"Monsters, Inc.",
    "Contact-Name":"Mike Wazowski",
    "External-Description":"Profile for testing tag files required and allowed",
    "Version":"0.3"
  },
  "Tag-Files-Required": [
    "Special-tag-file.txt"
  ],
  "Tag-Files-Allowed": [
    "Special-tag-file.txt",
    "Another-special-tag-file.txt"
  ],
  "Serialization": "forbidden",
  "Accept-BagIt-Version":[
    "1.0"
  ]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
        $this->assertTrue($profile->isValid());
    }

    /**
     * @group Profiles
     * @covers \whikloj\BagItTools\Bag::isValid
     * @covers \whikloj\BagItTools\Bag::addBagProfileByJson
     * @covers ::validateBag
     */
    public function testAddProfileToBag(): void
    {
        $profileJson = <<<JSON
{
  "BagIt-Profile-Info":{
    "BagIt-Profile-Identifier":"http://example.profile.org/bagit-test-profile.json",
    "BagIt-Profile-Version": "1.1.0",
    "Source-Organization":"Example Organization",
    "Contact-Name":"John D Smith",
    "External-Description":"BagIt Profile for testing bag profiles",
    "Version":"0.3"
  },
  "Bag-Info":{
    "Bagging-Date":{
      "required":true
    },
    "Source-Organization":{
      "required":true,
      "values":[
        "Simon Fraser University",
        "York University"
      ]
    },
    "Contact-Phone":{
      "required":true
    }
  },
  "Manifests-Required":[
    "md5"
  ],
  "Accept-Serialization":[
    "application/zip"
  ],
  "Allow-Fetch.txt":false,
  "Accept-BagIt-Version":[
    "1.0",
    "0.97"
  ]
}
JSON;
        $profile = BagItProfile::fromJson($profileJson);
        $this->assertTrue($profile->isValid());
        $bag = Bag::create($this->tmpdir);
        $bag->addBagProfileByJson($profileJson);
        $this->assertFalse($bag->isValid());
        $bag->addBagInfoTag("Source-Organization", "Simon Fraser University");
        $bag->addBagInfoTag("Contact-Phone", "555-555-5555");
        $this->assertFalse($bag->isValid());
        $this->assertCount(1, $bag->getErrors());
        $error = $bag->getErrors()[0];
        $this->assertEquals(
            [
                "file" => "http://example.profile.org/bagit-test-profile.json",
                "message" => "Profile requires payload manifest(s) which are missing from the bag (md5)"
            ],
            $error
        );
        $bag->setAlgorithm("md5");
        $this->assertTrue($bag->isValid());
    }

    /**
     * @group Profiles
     * @covers \whikloj\BagItTools\Bag::addBagProfileByJson
     * @covers \whikloj\BagItTools\Bag::addBagProfileInternal
     * @covers \whikloj\BagItTools\Bag::removeBagProfile
     */
    public function testAddSameProfileTwice(): void
    {
        $profileJson = file_get_contents(self::$profiles . "/bagProfileFoo.json");
        $bag = Bag::create($this->tmpdir);
        $this->assertCount(0, $bag->getBagProfiles());
        $bag->addBagProfileByJson($profileJson);
        $this->assertCount(1, $bag->getBagProfiles());
        $bag->addBagProfileByJson($profileJson);
        $this->assertCount(1, $bag->getBagProfiles());
        $bag->removeBagProfile("http://some.incorrect.identifier");
        $this->assertCount(1, $bag->getBagProfiles());
        $bag->removeBagProfile("http://www.library.yale.edu/mssa/bagitprofiles/disk_images.json");
        $this->assertCount(0, $bag->getBagProfiles());
    }
}
