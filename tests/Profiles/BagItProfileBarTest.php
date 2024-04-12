<?php

namespace Profiles;

use whikloj\BagItTools\Profiles\BagItProfile;
use whikloj\BagItTools\Test\BagItTestFramework;
use whikloj\BagItTools\Test\Profiles\ProfileTestFramework;

/**
 * Test BagItProfile against the specifications foo
 * @package Profiles
 * @coversDefaultClass \whikloj\BagItTools\Profiles\BagItProfile
 */
class BagItProfileBarTest extends ProfileTestFramework
{
    protected function getProfileFilename(): string
    {
        return self::TEST_RESOURCES . '/profiles/bagProfileBar.json';
    }

    protected function getProfileUri(): string
    {
        return self::$remote_urls[1];
    }

    protected function getProfileValues(): array
    {
        return [
            'identifier' => 'http://canadiana.org/standards/bagit/tdr_ingest.json',
            'spec_version' => '1.2.0',
            'version' => '1.2',
            'source_organization' => 'Candiana.org',
            'external_description' => 'BagIt Profile for ingesting content into the C.O. TDR loading dock.',
            'contact_name' => 'William Wueppelmann',
            'contact_email' => 'tdr@canadiana.com',
            'contact_phone' => null,
            'bag_info_tags' => [
                "Source-Organization" => [
                    "required" => true,
                    "values" => [
                        "Simon Fraser University",
                        "York University"
                    ],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Organization-Address" => [
                    "required" => true,
                    "values" => [
                        "8888 University Drive Burnaby, B.C. V5A 1S6 Canada",
                        "4700 Keele Street Toronto, Ontario M3J 1P3 Canada"
                    ],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Contact-Name" => [
                    "required" => true,
                    "values" => [
                        "Mark Jordan",
                        "Nick Ruest"
                    ],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Contact-Phone" => [
                    "required" => false,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Contact-Email" => [
                    "required" => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "External-Description" => [
                    "required" => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "External-Identifier" => [
                    "required" => false,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Bag-Size" => [
                    "required" => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Bag-Group-Identifier" => [
                    "required" => false,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Bag-Count" => [
                    "required" => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Internal-Sender-Identifier" => [
                    "required" => false,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Internal-Sender-Description" => [
                    "required" => false,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Bagging-Date" => [
                    "required" => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Payload-Oxum" => [
                    "required" => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
            ],
            'manifests_required' => [
                "md5",
            ],
            'manifests_allowed' => [],
            'allow_fetch_txt' => false,
            'require_fetch_txt' => false,
            'data_empty' => false,
            'serialization' => 'optional',
            'accept_serialization' => [
                "application/zip",
            ],
            'accept_bagit_version' => [
                "0.96",
            ],
            'tag_manifests_required' => [
                "md5"
            ],
            'tag_manifests_allowed' => [],
            'tag_files_required' => [
                "DPN/dpnFirstNode.txt",
                "DPN/dpnRegistry",
            ],
            'tag_files_allowed' => [],
            'payload_files_required' => [],
            'payload_files_allowed' => [],
        ];
    }
}
