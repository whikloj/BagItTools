<?php

declare(strict_types=1);

namespace whikloj\BagItTools\Profiles;

/**
 * Class for holding the BagItProfile bag-info requirements.
 *
 * @package whikloj\BagItTools\Profiles
 * @author Jared Whiklo
 * @since 5.0.0
 */
class ProfileTags
{
    /**
     * Array with keys matching optional keys from specification, all other keys are system specific.
     */
    private const SPEC_TAGS = ['required' => 0, 'values' => 0, 'repeatable' => 0, 'description' => 0];

    /**
     * @var string
     */
    private string $tag;

    /**
     * @var bool
     */
    private bool $required = false;

    /**
     * @var array
     */
    private array $values = [];

    /**
     * @var bool
     */
    private bool $repeatable = true;

    /**
     * @var string
     */
    private string $description = "";

    /**
     * @var array<string, mixed>
     */
    private array $otherOptions = [];

    /**
     * ProfileTags constructor.
     * @param string $tag
     * @param bool $required
     * @param array $values
     * @param bool $repeatable
     * @param string $description
     */
    public function __construct(string $tag, bool $required, array $values, bool $repeatable, string $description)
    {
        $this->tag = $tag;
        $this->required = $required;
        $this->values = $values;
        $this->repeatable = $repeatable;
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return bool
     */
    public function isRepeatable(): bool
    {
        return $this->repeatable;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Return any tags defined in the BagItProfile but not in the specification.
     * @return array<string, string> Array of tagName => tagValue
     */
    public function getOtherTagOptions(): array
    {
        return $this->otherOptions;
    }

    /**
     * Set the other tag options.
     * @param array $tagOptions Array of optionName => optionValue
     */
    protected function setOtherTagOptions(array $tagOptions): void
    {
        $this->otherOptions = $tagOptions;
    }

    /**
     * Create a ProfileTags object from a JSON array.
     * @param string $tag Tag name
     * @param array<string, string> $tagOpts Tag options
     * @return ProfileTags The created object.
     */
    public static function fromJson(string $tag, array $tagOpts): ProfileTags
    {
        $profileTag = new ProfileTags(
            $tag,
            $tagOpts['required'] ?? false,
            $tagOpts['values'] ?? [],
            $tagOpts['repeatable'] ?? true,
            $tagOpts['description'] ?? ""
        );
        $otherTags = array_diff_key($tagOpts, self::SPEC_TAGS);
        if (count($otherTags) > 0) {
            $profileTag->setOtherTagOptions($otherTags);
        }
        return $profileTag;
    }
}
