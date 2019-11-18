<?php

namespace whikloj\BagItTools;

/**
 * Tag Manifest class.
 * @package whikloj\BagItTools
 */
class TagManifest extends AbstractManifest
{

  /**
   * PayloadManifest constructor.
   *
   * @param \whikloj\BagItTools\Bag $bag
   *   The bag this manifest is part of.
   * @param $algorithm
   *   The BagIt name of the hash algorithm.
   * @param bool $load
   *   Whether we are loading an existing file
   */
    public function __construct(\whikloj\BagItTools\Bag $bag, $algorithm, $load = false)
    {
        parent::__construct($bag, $algorithm, "tagmanifest-{$algorithm}.txt", $load);
    }

  /**
   * {@inheritdoc}
   */
    public function update()
    {
        $this->hashes = [];
        $files = $this->getAllFiles($this->bag->getBagRoot(), ["data"]);
        foreach ($files as $file) {
            if (substr(basename($file), 0, 11) !== "tagmanifest") {
                $this->hashes[$this->bag->makeRelative($file)] = "";
            }
        }
        parent::update();
    }
}
