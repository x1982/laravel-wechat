<?php
namespace Landers\Framework\Apps\Wechat;

use Landers\Substrate\Utils\Cookie;

trait WechatOpenId {
    public $openid;

    public function getOpenid(){
        if (!$ret = $this->openid) {
            $ret = Cookie::get('openid');
        };
        return $ret;
    }

    public function setOpenid($openid, $is_cache = false){
        trace('设置了openid', $openid);
        if ( !$openid ) {
            dp(trace_call_files());
        }
        $this->openid = $openid;
        if ($is_cache) Cookie::set('openid', $openid, 24);
        return $this;
    }
}