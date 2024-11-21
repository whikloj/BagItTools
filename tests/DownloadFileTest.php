<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test;

use whikloj\BagItTools\DownloadFile;
use whikloj\BagItTools\Exceptions\BagItException;

/**
 * Class DownloadFileTest
 * @package whikloj\BagItTools\Test
 * @coversDefaultClass \whikloj\BagItTools\DownloadFile
 * @since 5.0.0
 * @author whikloj
 */
class DownloadFileTest extends BagItTestFramework
{
    /**
     * @covers ::__construct
     * @covers ::getUrl
     * @covers ::getDestination
     * @covers ::getSize
     * @covers ::getSizeString
     */
    public function testCreateAndGet(): void
    {
        $file = new DownloadFile('uri', 'data/destination.txt');
        $this->assertInstanceOf(DownloadFile::class, $file);
        $this->assertEquals('uri', $file->getUrl());
        $this->assertEquals('data/destination.txt', $file->getDestination());
        $this->assertNull($file->getSize());
        $this->assertEquals("-", $file->getSizeString());
    }

    /**
     * @covers ::__construct
     * @covers ::getUrl
     * @covers ::getDestination
     * @covers ::getSize
     * @covers ::getSizeString
     */
    public function testCreateAndGetSize(): void
    {
        $file = new DownloadFile('uri', 'data/destination.txt', 1024);
        $this->assertInstanceOf(DownloadFile::class, $file);
        $this->assertEquals('uri', $file->getUrl());
        $this->assertEquals('data/destination.txt', $file->getDestination());
        $this->assertEquals(1024, $file->getSize());
        $this->assertEquals("1024", $file->getSizeString());
    }

    /**
     * @covers ::__construct
     * @covers ::validateDownload
     * @covers ::validateUrl
     * @covers ::internalValidateUrl
     */
    public function testValidate(): void
    {
        $file = new DownloadFile('http://somehost.io/filename', 'data/destination.txt');
        $file->validateDownload();
        $this->assertTrue(true);
    }

    /**
     * @covers ::__construct
     * @covers ::validateDownload
     * @covers ::validateUrl
     */
    public function testInvalidUrl(): void
    {
        $file = new DownloadFile('//somehost.io/filename', 'data/destination.txt');
        $this->expectException(BagItException::class);
        $file->validateDownload();
    }

    /**
     * @covers ::__construct
     * @covers ::validateDownload
     * @covers ::validateUrl
     * @covers ::internalValidateUrl
     */
    public function testInvalidUrlScheme(): void
    {
        $file = new DownloadFile('ftp://somehost.io/filename', 'data/destination.txt');
        $this->expectException(BagItException::class);
        $file->validateDownload();
    }
}
