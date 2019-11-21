#!/usr/local/bin/php -q
<?php

/**
 * This is a simple CLI interface to allow BagItTools to run the bagit-conformance-suite
 *
 * @see https://github.com/LibraryOfCongress/bagit-conformance-suite
 */
use whikloj\BagItTools\Bag;

require_once __DIR__ . '/../vendor/autoload.php';

if ($argc == 1) {
    print "Usage: " . basename($_SERVER['PHP_SELF']) . ' (path to Bag )' . PHP_EOL;
} else {
    $directory = getcwd();
    $input = $argv[1];
    if ($input[0] == DIRECTORY_SEPARATOR) {
        $full_path = $input;
    } else {
        $full_path = $directory . DIRECTORY_SEPARATOR . $input;
    }
    $bag = Bag::load($full_path);
    $valid = $bag->validate();
    $warnings = $bag->getWarnings();
    foreach ($warnings as $warning) {
        $line = sprintf("WARNING: %s -- in file: %s" . PHP_EOL, $warning['message'], $warning['file']);
        fwrite(STDERR, $line);
    }
    if ($valid) {
        exit(0);
    } else {
        exit(1);
    }
}
