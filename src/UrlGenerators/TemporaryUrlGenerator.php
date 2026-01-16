<?php
namespace Lx\StorageOSS\UrlGenerators;

use DateTimeInterface;
use Lx\StorageOSS\OssClient;
use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator as FlysystemTemporaryUrlGenerator;

class TemporaryUrlGenerator implements FlysystemTemporaryUrlGenerator {
    protected $client;

    public function __construct(OssClient $client) {
        $this->client = $client;
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string {
        $object = $path;
        $bucket = $this->client->bucket;
        $timeout = $expiresAt->getTimestamp() - time();

        $method = $config->get('method', 'GET');

        $object = $this->applyPathPrefix($object);

        $url = $this->client->getClient()->signUrl(
            $bucket,
            $object,
            $timeout,
            $method
        );

        $url = urldecode($url);
        $parsedUrl = parse_url($url);

        if ($this->client->is_cname && $this->client->cdn_domain) {
            $scheme = $parsedUrl['scheme'] . '://';
            $baseUrl = $this->client->cdn_domain;
            $path = $parsedUrl['path'];
            $query = $parsedUrl['query'];
            
            $url = $scheme . $baseUrl . $path . '?' . $query;
        }

        return $url;
    }

    public function applyPathPrefix(string $path): string {
        if ($this->client->object_case) 
            return rtrim($this->client->object_case, '/') . '/' . ltrim($path, '/');
        return $path;
    }
}