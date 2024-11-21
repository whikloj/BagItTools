<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\CurlInstance;

/**
 * Class CurlInstanceTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\CurlInstance
 * @since 5.0.0
 * @author whikloj
 */
class CurlInstanceTest extends BagItTestFramework
{
    /**
     * @covers ::createCurl
     * @covers ::setupCurl
     */
    public function testCreateCurl(): void
    {
        $mock = new class
        {
            use CurlInstance;
        }; // anonymous class

        $handle = $mock->createCurl('http://example.com');
        $this->assertInstanceOf(\CurlHandle::class, $handle);
    }

    /**
     * @covers ::createCurl
     * @covers ::setupCurl
     */
    public function testCreateSingleCurl(): void
    {
        $mock = new class
        {
            use CurlInstance;
        }; // anonymous class

        $handle = $mock->createCurl('http://example.com', true);
        $this->assertInstanceOf(\CurlHandle::class, $handle);
    }

    /**
     * @covers ::createMultiCurl
     */
    public function testCurlMulti(): void
    {
        $mock = new class
        {
            use CurlInstance;
        }; // anonymous class

        $handle = $mock->createMultiCurl();
        $this->assertInstanceOf(\CurlMultiHandle::class, $handle);
    }
}
