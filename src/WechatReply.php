<?php
namespace Landers\Wechat;

use Landers\Substrate\Utils\Str;
use Landers\Framework\Core\ArchiveModel;
use Landers\Framework\Core\System;

/*微信回复类*/
trait WechatReply {

    //取得回复内容
    public function getContent($reply_id) {
        if (!$reply_id) {
            return self::$msgs['no_define'];
        }
        $reply_id = Str::split($reply_id);
        $lists = ArchiveModel::make('wechat_reply')->lists(array(
            'is_page'   => false,
            'fields'    => 'title, media, media_id, description, type, id, linkurl, linkurl_is_authorize',
            'awhere'    => array('id' => $reply_id),
            'is_conv_upload' => true,
        ));
        if (!$lists) return self::$msgs['no_define'];
        foreach ($lists as &$item) {
            if ($item['type'] == 'news') {
                $linkurl = trim($item['linkurl']);
                $linkurl or $linkurl = System::rewrite('wechat', 'news', array('id' => $item['id']), 1);
                $linkurl = WechatHelper::buildOpenidLink($linkurl, $this->wcInfo, $this->openid);
                if ((int)$item['linkurl_is_authorize']) {
                    $linkurl = WechatHelper::buildAuthLink($this->wcInfo, $linkurl);
                }
                $item['link_url'] = $linkurl;
            }
        }; unset($item);
        return $lists;
    }

    private function updateMediaId($reply_id, $media_id, $type = NULL) {
        $data = array('media_id' => $media_id);
        if ($type) $data['type'] = $type;
        return ArchiveModel::make('wechat_reply')->update($data, array('id' => $reply_id));
    }

    public function replySend() {
        if (func_num_args() == 2) {
            list($type, $data) = func_get_args();
            $str = call_user_func_array(array($this->packer, $type), array($data));
            trace('最终推送的数据', $str);
            $this->wcSession->write();
            echo $str; exit();
        } else {
            if (!$pack = func_get_arg(0)) {
                $this->replyError('inavlid');
            }

            if (is_string($pack)) {
                $pack = json_decode($pack);
            }

            if (!$pack) {
                $this->replyError('cant_parse_pack');
            } else {
                $type = $pack->response_type;
                $data = $pack->response_data;
            }

            if (!$type && !$data) $this->replyError('no_define');
            if (!$type) $this->replyError('no_response_type');
            if (!$data) $this->replyError('no_response_data');
            switch($type) {
                case 'text' :
                    $this->replyText($data);

                case 'news' :;
                case 'music' :
                case 'image':;
                case 'video':;
                case 'voice':;
                    $this->replyFromLib($data);

                case 'link':
                    $this->replyError('no_support', $type);

                case 'handler':
                    $this->replyHandler($data);

                default :
                    $this->replyError('no_define');
            }
        }
    }

    //回应应用
    public function replyHandler($key, $mod_name = ''){
        if (method_exists($this, $key)) {
            call_user_func_array([$this, $key], [$this->receives->Content]);
        } else {
            $this->replyError('no_handler|'.$mod_name);
        }
    }

    //用回复库回应
    public function replyFromLib($reply_id) {
        $list = $this->getContent($reply_id);
        if (!$list) {
            $this->error('not_found_data');
        }
        $title = $list[0]['title'];
        $type = strtolower($list[0]['type']);
        $description = $list[0]['description'];
        $media = $list[0]['media'];
        $media_id = $list[0]['media_id'];
        trace('回复' . self::$txType[$type]);

        switch ($type) {
            case 'news' :
                $this->wcSession->clear();
                $data = array();
                foreach ($list as $item) {
                    $data[] = $this->buildNews($item['title'], $item['description'], $item['link_url'], $item['media']);
                }
                $this->replySend('news', $data);

            case 'music' :
                $this->replyMusic($media, $title);

            case 'image':
                if (!$media_id) {
                    $media_id = $this->upload('image', $media);
                    $this->updateMediaId($reply_id, $media_id);
                }
                $this->replyImage($media_id);

            case 'video':;
            case 'voice':;
                $this->replyError('no_support', $type);
        }
    }

    private function buildNews($title, $description, $link_url, $image_url = NULL) {
        return array(
            'title' => $title,
            'description' => $description,
            'image_url' => $image_url,
            'link_url' => $link_url,
        );
    }

    //回应单图文
    public function replyNews($title, $description, $link_url, $image_url = NULL){
        $data = $this->buildNews($title, $description, $link_url, $image_url);
        return $this->replySend('news', array($data));
    }

    //回应文本
    public function replyText($text){
        if ( ENV_DEBUG_client === true ) {
            $deepth = '(当前层级：'.$this->wcSession->deepth('input').')';
        } else {
            $deepth = '';
        }
        $this->replySend('text', $text.$deepth);
    }

    //回应图片
    public function replyImage($media_id){
        $this->replySend('image', $media_id);
    }

    //回应图片
    public function replyVoice($media_id){
        $this->replySend('voice', $media_id);
    }

    //回应图片
    public function replyMusic($music_url, $title = '', $description = '', $thumb_id = 'O24Zj8obEsG2TC5fowtyLCL5NGY45albyIUqL_3jGixYKk0rGDdFvNBK393uNNpB'){
        $this->replySend('music', array(
            'music_url' => $music_url,
            'hq_music_url' => $music_url,
            'thumb_id' => $thumb_id,
            'title' => $title,
            'description' => $description
        ));
    }

    //回应图片
    public function replyVideo($media_id, $thumb_id, $title = NULL, $description = NULL){
        $data = array(
            'media_id' => $media_id,
            'thumb_id' => $thumb_id,
            'title' => $title,
            'description' => $description
        );
        $this->replySend('video', $data);
    }

    //回应友好文本
    public function replyFriendText($msgType, $text = '') {
        $t = '你发送的是【%s】'."信息，当前没有可处理的会话。%s";
        $str_type = self::$rxType[$msgType];
        $text = sprintf($t, $str_type, $text ? "\n".$text : $text);
        $this->replyText($text);
    }

    //回应错误
    public function replyError($key1, $key2 = NULL){
        $this->wcSession->pop('input');
        $a = explode('|', $key1); $key1 = $a[0]; $text = $a[1] or $text = '';
        $errs = array(
            'no_handler'                => sprintf(self::$msgs['developping'], $text ? '【'.$text.'】' : ''),
            'no_handler_new_session'    => sprintf(self::$msgs['developping'], $text ? '【'.$text.'】' : '')."\n".self::$msgs['new_session'],
            'accord_prompt_new_session' => self::$msgs['accord_prompt'].'，'.self::$msgs['new_session'],
        );
        $msg = $errs[$key1] or $msg = self::$msgs[$key1] or $msg = self::$msgs['inavlid'];
        if (is_array($msg)) $msg = $key2 ? $msg[$key2] : $errs['inavlid'];
        $this->replyText($msg);
    }


    //回应无匹配
    public function replyNoMatch() {
        trace('无匹配时的回复数据', $this->wcInfo->on_nomatch);
        $this->replySend($this->wcInfo->on_nomatch);
    }
}