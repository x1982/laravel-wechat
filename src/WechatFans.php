<?php
namespace Landers\Framework\Apps\Wechat;

use Landers\Framework\Modules\Auth;
use Landers\Framework\Core\ArchiveModel;
use Landers\Substrate\Utils\Arr;


class WechatFans {

    use WechatToken, WechatOpenId;

    private $appid, $appsecret;

    private static $archive;

    protected $wcId;

    /*构造方法*/
    public function __construct ( $appid, $appsecret, $openid, $wc_id ) {
        $this->openid = $openid;
        $this->wcId = $wc_id;
        $this->appid = $appid;
        $this->appsecret = $appsecret;

        self::$archive = self::archive();
        self::$archive->setBase('wechat_id', $this->wcId );
    }

    private static function archive() {
        if ( !self::$archive ) {
            self::$archive = ArchiveModel::make('wechat_fans');
        }
        return self::$archive;
    }


    //转换用户昵称以存入数据库
    private static function convInfo($uinfo, $avatar_size = 132) {
        $uinfo['privilege'] = isset($uinfo['privilege']) ? json_encode($uinfo['privilege']) : NULL;
        $uinfo['nickname']  = addslashes(trim(json_encode($uinfo['nickname']), '"'));
        $uinfo['headimgurl']= self::getAvatar($uinfo['headimgurl'], $avatar_size);
        $uinfo['sex']       = (int)trim($uinfo['sex'],"'");
        return $uinfo;
    }

    //从数据库读出后，转换用户昵称
    public static function unconvInfo($uinfo){
        $uinfo['nickname']  = json_decode('"'.stripslashes($uinfo['nickname']).'"', true);
        $uinfo['sex']       = strlen($uinfo['sex']) ? ($uinfo['sex'] ? '男' : '女') : '未知';
        $uinfo['privilege'] = json_decode($uinfo['privilege'], true);
        return $uinfo;
    }

    //转换用户头像
    private static function getAvatar($dat, $size = 96) {
        $img = is_array($dat) ? $dat['headimgurl'] : $dat; //0、46、64、96、132
        $img = preg_replace('/\d{1,3}+$/i', $size, $img, 1);
        return $img;
    }

    public static function findLocal($uid, $fields = NULL) {
        return self::archive()->find([
            'awhere' => ['uid' => $uid],
            'fields' => $fields
        ]);
    }

    public function getLocalInfo() {
        $info = self::archive()->find(array(
            'awhere' => array('openid' => $this->openid),
        ));
        if (!$info) return array();
        return self::unconvInfo($info);
    }

    public function getInfo() {
        if (!$this->openid) {
            throw new \Exception('openid未获得');
        }
        $url = '%s/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN';
        $url = sprintf($url, self::$apihost, $this->getAccessToken(), $this->openid);
        $result = WechatHelper::httpGet($url);
        if ($result && $result->success) {
            trace('获取微信用户信息成功', $result->data);
            return $result->data;
        } else {
            trace('获取微信用户信息失败', $result);
            return false;
        }
    }

    //要据授权后得到的code取用户信息
    public function getInfoByCode($code){
        //通过code换取access_token
        trace('通过code获取授权 access_token ');
        $token_pack = $this->getAuthAccessToken($code);
        $access_token   = $token_pack->access_token;
        $openid         = $token_pack->openid;

        //本地不存在，用access_token获取用户资料，并入库
        trace('用授权access_token获取用户资料', 'access_token：'.$access_token);
        $url = '%s/sns/userinfo?access_token=%s&openid=%s&lang=zh_CN';
        $url = sprintf($url, self::$apihost, $access_token, $openid);
        trace('得到授权换取地址', $url);
        $api_result = WechatHelper::httpGet($url);
        if ( $api_result->success ) {
            $info = $api_result->data;
            trace('授权用户信息获取成功', $info);
        } else {
            trace('授权用户信息获取失败：'.$api_result->message);
            $info = array();
        }
        return $info;
    }

    //绑定系统用户和微信用户
    public function bindLocal($userid) {
        if (!$this->openid) {
            throw new \Exception('openid未获得');
        }

        $info = self::archive()->find(array(
            'fields'    => 'id, uid',
            'awhere'    => array('openid' => $this->openid),
        ));
        if (!$info) return false;
        if ($info['uid']) return true;
        $bool = self::archive()->debug(0)->update(
            array('uid' => $userid), $info['id']
        );
        trace('微信用户与本地用户绑定', $bool);
        return $bool;
    }

    //用户入本地库
    public function create($info){
        $openid = $this->openid or $openid = $info['openid'];
        if (!$openid) throw new \Exception('openid未获得');
        $this->setOpenid($openid, true);

        if ( $this->exists() ) {
            $updata = Arr::slice($info, ['subscribe', 'subscribe_time']);
            trace('用户已存在，更新为关注状态');
            self::archive()->update($updata, [
                'openid' => $openid
            ]);
            return self::archive()->find([
                'awhere' => ['openid' => $openid]
            ]);
        } else {
            $info = self::convInfo($info);
            $info['openid'] = $openid;
            $info['wechat_id'] = $this->wcId;
            $user = self::archive()->create($info);

            trace(sprintf('openid入库%s', $user ? '成功' : '失败'), json_encode($info));
            if ( !$user ) throw new \Exception(sprintf('OpenId“%s”用户入库失败', $info['openid']));
            return $user;
        }
    }

    //删除用户
    public function unsubscribe() {
        return self::archive()->update([
            'subscribe' => 0
        ],[
            'openid' => $this->openid
        ]);
    }

    //更新本地用户信息
    public function update($data){
        if (!$this->openid) {
            throw new \Exception('openid未获得');
        }
        $awhere = array('openid' => $this->openid);
        $data or $data = array();

        //市民卡专有
        $data = array_merge($data, array(
            'update_date'   => date('Y-m-d'),
        ));
        $bool = self::archive()->update($data, $awhere);
        trace(sprintf('本地微信用户更新%s', $user ? '成功' : '失败'), $bool);
        return $bool;
    }

    //本地是否存在用户
    public function exists(){
        if (!$this->openid) {
            throw new \Exception('openid未获得');
        }
        $count = self::archive()->count(['openid' => $this->openid]);
        return $count > 0;
    }

    //用openid登录本地用户
    public function login(){
        if (!$this->openid) {
            throw new \Exception('openid未获得');
        }
        return Auth::user('member')->login_by_wechat($this->openid);
    }
}