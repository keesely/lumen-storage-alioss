<?php
/**
 * 
 * @fileName OssAdapter.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 08/04/2019
 * @version OssAdapter.php 2019.04.08
 * */
namespace Lx\StorageOSS;

use Lx\StorageOSS\OssClient as Client;
use Lx\StorageOSS\UrlGenerators\PublicUrlGenerator;
use Lx\StorageOSS\UrlGenerators\TemporaryUrlGenerator;
use OSS\Core\OssException;
use OSS\OssClient;
use Log;

use League\Flysystem\Config;
//use League\Flysystem\Util;
//use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FileAttributes;
//use Symfony\Component\Mime\MimeTypes;

//class OssAdapter extends AbstractAdapter {
class OssAdapter implements FilesystemAdapter {

  //use NotSupportingVisibilityTrait;

  use BackwardCompatibilitySupport;

  protected static $resultMap = [
    'Body'           => 'raw_contents',
    'Content-Length' => 'size',
    'ContentType'    => 'mimetype',
    'Size'           => 'size',
    'StorageClass'   => 'storage_class',
  ];

  protected static $metaOptions = [
    'CacheControl',
    'Expires',
    'ServerSideEncryption',
    'Metadata',
    'ACL',
    'ContentType',
    'ContentDisposition',
    'ContentLanguage',
    'ContentEncoding',
  ];

  protected static $metaMap = [
    'CacheControl'         => 'Cache-Control',
    'Expires'              => 'Expires',
    'ServerSideEncryption' => 'x-oss-server-side-encryption',
    'Metadata'             => 'x-oss-metadata-directive',
    'ACL'                  => 'x-oss-object-acl',
    'ContentType'          => 'Content-Type',
    'ContentDisposition'   => 'Content-Disposition',
    'ContentLanguage'      => 'response-content-language',
    'ContentEncoding'      => 'Content-Encoding',
  ];

  //配置
  protected $options = [
    'Multipart'   => 128
  ];

  protected $publicUrlGenerator;
  protected $temporaryUrlGenerator;

  static $metadata = [];

  public function __construct(Client $client, string $prefix = '', array $options = []) {
    $this->setPathPrefix($prefix);
    $this->client = $client;
    $this->options = array_merge($this->options, $options);
    
    $this->publicUrlGenerator = new PublicUrlGenerator($client);
    $this->temporaryUrlGenerator = new TemporaryUrlGenerator($client);
  }

  public function __get ($key) {
    return $this->client->$key;
  }

  /**
   * Get the OSSClient instance.
   *
   * @return OssClient
   */
  public function getClient()
  {
    return $this->client->getOssClient();
  }

