<?php
namespace Landers\Framework\Apps\Wechat;

use Landers\Substrate\Utils\Cookie;
use Landers\Substrate\Traits\MakeInstance;
use Landers\Substrate\Classes\FetchUrl;

/*微信Token类*/
trait WechatToken {
    use MakeInstance;

    private static $apihost = 'https://api.weixin.qq.com';

    private $appid, $appsecret;

    //取得普通access_token
    public function getAccessToken(){
        $cookie_key = 'wechat_access_token_for_'.$this->appid;
        if ( (!$ret = Cookie::get($cookie_key)) || true ) {
            $url = '%s/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s';
            $url = sprintf($url, self::$apihost, $this->appid, $this->appsecret);
            trace('cookie无普通access_tooken，通过url获取它', 'url为：'.$url);
            $ret = WechatHelper::httpGet($url);
            if ( $ret->success ) {
                $data = (object)$ret->data;
                trace('成为获取到普通access_tooken', $data->access_token);
                Cookie::set($cookie_key, $data->access_token, (int)$data->expires_in);
                return $data->access_token;
            } else {
                trace('普通access_token获取失败', $ret);
                throw new \Exception('普通access_token获取失败：'.$ret->message);
            }
        } else {
            trace('从cookie得到普通access_token', $ret);
            return $ret;
        }
    }

    //通过授权链接返回的code，取得授权用的access_token
    public function getAuthAccessToken($code = NULL){
        $cookie_key = 'wechat_auth_access_token_for_'.$wc_appid;
        Cookie::forgot($cookie_key);
        if ( !$token_data = Cookie::get($cookie_key) ) {
            $code or $code = Request::get('code', true);
            $url = '%s/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code';
            $url = sprintf($url, self::$apihost, $this->appid, $this->appsecret, $code);
            trace('cookie无 授权access_tooken，通过url获取它', 'url为：'.$url);
            $api_result = WechatHelper::httpGet($url);
            if ( !$api_result->success ){
                trace('授权access_token获取失败', $api_result);
                throw new \Exception('授权access_token获取失败！');
            } else {
                $token_data = (object)$api_result->data;
                trace('成功获取授权 access_token', $token_data->access_token);
                Cookie::set($cookie_key, serialize($token_data), (int)$token_data->expires_in);
            }
        } else {
            $token_data = unserialize($token_data);
            trace('从cookie得到授权access_token数据包', $token_data);
        }
        return $token_data;
    }
}