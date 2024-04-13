<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Profiles;

use Exception;
use whikloj\BagItTools\Bag;
use whikloj\BagItTools\BagUtils;
use whikloj\BagItTools\Exceptions\ProfileException;

/**
 * Class for holding a BagItProfile.
 *
 * @package whikloj\BagItTools\Profiles
 * @author Jared Whiklo
 * @since 5.0.0
 */
class BagItProfile
{
    /**
     * @var string
     * The identifier (and resolvable URI) of the BagItProfile.
     */
    protected string $bagItProfileIdentifier;

    /**
     * @var string
     * The value of the BagItProfile specification that this profile conforms to.
     */
    protected string $bagItProfileVersion;

    /**
     * @var string
     * The version of this BagItProfile specification.
     */
    protected string $version;

    /**
     * @var string
     * The Organization responsible for this BagItProfile.
     */
    protected string $sourceOrganization;

    /**
     * @var string
     * A brief explanation of the purpose of this profile.
     */
    protected string $externalDescription;

    /**
     * @var string|null
     * The contact name for this profile (optional).
     */
    protected ?string $contactName = null;

    /**
     * @var string|null
     * The contact phone for this profile (optional).
     */
    protected ?string $contactPhone = null;

    /**
     * @var string|null
     * The contact email for this profile (optional).
     */
    protected ?string $contactEmail = null;

    /**
     * @var array
     * The list of profile specific tags for this profile. Each tag is a key to an array with keys of "required",
     * "values", "repeatable" and "description". Does not include the required "BagIt-Profile-Identifier" tag.
     */
    protected array $profileBagInfoTags = [];

    /**
     * @var array A list of "required" BagInfo tags.
     */
    protected array $requiredBagInfoTags = [];

    /**
     * @var array
     * The list of required manifest algorithms. e.g. ["sha1", "md5"].
     */
    protected array $manifestsRequired = [];

    /**
     * @var array
     * The list of allowed manifest algorithms. e.g. ["sha1", "md5"]. If manifestsRequired is not empty then this list
     * must include all the required algorithms.
     */
    protected array $manifestsAllowed = [];

    /**
     * @var bool
     * Whether to allow the existence of a fetch.txt file. Default is true.
     */
    protected bool $allowFetchTxt = true;

    /**
     * @var bool
     * Whether to require the existence of a fetch.txt file. Default is false.
     */
    protected bool $requireFetchTxt = false;

    /**
     * @var bool
     * If true then the /data directory must contain either no files or a single zero byte length file. If false, no
     * constraints are placed on the /data directory. Default is false.
     */
    protected bool $dataEmpty = false;

    /**
     * @var string
     * Whether serialization of the bad is forbidden|required|optional. Default is "optional"
     */
    protected string $serialization = "optional";

    /**
     * @var array
     * A list of MIME types acceptable as serialized formats. If serialization is required then this list must contain
     * one or more values. If serialization is forbidden, this is ignored.
     */
    protected array $acceptSerialization = [];

    /**
     * @var array
     * A list of BagIt version numbers that will be accepted, e.g. "1.0". At least one version is required.
     */
    protected array $acceptBagItVersion = [];

    /**
     * @var array
     * Each tag manifest type in LIST is required. e.g. ["sha1", "md5"].
     */
    protected array $tagManifestsRequired = [];

    /**
     * @var array
     * The list of allowed tag manifest algorithms. e.g. ["sha1", "md5"]. If tagManifestsRequired is not empty then
     * this list must include all the required algorithms.
     */
    protected array $tagManifestsAllowed = [];

    /**
     * @var array
     * A list of a tag files that MUST be included in a conformant Bag. Entries are full path names relative to the
     * Bag base directory.
     */
    protected array $tagFilesRequired = [];

    /**
     * @var array
     * A list of tag files that MAY be included in a conformant Bag. Entries are either full path names relative to the
     * bag base directory or path name patterns in which asterisks can represent zero or more characters (c.f. glob(7)).
     * At least all the tag files listed in Tag-Files-Required MUST be in included in Tag-Files-Allowed.
     */
    protected array $tagFilesAllowed = [];

    /**
     * @var array
     * A list of a payload files and/or directories that MUST be included in a conformant Bag. Entries are full path
     * names relative to the Bag base directory, e.g. data/LICENSE.txt or data/src/.
     */
    protected array $payloadFilesRequired = [];

