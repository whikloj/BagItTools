<?php

namespace whikloj\BagItTools\Test\Profiles;

use ReflectionException;
use whikloj\BagItTools\Profiles\BagItProfile;
use whikloj\BagItTools\Test\BagItTestFramework;

/**
 * @coversDefaultClass  \whikloj\BagItTools\Profiles\BagItProfile
 */
class InternalProfileTest extends BagItTestFramework
{
    /**
     * Test conversion from glob to regex syntax.
     * @throws ReflectionException
     * @covers ::convertGlobToRegex
     */
    public function testGlobToRegex(): void
    {
        $test_cases = [
            '*' => '~^[^/]+$~',
            'myFiles*' => '~^myFiles[^/]+$~',
            'data/some/*/file.txt' => '~^data/some/[^/]+/file\.txt$~',
            'some/directories/' => '~^some/directories/$~',
            'some/more/directories/*' => '~^some/more/directories/[^/]+$~',
            'some/directory/[a-z].txt' => '~^some/directory/[a-z]\.txt$~',
            'some/directory/[!a-z].txt' => '~^some/directory/[^a-z]\.txt$~',
        ];

        $method = $this->getReflectionMethod('\whikloj\BagItTools\Profiles\BagItProfile', 'convertGlobToRegex');

        $profile = new BagItProfile();

        foreach ($test_cases as $glob => $expected) {
            $result = $method->invokeArgs($profile, [$glob]);
            $this->assertEquals($expected, $result);
        }
    }

    /**
     * Test conversion and matching of glob required to allowed paths.
     * @throws ReflectionException
     * @covers ::isRequiredPathsCoveredByAllowed
     * @covers ::convertGlobToRegex
     */
    public function testRequiredVersusAllowedTag(): void
    {
        $method = $this->getReflectionMethod(
            '\whikloj\BagItTools\Profiles\BagItProfile',
            'isRequiredPathsCoveredByAllowed'
        );

        $profile = new BagItProfile();

        $this->assertTrue(
            $method->invokeArgs($profile, [['DPN/dpnFirstNode.txt', 'DPN/dpnRegistry'], ['DPN/*']])
        );

        $this->assertFalse(
            $method->invokeArgs($profile, [['DPN/dpnFirstNode.txt', 'DPN/dpnRegistry'], ['DPN/dpnFirstNode.txt']])
        );

        $this->assertTrue(
            $method->invokeArgs($profile, [
                ['DPN/dpnFirstNode.txt', 'DPN/dpnRegistry'], ['DPN/dpnFirstNode.txt', 'DPN/dpnRegistry']
            ])
        );

        $this->assertFalse(
            $method->invokeArgs($profile, [
                ['DPN/dpnFirstNode.txt', 'DPN/dpnRegistry'], ['DPN/']
            ])
        );
    }
}
