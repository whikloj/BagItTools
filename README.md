# BagItTools

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://travis-ci.com/whikloj/BagItTools.svg?branch=master)](https://travis-ci.com/whikloj/BagItTools)
[![LICENSE](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](./LICENSE)
[![codecov](https://codecov.io/gh/whikloj/BagItTools/branch/master/graph/badge.svg)](https://codecov.io/gh/whikloj/BagItTools)

## Introduction

BagItTools is a PHP implementation of the BagIt v1.0 specification ([RFC-8493](https://tools.ietf.org/html/rfc8493)).

It is currently in active development see [Development](#development).

Features:

* Create new bag
* Load existing directory as a bag.
* Load archive file (*.zip, *.tar, *.tar.gz, *.tgz, *.tar.bz2)
* Validate a bag
* Add/Remove files
* Add/Remove fetch urls
* Add/Remove hash algorithms (md5, sha1, sha224, sha256, sha384, sha512, sha3-224, sha3-256, sha3-384, sha3-512)
* Generate payload for all data/ files for all hash algorithms (depending on PHP support)
* Generate tag manifests for all root level files and any additional tag directories/files.
* Add/Remove tags from bag-info.txt files, maintains ordering of tags loaded.
* Generates/updates payload-oxum and bagging-date.
* Passes all bagit-conformance-suite tests.
* Create an archive (zip, tar, tar.gz, tgz, tar.bz2)

## Installation

There is no release on packagist.org (yet). 

So for now you must:

```bash
git clone https://github.com/whikloj/BagItTools
cd BagItTools
composer install
```

## Dependencies

All dependencies are installed or identified by composer. 

Some PHP extensions are required and this library will not install if they cannot be found in the default PHP installation (the one used by composer).

The required extensions are:

* [Client URL Library](https://www.php.net/manual/en/book.curl.php)
* [Internationalization functions](https://www.php.net/manual/en/book.intl.php)
* [Multibyte string](https://www.php.net/manual/en/book.mbstring.php)
* [Zip](https://www.php.net/manual/en/book.zip.php)

## Usage

### Create a new bag

As this is a v1.0 implementation, by default bags created use the UTF-8 file encoding and the SHA-512 hash algorithm.

```php

require_once './vendor/autoload.php';

use \whikloj\BagItTools\Bag;

$dir = "./newbag";

// Create new bag as directory $dir
$bag = Bag::create($dir);

// Add a file
$bag->addFile('../README.md', 'data/documentation/myreadme.md');

// Add another algorithm
$bag->addAlgorithm('sha1');

// Add a fetch url
$bag->addFetchFile('http://www.google.ca', 'data/mywebsite.html');

// Add some bag-info tags
$bag->addBagInfoTag('Contact-Name', 'Jared Whiklo');
$bag->addBagInfoTag('CONTACT-NAME', 'Additional admins');

// Check for tags.
if ($bag->hasBagInfoTag('contact-name')) {

    // Get tags
    $tags = $bag->getBagInfoByTag('contact-name');
    
    var_dump($tags); // array(
                     //    'Jared Whiklo',
                     //    'Additional admins',
                     // )

    // Remove a specific tag value using array index from the above listing.
    $bag->removeBagInfoTagIndex('contact-name', 1); 
    
    // Get tags
    $tags = $bag->getBagInfoByTag('contact-name');
    
    var_dump($tags); // array(
                     //    'Jared Whiklo',
                     // )

    // Remove all values for the specified tag.
    $bag->removeBagInfoTag('contact-name');
}

// Write bagit support files (manifests, bag-info, etc)
$bag->update();

// Write the bag to the specified path and filename using the expected archiving method.
$bag->package('./archive.tar.bz2');

```

## Maintainer

[Jared Whiklo](https://github.com/whikloj)

## License

[MIT](./LICENSE)

## Development

This is still a work in progress, if you have a use case or discover a problem please open an [issue](https://github.com/whikloj/BagItTools/issues)

### Roadmap-ish

To-Do:

* Allow insert of bag-info.txt tags
* In-place upgrade of bag from v0.97 to v1.0
* CLI interface to handle simple validation functions.
