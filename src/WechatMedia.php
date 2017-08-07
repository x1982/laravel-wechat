<?php
namespace Landers\Framework\Apps\Wechat;


trait WechatMedia {

    //上传媒体文件
    public function upload($type, $media){
        $url = "%s/cgi-bin/media/upload?access_token=%s&type=%s";
        $url = sprintf($url, self::$apihost, $this->getAccessToken(), $type);
        if ( class_exists('CURLFile') ) {
            $media_file = Url::toLocal($media);
            $data = array('media' => new \CURLFile($media_file));
        } else {
            $data = array();
            $key = 'media';//上传到$_FILES数组中的 key
            $filename = pathinfo($media, PATHINFO_BASENAME);//文件名
            $mimetype = 'text/plain';//文件类型
            $postkey = "$key\"; filename=\"$filename\r\nContent-Type: $mimetype\r\n";
            $data[$postkey] = Storage::read($media);
            // $data = array('media' => Storage::read($media));
        }
        trace('上传媒体文件数据包', $data);
        $ret = WechatHelper::httpPost($url, $data, false);
        if ($ret->success) {
            return $ret->data['media_id'];
        } else {
            $message = '资源上传到微信服务器失败！';
            trace($message, $ret);
            throw new \Exception($message);
        }
    }
}