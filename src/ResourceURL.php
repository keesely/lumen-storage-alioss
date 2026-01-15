<?php
/**
 * 
 * @fileName ResourceURL.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 10/04/2019
 * @version ResourceURL.php 2019.04.10
 * */
namespace Lx\StorageOSS;
use Lx\StorageOSS\OssClient as Client;

class ResourceURL {

  protected $_object;
  protected $_timeout;
  protected $client;

  public function __construct (Client $client, $object, $timeout = 3600) {
    $this->_object = $object;
    $this->_timeout = $timeout > 0 ? $timeout : 3600;
    $this->client = $client;
  }

  public function __toString () {
    $data = $this->getResourceUrl($this->_object, $this->_timeout);

    return $data['resource_url'];
  }

  public function toArray () {
    return $this->getResourceUrl($this->_object, $this->_timeout);
  }

  /**
   * 获取私有文件访问连接
   * */
  public function getSignURL ($object, $timeout = 3600) {
    $url = $this->client->getClient()->signUrl(
      $this->client->bucket, 
      $object, 
      $timeout
    );
    $url = urldecode($url);
    return parse_url($url);
  }

  public function getResourceUrl ($path, $timeout = 3600) {
    $purl = $this->getSignURL($path, $timeout);
    // 解码
    parse_str($purl['query'], $query_array);
    $query_array['Signature'] = urlencode($query_array['Signature']);
    // 处理%2B
    $query_array['Signature'] = str_replace('+', "%2B", $query_array['Signature']);

    $query_tmp = [];
    foreach ($query_array as $k => $v) $query_tmp[] = "{$k}={$v}";
    $OSSsignURL = implode("&", $query_tmp);
    $purl['query'] = $OSSsignURL;

    //$resource_url = $purl['scheme'] . '://' .
    if ($this->client->is_cname && $this->client->cdn_domain) {
      $resource_url = $this->client->cdn_domain;
    } 
    else $resource_url = $purl['host'];
    $resource_url .= $purl['path'];
    $resource_url = $this->setResourceUrl($resource_url . '?'. $OSSsignURL);

    return [
      'resource_url' => $resource_url,
      'resource_query_str' => $purl['query'],
      'parse_str' => $purl,
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
    $scheme = $this->client->getClient()->isUseSSL() ? 'https://' : 'http://';
    return $scheme.$resource_url;
  }
}
