<?php

namespace whikloj\BagItTools\Test\Profiles;

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Profiles\BagItProfile;
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
        ];
        parent::setUpBeforeClass();
    }

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
}
