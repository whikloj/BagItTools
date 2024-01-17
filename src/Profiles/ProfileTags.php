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

    public static function fromJson(string $tag, array $tagOpts): ProfileTags
    {
        return new ProfileTags(
            $tag,
            $tagOpts['required'] ?? false,
            $tagOpts['values'] ?? [],
            $tagOpts['repeatable'] ?? true,
            $tagOpts['description'] ?? ""
        );
    }
}
