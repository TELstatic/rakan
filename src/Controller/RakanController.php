<?php

namespace TELstatic\Rakan\Controller;

use TELstatic\Rakan\Models\Files;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class RakanController extends BaseController
{
    /**
     * 保存文件
     */
    public function saveFile(Request $request)
    {
        $this->verify();

        $fileInfo = pathinfo($request->filename);

        $folder = Files::where(['path' => $fileInfo['dirname']])->firstOrFail();

        $data = [
            'path'      => $request->filename,
            'size'      => $request->size,
            'width'     => $request->width,
            'height'    => $request->height,
            'ext'       => $request->mimeType,
            'name'      => $fileInfo['basename'],
            'module'    => $folder->module,
            'target_id' => $folder->target_id,
            'pid'       => $folder->id,
            'sort'      => 0,
            'type'      => 'file'
        ];

        $bool = Files::create($data);

        if ($bool) {
            return response()->json([
                'code' => 200
            ]);
        }

        return response()->json([
            'code' => 500
        ]);
    }

    /**
     * 验证合法性
     */
    protected function verify()
    {
        $auth = [
            'authorizationBase64' => $_SERVER['HTTP_AUTHORIZATION'],
            'pubKeyUrlBase64'     => $_SERVER['HTTP_X_OSS_PUB_KEY_URL'],
            'path'                => $_SERVER['REQUEST_URI'],
            'body'                => file_get_contents('php://input'),
        ];

        $authorizationBase64 = $auth['authorizationBase64'];
        $pubKeyUrlBase64 = $auth['pubKeyUrlBase64'];

        if ($authorizationBase64 == '' || $pubKeyUrlBase64 == '') {
            abort(403);
        }

        $authorization = base64_decode($authorizationBase64);

        $pubKeyUrl = base64_decode($pubKeyUrlBase64);

        $pubKey = file_get_contents($pubKeyUrl);

        if ($pubKey == "") {
            abort(403);
        }

        $path = $auth['path'];

        $pos = strpos($path, '?');
        if ($pos === false) {
            $authStr = urldecode($path) . "\n" . $auth['body'];
        } else {
            $authStr = urldecode(substr($path, 0, $pos)) . substr($path, $pos, strlen($path) - $pos) . "\n" . $auth['body'];
        }

        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);

        if (!$ok) {
            abort(403);
        }
    }

}