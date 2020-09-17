<?php
/**
 * Created by PhpStorm.
 * User: Sakuraiyaya
 * Date: 2020/9/8
 * Time: 9:33.
 */
require 'cos-php-sdk-v5/vendor/autoload.php';

use Qcloud\Cos\Client;

$appid = '***';  // 请替换为您的 APPID
$secretId = '***';  // 请替换为您的 SecretId
$secretKey = '***';  // 请替换为您的 SecretKey
$region = 'ap-shanghai';
$bucket = '***'.'-'.$appid;
$host = 'https://'.$bucket.'.cos.'.$region.'.myqcloud.com/';
$callbackUrl = "http://***/rakan/callback/cos/$bucket";

$cosClient = new Client([
    'region'      => $region,
    'credentials' => [
        'secretId'  => $secretId,
        'secretKey' => $secretKey,
    ],
]);

function main_handler($event, $context)
{
    global $appid;
    global $cosClient;
    global $bucket;
    global $host;
    global $secretKey;
    global $callbackUrl;

    foreach ($event->Records as $record) {
        $key = str_replace('/'.$appid.'/'.$record->cos->cosBucket->name.'/', '', $record->cos->cosObject->key);

        $metadata = $cosClient->headObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);

        if (($metadata['ContentType'] & 'image') === 'image') {
            $bool = exif_imagetype($host.$key);

            if ($bool) {
                $url = $host.$key.'?imageInfo';

                $fileInfo = json_decode(file_get_contents($url), true);
            } else {
                $fileInfo['width'] = 0;
                $fileInfo['height'] = 0;
            }
        } else {
            $fileInfo['width'] = 0;
            $fileInfo['height'] = 0;
        }

        $data = [
            'key'      => $key,
            'filename' => $key,
            'size'     => $metadata['ContentLength'],
            'mimeType' => $metadata['ContentType'],
            'width'    => $fileInfo['width'],
            'height'   => $fileInfo['height'],
        ];

        ksort($data);

        $data['sign'] = md5(http_build_query($data).'&secretkey='.$secretKey);

        $params = http_build_query($data, null, '&');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $callbackUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [],
            CURLOPT_HEADER         => 1,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $params,
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;
    }

    return 'Success';
}
