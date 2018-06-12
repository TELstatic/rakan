<?php

use TELstatic\Rakan\Models\Storages;

/**
 * JSON返回封装
 */
if (!function_exists('responseData')) {
    function responseData($code = 200, $data)
    {
        return response()->json([
            'code' => $code,
            'data' => $data,
        ], $code);
    }
}

/**
 * 阿里回调合法性检测
 */
if (!function_exists('vefiryData')) {
    function verifyData($auth)
    {
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

        return $ok == 1 ? true : false;
    }
}

/**
 * 阿里直传签名生成
 */
if (!function_exists('newPolicy')) {
    function newPolicy($target_id)
    {
        if (verifyUsage($target_id)) {
            return responseData(201, "使用空间不足");
        }

        $id = config('rakan.alioss.ak');
        $key = config('rakan.alioss.sk');
        $host = config('rakan.alioss.host');
        $callbackUrl = config('rakan.alioss.callback');
        $expire = config('rakan.alioss.expire');

        $callback_param = array('callbackUrl' => $callbackUrl,
            'callbackBody' => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => "application/x-www-form-urlencoded");
        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);

        $now = time();
        $end = $now + $expire;
        $expiration = newTime($end);

        $dir = newDir($target_id);

        $condition = array(0 => 'content-length-range', 1 => 0, 2 => 1048576000);
        $conditions[] = $condition;

        $start = array(0 => 'starts-with', 1 => '$key', 2 => $dir);
        $conditions[] = $start;

        $arr = array('expiration' => $expiration, 'conditions' => $conditions);

        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));

        $response = [];
        $response['accessid'] = $id;
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        $response['callback'] = $base64_callback_body;
        $response['dir'] = $dir;

        return responseData(200, $response);

    }
}

/**
 * 使用空间检测
 */
if (!function_exists('verifyUsage')) {
    function verifyUsage($target_id)
    {
        $storage = Storages::where(["target_id" => $target_id])->firstOrFail();

        return $storage->usage >= $storage->space ? true : false;
    }
}

/**
 * 阿里签名 时间 参数生成
 */
if (!function_exists('newTime')) {
    function newTime($time)
    {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }
}

/**
 * 阿里签名 目录 参数生成
 */
if (!function_exists('newDir')) {
    function newDir($target_id)
    {
        $target_id = hashid_encode($target_id);
        return config('rakan.dir') . $target_id;
    }
}