<?php

namespace Profiles;

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
    /**
     * Try to validate a bag.
     * @group Profiles
     * @covers ::validateBag
     */
    public function testValidateBag1(): void
    {
        $profile = BagItProfile::fromJson(file_get_contents(self::TEST_RESOURCES . '/profiles/bagProfileFoo.json'));
        $this->assertTrue($profile->isValid());
        $this->tmpdir = $this->prepareExtendedTestBag();
        $bag = Bag::load($this->tmpdir);
        $this->assertTrue($bag->isValid());
        $this->expectException(ProfileException::class);
        $profile->validateBag($bag);
    }
}
