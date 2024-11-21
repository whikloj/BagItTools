<?php

namespace whikloj\BagItTools\Test\Profiles;

use whikloj\BagItTools\Profiles\ProfileTags;
use whikloj\BagItTools\Test\BagItTestFramework;

/**
 * Class ProfileTagTest
 * @package whikloj\BagItTools\Test\Profiles
 * @coversDefaultClass \whikloj\BagItTools\Profiles\ProfileTags
 * @since 5.0.0
 * @group Profiles
 * @author whikloj
 */
class ProfileTagTest extends BagItTestFramework
{
    /**
     * @covers ::__construct
     * @covers ::getTag
     * @covers ::isRequired
     * @covers ::getValues
     * @covers ::isRepeatable
     * @covers ::getDescription
     */
    public function testBasicTag(): void
    {
        $tag = new ProfileTags(
            'Bag-Size',
            true,
            [
                'some value',
            ],
            false,
            'The size of the bag in bytes'
        );
        $this->assertEquals('Bag-Size', $tag->getTag());
        $this->assertTrue($tag->isRequired());
        $this->assertArrayEquals(['some value'], $tag->getValues());
        $this->assertFalse($tag->isRepeatable());
        $this->assertEquals('The size of the bag in bytes', $tag->getDescription());
    }

    /**
     * @covers ::setOtherTagOptions
     * @covers ::getOtherTagOptions
     */
    public function testOtherTags(): void
    {
        $tag = new ProfileTags(
            'Bag-Size',
            true,
            [
                'some value',
            ],
            false,
            'The size of the bag in bytes'
        );

        $otherOptions = [
            'some' => 'value',
            'another' => 'thing',
        ];

        $method = $this->getReflectionMethod('\whikloj\BagItTools\Profiles\ProfileTags', 'setOtherTagOptions');

        $method->invoke($tag, $otherOptions);

        $this->assertArrayEquals($otherOptions, $tag->getOtherTagOptions());
    }

    /**
     * @covers ::fromJson
     * @covers ::getTag
     * @covers ::isRequired
     * @covers ::getValues
     * @covers ::isRepeatable
     * @covers ::getDescription
     */
    public function testFromJsonBasic(): void
    {
        $json = '{"required": true, "values": ["some value"], "repeatable": false, '
            . '"description": "The size of the bag in bytes"}';
        $decoded = json_decode($json, true);
        $tag = ProfileTags::fromJson("Bag-Size", $decoded);
        $this->assertEquals('Bag-Size', $tag->getTag());
        $this->assertTrue($tag->isRequired());
        $this->assertArrayEquals(['some value'], $tag->getValues());
        $this->assertFalse($tag->isRepeatable());
        $this->assertEquals('The size of the bag in bytes', $tag->getDescription());
    }

    /**
     * @covers ::fromJson
     * @covers ::getTag
     * @covers ::isRequired
     * @covers ::getValues
     * @covers ::isRepeatable
     * @covers ::getDescription
     * @covers ::getOtherTagOptions
     */
    public function testFromJsonExtended(): void
    {
        $json = '{"required": true, "values": ["some value"], "repeatable": false, '
            . '"description": "The size of the bag in bytes", "other": {"some": "value", "another": "thing"}}';
        $decoded = json_decode($json, true);
        $tag = ProfileTags::fromJson("Bag-Size", $decoded);
        $this->assertEquals('Bag-Size', $tag->getTag());
        $this->assertTrue($tag->isRequired());
        $this->assertArrayEquals(['some value'], $tag->getValues());
        $this->assertFalse($tag->isRepeatable());
        $this->assertEquals('The size of the bag in bytes', $tag->getDescription());
        $other = $tag->getOtherTagOptions();
        $this->assertArrayHasKey('other', $other);
        $this->assertArrayEquals(['some' => 'value', 'another' => 'thing'], $other['other']);
    }
}
