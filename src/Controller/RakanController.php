<?php

namespace TELstatic\Rakan\Controller;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use TELstatic\Rakan\Models\Rakan as File;

class RakanController extends BaseController
{
    /**
     * 保存文件.
     */
    public function saveFile(Request $request, $gateway)
    {
        //非本地环境验证 合法性
        if (config('app.env') !== 'local') {
            Storage::disk($gateway)->verify();
        }

        $fileInfo = pathinfo($request->get('filename'));

        $where = [];

        $where[] = [
            'path', $fileInfo['dirname']
        ];

        $where[] = [
            'gateway', $gateway
        ];

        $folder = File::where($where)->firstOrFail();

        $data = [
            'path'      => $request->get('filename'),
            'size'      => $request->get('size', 1),
            'width'     => $request->get('width', 0),
            'height'    => $request->get('height', 0),
            'ext'       => $request->get('mimeType'),
            'name'      => $fileInfo['basename'],
            'gateway'   => $gateway,
            'host'      => $folder->host,
            'module'    => $folder->module,
            'target_id' => $folder->target_id,
            'pid'       => $folder->id,
            'sort'      => 0,
            'type'      => 'file',
        ];

        $bool = File::create($data);

        return response()->json([
            'status' => $bool ? 200 : 500,
        ]);
    }
}
