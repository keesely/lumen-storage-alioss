<?php
/**
 * 
 * @fileName FileSystemAdapter.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 09/04/2019
 * @version FileSystemAdapter.php 2019.04.09
 * */
namespace Package\StorageOSS;

use Illuminate\Filesystem\FilesystemAdapter as FA;
use Illuminate\Http\File;

class FileSystemAdapter  extends FA {

  public function putRemoteFile ($path, $remote_url, array $options = NULL) {
  
  } 

  public function PutFile ($path, $filePath, $options = array()) {
    if (is_string($filePath) && is_file($filePath)) {
      return (bool)$this->driver->writeFile($path, $filePath, $options);
    }
    return parent::PutFile($path, $filePath, $options);
  }
}
