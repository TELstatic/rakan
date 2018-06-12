<?php

namespace TELstatic\Rakan\Controller;

use Illuminate\Http\Request;
use TELstatic\Rakan\Models\Files;
use TELstatic\Rakan\Rakan;

class FileController extends Controller
{
    public $file;

    public function __construct(Files $files)
    {
        $this->file = $files;
    }

    public function index(Request $request)
    {
        $where = [];

        if ($request->filled('keyword')) {
            $where[] = [
                'filename', 'like', '%' . $request->keyword . '%'
            ];
        }

        if ($request->filled('path')) {
            $where[] = [
                'path', $request->path
            ];
        }

        return Rakan::getFiles();
    }

    public function store(Request $request)
    {
        $auth = [
            'authorizationBase64' => $request->header('authorization'),
            'pubKeyUrlBase64' => $request->header('x-oss-pub-key-url'),
            'path' => $request->getpathInfo(),
            'body' => file_get_contents('php://input')
        ];

        if (!config('rakan.debug')) {
            if (verifyData($auth)) {
                abort(403);
            }
        } else {
            $request->filename = "Images/lejRej/default/2.jpg";
            $request->width = "10";
            $request->height = "10";
            $request->size = "10";
            $request->mimeType = "jpg";
        }

        $temp = pathinfo(str_replace(config('rakan.dir'), "", $request->filename));

        $file_arr = explode('/', $temp["dirname"]);

        $this->file->width = $request->width;
        $this->file->height = $request->height;
        $this->file->size = $request->size;
        $this->file->type = $request->mimeType;
        $this->file->path = $request->filename;
        $this->file->floder = $temp["dirname"];
        $this->file->filename = $temp["basename"];
        $this->file->target_id = hashid_decode($file_arr[0]);

        //  return $this->file;

        $bool = $this->file->save();

        return responseData($bool ? 200 : 500, $this->file);
    }
}