    /**
     * @var array
     * A list of payload files or directories that MAY be included in a conformant Bag. Each entry MUST be either a
     * full path name relative to the bag base directory, or a path name pattern in which asterisks can represent zero
     * or more characters (c.f. glob(7)). Paths requiring permitted directories MUST end with /* (not /).
     * At least all the payload paths listed in Payload-Files-Required MUST be covered by the list of path patterns in
     * Payload-Files-Allowed.
     *
     * @see http://man7.org/linux/man-pages/man7/glob.7.html
     */
    protected array $payloadFilesAllowed = [];

    /**
     * @var array
     * A list of warnings that were generated during the validation of the profile.
     */
    protected array $profileWarnings = [];

    /**
     * @return string The identifier (and resolvable URI) of the BagItProfile.
     */
    public function getProfileIdentifier(): string
    {
        return $this->bagItProfileIdentifier;
    }

    /**
     * @param string $bagItProfileIdentifier The identifier (and resolvable URI) of the BagItProfile.
     * @return BagItProfile The profile object.
     */
    private function setProfileIdentifier(string $bagItProfileIdentifier): BagItProfile
    {
        $this->bagItProfileIdentifier = $bagItProfileIdentifier;
        return $this;
    }

    /**
     * @return string The value of the BagItProfile specification that this profile conforms to.
     */
    public function getProfileSpecVersion(): string
    {
        return $this->bagItProfileVersion;
    }

    /**
     * @param string $bagItProfileVersion The value of the BagItProfile specification that this profile conforms to.
     * @return BagItProfile The profile object.
     */
    private function setProfileSpecVersion(string $bagItProfileVersion): BagItProfile
    {
        $this->bagItProfileVersion = $bagItProfileVersion;
        return $this;
    }

    /**
     * @return string The version of this BagItProfile specification.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version The version of this BagItProfile specification.
     * @return BagItProfile The profile object.
     */
    private function setVersion(string $version): BagItProfile
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return string The Organization responsible for this profile.
     */
    public function getSourceOrganization(): string
    {
        return $this->sourceOrganization;
    }

    /**
     * @param string $sourceOrganization The Organization responsible for this profile.
     * @return BagItProfile The profile object.
     */
    private function setSourceOrganization(string $sourceOrganization): BagItProfile
    {
        $this->sourceOrganization = $sourceOrganization;
        return $this;
    }

    /**
     * @return string A brief explanation of the purpose of this profile.
     */
    public function getExternalDescription(): string
    {
        return $this->externalDescription;
    }

    /**
     * @param string $externalDescription A brief explanation of the purpose of this profile.
     * @return BagItProfile The profile object.
     */
    private function setExternalDescription(string $externalDescription): BagItProfile
    {
        $this->externalDescription = $externalDescription;
        return $this;
    }

    /**
     * @return string|null The contact name for this profile or null if none.
     */
    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    /**
     * @param string|null $contactName The contact name for this profile or null if none.
     * @return BagItProfile The profile object.
     */
    private function setContactName(?string $contactName): BagItProfile
    {
        $this->contactName = $contactName;
        return $this;
    }

    /**
     * @return string|null The contact phone for this profile or null if none.
     */
    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    /**
     * @param string|null $contactPhone The contact phone for this profile or null if none.
     * @return BagItProfile The profile object.
     */
    private function setContactPhone(?string $contactPhone): BagItProfile
    {
        $this->contactPhone = $contactPhone;
        return $this;
    }

    /**
     * @return string|null The contact email for this profile or null if none.
     */
    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    /**
     * @param string|null $contactEmail The contact email for this profile if none.
     * @return BagItProfile The profile object.
     */
    private function setContactEmail(?string $contactEmail): BagItProfile
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    /**
     * @return array The list of acceptable tags for this profile.
     */
    public function getBagInfoTags(): array
    {
        return $this->profileBagInfoTags;
    }

