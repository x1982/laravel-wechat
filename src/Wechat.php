<?php
namespace Landers\Wechat;

use Illuminate\Support\Facades\Request;
use Landers\LaravelAms\Models\WechatMatchRuleModel;
use Landers\LaravelAms\Models\WechatModel;

class Wechat {
    use WechatConst, WechatToken, WechatHandle, WechatReply, WechatAuth, WechatMedia, WechatOpenId;

    protected $wcInfo, $receives, $location;
    protected $wcSession, $reply;
    public $wcFans;

    /**
     * 构造函数：完成 wcInfo, openid, $appid, $appsecret的初始化
     * @param [type] $ukey [description]
     */
    public function __construct( $ukey ){
        trace('初始化微信应用模块');
        $where = $ukey === true ?
            ['is_default' => 1] :
            ['ukey' => $ukey];
        $this->wcInfo = app(WechatModel::class)->where($where)->first();

        if (!$this->wcInfo) {
            throw new \Exception('无效ukey！');
        } else {
            $this->wcInfo = (object)$this->wcInfo;
        }

        //if ( $tempMenu = $this->wcInfo->menu) {
        //    $tempMenu = json_decode($tempMenu);
        //}
        //dp($tempMenu);
        $this->appid = $this->wcInfo->wechat_appid;
        $this->appsecret = $this->wcInfo->wechat_appsecret;

        if (!$openid = $this->getOpenid()) {
            trace('$this->getOpenid 未能获取到 openid');
            if ($user = \Auth::guard('member')->user()) {
                $openid = WechatFans::findLocal($user['id'], 'openid');
                trace('发现用户已登陆, 从本地户与粉丝关系中找到 openid');
            }
        } else {
            trace('$this->getOpenid 获取到 openid', $openid);
        }

        // 延长openid缓存时间
        if ($openid) {
            $this->setOpenid($openid, true);
        }

        $this->wcFans = WechatFans::make(
            $this->wcInfo->wechat_appid,
            $this->wcInfo->wechat_appsecret,
            $openid,
            $this->wcInfo->id
        );
    }

    /**
     * @return object
     */
    public function getInfo() {
        return $this->wcInfo;
    }

