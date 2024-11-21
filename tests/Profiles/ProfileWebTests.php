<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test\Profiles;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Exceptions\BagItException;
use whikloj\BagItTools\Profiles\ProfileFactory;
use whikloj\BagItTools\Test\BagItWebserverFramework;

class ProfileWebTests extends BagItWebserverFramework
{
    public static function setUpBeforeClass(): void
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
        self::$webserver_files = [
            'profile1.json' => [
                'content' => $profileJson,
                'path' => 'bagit-test-profile.json',
            ],
            'test-profile-bag.json' => [
                'filename' => self::TEST_RESOURCES . '/profiles/test-profile-bag.json',
            ],
        ];
        parent::setUpBeforeClass();
    }

    /**
     * @group Profiles
     * @covers \whikloj\BagItTools\Bag::addBagProfileByURL
     * @covers \whikloj\BagItTools\Bag::addBagProfileInternal
     * @covers \whikloj\BagItTools\Profiles\BagItProfile::validateBag
     */
    public function testAddProfileToBagUri(): void
    {
        $profile = ProfileFactory::generateProfileFromUri(self::$remote_urls[0]);
        $this->assertTrue($profile->isValid());
        $bag = Bag::create($this->tmpdir);
        $bag->addBagProfileByURL(self::$remote_urls[0]);
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
    public function testAddSameProfileTwiceByUri(): void
    {
        $bag = Bag::create($this->tmpdir);
        $this->assertCount(0, $bag->getBagProfiles());
        $bag->addBagProfileByURL(self::$remote_urls[0]);
        $this->assertCount(1, $bag->getBagProfiles());
        $bag->addBagProfileByURL(self::$remote_urls[0]);
        $this->assertCount(1, $bag->getBagProfiles());
        $bag->removeBagProfile("http://some.incorrect.identifier");
        $this->assertCount(1, $bag->getBagProfiles());
        $bag->removeBagProfile("http://www.library.yale.edu/mssa/bagitprofiles/disk_images.json");
        $this->assertCount(0, $bag->getBagProfiles());
    }

    /**
     * @group Profiles
     * @covers \whikloj\BagItTools\Profiles\BagItProfile::validateBag
     */
    public function testBagDoesntSupportSerialization(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('BagIt-Profile-Identifier', trim(self::$remote_urls[1]));
        $bag->addBagInfoTag('Contact-Name', 'Some Person');
        $bag->addBagInfoTag('Contact-Phone', '555-555-5555');
        $bag->addBagInfoTag('Contact-Email', 'some.person@noreply.org');
        $bag->addBagInfoTag('Contact-Address', '1234 Some Street, Some City, Some State, 12345');
        $bag->addBagInfoTag('Source-Organization', 'BagItTools');
        $tmpfile = $this->getTempName();
        file_put_contents($tmpfile, "CUSTOM-TAG-ID: 1234\nCUSTOM-TAG-ORG: 5678\n");
        $bag->addTagFile($tmpfile, 'tagFiles/special-tags.txt');
        $bag->createFile(
            "This is an example test file in the TestProfileBag. It is used to test the\n" .
            "validation of a profile.",
            "example-file.txt"
        );
        $bag->addAlgorithm('sha1');
        $tmpPackage = $this->getTempName() . ".tgz";
        $bag->package($tmpPackage);
        $this->assertFileExists($tmpPackage);

        $new_bag = Bag::load($tmpPackage);
        $this->assertFalse($new_bag->isValid());
        $this->assertCount(1, $new_bag->getErrors());
        $error = $new_bag->getErrors()[0];
        $this->assertEquals(
            [
                "file" => "http://example.org/example/test-profile-bag.json",
                "message" => "Profile allows for serialization MIME type (application/zip) but the bag has MIME " .
                    "type (application/gzip)"
            ],
            $error
        );
    }

    /**
     * @group Profiles
     * @covers \whikloj\BagItTools\Profiles\BagItProfile::validateBag
     */
    public function testProfileMissingRequiredTag(): void
    {
        $bag = Bag::create($this->tmpdir);
        $bag->addBagInfoTag('BagIt-Profile-Identifier', trim(self::$remote_urls[1]));
        $bag->addBagInfoTag('Contact-Name', 'Some Person');
        $bag->addBagInfoTag('Source-Organization', 'BagItTools');
        $tmpfile = $this->getTempName();
        file_put_contents($tmpfile, "CUSTOM-TAG-ID: 1234\nCUSTOM-TAG-ORG: 5678\n");
        $bag->addTagFile($tmpfile, 'tagFiles/special-tags.txt');
        $bag->createFile(
            "This is an example test file in the TestProfileBag. It is used to test the\n" .
            "validation of a profile.",
            "example-file.txt"
        );
        $bag->addAlgorithm('sha1');
        $tmpPackage = $this->getTempName() . ".zip";
        $this->expectException(BagItException::class);
        $this->expectExceptionMessage("Bag is not valid, cannot package.");
        $bag->package($tmpPackage);
    }
}
