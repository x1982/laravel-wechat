<?php
namespace Landers\Framework\Apps\Wechat;

trait WechatConst {
    public static $grade = array(
        '1' => '订阅号',
        '2' => '服务号',
        '3' => '高级服务号'
    );

    public static $rxType = array(
        'event'                 => '事件',
        'event_subscribe'       => '关注',
        'event_unsubscribe'     => '取消关注',
        'event_SCAN'            => '扫描场景',
        'event_CLICK'           => '点击菜单',
        'event_LOCATION'        => '上传位置',

        'text'                  => '文本',
        'image'                 => '图像',
        'location'              => '位置',
        'voice'                 => '语音',
        'video'                 => '视频',
        'link'                  => '链接'
    );

    public static $txType = array(
        'handler'               => '应用处理',
        'text'                  => '文本',
        'news'                  => '图文',
        'image'                 => '图像',
        'voice'                 => '语音',
        'video'                 => '视频',
        'music'                 => '音乐',
        'link'                  => '链接'
    );

    private static $msgs = array(
        'end_session'           => '若要结束当前会话应用模块，请回复“#”。',
        'new_session'           => '若要调起其它会话应用模块，请回复“#关键词”或直接选择菜单。',
        'accord_prompt'         => '亲，请根据会话提示输入',
        'inavlid'               => '无效的关键词或动作。',
        'developping'           => '微应用模块%s正在开发中...敬请期待。',
        'no_define'             => '未定义回复内容。',
        'no_support'            => array(
            'image'             => '暂不支持回复图像。',
            'music'             => '暂不支持回复音乐。',
            'voice'             => '暂不支持回复语音。',
            'video'             => '暂不支持回复视频。',
            'link'              => '暂不支持回复链接。',
        ),
        'no_response_type'      => '未指定回复类型！',
        'no_response_data'      => '未指定回复内容！',
        'not_found_data'        => '未找到数据！',
        'cant_parse_pack'       => '不可解析的回复数据包！',
        'path_level_error'      => '系统繁忙！请输入"#"以进行新会话！',
        'fatal_error'           => '系统繁忙！',
    );

    private static $scanTypes  = array(
        'barcode' => '条形码',
        'qrcode' => '二维码'
    );
}