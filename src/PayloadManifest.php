<?php

namespace whikloj\BagItTools;

/**
 * Payload Manifest class.
 * @package whikloj\BagItTools'
 */
class PayloadManifest extends AbstractManifest
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
        parent::__construct($bag, $algorithm, "manifest-{$algorithm}.txt", $load);
    }


  /**
   * Remove a file from the manifest.
   *
   * @param string $path
   *   The path of the file.
   */
    public function removeFile($path)
    {
        $path = BagUtils::baseInData($path);
        if (in_array($path, array_keys($this->hashes))) {
            unset($this->hashes[$path]);
        }
    }

  /**
   * Add a new file to the manifest.
   *
   * @param string $path
   *   The path of the file.
   */
    public function addFile($path)
    {
        $path = BagUtils::baseInData($path);
        if (!in_array($path, array_keys($this->hashes))) {
            $this->hashes[$path] = $this->calculateHash($this->bag->makeAbsolute($path));
        }
    }

  /**
   * {@inheritdoc}
   */
    public function update()
    {
        $this->hashes = [];
        $files = $this->getAllFiles($this->bag->makeAbsolute("data"));
        foreach ($files as $file) {
            $this->hashes[$this->bag->makeRelative($file)] = "";
        }
        parent::update();
    }
}