    /**
     * @param array $bagInfoTags Parsed profile Bag-Info sections
     * @return BagItProfile The profile object.
     * @throws ProfileException If invalid options are specified for a tag.
     */
    private function setBagInfoTags(array $bagInfoTags): BagItProfile
    {
        $expectedKeys = ['required' => 0, 'values' => 0, 'repeatable' => 0, 'description' => 0];
        $this->profileBagInfoTags = [];
        foreach ($bagInfoTags as $tagName => $tagOpts) {
            if (count(array_diff_key($tagOpts, $expectedKeys)) > 0) {
                throw new ProfileException("Invalid tag options for $tagName");
            }
            if (self::matchStrings('BagIt-Profile-Identifier', $tagName)) {
                $this->profileWarnings[] = "The tag BagIt-Profile-Identifier is always required, but SHOULD NOT be " .
                "listed under Bag-Info in the Profile.";
            } else {
                $profileTag = ProfileTags::fromJson($tagName, $tagOpts);
                $this->profileBagInfoTags[BagUtils::trimLower($tagName)] = $profileTag;
                if ($profileTag->isRequired()) {
                    $this->requiredBagInfoTags[] = BagUtils::trimLower($tagName);
                }
            }
        }
        return $this;
    }

    /**
     * @return array The list of required manifest algorithms. e.g. ["sha1", "md5"].
     */
    public function getManifestsRequired(): array
    {
        return $this->manifestsRequired;
    }

    /**
     * @param array $manifestsRequired The list of required manifest algorithms. e.g. ["sha1", "md5"].
     * @return BagItProfile The profile object.
     */
    private function setManifestsRequired(array $manifestsRequired): BagItProfile
    {
        $this->manifestsRequired = $manifestsRequired;
        return $this;
    }

    /**
     * @return array The list of allowed manifest algorithms. e.g. ["sha1", "md5"].
     */
    public function getManifestsAllowed(): array
    {
        return $this->manifestsAllowed;
    }

    /**
     * @param array $manifestsAllowed The list of allowed manifest algorithms. e.g. ["sha1", "md5"].
     * @return BagItProfile The profile object.
     * @throws ProfileException If manifestsAllowed does not include all entries from manifestsRequired.
     */
    private function setManifestsAllowed(array $manifestsAllowed): BagItProfile
    {
        if ($this->manifestsRequired !== [] && array_diff($this->manifestsRequired, $manifestsAllowed) !== []) {
            throw new ProfileException("Manifests-Allowed must include all entries from Manifests-Required");
        }
        $this->manifestsAllowed = $manifestsAllowed;
        return $this;
    }

    /**
     * @return bool Whether to allow the existence of a fetch.txt file. Default is true.
     */
    public function isAllowFetchTxt(): bool
    {
        return $this->allowFetchTxt;
    }

    /**
     * @param bool $allowFetchTxt Whether to allow the existence of a fetch.txt file. Default is true.
     * @return BagItProfile The profile object.
     */
    private function setAllowFetchTxt(?bool $allowFetchTxt): BagItProfile
    {
        $this->allowFetchTxt = $allowFetchTxt ?? true;
        return $this;
    }

    /**
     * @return bool Whether to require the existence of a fetch.txt file. Default is false.
     */
    public function isRequireFetchTxt(): bool
    {
        return $this->requireFetchTxt;
    }

    /**
     * @param bool $requireFetchTxt Whether to require the existence of a fetch.txt file. Default is false.
     * @return BagItProfile The profile object.
     * @throws ProfileException If requireFetchTxt is true and allowFetchTxt is false.
     */
    private function setRequireFetchTxt(?bool $requireFetchTxt): BagItProfile
    {
        if ($requireFetchTxt === true && $this->allowFetchTxt === false) {
            throw new ProfileException("Allow-Fetch.txt cannot be false if Require-Fetch.txt is true");
        }
        $this->requireFetchTxt = $requireFetchTxt ?? false;
        return $this;
    }

    /**
     * @return bool If true then the /data directory must contain either no files or a single zero byte
     */
    public function isDataEmpty(): bool
    {
        return $this->dataEmpty;
    }

    /**
     * @param bool $dataEmpty If true then the /data directory must contain either no files or a single zero byte
     * @return BagItProfile The profile object.
     */
    private function setDataEmpty(bool $dataEmpty): BagItProfile
    {
        $this->dataEmpty = $dataEmpty;
        return $this;
    }

    /**
     * @return string The serialization option value. One of forbidden, required, optional.
     */
    public function getSerialization(): string
    {
        return $this->serialization;
    }

    /**
     * @param string $serialization The serialization option value.
     * @return BagItProfile The profile object.
     */
    private function setSerialization(string $serialization): BagItProfile
    {
        $this->serialization = $serialization;
        return $this;
    }

