<?php
/**
 * 
 * @fileName Oss.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 西元2017年06月13日 (週二) 00時35分20秒
 * @since 13/06/2017
 * @version Oss.php 2017.06.13
 * */
namespace Package\StorageOSS;

use OSS\OssClient as Client;
use OSS\Core\OssException;
use Log;

class OssClient {

  protected $_config = array();

  protected $ossClient = false;

  protected $msg = '';

  protected $allowConf = array(
    'access_id', 'access_key', 'is_cname', 'cdn_domain', 'ssl', 
    'endpoint', 'endpoint_internal', 'bucket', 'object_case',
    'debug', 'timeout', 'connect_timeout'
  );

  protected function _setConf (array $config) {
    $config['is_cname']   = self::array_get($config, 'is_cname', false);
    $config['cdn_domain'] = self::array_get($config, 'cdn_domain', '');
    $config['ssl']        = self::array_get($config, 'ssl', false);
    $config['debug']      = self::array_get($config, 'debug', false);

    // @var int $timeout <设置请求超时时间，单位秒，默认是5184000秒, 这里建议 不要设置太小，如果上传文件很大，消耗的时间会比较长>
    $config['timeout'] = self::array_get($config, 'timeout', 5184000);

    // @var int $connectTimeout <设置连接超时时间，单位秒，默认是10秒>
    $config['connect_timeout'] = self::array_get($config, 'connect_timeout', 30);

    // 内网地址
    $config['endpoint_internal'] = $config['is_cname'] && $config['cdn_domain'] 
      ? $config['cdn_domain'] 
      : (self::array_get($config, 'endpoint_internal', $config['endpoint']));

    foreach ($this->allowConf as $key) {
      $this->_config[$key] = self::array_get($config, $key, NULL);
    }
    return $this->_config;
  }

  protected function getConf ($key, $default = false) {
    return self::array_get($this->_config, $key, $default);
  }

  static private function array_get (array $array, $key, $default = NULL) {
    if (function_exists('array_get')) return array_get($array, $key, $default);
    if (is_null($key)) return $array; 
    if (isset($array[$key])) return $array[$key];

    foreach (explode('.', $key) as $segment) {
      if (! is_array($array) || ! array_key_exists($segment, $array)) {
        return $default;
      }

      $array = $array[$segment];
    }
    return $array;
  }

  public function __get ($key) {
    if ('client' == $key) return $this->ossClient;
    if ('msg' == $key) return $this->msg;
    return $this->getConf($key, NULL);
  }

  public function __construct (array $config) {
    try {
      $config = $this->_setConf($config);

      $accessKeyId     = $this->getConf('access_id');
      $accessKeySecret = $this->getConf('access_key');
      $endpoint        = $this->getConf('endpoint_internal', $this->getConf('endpoint'));
      $bucket          = $this->getConf('bucket');
      $cname           = $this->getConf('is_cname');

      if($debug = $this->getConf('debug')) Log::debug('OSS config:', $config);
      if ($timeout = $this->getConf('timeout')) $this->timeout = $timeout;

      $this->ossClient = new Client(
        $accessKeyId, 
        $accessKeySecret, 
        $endpoint, 
        $cname
      );

      if ($debug) Log::debug('OSS Client: ', [
        'accessKeyId'     => $accessKeyId, 
        'accessKeySecret' => $accessKeySecret, 
        'endpoint'        => $endpoint, 
        'cname'           => $cname
      ]);

      if ($timeout = $this->getConf('timeout')) $this->ossClient->setTimeout($timeout);

      if ($connect_timeout = $this->getConf('connect_timeout')) $this->ossClient->setConnectTimeout($connect_timeout);

      if ($ssl = $this->getConf('ssl')) $this->ossClient->setUseSSL(true);

    } catch (OssException $e) {
      return $this->msg = $e->getMessage();
    }
  }

  public function setTimeout ($timeout, $connectTimeout = null) {
    if ($timeout > 0) {
      $this->_config['timeout'] = $timeout;
      $this->ossClient->setTimeout($timeout);
    }
    if ($connectTimeout > 0) {
      $this->_config['connect_timeout'] = $connectTimeout;
      $this->ossClient->setConnectTimeout($connectTimeout);
    }
    return $this;
  }

  public function getOssClient () {
    return $this->ossClient;
  }

  public function getClient () {
    return $this->ossClient;
  }

  public function getBucket () {
    return $this->bucket;
  }

  /**
   * 列出OSS文件列表
   * */
  public function listObjects ($delimter = '/', $prefix = NULL, array $options = array()) {
    if ($delimter) $options['delimter'] = $delimter;
    if ($prefix)   $options['prefix']   = $prefix;
    if (!self::array_get($options, 'max-keys')) $options['max-keys'] = 1000;
    if (!self::array_get($options, 'marker'))   $options['marker']   = '';

    return $this->ossClient->listObjects($this->getBucket(), $options);
  }

