<?php
namespace Landers\Wechat;

use Landers\Substrate\Utils\Arr;
use Landers\Substrate\Utils\Str;
use Landers\Substrate\Classes\Url;

/*菜单接口类*/
class WechatMenu {

    use WechatToken;

    /*实例化构造方法*/
    function __construct($appid, $appsecret) {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
    }

    //创建菜单
    public function create($data){
        $url = '%s/cgi-bin/menu/create?access_token=%s';
        $url = sprintf($url, self::$apihost, $this->getAccessToken());
        foreach ($data['button'] as &$button) {
            foreach ($button['sub_button'] as &$item) {
                if (!$item['url']) unset($item['url']);
            }; unset($item);
        }; unset($button);

        trace('最终菜单数据', $data);
        $ret = WechatHelper::httpPost($url, $data);
        return $ret;
    }

    //获取菜单
    public function get(){
        $url = '%s/cgi-bin/menu/get?access_token=%s';
        $url = sprintf($url, self::$apihost, $this->getAccessToken());
        return WechatHelper::httpGet($url);
    }

    //删除菜单
    public function delete(){
        $url = '%s/cgi-bin/menu/delete?access_token=%s';
        $url = sprintf($url, self::$apihost, $this->getAccessToken());
        return WechatHelper::httpGet($url);
    }

    //纠正菜单项数据
    public static function correctItem(&$menu_data){
        if ($menu_data['sub_button']) {
            $menu_data = Arr::slice($menu_data, 'name, sub_button');
        } else {
            if ($menu_data['url']) {
                $menu_data['url'] = Url::format($menu_data['url'], true);
            };
            $keys = 'sorter, response_type, response_data, is_cache, is_clear_cache';
            foreach ( Str::split($keys) as $key) unset($menu_data[$key]);
        }
    }
}