    /**
     * @return array The list of MIME types acceptable as serialized formats.
     */
    public function getAcceptSerialization(): array
    {
        return $this->acceptSerialization;
    }

    /**
     * @param array|null $acceptSerialization The list of MIME types acceptable as serialized formats.
     * @return BagItProfile The profile object.
     */
    private function setAcceptSerialization(?array $acceptSerialization): BagItProfile
    {
        $this->acceptSerialization = $acceptSerialization;
        return $this;
    }

    /**
     * @return array The list of BagIt version numbers that will be accepted, e.g. "1.0".
     */
    public function getAcceptBagItVersion(): array
    {
        return $this->acceptBagItVersion;
    }

    /**
     * @param array $acceptBagItVersion The list of BagIt version numbers that will be accepted, e.g. "1.0".
     * @return BagItProfile The profile object.
     */
    private function setAcceptBagItVersion(array $acceptBagItVersion): BagItProfile
    {
        $this->acceptBagItVersion = $acceptBagItVersion;
        return $this;
    }

    /**
     * @return array The list of required tag manifest algorithms. e.g. ["sha1", "md5"].
     */
    public function getTagManifestsRequired(): array
    {
        return $this->tagManifestsRequired;
    }

    /**
     * @param array $tagManifestsRequired The list of required tag manifest algorithms. e.g. ["sha1", "md5"].
     * @return BagItProfile The profile object.
     */
    private function setTagManifestsRequired(array $tagManifestsRequired): BagItProfile
    {
        $this->tagManifestsRequired = $tagManifestsRequired;
        return $this;
    }

    /**
     * @return array The list of allowed tag manifest algorithms. e.g. ["sha1", "md5"].
     */
    public function getTagManifestsAllowed(): array
    {
        return $this->tagManifestsAllowed;
    }

    /**
     * @param array $tagManifestAllowed The list of allowed tag manifest algorithms. e.g. ["sha1", "md5"].
     * @return BagItProfile The profile object.
     */
    private function setTagManifestsAllowed(array $tagManifestAllowed): BagItProfile
    {
        $this->tagManifestsAllowed = $tagManifestAllowed;
        return $this;
    }

    /**
     * @return array The list of tag files that MUST be included in a conformant Bag.
     */
    public function getTagFilesRequired(): array
    {
        return $this->tagFilesRequired;
    }

    /**
     * @param array $tagFilesRequired The list of tag files that MUST be included in a conformant Bag.
     * @return BagItProfile The profile object.
     */
    private function setTagFilesRequired(array $tagFilesRequired): BagItProfile
    {
        $this->tagFilesRequired = $tagFilesRequired;
        return $this;
    }

    /**
     * @return array The list of tag files that MAY be included in a conformant Bag.
     */
    public function getTagFilesAllowed(): array
    {
        return $this->tagFilesAllowed;
    }

    /**
     * @param array $tagFilesAllowed The list of tag files/paths that MAY be included in a conformant Bag.
     * @return BagItProfile The profile object.
     */
    private function setTagFilesAllowed(array $tagFilesAllowed): BagItProfile
    {
        $this->tagFilesAllowed = $tagFilesAllowed;
        return $this;
    }

    /**
     * Assert that the array of paths are covered by the array of allowed paths and glob style patterns.
     * @param array $paths The list of paths.
     * @param array $allowed The list of allowed paths, and glob style patterns.
     * @return bool True if all paths are covered by allowed paths/patterns.
     */
    private function isRequiredPathsCoveredByAllowed(array $paths, array $allowed): bool
    {
        if (count($paths) === 0 || count($allowed) === 0) {
            return true;
        }
        $perfect_match = array_intersect($paths, $allowed);
        if (count($perfect_match) === count($paths)) {
            return true;
        }
        return $this->getPathsNotCoveredByAllowed($paths, $allowed) === [];
    }

    /**
     * Get the list of paths that are not covered by the allowed paths and glob style patterns.
     * @param array $paths The list of paths.
     * @param array $allowed The list of allowed paths and glob style patterns.
     * @return array The list of paths not covered by allowed paths/patterns.
     */
    private function getPathsNotCoveredByAllowed(array $paths, array $allowed): array
    {
        $perfect_match = array_intersect($paths, $allowed);
        $remaining = array_diff($paths, $perfect_match);
        foreach ($allowed as $allowedFile) {
            $regex = $this->convertGlobToRegex($allowedFile);
            $matching = array_filter($remaining, function ($tagFile) use ($regex) {
                return preg_match($regex, $tagFile) === 1;
            });
            if (count($matching) > 0) {
                $remaining = array_diff($remaining, $matching);
            }
            if (count($remaining) === 0) {
                return [];
            }
        }
        return $remaining;
    }