  /**
   * 检测Object是否存在
   * */
  public function doesObjectExist ($object, array $options = NULL) {
    return $this->ossClient->doesObjectExist($this->getBucket(), $object, $options);
  }

  /**
   * 获取文件内容
   * */
  public function getObject ($object, array $options = NULL) {
    return $this->ossClient->getObject($this->getBucket(), $object, $options);
  }

  /**
   * 获取文件头信息
   * */
  public function getObjectMeta ($object, array $options = NULL) {
    return $this->ossClient->getObjectMeta($this->getBucket(), $object, $options);
  }

  /**
   * 上传文件
   * */
  public function putObject ($object, $content, array $options = NULL) {
    return $this->ossClient->putObject($this->getBucket(), $object, $content, $options);
  }

  /**
   * 追加内容
   * */
  public function appendObject ($object, $content, $position, array $options = NULL) {
    return $this->ossClient->appendObject($this->getBucket(), $object, $content, $position, $options);
  }

  /**
   * 移除文件
   * */
  public function deleteObject ($object) {
    return $this->ossClient->deleteObject($this->getBucket(), $object);
  }

  /**
   * copy 文件
   * */
  public function copyObject ($obejct, $toBucket, $toObject, array $options = NULL) {
    $fromBucket = $this->getBucket();
    return $this->ossClient->copyObject($fromBucket, $object, $toBucket, $toObject, $options);
  }

  /**
   * 设置文件权限
   * @param string $object
   * @param string $acl 
   *
   * 权限 | 描述 | 值
   * | ---:--- | ---:--- | ---:--- |
   * | 默认 | Objec是遵循Bucket的读写权限，即Bucket是什么权限，Object就是什么权限，Object的默认权限 | default |
   * | 私有读写 | Object是私有资源，即只有该Object的Owner拥有该Object的读写权限，其他的用户没有权限操作该Object | private |
   * | 公共读私有写 | Object是公共读资源，即非Object Owner只有Object的读权限，而Object Owner拥有该Object的读写权限 | public-read |
   * | 公共读写 | Object是公共读写资源，即所有用户拥有对该Object的读写权限 | public-read-write |
   * */
  public function setAcl($object, $acl) {
    return $this->ossClient->putObjectAcl($this->getBucket(), $object, $acl);
  }

  /**
   * 获取私有文件访问连接
   * */
  public function getSignURL ($object, $timeout = 3600) {
    $url = $this->ossClient->signUrl($this->getBucket(), $object, $timeout);
    //$url = rawurldecode($url);
    $url = urldecode($url);
    //$url = str_replace('http=>//', '', $url);
    return parse_url($url);
  }

  public function getResourceUrl ($resource_url, $object_case, $object_name, $acl, $timeout = 3600) {
    if ('private' != $acl) {
      return ['resource_url' => $this->setResourceUrl($resource_url)];
    }
    $purl = $this->getSignURL("{$object_case}/{$object_name}", $timeout);
    // 解码
    parse_str($purl['query'], $query_array);
    $query_array['Signature'] = urlencode($query_array['Signature']);
    // 处理%2B
    $query_array['Signature'] = str_replace('+', "%2B", $query_array['Signature']);

    $query_tmp = [];
    foreach ($query_array as $k => $v) $query_tmp[] = "{$k}={$v}";
    $OSSsignURL = implode("&", $query_tmp);
    //$OSSsignURL = http_build_query($query_array);
    $resource_url = $this->setResourceUrl($resource_url . '?'. $OSSsignURL);

    return [
      'resource_url' => $resource_url,
      'resource_query_str' => $purl['query'],
      'expire_at' => time() + $timeout,
    ];
  }

  protected function setResourceUrl ($resource_url) {
    if (strpos($resource_url, 'http://') === 0) {
      $resource_url = substr($resource_url, strlen('https://')-1);
    }
    elseif (strpos($resource_url, 'https://') === 0) {
      $resource_url = substr($resource_url, strlen('https://')-1);
    }
    $scheme = $this->ossClient->isUseSSL() ? 'https://' : 'http://';
    return $scheme.$resource_url;
  }

  static public function fileInfo ($file) {
    if (!function_exists('finfo_open')) {
      return [];
    }
    $finfo = finfo_open();
    $fmime = finfo_buffer($finfo, $file, FILEINFO_MIME_TYPE);
    $encode = finfo_buffer($finfo, $file, FILEINFO_MIME_ENCODING);
    $fdesc = finfo_buffer($finfo, $file);
    $fsize = number_format(strlen($file) / 1024);
    finfo_close($finfo);
    return [
      'mime' => $fmime, 
      'size' => $fsize, 
      'encode' => $encode, 
      'desc' => $fdesc
    ];
  }
}
