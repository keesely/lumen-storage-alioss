<?php
namespace Lx\StorageOSS\Plugins;

use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;
use OSS\Core\OssException as Exception;

class PutFile extends AbstractPlugin
{

    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod() {
        return 'putLocalFile';
    }

    public function handle($path, $filePath, array $options = []){
      $config = new Config($options);
      if (method_exists($this->filesystem, 'getConfig')) {
        $config->setFallback($this->filesystem->getConfig());
      }

      if (!is_file($filePath)) {
        throw new Exception("local file is not exits: {$filePath}");
      }

      return (bool)$this->filesystem->getAdapter()->writeFile($path, $filePath, $config);
    }
}
