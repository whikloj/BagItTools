<?php

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\CurlInstance;

class CurlInstanceTest extends BagItTestFramework
{
    public function testCreateCurl()
    {
        $mock = new class
        {
            use CurlInstance;
        }; // anonymous class

        $handle = $mock->createCurl('http://example.com');
        $this->assertInstanceOf(\CurlHandle::class, $handle);
    }

    public function testCurlMulti()
    {
        $mock = new class
        {
            use CurlInstance;
        }; // anonymous class

        $handle = $mock->createMultiCurl();
        $this->assertInstanceOf(\CurlMultiHandle::class, $handle);
    }
}