  /**
   * Write a new file.
   *
   * @param string $path
   * @param string $contents
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
   */
  public function write(string $path, string $contents, Config $config): void {
    $object = $this->applyPathPrefix($path);
    $options = $this->getOptions($this->options, $config);

    if (! isset($options[OssClient::OSS_LENGTH])) {
      //$options[OssClient::OSS_LENGTH] = Util::contentSize($contents);
      //$options[OssClient::OSS_LENGTH] = mb_strlen($contents);
      $size = defined('MB_OVERLOAD_STRING') ? mb_strlen($contents, '8bit') : strlen($contents);
      $options[OssClient::OSS_LENGTH] = $size;
    }
    if (! isset($options[OssClient::OSS_CONTENT_TYPE])) {
      $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, $contents);
    }
    try {
      $this->client->putObject($object, $contents, $options);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return;
      //return fals
    }
    //return 
    $this->normalizeResponse($options, $path);
    return;
  }

  /**
   * Write a new file using a stream.
   *
   * @param string $path
   * @param resource $contents
   * @param Config $config Config object
   *
   * @throws UnableToWriteFile
   * @throws FilesystemException
   */
  public function writeStream(string $path, $contents, Config $config): void {
    $object = $this->applyPathPrefix($path);
    $options = $this->getOptions($this->options, $config);
    
    try {
      $this->client->putStream($object, $contents, $options);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      throw new \League\Flysystem\UnableToWriteFile($path, $e);
    }
    
    return;
  }

  public function writeFile($path, $filePath, Config $config) {
    $object = $this->applyPathPrefix($path);
    $options = $this->getOptions($this->options, $config);
    $options[OssClient::OSS_CHECK_MD5] = true;

    if (! isset($options[OssClient::OSS_CONTENT_TYPE])) {
      $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, '');
    }
    try {
      $this->getClient()->uploadFile($this->bucket, $object, $filePath, $options);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return false;
    }
    return $this->normalizeResponse($options, $path);
  }

  /**
   * Update a file.
   *
   * @param string $path
   * @param string $contents
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
   */
  public function update($path, $contents, Config $config) {
    if (! ($vs = $config->has('visibility')) && ! ($acl = $config->has('ACL'))) {
      $config->set(static::$metaMap['ACL'], $this->getObjectACL($path));
    }
    // $this->delete($path);
    return $this->write($path, $contents, $config);
  }

  /**
   * Update a file using a stream.
   *
   * @param string $path
   * @param resource $resource
   * @param Config $config Config object
   *
   * @return array|false false on failure file meta data on success
   */
  public function updateStream($path, $resource, Config $config) {
    $contents = stream_get_contents($resource);
    return $this->update($path, $contents, $config);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($path, $newpath, Config $config) {
    if (! $this->copy($path, $newpath, $config)){
      return false;
    }

    return $this->delete($path);
  }

  /**
   * {@inheritdoc}
   */
  public function copy($path, $newpath, Config $config): void {
    $object = $this->applyPathPrefix($path);
    $newObject = $this->applyPathPrefix($newpath);
    try{
      $this->getClient()->copyObject($this->bucket, $object, $this->bucket, $newObject);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return;
    }

    return; // true;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path): void {
    $object = $this->applyPathPrefix($path);
    try{
      $this->client->deleteObject($object);
    }catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return;
    }

    //return 
    ! $this->has($path);
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDir($dirname) {
    $dirname = rtrim($this->applyPathPrefix($dirname), '/').'/';
    $dirObjects = $this->listDirObjects($dirname, true);

    if(count($dirObjects['objects']) > 0 ){
      foreach($dirObjects['objects'] as $object) {
        $objects[] = $object['Key'];
      }

      try {
        $this->getClient()->deleteObjects($this->bucket, $objects);
      } catch (OssException $e) {
        $this->logErr(__FUNCTION__, $e);
        return false;
      }

    }

    try {
      $this->client->deleteObject($dirname);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return false;
    }

    return true;
  }

  /**
   * 列举文件夹内文件列表；可递归获取子文件夹；
   * @param string $dirname 目录
   * @param bool $recursive 是否递归
   * @return mixed
   * @throws OssException
   */
  public function listDirObjects($dirname = '', $recursive =  false)
  {
    $delimiter = '/';
    $nextMarker = '';
    $maxkeys = 1000;
    //$dirname = $this->applyPathPrefix($dirname);

    //存储结果
    $result = [];

    while(true){
      $options = [
        'delimiter' => $delimiter,
        'prefix'    => $dirname,
        'max-keys'  => $maxkeys,
        'marker'    => $nextMarker,
      ];

      try {
        //$listObjectInfo = $this->getClient()->listObjects($this->bucket, $options);
        $listObjectInfo = $this->client->listObjects($delimiter, $dirname, $options);
      } catch (OssException $e) {
        $this->logErr(__FUNCTION__, $e);
        // return false;
        throw $e;
      }

      $nextMarker = $listObjectInfo->getNextMarker(); // 得到nextMarker，从上一次listObjects读到的最后一个文件的下一个文件开始继续获取文件列表
      $objectList = $listObjectInfo->getObjectList(); // 文件列表
      $prefixList = $listObjectInfo->getPrefixList(); // 目录列表

      if (!empty($objectList)) {
        foreach ($objectList as $objectInfo) {

          $object['Prefix']       = $dirname;
          $object['Key']          = $objectInfo->getKey();
          $object['LastModified'] = $objectInfo->getLastModified();
          $object['eTag']         = $objectInfo->getETag();
          $object['Type']         = $objectInfo->getType();
          $object['Size']         = $objectInfo->getSize();
          $object['StorageClass'] = $objectInfo->getStorageClass();

          $result['objects'][] = $object;
        }
      }else{
        $result["objects"] = [];
      }

      if (!empty($prefixList)) {
        foreach ($prefixList as $prefixInfo) {
          $result['prefix'][] = $prefixInfo->getPrefix();
        }
      }else{
        $result['prefix'] = [];
      }

      //递归查询子目录所有文件
      if($recursive){
        foreach( $result['prefix'] as $pfix){
          $next  =  $this->listDirObjects($pfix , $recursive);
          $result["objects"] = array_merge($result['objects'], $next["objects"]);
        }
      }

      //没有更多结果了
      if ($nextMarker === '') {
        break;
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createDir($dirname, Config $config)
  {
    $object = $this->applyPathPrefix($dirname);
    $options = $this->getOptionsFromConfig($config);

    try {
      $this->getClient()->createObjectDir($this->bucket, $object, $options);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return false;
    }

    return ['path' => $dirname, 'type' => 'dir'];
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibility($path, $visibility): void
  {
    $object = $this->applyPathPrefix($path);
    if (!in_array($visibility, ['default', 'public', 'protected', 'private', 'public-read', 'public-read-write'])) {
        $visibility = 'default';
    }
    if ('public' == $visibility) $visibility = 'public-read-write';
    if ('protected' == $visibility) $visibility = 'public-read';
    //$acl = ( $visibility === 'public' ) ? OssClient::OSS_ACL_TYPE_PUBLIC_READ : OssClient::OSS_ACL_TYPE_PRIVATE;

    $this->client->setAcl($object, $visibility);
    //$this->getClient()->putObjectAcl($this->bucket, $object, $acl);

    return;
    //return compact('visibility');
  }

  /**
   * {@inheritdoc}
   */
  public function has($path) {
    $object = $this->applyPathPrefix($path);
    try {
      return $this->client->doesObjectExist($object);
    } catch (\Exception $e) {
      if ($this->client->debug) {
        Log::error($e);
      }
      return false;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function read($path): string {
    $result = $this->readObject($path);
    $result['contents'] = (string) $result['raw_contents'];
    unset($result['raw_contents']);
    return $result['contents'];
  }

  /**
   * {@inheritdoc}
   */
  public function readStream(string $path) {
    $object = $this->applyPathPrefix($path);
    
    try {
      $stream = $this->client->getStream($object);
      
      if (!is_resource($stream)) {
        throw new \League\Flysystem\UnableToReadFile($path);
      }
      
      rewind($stream);
      return $stream;
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      throw new \League\Flysystem\UnableToReadFile($path, $e);
    }
  }

  /**
   * Read an object from the OssClient.
   *
   * @param string $path
   *
   * @return array
   */
  protected function readObject($path) {
    $object = $this->applyPathPrefix($path);
    $result['Body'] = $this->client->getObject($object);
    $result = array_merge($result, ['type' => 'file']);
    return $this->normalizeResponse($result, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function listContents($directory = '', $recursive = false): iterable
  {
    $directory = $this->applyPathPrefix($directory);
    $dirObjects = $this->listDirObjects($directory, true);
    $contents = $dirObjects["objects"];

    $result = array_map([$this, 'normalizeResponse'], $contents);
    $result = array_filter($result, function ($value) {
      return $value['path'] !== false;
    });

    return Util::emulateDirectories($result);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($path): FileAttributes {
    $object = $this->applyPathPrefix($path);
    if ($meta = static::$metadata[$object] ?? null) return $meta;

    try {
      $meta = $this->client->getObjectMeta($object);
      $acl = $this->client->getAcl($object);

      return static::$metadata[$object] = new FileAttributes(
        path: $path,
        fileSize: $meta['content-length'] ?? 0,
        visibility: $acl, //$meta['x-oss-object-type'] ?? '',
        lastModified: strtotime($meta['last-modified'] ?? null),
        mimeType: $meta['content-type'] ?? 'none',
        extraMetadata: $meta,
      );
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return new FileAttributes(
        path: $path,
        fileSize: 0,
        visibility: 'none',
        lastModified: 0,
        mimeType: 'none',
      );
    }

    //return $meta;
  }

  /**
   * {@inheritdoc}
   */
  public function getSize($path)
  {
    $object = $this->getMetadata($path);
    $object['size'] = $object['content-length'];
    return $object;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimetype($path)
  {
    if( $object = $this->getMetadata($path))
      $object['mimetype'] = $object['content-type'];
    return $object;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp($path)
  {
    if( $object = $this->getMetadata($path))
      $object['timestamp'] = strtotime( $object['last-modified'] );
    return $object;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility($path) {
    $object = $this->applyPathPrefix($path);
    try {
      $acl = $this->getClient()->getObjectAcl($this->bucket, $object);
    } catch (OssException $e) {
      $this->logErr(__FUNCTION__, $e);
      return false;
    }

    if ($acl == OssClient::OSS_ACL_TYPE_PUBLIC_READ ){
      $res['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;
    }else{
      $res['visibility'] = AdapterInterface::VISIBILITY_PRIVATE;
    }

    return $res;
  }


  /**
   * @param $path
   *
   * @return string
   */
  public function getUrl( $path, array|null $options = NULL) {
    $object = $this->applyPathPrefix($path);
    if (!$this->has($path)) throw new FileNotFoundException($path.' not found');
    return $this->publicUrlGenerator->generate($object);
  }
  
  /**
   * Get a temporary URL for a file.
   *
   * @param string $path
   * @param int $expiration
   * @param array|null $options
   * @return string
   */
  public function getTemporaryUrl(string $path, int $expiration, array|null $options = NULL): string {
    $object = $this->applyPathPrefix($path);
    if (!$this->has($path)) throw new FileNotFoundException($path.' not found');
    $method = $options['method'] ?? 'GET';
    return $this->temporaryUrlGenerator->generate($object, $expiration, $method);
  }

  /**
   * The the ACL visibility.
   *
   * @param string $path
   *
   * @return string
   */
  protected function getObjectACL($path)
  {
    $metadata = $this->getVisibility($path);

    return $metadata['visibility'] === AdapterInterface::VISIBILITY_PUBLIC ? OssClient::OSS_ACL_TYPE_PUBLIC_READ : OssClient::OSS_ACL_TYPE_PRIVATE;
  }

  /**
   * Normalize a result from OSS.
   *
   * @param array  $object
   * @param string $path
   *
   * @return array file metadata
   */
  protected function normalizeResponse(array $object, $path = null) {
    $result = [
      'path' => $path ?: 
      $object['Key']
        //$this->removePathPrefix(isset($object['Key']) ? 
          //$object['Key'] : 
          //$object['Prefix'])
    ];
    $result['dirname'] = Util::dirname($result['path']);

    if (isset($object['LastModified'])) {
      $result['timestamp'] = strtotime($object['LastModified']);
    }

    if (substr($result['path'], -1) === '/') {
      $result['type'] = 'dir';
      $result['path'] = rtrim($result['path'], '/');
      return $result;
    }

    $result = array_merge($result, Util::map($object, static::$resultMap), ['type' => 'file']);
    return $result;
  }

  /**
   * Get options for a OSS call. done
   *
   * @param array  $options
   *
   * @return array OSS options
   */
  protected function getOptions(array $options = [], Config|null $config = null) {
    $options = array_merge($this->options, $options);

    if ($config) {
      $options = array_merge($options, $this->getOptionsFromConfig($config));
    }

    return array(OssClient::OSS_HEADERS => $options);
  }

  /**
   * Retrieve options from a Config instance. done
   *
   * @param Config $config
   *
   * @return array
   */
  protected function getOptionsFromConfig(Config $config)
  {
    $options = [];

    foreach (static::$metaOptions as $option) {
      if (!$val = $config->get($option)) {
        continue;
      }
      $options[static::$metaMap[$option]] = $val; //$config->get($option);
    }

    if ($visibility = $config->get('visibility')) {
      // For local reference
      // $options['visibility'] = $visibility;
      // For external reference
      $options['x-oss-object-acl'] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? OssClient::OSS_ACL_TYPE_PUBLIC_READ : OssClient::OSS_ACL_TYPE_PRIVATE;
    }

    if ($mimetype = $config->get('mimetype')) {
      // For local reference
      // $options['mimetype'] = $mimetype;
      // For external reference
      $options['Content-Type'] = $mimetype;
    }

    return $options;
  }

  /**
   * @param $fun string function name : __FUNCTION__
   * @param $e
   */
  protected function logErr($fun, $e){
    if( $this->debug ){
      Log::error($fun . ": FAILED");
      Log::error($e->getMessage());
    }
  }

  public function move(string $source, string $destination, Config $config): void {
    $this->copy($source, $destination, $config) & $this->delete($source);
    //$this->rename($source, $destination, $config);
  }

  public function fileExists(string $path): bool {
    return $this->has($path);
  }

  public function directoryExists(string $path): bool {
    $path = rtrim($this->applyPathPrefix($path), '/') .'/';
    $dirObjects = $this->listDirObjects($path, false);
    return count($dirObjects['objects']) > 0;
  }

  public function deleteDirectory(string $path): void {
    $this->deleteDir($path);
    return;
  }

  public function createDirectory(string $path, Config $config): void {
    $this->createDir($path, $config);
  }

  public function visibility(string $path): FileAttributes {
    return $this->getMetadata($path);
  }

  public function mimeType(string $path): FileAttributes {
    return $this->getMetadata($path);
  }

  public function fileSize(string $path): FileAttributes {
    return $this->getMetadata($path);
  }

  public function lastModified(string $path): FileAttributes {
    return $this->getMetadata($path);
  }

}
