<?php

namespace TELstatic\Rakan\Traits;

use Hashids\Hashids;
use Illuminate\Support\Facades\Storage;
use TELstatic\Rakan\Models\Rakan as File;

/**
 * 文件扩展.
 */
trait Rakan
{
    public $prefix;
    public $module;
    public $gateway;

    public function query()
    {
        if ($this->rakanTable) {
            return (new File())->setTable($this->rakanTable);
        }

        return (new File());
    }

    /**
     * 前缀.
     */
    public function prefix($prefix = 'rakan')
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * 模块.
     */
    public function module($module = 'default')
    {
        $this->module = $module;

        return $this;
    }

    /**
     * 网关.
     */
    public function gateway($gateway = 'oss')
    {
        $this->gateway = $gateway;

        return $this;
    }

    /**
     * 根目录.
     */
    protected function root()
    {
        $hashids = new Hashids(config('rakan.hashids.salt'), config('rakan.hashids.length'),
            config('rakan.hashids.alphabet'));

        $root = $this->prefix ?? config('rakan.default.prefix').'/'.($this->module ?? config('rakan.default.module')).'/'.$hashids->encode($this->id);

        return $root;
    }

    /**
     * 获取Root目录.
     * 不存在则创建.
     */
    protected function getRootFolder()
    {
        $path = $this->root();

        $data = [
            'pid'       => 0,
            'path'      => $path,
            'name'      => 'Root',
            'module'    => $this->module ?? config('rakan.default.module'),
            'gateway'   => $this->gateway ?? config('rakan.default.gateway'),
            'host'      => config('rakan.gateways.'.($this->gateway ?? config('rakan.default.gateway')).'.host'),
            'target_id' => $this->id,
            'type'      => 'folder',
            'sort'      => 255,
        ];

        $where = [];

        $where[] = [
            'name',
            'Root',
        ];

        $where[] = [
            'module',
            $this->module ?? config('rakan.default.module'),
        ];

        $where[] = [
            'target_id',
            $this->id,
        ];

        $where[] = [
            'gateway',
            $this->gateway ?? config('rakan.default.gateway'),
        ];

        $where[] = [
            'path',
            $path,
        ];

        $files = $this->query()->where($where)->firstOrCreate($data, $data);

        return $files;
    }

    /**
     * 获取文件及目录.
     */
    public function getFiles($pid = 0, $per_page = 50, $keyword = null)
    {
        $where = [];

        $where[] = [
            'module',
            $this->module ?? config('rakan.default.module'),
        ];

        $where[] = [
            'target_id',
            $this->id,
        ];

        if (!$pid) {
            $where[] = [
                'pid',
                $pid,
            ];

            $parent = $this->getRootFolder();
        } else {
            $where[] = [
                'id',
                $pid,
            ];
            //todo should remove?
            $parent = $this->query()->where($where)->first();
        }

        if ($keyword) {
            $parent = $this->getRootFolder();

            $children = $this->query()->where('target_id', $this->id)->where('name', 'like', $keyword.'%')->orderBy('sort',
                'desc')->paginate($per_page);
        } else {
            $children = $this->query()->where(['pid' => $parent->id])->orderBy('sort', 'desc')->paginate($per_page);
        }

        $data = [
            'parent'   => $parent,
            'children' => $children,
        ];

        return $data;
    }

    /**
     * 创建目录.
     */
    public function createFolder($pid, $name)
    {
        $parent = $this->query()->findOrFail($pid);

        $where = [];

        $where[] = [
            'pid',
            $pid,
        ];

        $where[] = [
            'type',
            'folder',
        ];

        $where[] = [
            'name',
            $name,
        ];

        $where[] = [
            'gateway',
            $parent->gateway ?? config('rakan.default.gateway'),
        ];

        $folder = $this->query()->where($where)->first();

        if ($folder) {
            return [
                'status' => 500,
                'msg'    => '目录已存在',
            ];
        }

        $data = [
            'pid'       => $parent->id,
            'path'      => $parent->path.'/'.$name,
            'name'      => $name,
            'module'    => $parent->module,
            'gateway'   => $parent->gateway ?? config('rakan.default.gateway'),
            'host'      => $parent->host,
            'target_id' => $this->id,
            'type'      => 'folder',
            'sort'      => 255,
        ];

        $bool = $this->query()->create($data);

        return [
            'status' => $bool ? 200 : 500,
            'msg'    => '目录创建'.$bool ? '成功' : '失败',
        ];
    }

