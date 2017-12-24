<?php
namespace Landers\Wechat;

use Landers\Substrate2\Classes\ApiResult;
use Landers\Substrate\Classes\Url;
use Landers\Substrate\Classes\FetchUrl;
use Illuminate\Support\Facades\Request;

class WechatHelper {
    //检查安全合法性
    public static function check($token) {
        $signature  = Request::get('signature');
        $timestamp  = Request::get('timestamp');
        $nonce      = Request::get('nonce');
        $tmpArr     = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        return $tmpStr == $signature;
    }

    public static function httpGet($url) {
        $ret = FetchUrl::get($url);
        return self::httpReturn($ret);
    }

    public static function httpPost($url, $data, $is_hd_data = true) {
        if ($is_hd_data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $data = stripslashes($data);
        }
        $ret = FetchUrl::post($url, $data);
        return self::httpReturn($ret);
    }

    //解析返回
    public static function httpReturn($ret){
        if (!$ret) {
            return ApiResult::make('api调用失败！');
        }

        if (is_string($ret)) {
            $ret = json_decode($ret, true);
        }

        if ( isset($ret['errcode']) ) {
            if ($ret['errcode'] == 0) {
                return ApiResult::make(true, $ret['errmsg']);
            } else {
                $message = $ret['errmsg'].'(错误码：'.$ret['errcode'].')';
                return ApiResult::make($message);
            }
        } else {
            return ApiResult::make($ret);
        }
    }

    //生成网页授权链接
    public static function buildAuthLink($wcInfo, $redirect_uri) {
        $tpl = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
        $ret = sprintf($tpl, $wcInfo->wechat_appid, urlencode($redirect_uri));
        return $ret;
    }

    //生成链接标签代码
    public static function buildLinkCode($url, $text){
        return sprintf('<a href="%s">%s</a>', $url, $text);
    }

    //生成带openid参数的链接
    public static function buildOpenidLink($url, $wcInfo, $openid){
        $params = [
            'ukey'      => $wcInfo->ukey,
            'openid'    => $openid
        ];
        $url = Url::build($url, $params, true);
        return $url;
    }
}