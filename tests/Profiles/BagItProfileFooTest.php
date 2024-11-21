<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Test\Profiles;

/**
 * Test BagItProfile against the specifications foo
 * @package Profiles
 * @coversDefaultClass \whikloj\BagItTools\Profiles\BagItProfile
 */
class BagItProfileFooTest extends ProfileTestFramework
{
    /**
     * {@inheritDoc}
     */
    protected function getProfileFilename(): string
    {
        return self::TEST_RESOURCES . '/profiles/bagProfileFoo.json';
    }

    /**
     * {@inheritDoc}
     */
    protected function getProfileUri(): string
    {
        return self::$remote_urls[0];
    }

    /**
     * {@inheritDoc}
     */
    protected function getProfileValues(): array
    {
        return [
            'identifier' => 'http://www.library.yale.edu/mssa/bagitprofiles/disk_images.json',
            'spec_version' => '1.1.0',
            'version' => '0.3',
            'source_organization' => 'Yale University',
            'external_description' => 'BagIt Profile for packaging disk images',
            'contact_name' => 'Mark Matienzo',
            'contact_email' => null,
            'contact_phone' => null,
            'bag_info_tags' => [
                'Bagging-Date' => [
                    'required' => true,
                    'values' => [],
                    'repeatable' => true,
                    'description' => '',
                ],
                'Source-Organization' => [
                    'required' => true,
                    'values' => [
                        'Simon Fraser University',
                        'York University',
                    ],
                    'repeatable' => true,
                    'description' => '',
                ],
                "Contact-Phone" => [
                    "required" => true,
                    "values" => [],
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
            'serialization' => 'required',
            'accept_serialization' => [
                "application/tar",
                "application/zip",
            ],
            'accept_bagit_version' => [
                "0.96",
                "0.97",
            ],
            'tag_manifests_required' => [],
            'tag_manifests_allowed' => [],
            'tag_files_required' => [],
            'tag_files_allowed' => [],
            'payload_files_required' => [],
            'payload_files_allowed' => [],
        ];
    }
}