    /**
     * 检查文件是否存在.
     */
    public function checkFile($path)
    {
        $bool = Storage::disk($this->gateway ?? config('rakan.default.gateway'))->exists($path);

        return [
            'status' => $bool ? 500 : 200,
            'msg'    => $bool ? '文件已存在' : '',
        ];
    }

    /**
     * 删除本地文件记录.
     */
    public function deleteFiles($ids)
    {
        $where = [];

        $where[] = [
            'target_id',
            $this->id,
        ];

        $folders = $this->query()->where($where)->where(['type' => 'folder'])->whereIn('id', $ids)->pluck('path');
        $files = $this->query()->where($where)->where(['type' => 'file'])->whereIn('id', $ids)->pluck('path')->toArray();

        //检查目录下是否存在其他目录 或者 文件
        foreach ($folders as $folder) {
            $whereFolder = [];

            $whereFolder[] = [
                'path',
                'like',
                $folder.'%',
            ];

            if ($this->query()->where($whereFolder)->count() > 1) {
                return [
                    'status' => 500,
                    'msg'    => '目录'.$folder.'不为空',
                ];
                break;
            }
        }

        $bool = $this->deleteObjects($files);

        if ($bool) {
            $this->query()->destroy($ids);

            return [
                'status' => 200,
                'msg'    => '文件删除成功',
            ];
        }

        return [
            'status' => 500,
            'msg'    => '文件删除失败',
        ];
    }

    /**
     * 文件目录重命名.
     */
    public function rename($fileId, $name)
    {
        $file = $this->query()->where('target_id', $this->id)->findOrFail($fileId);

        if ($file->type == 'file') {
            $FilePath = pathinfo($file->path);
            $newFilePath = rtrim($FilePath['dirname'], '/').'/'.$name;

            $bool = Storage::disk($file->gateway)->rename($file->path, $newFilePath);

            if ($bool) {
                $file->name = $name;
                $file->path = $newFilePath;
                $file->visible = File::RAKAN_ACL_TYPE_PUBLIC_READ;

                $file->save();

                return [
                    'status' => 200,
                    'msg'    => '文件重命名成功',
                ];
            }

            return [
                'status' => 500,
                'msg'    => '文件重命名失败',
            ];
        } else {
            $folder = $file;

            $folderInfo = pathinfo($folder->path);
            //目录移动
            $files = $this->query()->where('type', 'file')->where('path', 'like', $folder->path.'%')->get();

            foreach ($files as $file) {
                $newFilePath = str_replace($folder->path, rtrim($folderInfo['dirname'], '/').'/'.$name, $file->path);

                //非同目录移动
                if ($file->path !== $newFilePath) {
                    $bool = Storage::disk($file->gateway)->move($file->path, $newFilePath);

                    if ($bool) {
                        $file->path = $newFilePath;
                        $file->save();
                    }
                }
            }

            $folder->name = $name;
            $folder->path = rtrim($folderInfo['dirname'], '/').'/'.$name;

            $bool = $folder->save();

            if ($bool) {
                return [
                    'status' => 200,
                    'msg'    => '目录重命名成功',
                ];
            }

            return [
                'status' => 500,
                'msg'    => '目录重命名失败',
            ];
        }
    }

