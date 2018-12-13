# 阿里云OSS配套插件

### 安装

    composer require telstatic/rakan

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
            return $this->user->getFiles($request);
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


### 属性

| name| type | require | default| memo | 
| ---- | --- | --- | ---| ---|
| prefix | string | true | rakan | 目录前缀|
| module | string | true | default | 模块 |
| expire    | int | false | 3600| 策略有效时间|

### 配置
    
| name | default|memo|
| --- | --- | --- |
| ALI_HOST | null | OSS上传地址 |    
| ALI_BUCKET | null | OSS bucket |
| ALI_AK | null | OSS access_key |
| ALI_SK | null | OSS secret_key |
| ALI_CALLBACK | null | 上传回调地址 |
| ALI_EXPIRE | 120 | 策略有效时间 单位:s |

### 方法
    
- getFiles 获取文件及目录
    
参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |  
| pid | string | false | 0 | 父级目录ID|

返回
    形如:
    
    {
        "parent": {
            "_id": "5b7c008bd16f0b3014005282",
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
                    "_id": "5b7e09efd16f0b2b04005abf",
                    "pid": "5b7c008bd16f0b3014005282",
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

- createFolder 创建目录

参数
    
| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| pid | string | true | null | 父级目录ID|
| name | string | true | null | 目录名称 |

返回

> 状态码 + 错误提示

形如:
    
    {
        status:500,
        msg:'目录已存在'
    }
    
    {
        status:200,
        msg:'目录创建成功'
    }

- checkFile 检查文件是否存在

参数
    
| name | type | require | default | memo |
| --- | --- | --- | --- | --- |
| path | string | true | null | 文件路径 |
    
返回
    
> 状态码 + 错误提示

形如
    
    {
        status :500,
        msg : '文件已存在'
    }
    
    {
        status :200,
        msg : ''
    }
    
- deleteFiles 删除文件及目录
    
参数

| name | type | require | default | memo |
| --- | --- | --- | --- | --- |    
| ids | array | true | null | 文件或目录ID集合|        

返回
    
>状态 + 错误提示

形如
    
    {
        status:200,
        msg:'删除成功'
    }
    
    {
        status:500,
        msg:'目录 test 不为空'
    }
    
- getPolicy 获取上传策略
    
返回
    
形如
    
    {
        "accessid": "{your-access_id}",
        "host": "//{your-bucket}.oss-cn-shanghai.aliyuncs.com/",
        "policy": "eyJleHBpcmF0aW9uIjoiMjAxOC0wOC0yM1QxMjo1NzoyMFoiLCJjb25kaXRpb25zIjpbWyJjb250ZW40LWxlBmd0aC1yYW5nZSIsMCwxMDQ4NTc2MDAwXV19",
        "signature": "sF+64xm7tIxXAOrduy/CTZ4ZyWw=",
        "expire": 3600,
        "callback": "eyJjYWxsYmFja1VybCI6Imh0dHBzOlwvXc9zag9wLnRlbHN0YXRpyy54EXpcL2NhbGxiYWNrXC9vc3MiLCJjYWxsYmFja0JvZHkiOiJmaWxlbmFtZT0ke29iamVjdH0mc2l6ZT0ke3NpemV9Jm1pbWVUeXBlPSR7bWltZVR5cGV9JmhlaWdodD0ke2ltYWdlSW5mby5oZWlnaHR9JndpZHRoPSR7aW1hZ2VJbmZvLndpZHRofSIsImNhbGxiYWNrQm9keVR5cGUiOiJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkIn0="
    }
