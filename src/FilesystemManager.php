<?php
/**
 * 
 * @fileName FileSystemManager.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 09/04/2019
 * @version FileSystemManager.php 2019.04.09
 * */
namespace Lx\StorageOSS;

use Illuminate\Filesystem\FilesystemManager as FM;
use League\Flysystem\FilesystemInterface;

class FileSystemManager  extends FM {

  protected function adapt(FilesystemInterface $filesystem) {
    return new FilesystemAdapter($filesystem);
  }
}