    /**
     * Convert a glob pattern to a regex pattern.
     * @param string $glob The glob pattern.
     * @return string The regex pattern.
     */
    private function convertGlobToRegex(string $glob): string
    {
        $regex = str_replace('.', '\.', $glob);
        $regex = preg_replace('~(?<=\[)(!)~', '^', $regex);
        $regex = str_replace('*', '[^/]+', $regex);
        $regex = str_replace('?', '[^/]', $regex);
        return "~^$regex$~";
    }

    /**
     * @return array The list of payload files that MUST be included in a conformant Bag.
     */
    public function getPayloadFilesRequired(): array
    {
        return $this->payloadFilesRequired;
    }

    /**
     * @param array $payloadFilesRequired The list of payload files that MUST be included in a conformant Bag.
     * @return BagItProfile The profile object.
     */
    private function setPayloadFilesRequired(array $payloadFilesRequired): BagItProfile
    {
        $this->payloadFilesRequired = $payloadFilesRequired;
        return $this;
    }

    /**
     * @return array The list of payload files that MAY be included in a conformant Bag.
     */
    public function getPayloadFilesAllowed(): array
    {
        return $this->payloadFilesAllowed;
    }

    /**
     * @param array $payloadFilesAllowed The list of payload files/paths that MAY be included in a conformant Bag.
     * @return BagItProfile The profile object.
     */
    private function setPayloadFilesAllowed(array $payloadFilesAllowed): BagItProfile
    {
        $this->payloadFilesAllowed = $payloadFilesAllowed;
        return $this;
    }

    /**
     * Case-insensitive string comparison.
     * @param string $expected The expected string.
     * @param string|null $provided The provided string.
     * @return bool True if the strings match.
     */
    private static function matchStrings(string $expected, ?string $provided): bool
    {
        return ($provided !== null && BagUtils::trimLower($expected) === BagUtils::trimLower($provided));
    }