    /**
     * 文件目录复制.
     */
    public function copy($fileIds, $folderId)
    {
        $files = $this->query()->where('target_id', $this->id)->where('type', 'file')->whereIn('id', $fileIds)->get();

        $folders = $this->query()->where('target_id', $this->id)->where('type', 'folder')->whereIn('id', $fileIds)->get();

        $currentFolder = $this->query()->where('target_id', $this->id)->findOrFail($folderId);

        DB::transaction(function () use ($files, $folders, $currentFolder) {
            //文件复制
            foreach ($files as $file) {
                $newFilePath = rtrim($currentFolder->path, '/').'/'.$file->name;

                //非同目录复制
                if ($file->path !== $newFilePath) {
                    //文件是否存在 存在跳过复制
                    if (!Storage::disk($file->gateway)->exists($newFilePath)) {
                        $bool = Storage::disk($file->gateway)->copy($file->path, $newFilePath);

                        if ($bool) {
                            if ($file->gateway == 'oss') {
                                Storage::disk($file->gateway)->setVisibility($newFilePath,
                                    File::$rakanACLTypeMap[$file->attributes['visible']]);
                            }

                            $this->query()->create([
                                'target_id' => $file->target_id,
                                'pid'       => $currentFolder->id,
                                'path'      => $newFilePath,
                                'module'    => $file->module,
                                'name'      => $file->name,
                                'gateway'   => $file->gateway,
                                'host'      => $file->host,
                                'ext'       => $file->ext,
                                'type'      => 'file',
                                'size'      => $file->size,
                                'width'     => $file->width,
                                'height'    => $file->height,
                                'sort'      => 0,
                                'visible'   => $file->attributes['visible'],
                            ]);
                        }
                    }
                }
            }

            //目录复制
            foreach ($folders as $folder) {
                $files = $this->query()->where('type', 'file')->where('path', 'like', $folder->path.'%')->get();

                foreach ($files as $file) {
                    $folderInfo = pathinfo($folder->path);

                    $newFilePath = rtrim($currentFolder->path, '/').'/'.str_replace($folderInfo['dirname'], '',
                            $file->path);

                    //非同目录复制
                    if ($file->path !== $newFilePath) {
                        $bool = Storage::disk($file->gateway)->copy($file->path, $newFilePath);

                        if ($bool) {
                            $pathInfo = pathinfo($newFilePath);

                            $parentFolder = $this->getParentFolder($pathInfo['dirname'], $file->gateway);

                            if ($file->gateway == 'oss') {
                                Storage::disk($file->gateway)->setVisibility($newFilePath,
                                    File::$rakanACLTypeMap[$file->attributes['visible']]);
                            }

                            $this->query()->create([
                                'target_id' => $file->target_id,
                                'pid'       => $parentFolder->id,
                                'path'      => $newFilePath,
                                'module'    => $file->module,
                                'name'      => $file->name,
                                'gateway'   => $file->gateway,
                                'host'      => $file->host,
                                'ext'       => $file->ext,
                                'type'      => 'file',
                                'size'      => $file->size,
                                'width'     => $file->width,
                                'height'    => $file->height,
                                'sort'      => 0,
                                'visible'   => $file->attributes['visible'],
                            ]);
                        }
                    }
                }
            }
        });

        return [
            'status' => 200,
            'msg'    => '文件目录复制成功',
        ];
    }

