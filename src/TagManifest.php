<?php

namespace whikloj\BagItTools;


class TagManifest extends Manifest {

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
  public function __construct(\whikloj\BagItTools\Bag $bag, $algorithm, $load = FALSE) {
    parent::__construct($bag, $algorithm, $load);
    $this->filename = "tagmanifest-{$algorithm}.txt";
  }

  /**
   * {@inheritdoc}
   */
  public function update() {
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