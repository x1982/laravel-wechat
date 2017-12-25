<?php
namespace Landers\Wechat;

use Illuminate\Support\Facades\URL;

trait WechatAuth {
    public function authByCode($code) {
        trace('授权后得到code，并用它进行获取用户信息', $code);
        $info = $this->wcFans->getInfoByCode($code);
        if ( $info ) {
            $this->setOpenid($info['openid'], true);
            $this->wcFans->create($info);
        } else {
            throw new \Exception('未能从官方获得用户信息');
        }
        return $this;
    }

    public function authByOpenid($openid) {
        trace('有openid参数，获取粉丝用户信息', $openid);
        if ( !$this->wcFans->exists() ){
            if ($info = $this->wcFans->getInfo()) {
                $this->wcFans->create($info);
            }
        }
        $this->setOpenid($openid, true);
        $this->wcFans->setOpenid($openid, true);
        return $this;
    }

    public function authConnact(array $params = array()){
        $url_callback = URL::current(true);
        $url_connect = WechatHelper::buildAuthLink($this->wcInfo, $url_callback);
        //if (!Client::isPadDevice()) dp($url_connect);
        header("Location: $url_connect"); exit();
    }
}