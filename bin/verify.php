#!/usr/bin/php -q
<?php

use whikloj\BagItTools\Bag;

require_once __DIR__ . '/../vendor/autoload.php';

$debug = false;

if ($argc == 1) {
    print "Usage: " . basename($_SERVER['PHP_SELF']) . ' (Test Bag)' . PHP_EOL;
} else {
    $directory = getcwd();
    $input = $argv[1];
    if ($input[0] == DIRECTORY_SEPARATOR) {
        $full_path = $input;
    } else {
        $full_path = $directory . DIRECTORY_SEPARATOR . $input;
    }
    $bag = new Bag($full_path, false);
    $valid = $bag->validate();
    if ($valid) {
        exit(0);
    } else {
        $warnings = $bag->getWarnings();
        foreach ($warnings as $warning) {
            $line = sprintf("WARNING: %s -- in file: %s" . PHP_EOL, $warning['message'], $warning['file']);
            fwrite(STDERR, $line);
        }
        exit(1);
    }
}
