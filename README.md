# rakan

>Laravel 文件系统扩展包 + 文件管理系统

<p align="center">
    <img src="https://github.styleci.io/repos/137040717/shield?branch=master" alt="StyleCI Shield">
    <img src="https://img.shields.io/packagist/v/telstatic/rakan.svg?style=flat-square" alt="licence" />
    <img src="https://img.shields.io/packagist/l/telstatic/rakan.svg?style=flat-square" alt="licence" />
    <img src="https://img.shields.io/github/languages/code-size/telstatic/rakan.svg?style=flat-square" alt="code size" />
    <img src="https://img.shields.io/packagist/dt/telstatic/rakan.svg?style=flat-square" alt="downloads" />
    <img src="https://img.shields.io/packagist/dm/telstatic/rakan.svg?style=flat-square" alt="downloads" />
    <img src="https://img.shields.io/packagist/php-v/telstatic/rakan.svg?style=flat-square" alt="downloads" />

</p>

>适配阿里云OSS,七牛云

* [安装](#installation)
* [使用](#usage)
* [属性](#props)
* [配置](#env)
* [方法](#function) 
    * [设置网关](#setGateway)
    * [设置目录前缀](#setPrefix)
    * [设置模块](#setModule)
    * [获取文件列表](#getFiles)
    * [获取上传策略](#getPolicy)
    * [创建目录](#createFolder)
    * [检查文件唯一性](#checkFile)
    * [删除文件及目录](#deleteFiles)

* [路由](#router)
* [文件系统](#filesystem)
* [TODO](#todo)
* [感谢](#thanks)
* [资料](#doc)

### 简介
    通用文件管理器,支持阿里云OSS,七牛云直传

<div id="installation"></div>

### 安装

    composer require telstatic/rakan

<div id="usage"></div>

### 配置

    修改config/filessystems.php,disk中添加
    'oss' => [
        'driver' => 'oss'
    ],
    'qiniu' => [
        'driver' => 'qiniu'
    ],

<div id="usage"></div>

### 使用


    Model user.php
    <?php
    
    namespace App\Models\User;
    
    use TELstatic\Rakan\Traits\Rakan;
    ...
    
    class User {
        use Rakan;
    }
    
    Controller FileController.php
    <?php
    
    namespace App\Http\Controller\User;
    
    use App\Models\User;
    use Illuminate\Http\Request;
    
    class FileController {
        public $user;
        
        public function __constract(){
            $this->user =User::find(1); 
        }
        
        public function getFiles(Request $request){
            return $this->user->prefix('rakan')->module('default')->->getFiles($request);
        }
    
        public function getPolicy(){
            return $this->user->getPolicy();
        }
        
        public function createFolder(Request $request){
            return $this->user->createFolder($request->pid,$request->name);
        }
        
        public function deleteFiles(Request $request){
            return $this->user->deleteFiles($request->ids);
        }
    
        public function checkFile(Request $request){
            return $this->checkFile($request->path);
        }
    }

<div id="props"></div>

### 属性

| name| type | require | default| memo |
| ---- | --- | --- | ---| ---|
| prefix | string | true | rakan | 目录前缀|
| module | string | true | default | 模块 |
| gateway | string | true | oss | 网关 |
| expire    | int | false | 3600| 策略有效时间|

<div id="env"></div>

### 配置

| name | default|memo|
| --- | --- | --- |
| OSS_HOST | null | OSS上传地址 |
| OSS_BUCKET | null | OSS bucket |
| OSS_AK | null | OSS access_key |
| OSS_SK | null | OSS secret_key |
| OSS_ENDPOINT | null | OSS endpoint |
| OSS_EXPIRE | 3600 | 策略有效时间 单位:s |
| QINIU_AK | null |  七牛 access_key |
| QINIU_SK | null | 七牛 secret_key |
| QINIU_HOST | null | 七牛外链域名 |
| QINIU_BUCKET | null | 七牛 bucket |
| QINIU_EXPIRE | 3600 | 策略有效时间 单位:s |

<div id="function"></div>

### 方法

<div id="setGateway"></div>

- gateway 设置网关

参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| gateway | string | false | oss | 网关 oss,qiniu 默认oss|

<div id="setPrefix"></div>

- prefix 设置目录前缀

参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| prefix | string | false | rakan | 目录前缀|

<div id="setModule"></div>

- module 设置模块

参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| module | string | false | default | 模块名|

<div id="getFiles"></div>    

- getFiles 获取文件及目录
  

参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| pid | string | false | 0 | 父级目录ID|

返回
​    形如:
​    

    {
        "parent": {
            "id": "1",
            "path": "platform/q47G4R",
            "name": "Root",
            "type": "folder",
            "pid": 0,
            "module": "admin",
            "target_id": 1,
            "sort": 255,
            "updated_at": "2018-08-21 20:07:39",
            "created_at": "2018-08-21 20:07:39",
            "url": null,
            "checked": false
        },
        "children": {
            "current_page": 1,
            "data": [
                {
                    "id": "2",
                    "pid": "1",
                    "path": "platform/q47G4R/test1",
                    "name": "test1",
                    "module": "admin",
                    "target_id": 1,
                    "type": "folder",
                    "sort": 255,
                    "updated_at": "2018-08-23 09:12:15",
                    "created_at": "2018-08-23 09:12:15",
                    "url": null,
                    "checked": false
                }
            ],
            "first_page_url": "http://www.test.local/admin?page=1",
            "from": 1,
            "last_page": 1,
            "last_page_url": "http://www.test.local/admin?page=1",
            "next_page_url": null,
            "path": "http://www.test.local/admin",
            "per_page": 50,
            "prev_page_url": null,
            "to": 1,
            "total": 1
        }
    }


<div id="createFolder"></div>

- createFolder 创建目录

参数
​    

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| pid | string | true | null | 父级目录ID|
| name | string | true | null | 目录名称 |


返回

> 状态码 + 错误提示

形如:
​    

    {
        status:500,
        msg:'目录已存在'
    }
    
    {
        status:200,
        msg:'目录创建成功'
    }

<div id="checkFile"></div>

- checkFile 检查文件是否存在
    注意网关设置
    

参数
​    

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| path | string | true | null | 文件路径 |

返回
​    
> 状态码 + 错误提示

形如
​    

    {
        status :500,
        msg : '文件已存在'
    }
    
    {
        status :200,
        msg : ''
    }

<div id="deleteFiles"></div>    

- deleteFiles 删除文件及目录
  注意网关设置
  

参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| ids | array | true | null | 文件或目录ID集合|

返回
​    
>状态 + 错误提示

形如
​    

    {
        status:200,
        msg:'删除成功'
    }
    
    {
        status:500,
        msg:'目录 test 不为空'
    }

<div id="getPolicy"></div>

- getPolicy 获取上传策略
  注意网关设置

返回
​    
形如
​    

    {
        "data": {
            "OSSAccessKeyId": "{your-access_key}",
            "Policy": "eyleHBpcmF0aW9uIjoiMjAxOS0wMS0wM1QwMzozNTo1OFoiLCJjb25kaXRpb25zIjpbWyJjb250ZW50LWxlbmd0aC1yYW5nZSIsMCwxMDQ4NTc2MDAwXV19",
            "Signature": "oeE36A1SsWWBZJ6iWx3rSeSBWSQ=",
            "success_action_status": 200,
            "key": ""
        },
        "expire": "3600"
    }
    
    {
        "data": {
            "token": "JQ4aZNe87tA2FG-I01lxp7kJArP6opY_renx7MU:Tg8Zrx5XIVNY4fwhHK9nX200UcM=:eyJzY29wZSI6InRlbHN0YXRpYyIsImRlYWRsaW5lIjoxNTQ2NDg2NjE4fQ==",
            "key": ""
        },
        "expire": "3600"
    }

<div id="router"></div>

### 回调路由

    路由名称: rakan.callback
    Tip: 将路由 rakan.callback 加入白名单
    
    VerifyCsrfToken.php
    
    protected $except = [
        'rakan/callback/*'
    ];

<div id="filesystem"></div>

### 文件系统

        Storage::disk('qiniu')->putFileAs('avatars', new File($file), 'avatar3.jpg'); //指定文件名上传文件
        Storage::disk('qiniu')->put('avatars/avatar1.jpg', file_get_contents($file));//不指定文件名上传文件
    
        Storage::disk('qiniu')->prepend('file.log', 'Prepended Text');//文件头部追加
        Storage::disk('qiniu')->append('file.log', 'Appended Text');//文件尾部追加
    
        Storage::disk('qiniu')->copy('avatars/avatar1.jpg', 'faces/avatar1.jpg');//复制文件
        Storage::disk('qiniu')->move('avatars/avatar3.jpg', 'avatars/avatar5.jpg');//移动文件
    
        $visibility = Storage::disk('oss')->getVisibility('avatars/avatar5.jpg');//获取文件可见性,不支持七牛
        Storage::disk('qiniu')->setVisibility('avatars/avatar5.jpg', 'public');//设置文件可见性,不支持七牛
    
        Storage::disk('qiniu')->delete('avatars/avatar1.jpg');  //删除文件
        Storage::disk('qiniu')->delete(['avatars/avatar5.jpg', 'faces/avatar1.jpg']);//删除多个文件
    
        Storage::disk('qiniu')->makeDirectory('avatars/test');  //创建目录
        Storage::disk('qiniu')->deleteDirectory('avatars/test');  //删除目录及该目录下其他文件
    
        Storage::disk('qiniu')->files('/');     //获取指定目录下文件
        Storage::disk('qiniu')->allFiles('/');  //获取全部文件
        Storage::disk('qiniu')->directories('/');   //获取指定目录下目录
        Storage::disk('qiniu')->allDirectories('/');    //获取全部目录
    
        Storage::disk('qiniu')->exists('file.log'); //判断文件是否存在
    
       Storage::disk('qiniu')->get('file.log'); //获取文件内容
    
       Storage::disk('qiniu')->url('Pairs.jpg');    //获取文件访问地址
    
       Storage::disk('qiniu')->size('test/Paris.jpg');//获取文件大小
       Storage::disk('qiniu')->lastModified('test/Paris.jpg');//获取文件最后修改时间
       
       $file = '../public/images/faces/avatar3.jpg';
       
       $image_info = getimagesize($file);
       $image_data = fread(fopen($file, 'r'), filesize($file));
       $base64_image = 'data:'.$image_info['mime'].';base64,'.chunk_split(base64_encode($image_data));
    
       Storage::disk('oss')->base64('avatars/avatarx.jpg', $base64_image);//base64 字符串上传

<div id="todo"></div>

### TODO

    1. 云适配
        腾讯COS,又拍云,etc.
    2. 多文件类型适配
        根据不同MineType类型返回不同图标

<div id="tips"></div>        

### TIPS

1. 配套前台 https://github.com/TELstatic/xayah
2. 测试代码 https://github.com/TELstatic/RakanDemo

<div id="thanks"></div>

### 感谢开源

[hashids/hashids](https://github.com/ivanakimov/hashids.php)

[apollopy/flysystem-aliyun-oss](https://github.com/apollopy/flysystem-aliyun-oss)

[zgldh/qiniu-laravel-storage](https://github.com/abcsun/qiniu-laravel-storage)

<div id="doc"></div>

### 参考资料    

[阿里云OSS PHP-SDK](https://help.aliyun.com/document_detail/32099.html?spm=5176.doc31981.6.335.eqQ9dM)

[七牛云对象存储 PHP-SDK](https://developer.qiniu.com/kodo/sdk/1241/php)

### Thanks

[![](phpstorm.svg)](https://www.jetbrains.com)