<?php
namespace Landers\Wechat;

use Landers\Framework\Core\ArchiveModel;

/*微信消息推送类*/
class WechatMessage {

    use WechatToken;

    /*实例化构造方法*/
    function __construct($appid, $appsecret) {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
    }

    /**
     * 取得模板
     */
    private static function getTemplate($tplid) {
        $model = ArchiveModel::make('wechat_msg_template');
        $tplkey = $model->find($tplid, 'tpl_id');
        return $tplkey ? $tplkey : $tplid;
    }

    /**
     * 推荐模板消息
     */
    public function send($openid, $tplid, $data = array(), $topcolor = '#FF0000', $linkurl = ''){
        $url = '%s/cgi-bin/message/template/send?access_token=%s';
        $url = sprintf($url, self::$apihost, $this->getAccessToken());

        foreach ($data as $k => &$item) {
            $color = $k == 'first' || $k == 'remark' ? '#333333' : '#0066CC';
            if (is_string($item)) $item = array('value' => $item, 'color' => $color);
        };
        $pack = array(
            'touser'        => $openid,
            'template_id'   => self::getTemplate($tplid),
            'topcolor'      => $topcolor,
            'data'          => $data,
        );
        if ($linkurl) $pack['url'] = $linkurl;
        trace('最终微信推送消息数据包', $pack);
        $api_result = WechatHelper::httpPost($url, $pack);
        if ($api_result->success) {
            $api_result->message = '微信消息推送成功';
        }
        return $api_result;
    }
}