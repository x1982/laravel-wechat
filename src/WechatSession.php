<?php
namespace Landers\Wechat;

use Landers\Substrate\Traits\MakeInstance;
use Redis;

//缓存session
class WechatSession {

    use MakeInstance;

    private static $blank = array('input' => array(), 'menu' => array());

    private $openid, $data;

    function __construct($openid) {
        $this->openid = $openid;
        $this->read();
    }

    private function read(){
        if ($data = Redis::get($this->openid)) {
            $this->data = json_decode($data, true);
        } else {
            $this->data = self::$blank;
        }
    }

    public function write(){
        $content = json_encode($this->data, JSON_UNESCAPED_UNICODE);
        return Redis::set($this->openid, $content);
    }

    public function clear(){
        $this->data = self::$blank;
        $this->write();
    }

    private function lastKey($ttype){
        $data = &$this->data[$ttype];
        $data or $data = array();
        $keys = array_keys($data);
        if (!$keys) return NULL;
        return $keys[count($keys)-1];
    }

    public function get($ttype, $key = NULL){
        $data = &$this->data[$ttype];
        if (!$lastkey = $this->lastKey($ttype)) return array();
        $dat = $data[$lastkey];
        return $key ? $dat[$key] : $dat;
    }

    public function reget($ttype, $key = NULL){
        $this->read();
        return $this->get($ttype, $key);
    }

    public function set($ttype, $cache_key, $dat){
        $this->data[$ttype] = array($cache_key => $dat);
        $this->write();
    }

    private function remove($ttype, $cache_key) {
        $data = &$this->data[$ttype];
        if (isset($data[$cache_key])) {
            unset($data[$cache_key]);
        }
        return $data;
    }

    public function push($ttype, $cache_key, $dat){
        $this->data[$ttype] = $this->remove($ttype, $cache_key);  //为保证堆栈顺序，先删除存在的
        $this->data[$ttype][$cache_key] = $dat;
    }

    public function pop($ttype){
        $data = &$this->data[$ttype];
        $lastkey = $this->lastKey($ttype);
        if (!$lastkey) $data = array();
        else unset($data[$lastkey]);
    }

    public function deepth($ttype){
        return count($this->data[$ttype]);
    }
}