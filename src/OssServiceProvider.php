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

class OssServiceProvider extends ServiceProvider {

  public function boot () {
    $this->app->singleton(
      \Illuminate\Contracts\Filesystem\Factory::class,
      function ($app) {
        // 添加扩展支持
        $fs = new FilesystemManager($app);
        $fs->extend('oss', function ($app, $config) {
          $client = new OssClient($config);
          $adapter = new OssAdapter($client, $config['object_case'] ?: '');
          $filesystem = new Filesystem($adapter);
          $filesystem->addPlugin(new PutFile());
          $filesystem->addPlugin(new PutRemoteFile());
          return $filesystem;
        });
        return $fs;
      });
    /**
     * */

    $this->app->singleton('filesystem', function ($app) {
      // 添加扩展支持
      //$fs = new \Illuminate\Filesystem\FilesystemManager($app);
      $fs = new FilesystemManager($app);
      $fs->extend('oss', function ($app, $config) {
        $client = new OssClient($config);
        $adapter = new OssAdapter($client, $config['object_case'] ?: '');
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new PutFile());
        $filesystem->addPlugin(new PutRemoteFile());
        return $filesystem;
      });
      return $fs;
    });

     Storage::extend('oss', function($app, $config) {
        // 添加扩展支持
       $client = new OssClient($config);
       $adapter = new OssAdapter($client, $config['object_case'] ?: '');
       $filesystem = new Filesystem($adapter);
       //$filesystem->addPlugin(new PutFile());
       //$filesystem->addPlugin(new PutRemoteFile());
       return $filesystem;
     });
  }
}