    /**
     * Create a BagItProfile from a JSON string.
     * @param string|null $json_string The BagItProfile JSON string.
     * @throws ProfileException If there are errors with the profile.
     */
    public static function fromJson(?string $json_string): BagItProfile
    {
        $profileOptions = json_decode($json_string, true);
        if ($profileOptions === null) {
            throw new ProfileException("Error parsing profile");
        }
        $profile = new BagItProfile();
        try {
            $profile->setProfileIdentifier($profileOptions['BagIt-Profile-Info']['BagIt-Profile-Identifier'])
                ->setSourceOrganization($profileOptions['BagIt-Profile-Info']['Source-Organization'])
                ->setExternalDescription($profileOptions['BagIt-Profile-Info']['External-Description'])
                ->setVersion($profileOptions['BagIt-Profile-Info']['Version']);
        } catch (Exception $e) {
            throw new ProfileException("Missing required BagIt-Profile-Info tag", $e->getCode(), $e);
        }
        if (array_key_exists('BagIt-Profile-Version', $profileOptions['BagIt-Profile-Info'])) {
            $profile->setProfileSpecVersion($profileOptions['BagIt-Profile-Info']['BagIt-Profile-Version']);
        }
        if (array_key_exists('Contact-Name', $profileOptions['BagIt-Profile-Info'])) {
            $profile->setContactName($profileOptions['BagIt-Profile-Info']['Contact-Name']);
        }
        if (array_key_exists('Contact-Phone', $profileOptions['BagIt-Profile-Info'])) {
            $profile->setContactPhone($profileOptions['BagIt-Profile-Info']['Contact-Phone']);
        }
        if (array_key_exists('Contact-Email', $profileOptions['BagIt-Profile-Info'])) {
            $profile->setContactEmail($profileOptions['BagIt-Profile-Info']['Contact-Email']);
        }
        if (array_key_exists('Bag-Info', $profileOptions)) {
            $profile->setBagInfoTags($profileOptions['Bag-Info']);
        }
        if (array_key_exists('Manifests-Required', $profileOptions)) {
            $profile->setManifestsRequired($profileOptions['Manifests-Required']);
        }
        if (array_key_exists('Manifests-Allowed', $profileOptions)) {
            $profile->setManifestsAllowed($profileOptions['Manifests-Allowed']);
        }
        if (array_key_exists('Allow-Fetch.txt', $profileOptions)) {
            $profile->setAllowFetchTxt($profileOptions['Allow-Fetch.txt']);
        }
        if (array_key_exists('Require-Fetch.txt', $profileOptions)) {
            $profile->setRequireFetchTxt($profileOptions['Require-Fetch.txt']);
        }
        if (array_key_exists('Data-Empty', $profileOptions)) {
            $profile->setDataEmpty($profileOptions['Data-Empty']);
        }
        if (array_key_exists('Serialization', $profileOptions)) {
            $profile->setSerialization($profileOptions['Serialization']);
        }
        if (array_key_exists('Accept-Serialization', $profileOptions)) {
            $profile->setAcceptSerialization($profileOptions['Accept-Serialization']);
        }
        if (array_key_exists('Accept-BagIt-Version', $profileOptions)) {
            $profile->setAcceptBagItVersion($profileOptions['Accept-BagIt-Version']);
        }
        if (array_key_exists('Tag-Manifests-Required', $profileOptions)) {
            $profile->setTagManifestsRequired($profileOptions['Tag-Manifests-Required']);
        }
        if (array_key_exists('Tag-Manifests-Allowed', $profileOptions)) {
            $profile->setTagManifestsAllowed($profileOptions['Tag-Manifests-Allowed']);
        }
        if (array_key_exists('Tag-Files-Required', $profileOptions)) {
            $profile->setTagFilesRequired($profileOptions['Tag-Files-Required']);
        }
        if (array_key_exists('Tag-Files-Allowed', $profileOptions)) {
            $profile->setTagFilesAllowed($profileOptions['Tag-Files-Allowed']);
        }
        if (array_key_exists('Payload-Files-Required', $profileOptions)) {
            $profile->setPayloadFilesRequired($profileOptions['Payload-Files-Required']);
        }
        if (array_key_exists('Payload-Files-Allowed', $profileOptions)) {
            $profile->setPayloadFilesAllowed($profileOptions['Payload-Files-Allowed']);
        }
        return $profile;
    }

    /**
     * Validate this profile.
     * @return bool True if the profile is valid.
     * @throws ProfileException If the profile is not valid.
     */
    public function isValid(): bool
    {
        $errors = [];
        if ($this->getProfileIdentifier() === "") {
            $errors[] = "BagIt-Profile-Identifier is required";
        }
        if (count($this->getAcceptBagItVersion()) === 0) {
            $errors[] = "Accept-BagIt-Version must contain at least one version";
        }
        if (!in_array($this->getSerialization(), ['forbidden', 'required', 'optional'])) {
            $errors[] = "Serialization must be one of forbidden, required, optional";
        }
        if (
            in_array($this->getSerialization(), ['required', 'optional']) &&
            count($this->getAcceptSerialization()) === 0
        ) {
            $errors[] = "Accept-Serialization MIME type(s) must be specified if Serialization
             is required or optional";
        }
        if (
            !$this->isRequiredPathsCoveredByAllowed(
                $this->getManifestsRequired(),
                $this->getManifestsAllowed()
            )
        ) {
            $errors[] = "Manifests-Allowed must include all entries from Manifests-Required";
        }
        if (
            !$this->isRequiredPathsCoveredByAllowed(
                $this->getTagManifestsRequired(),
                $this->getTagManifestsAllowed()
            )
        ) {
            $errors[] = "Tag-Manifests-Allowed must include all entries from Tag-Manifests-Required";
        }
        if (
            !$this->isRequiredPathsCoveredByAllowed(
                $this->getTagFilesRequired(),
                $this->getTagFilesAllowed()
            )
        ) {
            $errors[] = "Tag-Files-Allowed must include all entries from Tag-Files-Required";
        }
        if (
            !$this->isRequiredPathsCoveredByAllowed(
                $this->getPayloadFilesRequired(),
                $this->getPayloadFilesAllowed()
            )
        ) {
            $errors[] = "Payload-Files-Allowed must include all entries from Payload-Files-Required";
        }
        if (
            !$this->isRequiredPathsCoveredByAllowed(
                $this->getPayloadFilesRequired(),
                $this->getPayloadFilesAllowed()
            )
        ) {
            $errors[] = "Payload-Files-Allowed must include all entries from Payload-Files-Required";
        }
        if (count($errors) > 0) {
            throw new ProfileException(implode("\n", $errors));
        }
        return true;
    }

