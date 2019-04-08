<?php
/**
 * 
 * @fileName OssClient.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 08/04/2019
 * @version OssClient.php 2019.04.08
 * */
namespace Package\StorageOSS;

use OSS\OssClient as Oss;

class OssClient {

  protected $_client;

  protected $_bucket;

  protected $_object;

  protected $_debug;

  protected $_config = array();

  public function __construct (array $config) {
    $accessId  = $config['access_id'];
    $accessKey = $config['access_key'];

    $cdnDomain = $config['cdnDomain'] = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
    $ssl       = $config['ssl'] = empty($config['ssl']) ? false : $config['ssl']; 
    $isCname   = $config['isCname'] = empty($config['isCName']) ? false : $config['isCName'];
    $endPoint  = $config['endpoint']; // 默认作为外部节点
    $epInternal= $config['endpoint_internal'] = $isCname?$cdnDomain:(empty($config['endpoint_internal']) ? $endPoint : $config['endpoint_internal']); // 内部节点

    $this->_bucket = $config['bucket'];
    $this->_debug  = $config['debug'] = empty($config['debug']) ? false : $config['debug'];
    $this->_config = $config;

    if($debug) Log::debug('OSS config:', $config);

    $this->_client  = new Oss($accessId, $accessKey, $epInternal, $isCname);
    return $this->_client;
  }

  public function __get ($key) {
    if (isset($this->_config[$key])) return $this->_config[$key];
    return NULL;
  }

  public function getOSSClient () {
    return $this->_client;
  }

}
