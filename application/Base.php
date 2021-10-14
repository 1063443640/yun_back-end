<?php

namespace app\common\controller;

use addons\mydemo\library\JWT;
use addons\mydemo\library\SignatureInvalidException;
use addons\mydemo\library\BeforeValidException;
use addons\mydemo\library\ExpiredException;
use addons\mydemo\library\Exception;
use app\common\controller\Api;
use think\Exception as ThinkException;

class Base extends Api
{
    protected function _initialize()
    {
        parent::_initialize();
        $header = $this->request->header();
        if (!assert($header["authorization"])) {
            $this->error("无登录状态", "", 403);
        }
        $key = "gongjingrong";
        try {
            JWT::$leeway = 60; //当前时间减去60，把时间留点余地
            $decoded = JWT::decode($header["authorization"], $key, ['HS256']); //HS256方式，这里要和签发的时候对应
            $this->uid = $decoded->data->userid;
            $this->role = $decoded->data->role;
            if ($decoded->data->role != "admin") {
                $this->nickname = $decoded->data->nickname;
                $this->account = $decoded->data->account;
                $this->id = $decoded->data->id;
            }
        } catch (SignatureInvalidException $e) {  //签名不正确
            return $this->error("登录状态错误", "", 403);
        } catch (BeforeValidException $e) {  // 签名在某个时间点之后才能用
            return $this->error("登录状态错误", "", 403);
        } catch (ExpiredException $e) {  // token过期
            return $this->error("登录状态过时", "", 403);
        } catch (ThinkException $e) {  //其他错误
            return $this->error("登录状态错误", "", 403);
        }
    }
}
