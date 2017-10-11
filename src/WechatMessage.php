<?php
namespace Landers\Wechat;

use Landers\AmsApp\Globals\Models\Bus\WechatMsgTemplateModel;

/*微信消息推送类*/
class WechatMessage {

    use WechatToken;

    public $apiResult;

    /*实例化构造方法*/
    function __construct($appid, $appsecret) {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
    }

    /**
     * 取得模板
     */
    private static function getTemplate($tplid) {
        $model = app(WechatMsgTemplateModel::class)->where('key', $tplid)->first();
        if ( $model ) {
            return $model->tpl_id;
        } else {
            return $tplid;
        }
    }

    /**
     * 推送消息
     * @param string|array $openids
     * @param string $tplkey
     * @param array $data
     * @param $topcolor
     * @param $linkurl
     * @return bool
     */
    public function send(
        $openids,
        string $tplkey,
        array $data = array(),
        $topcolor = '#FF0000',
        $linkurl = ''
    ){
        $url = '%s/cgi-bin/message/template/send?access_token=%s';
        $url = sprintf($url, self::$apihost, $this->getAccessToken());

        foreach ($data as $k => &$item) {
            $color = $k == 'first' || $k == 'remark' ? '#333333' : '#0066CC';
            if (is_string($item)) $item = array('value' => $item, 'color' => $color);
        };

        if ( !$openids = (array)$openids) {
            throw new \Exception('目标接收者为空!');
        }

        $bool = true;
        foreach ($openids as $openid) {
            $pack = array(
                'touser' => $openid,
                'template_id' => self::getTemplate($tplkey),
                'topcolor' => $topcolor,
                'data' => $data,
            );
            if ($linkurl) $pack['url'] = $linkurl;
            trace('最终微信推送消息数据包', $pack);
            $this->apiResult = WechatHelper::httpPost($url, $pack);
            $bool = $this->apiResult->success && $bool;
            if ( $bool ) {
                $this->apiResult->message = '微信消息推送成功';
            } else {
                $this->apiResult->message = '微信消息推送失败';
            }
        }
        return $bool;
    }
}