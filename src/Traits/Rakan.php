<?php

namespace TELstatic\Rakan\Traits;

use Hashids\Hashids;
use Illuminate\Support\Facades\DB;
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

    public function search()
    {
        if ($this->rakanTable) {
            return (new File())->setTable($this->rakanTable);
        }

        return new File();
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
    public function root()
    {
        $hashids = new Hashids(
            config('rakan.hashids.salt'),
            config('rakan.hashids.length'),
            config('rakan.hashids.alphabet')
        );

        $root = $this->prefix ?? config('rakan.default.prefix').'/'.($this->module ?? config('rakan.default.module')).'/'.$hashids->encode($this->id);

        return $root;
    }

    /**
     * 获取Root目录.
     * 不存在则创建.
     */
    public function getRootFolder($folder)
    {
        if ($folder !== 'root') {
            if ($defaultFolder = $this->search()
                ->where('is_default', File::IS_DEFAULT_ACTIVATE)
                ->where('module', $this->module ?? config('rakan.default.module'))
                ->where('gateway', $this->gateway ?? config('rakan.default.gateway'))
                ->where('target_id', $this->id)
                ->first()
            ) {
                return $defaultFolder;
            }
        }

        $path = $this->root();

        $host = $this->config['host'] ?? config('rakan.gateways.'.($this->gateway ?? config('rakan.default.gateway')).'.host');

        $data = [
            'pid'       => 0,
            'path'      => $path,
            'name'      => 'Root',
            'module'    => $this->module ?? config('rakan.default.module'),
            'gateway'   => $this->gateway ?? config('rakan.default.gateway'),
            'host'      => $host,
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

        return $this->search()->where($where)->firstOrCreate($data, $data);
    }

    /**
     * 获取文件及目录.
     */
    public function getFiles($pid = 0, $per_page = 50, $keyword = null, $folder = 'root')
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
            $parent = $this->getRootFolder($folder);
        } else {
            $where[] = [
                'id',
                $pid,
            ];
            //todo should remove?
            $parent = $this->search()->where($where)->first();
        }

        if ($keyword) {
            $parent = $this->getRootFolder($folder);

            $children = $this->search()
                ->where('target_id', $this->id)
                ->where('name', 'like', $keyword.'%')
                ->orderBy('sort', 'desc')
                ->paginate($per_page);
        } else {
            $children = $this->search()
                ->where(['pid' => $parent->id])
                ->orderBy('sort', 'desc')
                ->paginate($per_page);
        }

        return [
            'parent'   => $parent,
            'children' => $children,
        ];
    }

    /**
     * 创建目录.
     */
    public function createFolder($pid, $name)
    {
        $parent = $this->search()->findOrFail($pid);

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

        $folder = $this->search()->where($where)->first();

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

        $bool = $this->search()->create($data);

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
        $bool = Storage::disk($this->gateway ?? config('rakan.default.gateway'))->config($this->config)->exists($path);

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

        $folders = $this->search()
            ->where($where)
            ->where(['type' => 'folder'])
            ->whereIn('id', $ids)
            ->pluck('path');

        $files = $this->search()
            ->where($where)
            ->where(['type' => 'file'])
            ->where('readonly', 0)
            ->whereIn('id', $ids)
            ->pluck('path')
            ->toArray();

        //检查目录下是否存在其他目录 或者 文件
        foreach ($folders as $folder) {
            $whereFolder = [];

            $whereFolder[] = [
                'path',
                'like',
                $folder.'%',
            ];

            if ($this->search()->where($whereFolder)->count() > 1) {
                return [
                    'status' => 500,
                    'msg'    => '目录'.$folder.'不为空',
                ];
            }
        }

        $bool = $this->deleteObjects($files);

        if ($bool) {
            $this->search()->whereIn('id', $ids)
                ->where('readonly', 0)
                ->delete();

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
        $file = $this->search()->where('target_id', $this->id)->findOrFail($fileId);

        if ($file->type == 'file') {
            $FilePath = pathinfo($file->path);
            $newFilePath = rtrim($FilePath['dirname'], '/').'/'.$name;

            $bool = Storage::disk($file->gateway)->config($this->config)->rename($file->path, $newFilePath);

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
            $files = $this->search()
                ->where('type', 'file')
                ->where('path', 'like', $folder->path.'%')
                ->get();

            foreach ($files as $file) {
                $newFilePath = str_replace(
                    $folder->path,
                    rtrim($folderInfo['dirname'], '/').'/'.$name,
                    $file->path
                );

                //非同目录移动
                if ($file->path !== $newFilePath) {
                    $bool = Storage::disk($file->gateway)->config($this->config)->move($file->path, $newFilePath);

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
        $files = $this->search()
            ->where('target_id', $this->id)
            ->where('type', 'file')
            ->whereIn('id', $fileIds)
            ->get();

        $folders = $this->search()
            ->where('target_id', $this->id)
            ->where('type', 'folder')
            ->whereIn('id', $fileIds)
            ->get();

        $currentFolder = $this->search()
            ->where('target_id', $this->id)
            ->findOrFail($folderId);

        DB::transaction(function () use ($files, $folders, $currentFolder) {
            //文件复制
            foreach ($files as $file) {
                $newFilePath = rtrim($currentFolder->path, '/').'/'.$file->name;

                //非同目录复制
                if ($file->path !== $newFilePath) {
                    //文件是否存在 存在跳过复制
                    if (!Storage::disk($file->gateway)->config($this->config)->exists($newFilePath)) {
                        $bool = Storage::disk($file->gateway)->config($this->config)->copy($file->path, $newFilePath);

                        if ($bool) {
                            $this->search()->create([
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
                                'visible'   => $file->attributes['visible'] ?? $this->config['acl'] ?? File::RAKAN_ACL_TYPE_PUBLIC_READ,
                            ]);
                        }
                    }
                }
            }

            //目录复制
            foreach ($folders as $folder) {
                $files = $this->search()->where('type', 'file')->where('path', 'like', $folder->path.'%')->get();

                foreach ($files as $file) {
                    $folderInfo = pathinfo($folder->path);

                    $newFilePath = rtrim($currentFolder->path, '/').
                        str_replace(
                            $folderInfo['dirname'],
                            '',
                            $file->path
                        );

                    //非同目录复制
                    if ($file->path !== $newFilePath) {
                        $bool = Storage::disk($file->gateway)->config($this->config)->copy($file->path, $newFilePath);

                        if ($bool) {
                            $pathInfo = pathinfo($newFilePath);

                            $parentFolder = $this->getParentFolder($pathInfo['dirname'], $file->gateway);

                            $this->search()->create([
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
                                'visible'   => $file->attributes['visible'] ?? $this->config['acl'] ?? File::RAKAN_ACL_TYPE_PUBLIC_READ,
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
        $files = $this->search()
            ->where('target_id', $this->id)
            ->where('type', 'file')
            ->whereIn('id', $fileIds)
            ->get();

        $folders = $this->search()
            ->where('target_id', $this->id)
            ->where('type', 'folder')
            ->whereIn('id', $fileIds)
            ->get();

        $currentFolder = $this->search()->where('target_id', $this->id)->findOrFail($folderId);

        DB::transaction(function () use ($files, $folders, $currentFolder) {
            //文件移动
            foreach ($files as $file) {
                $newFilePath = rtrim($currentFolder->path, '/').'/'.$file->name;

                //非同目录移动
                if ($file->path !== $newFilePath) {
                    $bool = Storage::disk($file->gateway)->config($this->config)->move($file->path, $newFilePath);

                    if ($bool) {
                        $this->search()->create([
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
                            'visible'   => $file->attributes['visible'] ?? $this->config['acl'] ?? File::RAKAN_ACL_TYPE_PUBLIC_READ,
                        ]);

                        $file->delete();
                    }
                }
            }

            //目录移动
            foreach ($folders as $folder) {
                $files = $this->search()->where('type', 'file')->where('path', 'like', $folder->path.'%')->get();

                foreach ($files as $file) {
                    $folderInfo = pathinfo($folder->path);

                    $newFilePath = rtrim($currentFolder->path, '/').
                        str_replace(
                            $folderInfo['dirname'],
                            '',
                            $file->path
                        );

                    //非同目录移动
                    if ($file->path !== $newFilePath) {
                        $bool = Storage::disk($file->gateway)->config($this->config)->move($file->path, $newFilePath);

                        if ($bool) {
                            $pathInfo = pathinfo($newFilePath);

                            $parentFolder = $this->getParentFolder($pathInfo['dirname'], $file->gateway);

                            $this->search()->create([
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
                                'visible'   => $file->attributes['visible'] ?? $this->config['acl'] ?? File::RAKAN_ACL_TYPE_PUBLIC_READ,
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
     *
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

        if ($folder = $this->search()->where($where)->first()) {
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
     * 获取子目录路径.
     *
     * @desc
     */
    protected function getChildFolderPath($path)
    {
        $pathArr = explode('/', $path);

        array_pop($pathArr);

        return implode('/', $pathArr);
    }

    /**
     * 生成目录.
     *
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

        return $this->search()->create($data);
    }

    /**
     * 设置文件访问权限.
     */
    public function setACL($path, $visibility = 1)
    {
        return Storage::disk($this->gateway ?? config('rakan.default.gateway'))
            ->config($this->config)->setVisibility($path, $visibility);
    }

    /**
     * 删除文件.
     */
    protected function deleteObjects($objects)
    {
        if (empty($objects)) {
            return true;
        }

        return Storage::disk($this->gateway ?? config('rakan.default.gateway'))->config($this->config)->delete($objects);
    }

    /**
     * 获取上传策略.
     */
    public function getPolicy($route = 'rakan.callback')
    {
        return Storage::disk($this->gateway ?? config('rakan.default.gateway'))->config($this->config)->policy($route);
    }

    /**
     * 增加引用次数.
     *
     * @desc
     *
     * @param $step integer 1
     * @param $files array
     *
     * @return bool
     */
    public function incrementUseTimes($files, $step = 1)
    {
        return $this->search()->whereIn('path', $files)->increment('use_times', $step);
    }

    /**
     * 减少引用次数.
     *
     * @desc
     *
     * @param $files array
     * @param $step integer 1
     *
     * @return bool
     */
    public function decrementUseTimes($files, $step = 1)
    {
        return $this->search()->whereIn('path', $files)->decrement('use_times', $step);
    }

    /**
     * 获取书签
     * @desc 获取书签
     * @param int $per_page
     * @param null $keyword
     * @return mixed
     * @author TELstatic
     * Date: 2021/5/8/0008
     */
    public function getBookmarks($per_page = 50, $keyword = null)
    {
        $builder = $this->search();

        if ($keyword) {
            $builder->where('name', 'like', $keyword.'%');
        }

        return $builder
            ->where('target_id', $this->id)
            ->whereNotNull('marked_at')
            ->latest('marked_at', 'desc')
            ->paginate($per_page);
    }

    /**
     * 添加书签
     * @desc 添加书签
     * @param $id
     * @return array
     * @author TELstatic
     * Date: 2021/5/8/0008
     */
    public function createBookmark($id)
    {
        $this->search()
            ->where('target_id', $this->id)
            ->where('id', $id)
            ->update([
                'marked_at' => now(),
            ]);

        return [
            'status' => 200,
            'msg'    => '书签添加成功',
        ];
    }

    /**
     * 删除书签
     * @desc 删除书签
     * @param $id
     * @return array
     * @author TELstatic
     * Date: 2021/5/8/0008
     */
    public function deleteBookmark($id)
    {
        $this->search()
            ->where('id', $id)
            ->where('target_id', $this->id)
            ->update([
                'marked_at' => null,
            ]);

        return [
            'status' => 200,
            'msg'    => '书签删除成功',
        ];
    }

    /**
     * 设置默认目录
     * @desc 设置默认目录
     * @author TELstatic
     * Date: 2021/5/8/0008
     */
    public function setDefaultFolder($id)
    {
        $this->search()
            ->where('target_id', $this->id)
            ->where('id', '<>', $id)
            ->where('type', 'folder')
            ->update([
                'is_default' => File::IS_DEFAULT_DEACTIVATE,
            ]);

        $this->search()
            ->where('target_id', $this->id)
            ->where('id', $id)
            ->where('type', 'folder')
            ->update([
                'is_default' => File::IS_DEFAULT_ACTIVATE,
            ]);

        return [
            'status' => 200,
            'msg'    => '默认目录设置成功',
        ];
    }
}