    /**
     * Validate a bag against this profile.
     * @param Bag $bag The bag to validate.
     * @return bool True if the bag is valid.
     * @throws ProfileException If the bag is not valid.
     */
    public function validateBag(Bag $bag): bool
    {
        $errors = [];
        $warnings = [];
        if (count($this->requiredBagInfoTags) > 0 && !$bag->isExtended()) {
            $errors[] = "Profile requires Bag-Info tags but the Bag is not extended";
        }
        foreach ($this->getBagInfoTags() as $requiredTag => $infoTag) {
            if ($infoTag->isRequired() && !$bag->hasBagInfoTag($requiredTag)) {
                $errors[] = "Profile requires tag ($requiredTag) which is missing from the bag";
            }
            if (
                !$infoTag->isRepeatable() &&
                $bag->hasBagInfoTag($requiredTag) &&
                count($bag->getBagInfoByTag($requiredTag)) > 1
            ) {
                $errors[] = "Profile does not allow tag ($requiredTag) to repeat, there are " .
                count($bag->getBagInfoByTag($requiredTag)) . " values in the bag";
            }
            if ($infoTag->getValues() !== [] && $bag->hasBagInfoTag($requiredTag)) {
                $diff = array_diff($bag->getBagInfoByTag($requiredTag), $infoTag->getValues());
                if ($diff !== []) {
                    $errors[] = "Profile requires tag ($requiredTag) to have value(s) (" .
                        implode(", ", $infoTag->getValues()) . ") but the bag has value(s) (" .
                        implode(", ", $diff) . ")";
                }
            }
        }
        if (!$this->isAllowFetchTxt() && $bag->hasFetchFile()) {
            $errors[] = "Profile does not allow fetch.txt but the bag has one";
        }
        if ($this->isRequireFetchTxt() && !$bag->hasFetchFile()) {
            $errors[] = "Profile requires fetch.txt but the bag does not have one";
        }
        if ($this->isDataEmpty()) {
            $manifests = current($bag->getPayloadManifests());
            $hashes = $manifests->getHashes();
            if (count($hashes) > 1) {
                $errors[] = "Profile requires /data directory to be empty or contain a single 0 byte file but it" .
                    " contains " . count($hashes) . " files";
            } elseif (count($hashes) == 1) {
                $file = array_key_first($hashes);
                $absolute = $bag->makeAbsolute($file);
                if (stat($absolute)['size'] > 0) {
                    $errors[] = "Profile requires /data directory to be empty or contain a single 0 byte file but it" .
                        " contains a single file of size " . stat($absolute)['size'];
                }
            }
        }
        if ($this->getSerialization() === 'required') {
            if ($bag->getSerializationMimeType() === null) {
                $errors[] = "Profile requires serialization MIME type but the bag has none";
            } elseif (!in_array($bag->getSerializationMimeType(), $this->getAcceptSerialization())) {
                $errors[] = "Profile requires serialization MIME type (" .
                    implode(", ", $this->getAcceptSerialization()) .
                    ") but the bag has MIME type (" . $bag->getSerializationMimeType() . ")";
            }
        } elseif ($this->getSerialization() === 'forbidden' && $bag->getSerializationMimeType() !== null) {
            $errors[] = "Profile forbids serialization MIME type but the bag has MIME type (" .
                $bag->getSerializationMimeType() . ")";
        } elseif (
            $this->getSerialization() === 'optional' &&
            $bag->getSerializationMimeType() !== null &&
            !in_array($bag->getSerializationMimeType(), $this->getAcceptSerialization())
        ) {
            $errors[] = "Profile allows for serialization MIME type (" .
                implode(", ", $this->getAcceptSerialization()) .
                ") but the bag has MIME type (" . $bag->getSerializationMimeType() . ")";
        }
        if (
            $this->getAcceptBagItVersion() !== [] &&
            !in_array($bag->getVersionString(), $this->getAcceptBagItVersion())
        ) {
            $errors[] = "Profile requires BagIt version of (" . implode(", ", $this->getAcceptBagItVersion()) .
                ") but the bag has version (" . $bag->getVersionString() . ")";
        }
        if ($this->getManifestsRequired() !== []) {
            $manifests = array_keys($bag->getPayloadManifests());
            $diff = array_diff($manifests, $this->getManifestsRequired()) +
                array_diff($this->getManifestsRequired(), $manifests);
            if ($diff !== []) {
                $errors[] = "Profile requires payload manifest(s) which are missing from the bag (" .
                    implode(", ", $diff) . ")";
            }
        }
        if ($this->getManifestsAllowed() !== []) {
            $manifests = array_keys($bag->getPayloadManifests());
            $diff = array_diff($manifests, $this->getManifestsAllowed());
            if ($diff !== []) {
                $errors[] = "Profile allows payload manifest(s) (" . implode(", ", $this->getManifestsAllowed()) .
                    "), but the bag has manifest(s) (" . implode(", ", $diff) . ") which are not allowed";
            }
        }
        if ($this->getTagManifestsRequired() !== []) {
            $manifests = array_keys($bag->getTagManifests());
            $diff = array_diff($manifests, $this->getTagManifestsRequired()) +
                array_diff($this->getTagManifestsRequired(), $manifests);
            if ($diff !== []) {
                $errors[] = "Profile requires tag manifest(s) which are missing from the bag (" .
                    implode(", ", $diff) . ")";
            }
        }
        if ($this->getTagManifestsAllowed() !== []) {
            $manifests = array_keys($bag->getTagManifests());
            $diff = array_diff($manifests, $this->getTagManifestsAllowed());
            if ($diff !== []) {
                $errors[] = "Profile allows tag manifest(s) (" . implode(", ", $this->getTagManifestsAllowed()) .
                    "), but the bag has manifest(s) (" . implode(", ", $diff) . ") which are not allowed";
            }
        }
        if ($this->getTagFilesRequired() !== []) {
            // Grab the first tag manifest, they should all be the same
            $manifests = $bag->getTagManifests()[0];
            $tag_files = array_keys($manifests->getHashes());
            $diff = array_diff($this->getTagFilesRequired(), $tag_files) +
                array_diff($tag_files, $this->getTagFilesRequired());
            if ($diff !== []) {
                $errors[] = "Profile requires tag files(s) which are missing from the bag (" .
                    implode(", ", $diff) . ")";
            }
        }
        if ($this->getTagFilesAllowed() !== []) {
            // Grab the first tag manifest, they should all be the same
            $manifests = $bag->getTagManifests()[0];
            $tag_files = array_keys($manifests->getHashes());
            $diff = $this->getPathsNotCoveredByAllowed($tag_files, $this->getTagFilesAllowed());
            if ($diff !== []) {
                $errors[] = "Profile allows tag files(s) (" . implode(", ", $this->getTagFilesAllowed()) .
                    "), but the bag has manifest(s) (" . implode(", ", $diff) . ") which are not allowed";
            }
        }
        if ($this->getPayloadFilesRequired() !== []) {
            // Grab the first tag manifest, they should all be the same
            $manifests = $bag->getPayloadManifests()[0];
            $payload_files = array_keys($manifests->getHashes());
            $diff = array_diff($this->getPayloadFilesRequired(), $payload_files) +
                array_diff($payload_files, $this->getPayloadFilesRequired());
            if ($diff !== []) {
                $errors[] = "Profile requires payload file(s) which are missing from the bag (" .
                    implode(", ", $diff) . ")";
            }
        }
        if ($this->getPayloadFilesAllowed() !== []) {
            // Grab the first tag manifest, they should all be the same
            $manifests = $bag->getPayloadManifests()[0];
            $tag_files = array_keys($manifests->getHashes());
            $diff = $this->getPathsNotCoveredByAllowed($tag_files, $this->getPayloadFilesAllowed());
            if ($diff !== []) {
                $errors[] = "Profile allows payload files(s) (" . implode(", ", $this->getPayloadFilesAllowed()) .
                    "), but the bag has file(s) (" . implode(", ", $diff) . ") which are not allowed";
            }
        }
        if (count($errors) > 0) {
            throw new ProfileException(implode("\n", $errors));
        }
        return true;
    }

    /**
     * Get the list of warnings generated during the validation of the profile.
     * @return array The list of warnings.
     */
    public function getWarnings(): array
    {
        return $this->profileWarnings;
    }
}
