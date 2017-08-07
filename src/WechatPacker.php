<?php
namespace Landers\Framework\Apps\Wechat;

use Landers\Substrate\Classes\Url;
use Landers\Substrate\Traits\MakeInstance;

/*微信推送数据打包类*/
class WechatPacker {
    use MakeInstance;

    private $from, $to;

    function __construct($from, $to) {
        $this->from = $from;
        $this->to = $to;
    }

    public function text($text) {
        $textTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[%s]]></Content>
        </xml>';
        return sprintf($textTpl, $this->from, $this->to, time(), $text);
    }

    public function link($url, $title = NULL) {
        $title or $title = $url;
        $url = str_replace('{title}', $title, $url);
        return $this->text($this->from, $this->to, $url);
    }

    public function image($media_id) {
        $textTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[image]]></MsgType>
            <Image>
                <MediaId><![CDATA[%s]]></MediaId>
            </Image>
        </xml>';
        return sprintf($textTpl, $this->from, $this->to, time(), $media_id);
    }

    public function voice($media_id) {
        $textTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[voice]]></MsgType>
            <Voice>
                <MediaId><![CDATA[%s]]></MediaId>
            </Voice>
        </xml>';
        return sprintf($textTpl, $this->from, $this->to, time(), $media_id);
    }

    public function video($data) {
        $title = $data['title'] or $title = '';
        $description = $data['description'] or $description = '';
        $media_id = $data['media_id'];
        $textTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[video]]></MsgType>
            <Video>
                <MediaId><![CDATA[%s]]></MediaId>
                <Title><![CDATA[%s]]></Title>
                <Description><![CDATA[%s]]></Description>
            </Video>
        </xml>';
        return sprintf($textTpl, $this->from, $this->to, time(), $media_id, $title, $description);
    }

    public function news($pack) {
        if (!is_array($pack)) return '';
        $itemTpl  = '    <item>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            <PicUrl><![CDATA[%s]]></PicUrl>
            <Url><![CDATA[%s]]></Url>
        </item>';
        $news = '';
        foreach ($pack as $item) {
            if ($item['image_url']) {
                $item['image_url'] = Url::toHttp($item['image_url']);
                $item['link_url'] or $item['link_url'] = $item['image_url'];
            }
            $news .= sprintf($itemTpl, $item['title'], $item['description'], $item['image_url'], $item['link_url']);
        }
        $newsTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[news]]></MsgType>
            <Content><![CDATA[]]></Content>
            <ArticleCount>%s</ArticleCount>
            <Articles>
            %s
            </Articles>
        </xml>';
        return sprintf($newsTpl, $this->from, $this->to, time(), count($pack), $news);
    }

    public function music($data) {
        $url = Url::toHttp($data['music_url']);
        $hq_url = Url::toHttp($data['hq_music_url']);
        $title = $data['title'] or $title = '';
        $description = $data['description'] or $description = '';
        $thumb_id = $data['thumb_id'];
        $textTpl = '<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[music]]></MsgType>
            <Music>
                <Title><![CDATA[%s]]></Title>
                <Description><![CDATA[%s]]></Description>
                <MusicUrl><![CDATA[%s]]></MusicUrl>
                <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
                <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
            </Music>
        </xml>';
        return sprintf($textTpl, $this->from, $this->to, time(), $title, $description, $url, $hq_url, $thumb_id);
    }
}

