<?php
namespace Landers\Wechat;

use Landers\LaravelAms\Models\WechatMatchRuleModel;

trait WechatHandle {
    protected function handleText() {
        $text = $this->receives->Content;
        if (!$rule_id = $this->matchRule($text)) {
            trace('无匹配关键词', $text);
            $this->replyNoMatch();
        } else {
            trace('匹配到规则', '规则id:'.$rule_id);
        }

        $rule_info = app(WechatMatchRuleModel::class)->find($rule_id);
        $rsp_content = trim($rule_info['response_data'], '[]');
        $rsp_type = $rule_info['response_type'];
        trace('规则信息', $rule_info);

        if ($rsp_type === 'handler') {
            $this->replyHandler($rsp_content);

        } elseif (preg_match('/^(\d+\,)+\d+$/', str_replace(' ', '', $rsp_content)) >= 1 || is_numeric($rsp_content)) {
            $this->replyFromLib($rsp_content);

        } else {
            $this->replyText($rsp_content);
        }
    }

    protected function handleImage() {
        $media_id = $this->receives->MediaId;
        $this->replyImage($media_id);
    }

    protected function handleVoice() {
        $media_id = $this->receives->MediaId;
        $this->replyVoice($media_id);
    }

    protected function handleVideo() {
        $media_id = $this->receives->MediaId;
        $thumb_id = $this->receives->ThumbMediaId;
        $this->replyVideo($media_id, $thumb_id);
    }

    protected function handleMusic() {
        $this->replyMusic('http://yinyueshiting.baidu.com/data2/music/246614440/246614085162000128.mp3?xcode=67825dda0829953296a2d3f13bf86a03', '歌曲标题', 'O24Zj8obEsG2TC5fowtyLCL5NGY45albyIUqL_3jGixYKk0rGDdFvNBK393uNNpB');
    }

    protected function handleLink() {
        $title = $this->receives->Title;
        $description = $this->receives->Description;
        $linkurl = $this->receives->Url;
        $this->replyNews($title, $description, $linkurl);
    }

    // 处理事件后的数据
    protected function getCacheEventHandler($event_type) {
        $ttype = 'menu';
        //如果缓存中已经存在hd，则有可能为上一应用模块未结束留下的，需要等待1秒钟
        if ($hd = $this->wcSession->get($ttype)) {
            trace('缓存中已经存在hd，需要等待1秒钟，等待上次清空生', $hd); sleep(1);
        }
        $loop_counts = 5; $loop_count = 1;
        while ($loop_count <= $loop_counts) {
            $hd = $this->wcSession->reget($ttype);
            trace('第'.$loop_count.'次检测句柄', $hd);
            if ($hd) break; usleep(200000); $loop_count++;
        };
        if ($hd) {
            $hd = (object)$hd;
            if ($hd->event_type != $event_type) $hd = NULL;
        }
        return $hd;
    }

    protected function handleLocation() {
        $lat = $this->receives->Location_X;
        $lng = $this->receives->Location_Y;
        $this->location = array('lng' => $lng, 'lat' => $lat);
        trace(sprintf('用户发送的是地理位置：%s,%s', $lng, $lat));

        $hd = $this->getCacheEventHandler('location_select');
        if ($hd) {
            $this->wcSession->clear();
            $this->replyHandler($hd->handler_key);
        } else {
            $message = "当前位置：\n经度：%s\n纬度：%s\n未找到可处理位置信息的应用模块，请重试！";
            $message = sprintf($message, $lng, $lat);
            $this->replyText($message);
        }
    }

    protected function handleSubscribe(){
        if (!empty($rev->EventKey)) {
            trace('通过扫描二维码关注');
        } else {
            trace('通过关键词搜索关注');
        }
        $on_subscribe = json_decode($this->wcInfo->on_subscribe);
        if ($on_subscribe && !$on_subscribe->response_data) {
            $msgTpl = '{"response_type":"text","response_data":"感谢关注%s。"}';
            $on_subscribe = sprintf($msgTpl, $this->wcInfo->title);
        }
        if ($info = $this->wcFans->getInfo()) {
            $this->wcFans->create($info);
        }
        $this->replySend($on_subscribe);
    }

    protected function handleUnsubscribe() {
        $this->wcFans->unsubscribe();
        $this->replyText('谢谢一直对我们的支持！');
    }


    protected function handleScanCode() {
        $scan = (object)(array)$this->receives->ScanCodeInfo;
        $type_name = self::$scanTypes[$scan->ScanType] or $type_name = $scan->ScanType;
        $message = sprintf("扫码推事件（弹框）\n扫码类型：%s\n扫码结果：%s", $type_name, $scan->ScanResult);
        trace( $message );
        $this->replyText( $message );
    }
}