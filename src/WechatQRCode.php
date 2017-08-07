<?php
namespace Landers\Framework\Apps\Wechat;

/*二维码接口*/
class WechatQRCode {

    use WechatToken;

    /*实例化构造方法*/
    function __construct($appid, $appsecret) {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
    }

    public function getTicket($scene_id, $action_name = 'QR_LIMIT_SCENE', $expire_seconds = 0){
        $url = '%s/cgi-bin/qrcode/create?access_token=%s';
        $url = sprintf($url, self::$apihost, $this->getAccessToken());
        $data   = array(
            'action_name'   => $action_name,
            'action_info'   => array(
                'scene' => array('scene_id' => $scene_id),
            )
        );
        if ( $expire_seconds && $action_name == 'QR_SCENE') {
            $opts['expire_seconds'] = $expire_seconds;
        }
        return WechatHelper::httpPost($url, json_encode($data));
    }
}