<?php
namespace Lx\StorageOSS\UrlGenerators;

use Lx\StorageOSS\OssClient;
use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PublicUrlGenerator as FlysystemPublicUrlGenerator;

class PublicUrlGenerator implements FlysystemPublicUrlGenerator {
    protected $client;

    public function __construct(OssClient $client) {
        $this->client = $client;
    }

    public function publicUrl(string $path, Config $config): string {
        $object = $path;
        
        if ($this->client->is_cname && $this->client->cdn_domain) {
            $baseUrl = $this->client->cdn_domain;
        } else {
            $endpoint = $this->client->endpoint;
            $bucket = $this->client->bucket;
            $baseUrl = "{$bucket}.{$endpoint}";
        }

        $object = $this->applyPathPrefix($object);
        
        $scheme = $this->client->ssl ? 'https://' : 'http://';
        
        return $scheme . $baseUrl . '/' . ltrim($object, '/');
    }

    public function applyPathPrefix(string $path): string {
        if ($this->client->object_case) 
            return rtrim($this->client->object_case, '/') . '/' . ltrim($path, '/');
        return $path;
    }
}