    /**
     * 文件目录移动.
     */
    public function cut($fileIds, $folderId)
    {
        $files = $this->query()->where('target_id', $this->id)->where('type', 'file')->whereIn('id', $fileIds)->get();

        $folders = $this->query()->where('target_id', $this->id)->where('type', 'folder')->whereIn('id', $fileIds)->get();

        $currentFolder = $this->query()->where('target_id', $this->id)->findOrFail($folderId);

        DB::transaction(function () use ($files, $folders, $currentFolder) {
            //文件移动
            foreach ($files as $file) {
                $newFilePath = rtrim($currentFolder->path, '/').'/'.$file->name;

                //非同目录移动
                if ($file->path !== $newFilePath) {
                    $bool = Storage::disk($file->gateway)->move($file->path, $newFilePath);

                    if ($bool) {
                        if ($file->gateway == 'oss') {
                            Storage::disk($file->gateway)->setVisibility($newFilePath,
                                File::$rakanACLTypeMap[$file->attributes['visible']]);
                        }

                        $this->query()->create([
                            'target_id' => $file->target_id,
                            'pid'       => $currentFolder->id,
                            'path'      => $newFilePath,
                            'module'    => $file->module,
                            'name'      => $file->name,
                            'gateway'   => $file->gateway,
                            'host'      => $file->host,
                            'ext'       => $file->ext,
                            'type'      => 'file',
                            'size'      => $file->size,
                            'width'     => $file->width,
                            'height'    => $file->height,
                            'sort'      => 0,
                            'visible'   => $file->visible,
                        ]);

                        $file->delete();
                    }
                }
            }

            //目录移动
            foreach ($folders as $folder) {
                $files = $this->query()->where('type', 'file')->where('path', 'like', $folder->path.'%')->get();

                foreach ($files as $file) {
                    $folderInfo = pathinfo($folder->path);

                    $newFilePath = rtrim($currentFolder->path, '/').'/'.str_replace($folderInfo['dirname'], '',
                            $file->path);

                    //非同目录移动
                    if ($file->path !== $newFilePath) {
                        $bool = Storage::disk($file->gateway)->move($file->path, $newFilePath);

                        if ($bool) {
                            $pathInfo = pathinfo($newFilePath);

                            $parentFolder = $this->getParentFolder($pathInfo['dirname'], $file->gateway);

                            if ($file->gateway == 'oss') {
                                Storage::disk($file->gateway)->setVisibility($newFilePath,
                                    File::$rakanACLTypeMap[$file->attributes['visible']]);
                            }

                            $this->query()->create([
                                'target_id' => $file->target_id,
                                'pid'       => $parentFolder->id,
                                'path'      => $newFilePath,
                                'module'    => $file->module,
                                'name'      => $file->name,
                                'gateway'   => $file->gateway,
                                'host'      => $file->host,
                                'ext'       => $file->ext,
                                'type'      => 'file',
                                'size'      => $file->size,
                                'width'     => $file->width,
                                'height'    => $file->height,
                                'sort'      => 0,
                                'visible'   => $file->attributes['visible'],
                            ]);

                            $file->delete();
                        }
                    }
                }

                $folder->delete();
            }
        });

        return [
            'status' => 200,
            'msg'    => '文件目录移动成功',
        ];
    }


    /**
     * 获取父级目录.
     * @desc 不存在则使用递归创建目录
     */
    protected function getParentFolder($path, $gateway)
    {
        $where[] = [
            'path',
            $path,
        ];

        $where[] = [
            'gateway',
            $gateway,
        ];

        if ($folder = $this->query()->where($where)->first()) {
            return $folder;
        } else {
            if (!$path) {
                throw new \InvalidArgumentException('根目录不存在,请检查文件路径');
            }

            $parentFolder = $this->getParentFolder($this->getChildFolderPath($path), $gateway);

            if ($folder = $this->generateFolder($path, $gateway, $parentFolder)) {
                return $folder;
            }
        }
    }

    /**
     * 获取子目录路径
     * @desc
     */
    protected function getChildFolderPath($path)
    {
        $pathArr = explode('/', $path);

        array_pop($pathArr);

        $childFolder = implode('/', $pathArr);

        return $childFolder;
    }

    /**
     * 生成目录
     * @desc
     */
    protected function generateFolder($path, $gateway, $folder)
    {
        $pathArr = explode('/', $path);

        $data = [
            'path'      => $path,
            'size'      => 0,
            'width'     => 0,
            'height'    => 0,
            'ext'       => null,
            'name'      => end($pathArr),
            'gateway'   => $gateway,
            'host'      => $folder->host,
            'module'    => $folder->module,
            'target_id' => $folder->target_id,
            'pid'       => $folder->id,
            'sort'      => 0,
            'type'      => 'folder',
        ];

        $file = $this->query()->create($data);

        return $file;
    }

    /**
     * 删除文件.
     */
    protected function deleteObjects($objects)
    {
        if (empty($objects)) {
            return true;
        }

        return Storage::disk($this->gateway ?? config('rakan.default.gateway'))->delete($objects);
    }

    /**
     * 获取上传策略.
     */
    public function getPolicy()
    {
        return Storage::disk($this->gateway ?? config('rakan.default.gateway'))->policy();
    }

    /**
     * 增加引用次数.
     * @desc
     * @param $step integer 1
     * @param $files array
     * @return boolean
     */
    public function incrementUseTimes($step = 1, $files)
    {
        return $this->query()->whereIn('path', $files)->increment('use_times', $step);
    }

    /**
     * 减少引用次数.
     * @desc
     * @param $step integer 1
     * @param $files array
     * @return boolean
     */
    public function decrementUseTimes($step = 1, $files)
    {
        return $this->query()->whereIn('path', $files)->decrement('use_times', $step);
    }

}
