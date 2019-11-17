<?php
/**
 * Created by PhpStorm.
 * User: whikloj
 * Date: 2019-11-16
 * Time: 15:22
 */

namespace whikloj\BagItTools;


class BagFactory {



  public static function create($rootPath)
  {
    if (file_exists($rootPath)) {
      throw new BagItException("Path {$rootPath} already exists, cannot create a new bag there.");
    }
    if (strpos($rootPath, DIRECTORY_SEPARATOR) >= 0) {
      $components = explode($rootPath, DIRECTORY_SEPARATOR);
      if (strtolower($components[count($components)]) == "data") {
        throw new BagItException("Path cannot end with a directory called \"data\".");
      }
    }


  }

}