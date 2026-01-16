<?php
/**
 * 
 * @fileName OssServiceProvider.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 08/04/2019
 * @version OssServiceProvider.php 2019.04.08
 * */
namespace Lx\StorageOSS;

use Lx\StorageOSS\Plugins\PutFile;
use Lx\StorageOSS\Plugins\PutRemoteFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;

use Lx\StorageOSS\OssClient;
use Lx\StorageOSS\OssAdapter;
use Lx\StorageOSS\UrlGenerators\PublicUrlGenerator;
use Lx\StorageOSS\UrlGenerators\TemporaryUrlGenerator;
use League\Flysystem\PathNormalizer;

class OssServiceProvider extends ServiceProvider {

  public function boot () {
    // Register the 'oss' driver using Laravel's Storage facade
    Storage::extend('oss', function($app, $config) {
      // Create OSS client
      $client = new OssClient($config);
      
      // Create adapter with path prefix
      $adapter = new OssAdapter($client, $config['object_case'] ?? '');

      // Create filesystem instance
      $filesystem = new Filesystem(
        $adapter, [], null,
        new PublicUrlGenerator($client),
        new TemporaryUrlGenerator($client),
      );
      
      // Add plugins
      // $filesystem->addPlugin(new PutFile());
      // $filesystem->addPlugin(new PutRemoteFile());
      
      return $filesystem;
    });
  }
}
