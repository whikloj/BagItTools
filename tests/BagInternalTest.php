<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\Bag;

/**
 * Tests private or protected functions.
 * @package whikloj\BagItTools\Test
 */
class BagInternalTest extends BagItTestFramework
{

  /**
   * Test makeRelative
   * @group BagInternal
   * @covers \whikloj\BagItTools\Bag::makeRelative
   * @throws \ReflectionException
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testMakeRelativePlain()
    {
        $methodCall = $this->getReflectionMethod('\whikloj\BagItTools\Bag', 'makeRelative');

        $tmp = $this->getTempName();
        $bag = new Bag($tmp, true);
        $baseDir = $bag->getBagRoot();

        $valid_paths = [
        'data/image/someimage.jpg' => 'data/image/someimage.jpg',
        'data/picture.txt' => 'data/picture.txt',
        'data/images/subimages/../picture.jpg' => 'data/images/picture.jpg',
        'data/one/../two/../three/.././/eggs.txt' => 'data/eggs.txt',
        'somefile.txt' => 'somefile.txt',
        '/var/lib/somewhere' => 'var/lib/somewhere',
        ];

        $invalid_paths = [
        'data/../../../images/places/image.jpg',
        'data/one/..//./two/./../../three/.././../eggs.txt',
        ];

        foreach ($valid_paths as $path => $expected) {
            $fullpath = $baseDir . DIRECTORY_SEPARATOR . $path;
            $relative = $methodCall->invokeArgs($bag, [$fullpath]);
            $this->assertEquals($expected, $relative);
        }

        foreach ($invalid_paths as $path) {
            $fullpath = $baseDir . DIRECTORY_SEPARATOR . $path;
            $relative = $methodCall->invokeArgs($bag, [$fullpath]);
            $this->assertEquals('', $relative);
        }

        $this->deleteDirAndContents($tmp);
    }

  /**
   * Test pathInBagData
   * @group BagInternal
   * @covers \whikloj\BagItTools\Bag::pathInBagData
   * @throws \ReflectionException
   * @throws \whikloj\BagItTools\BagItException
   */
    public function testPathInBagData()
    {
        $methodCall = $this->getReflectionMethod('\whikloj\BagItTools\Bag', 'pathInBagData');

        $tmp = $this->getTempName();
        $bag = new Bag($tmp, true);

        $valid_paths = [
        'data/image/someimage.jpg',
        'data/picture.txt',
        'data/images/subimages/../picture.jpg',
        'data/one/../two/../three/.././/eggs.txt',
        ];
        $invalid_paths = [
        'data/../../../images/places/image.jpg',
        'somefile.txt',
        'Whatever',
        'data/one/../two/../three/.././../eggs.txt',
        ];

        foreach ($valid_paths as $path) {
            $relative = $methodCall->invokeArgs($bag, [$path]);
            $this->assertTrue($relative);
        }

        foreach ($invalid_paths as $path) {
            $relative = $methodCall->invokeArgs($bag, [$path]);
            $this->assertFalse($relative);
        }

        $this->deleteDirAndContents($tmp);
    }
}
