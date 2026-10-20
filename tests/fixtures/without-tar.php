<?php

declare(strict_types=1);

use whikloj\BagItTools\Bag;
use whikloj\BagItTools\Exceptions\BagItException;

// With an argument, exercise a real --no-dev Composer installation (CI).
// Otherwise isolate the library's source autoloader from dev dependencies (PHPUnit).
if (isset($argv[1])) {
    require $argv[1];
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'whikloj\\BagItTools\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
}

if (class_exists('Archive_Tar')) {
    throw new RuntimeException('This test requires an environment without Archive_Tar.');
}

$checkMessage = static function (BagItException $error): void {
    if (!str_contains($error->getMessage(), 'composer require pear/archive_tar')) {
        throw new RuntimeException('Missing installation instructions: ' . $error->getMessage());
    }
};

$removeTree = static function (string $root): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($root);
};

$root = sys_get_temp_dir() . '/bagit-without-tar-' . bin2hex(random_bytes(8));
mkdir($root);
try {
    $bag = Bag::create($root . '/bag');
    $bag->addFile(__FILE__, 'data/example.php');
    $bag->update();
    if (!$bag->isValid() || !Bag::load($root . '/bag')->isValid()) {
        throw new RuntimeException('Directory bag validation failed without PEAR.');
    }
    $bag->package($root . '/bag.zip');
    $unzipped = Bag::load($root . '/bag.zip');
    try {
        if (!$unzipped->isValid()) {
            throw new RuntimeException('ZIP round trip failed without PEAR.');
        }
    } finally {
        $removeTree(dirname($unzipped->getBagRoot()));
    }
    foreach (['tar', 'tar.gz', 'tgz', 'tar.bz2'] as $extension) {
        $output = $root . '/output.' . $extension;
        $before = hash_file('sha256', $root . '/bag/bagit.txt');
        try {
            $bag->package($output);
            throw new RuntimeException('Expected missing dependency error on tar creation.');
        } catch (BagItException $e) {
            $checkMessage($e);
        }
        if (file_exists($output) || $before !== hash_file('sha256', $root . '/bag/bagit.txt')) {
            throw new RuntimeException('Tar creation changed files before checking its dependency.');
        }
        // The dependency check must precede extraction; no valid tar bytes are necessary.
        file_put_contents($output, 'not an archive');
        try {
            Bag::load($output);
            throw new RuntimeException('Expected missing dependency error on tar extraction.');
        } catch (BagItException $e) {
            $checkMessage($e);
        }
    }
    echo "Directory and ZIP work; tar operations report the optional dependency.\n";
} finally {
    $removeTree($root);
}
