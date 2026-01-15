# storage-oss

直连阿里云OSS驱动 By Lumen

## Inspired By
- [thephpleague/flysystem-aws-s3-v2](https://github.com/thephpleague/flysystem-aws-s3-v2)
- [apollopy/flysystem-aliyun-oss](https://github.com/apollopy/flysystem-aliyun-oss) 
- [jacobcyl/Aliyun-oss-storage](https://github.com/jacobcyl/Aliyun-oss-storage)

## Require
- Laravel/Lumen 
- cURL extension
- iconv

##Installation

在composer.json 添加资源库:

```json
"storage-oss": {                                                           
  "type": "vcs",
    "url": "https://github.com/keesely/lumen-storage-alioss"
}
```

添加扩展包:

```
"keesely/lumen-storage-alioss": "main-dev"
```

执行: `composer install` OR `composer update`

或者直接简单执行:

```
composer require keesely/lumen-storage-alioss
```
    
在 config/app.php 中添加：

```php
'configure' => [
  ....

  'filesystems',
],

'providers' => [
  ....

  Lx\StorageOSS\OssServiceProvider::class,
    ....
]
```

## Configuration
Add the following in app/filesystems.php:

```php
'disks'=>[
    ...
    'oss' => [
            'driver'        => 'oss',
            'access_id'     => '<Your Aliyun OSS AccessKeyId>',
            'access_key'    => '<Your Aliyun OSS AccessKeySecret>',
            'bucket'        => '<OSS bucket name>',
            'object_case'   => '<OSS front object case name>',    // 新增特性，根据项目设置前置目录
            'endpoint'      => '<the endpoint of OSS, E.g: oss-cn-hangzhou.aliyuncs.com | custom domain, E.g:img.abc.com>', // OSS 外网节点或自定义外部域名
            'endpoint_internal' => '<internal endpoint [OSS内网节点] 如：oss-cn-shenzhen-internal.aliyuncs.com>', 
            'bucket_domain' => '<OSS Bucket domain, bucket 域名>',
            'cdn_domain'    => '<CDN domain, cdn域名>', 
            'ssl'           => true,
            'is_cname'      => true,
            'debug'         => true,
    ],
    ...
]

```
<!--Then set the default driver in app/filesystems.php:-->
如果使用OSS作为默认文件系统驱动， 则在`app/filesystems.php`修正为：

```php
'default' => 'oss',
```

否则使用: `Storage::disk('oss')` 调用

### 打开门面

** config/app.php **
'aliases' => [
  ....

  'Storage' => Illuminate\Support\Facades\Storage::class,

  ....
]

Ok, well! You are finish to configure. Just feel free to use Aliyun OSS like Storage!


## Usage
See [Larave doc for Storage](https://laravel.com/docs/5.2/filesystem#custom-filesystems)
Or you can learn here:

> First you must use Storage facade

```php
use Illuminate\Support\Facades\Storage;
```    
> Then You can use all APIs of laravel Storage

```php
Storage::disk('oss'); // if default filesystems driver is oss, you can skip this step

//fetch all files of specified bucket(see upond configuration)
Storage::files($directory);
Storage::allFiles($directory);

Storage::put('path/to/file/file.jpg', $contents); //first parameter is the target file path, second paramter is file content
Storage::putLocalFile('path/to/file/file.jpg', 'local/path/to/local_file.jpg'); // upload file from local path

Storage::get('path/to/file/file.jpg'); // get the file object by path
Storage::exists('path/to/file/file.jpg'); // determine if a given file exists on the storage(OSS)
Storage::size('path/to/file/file.jpg'); // get the file size (Byte)
Storage::lastModified('path/to/file/file.jpg'); // get date of last modification

Storage::directories($directory); // Get all of the directories within a given directory
Storage::allDirectories($directory); // Get all (recursive) of the directories within a given directory

Storage::copy('old/file1.jpg', 'new/file1.jpg');
Storage::move('old/file1.jpg', 'new/file1.jpg');
Storage::rename('path/to/file1.jpg', 'path/to/file2.jpg');

Storage::prepend('file.log', 'Prepended Text'); // Prepend to a file.
Storage::append('file.log', 'Appended Text'); // Append to a file.

Storage::delete('file.jpg');
Storage::delete(['file1.jpg', 'file2.jpg']);

Storage::makeDirectory($directory); // Create a directory.
Storage::deleteDirectory($directory); // Recursively delete a directory.It will delete all files within a given directory, SO Use with caution please.

// upgrade logs
// new plugin for v2.0 version
Storage::putRemoteFile('target/path/to/file/jacob.jpg', 'http://example.com/jacob.jpg'); //upload remote file to storage by remote url
// new function for v2.0.1 version
Storage::url('path/to/img.jpg') // get the file url
```

## Documentation
More development detail see [Aliyun OSS DOC](https://help.aliyun.com/document_detail/32099.html?spm=5176.doc31981.6.335.eqQ9dM)
## License
Source code is release under MIT license. Read LICENSE file for more information.
