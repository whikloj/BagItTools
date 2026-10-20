<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test;

use PHPUnit\Framework\TestCase;

/**
 * Exercise missing optional dependencies outside Composer's development autoloader.
 */
class OptionalTarDependencyTest extends TestCase
{
    public function testDirectoryAndZipWorkWithoutTarDependency(): void
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fixtures/without-tar.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertIsString($output);
        $this->assertIsString($errors);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertStringContainsString('Directory and ZIP work', $output);
    }
}