    //从用户接收数据
    public function receive(){
        $str_xml = array_get($GLOBALS, "HTTP_RAW_POST_DATA");
        $str_xml or $str_xml = file_get_contents("php://input");
        if ($str_xml) {
            $this->receives = simplexml_load_string($str_xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->receives = (object)(array)$this->receives;
            if ($this->receives->ToUserName != $this->wcInfo->wechat_key) {
                throw new \Exception('ToUserName 与 wechat_key不一致！');
            }
            if ($this->receives) {
                $this->openid = $this->receives->FromUserName;
                $this->setOpenid($this->openid, true);
                $this->wcFans->setOpenid($this->openid, true);
            }
        };
        if ($this->receives) {
            trace('收到用户数据', $this->receives);
        }
        return $this;
    }

    //根据关键词匹配回复内容
    private function matchRule($kw) {
        $kw = trim($kw);
        $awhere = array(
            'wechat_id' => $this->wcInfo->id,
        );

        $model = app(WechatMatchRuleModel::class);

        //完全匹配
        $rule = $model->where(
            array_merge($awhere, array(
                'matchtype' => 'whole',
                'keyword' => $kw
            ))
        )->first();
        if ( $rule ) return $rule->id;

        //匹配正则
        $list = $model->where(
            array_merge($awhere, array(
                'matchtype' => 'regular',
            ))
        )->select(['id', 'keyword'])
        ->orderBy('id', 'asc')->get();

        foreach($list as $item) {
            $reg = '/^'.$item['keyword'].'$/iu';
            try {
                if (preg_match($reg, $kw)) {
                    return $item['id'];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        //模糊匹配
        $rule = $model->where(
            array_merge($awhere, array(
                'matchtype' => 'fuzzy',
            ))
        )->where('keyword', 'like', "%{$kw}%")
        ->orderBy('id', 'desc')
        ->first();

        if ( $rule ) return $rule->id;

        return NULL;
    }


    /**
     * 搜索菜单标识
     * @param  [type] $menu_key [description]
     * @return [type]           [description]
     */
    protected function seachMenu($menu_key) {
        foreach ($this->wcInfo->menu as $items) {
            if ($items->key ==  $menu_key) {
                return $items;
            }
            if (!$sub_button = $items->sub_button) {
                continue;
            }
            foreach ($sub_button as $item) {
                if ($item->key == $menu_key) {
                    return $item;
                }
            }
        };
        return NULL;
    }

    protected function setEventCache() {
        $hd_key = $this->receives->EventKey;
        $event = $this->receives->Event;
        $this->wcSession->set('menu', $hd_key, array(
            'handler_key' => $hd_key,
            'event_type' => $even
        ));
        return $hd_key;
    }

    //响应用户操作
    public function response() {
        if ($echo = Request::get('echostr')) {
            if (WechatHelper::check($this->wcInfo->wechat_token)) {
                trace('验证输出', $echo);
                echo $echo;
            } else {
                trace('TOKEN验证失败');
            }
            exit();
        }

        if (!$receives = $this->receives) {
            trace('未收到用户数据！');
            throw new \Exception('未收到用户数据！');
        }
        if (!$this->openid) {
            trace('消息错误，未发现openid');
            throw new \Exception('消息错误，未发现openid');
        }

        $this->wcSession = WechatSession::make($this->openid);
        $this->packer = WechatPacker::make($this->openid, $this->wcInfo->wechat_key);

        switch ( $msgType = $receives->MsgType ){
            case 'text':
                $this->handleText();
                break;

            case 'image':
                $this->handleImage();
                break;

            case 'voice':
                $this->handleVoice();
                break;

            case 'shortvideo' :
            case 'video' :
                $this->handleVideo();
                break;

            case 'link':
                $this->handleLink();
                break;

            case 'location': //可能来自 “+”， 可能是菜单
                $this->handleLocation();
                break;

            case 'event':
                switch ($receives->Event) {
                    case 'subscribe':
                        $this->handleSubscribe();
                        break;

                    case 'unsubscribe':
                        $this->handleUnsubscribe();
                        break;

                    case 'SCAN':
                        $this->replyError('no_define');
                        break;

                    case 'CLICK':
                        $menu_key = $this->receives->EventKey;
                        $menu = $this->seachMenu($menu_key);
                        if ( !$menu ) {
                            $message = '未找到菜单标识：' . $menu_key;
                            trace($message); throw new \Exception($message);
                        }
                        trace('来自事件菜单', $menu);
                        if ( (int)$menu->is_cache ) {
                            $this->setEventCache();
                            trace(sprintf('点击推事件，缓存菜单句柄标识 “%s”', $menu_key) );
                        } else {
                            if ( (int)$menu->is_clear_cache ) {
                                trace('清空了缓存');
                                $this->wcSession->clear();
                            }
                        }
                        $this->replySend($menu->response_type, $menu->response_data);
                        break;
                    case 'location_select': // 自动强制缓存菜单句柄标识
                        $hd_key = $this->setEventCache();
                        $message = sprintf('地理位置选择器，缓存菜单句柄标识 “%s”', $hd_key);
                        trace( $message ); $this->replyText( $message );
                        break;

                    case 'pic_weixin':
                        $hd_key = $this->setEventCache();
                        $message = sprintf('微信发图器，并缓存菜单句柄标识 “%s”', $hd_key);
                        trace( $message ); $this->replyText( $message );
                        break;

                    case 'scancode_push':
                        $hd_key = $this->setEventCache();
                        $message = sprintf('扫描码事件，并缓存菜单句柄标识 “%s”', $hd_key);
                        trace( $message ); $this->replyText( $message );
                        break;

                    case 'scancode_waitmsg':
                        $this->handleScanCode();
                        break;

                    case 'pic_sysphoto' :
                        $hd_key = $this->setEventCache();
                        $message = sprintf('系统拍照，并缓存菜单句柄标识 “%s”', $hd_key);
                        trace( $message ); $this->replyText( $message );
                        break;

                    case 'pic_photo_or_album' :
                        $hd_key = $this->setEventCache();
                        $message = sprintf('拍照或相册，并缓存菜单句柄标识 “%s”', $hd_key);
                        trace( $message ); $this->replyText( $message );
                        break;

                    default:
                        $this->replyText('暂不支持【' . $receives->Event . '】事件响应！');
                        break;
                }
                break;
        }
    }
}