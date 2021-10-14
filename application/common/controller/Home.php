<?php

namespace app\common\controller;

use app\common\controller\Api;
use Exception;
use think\Controller;

class Home extends Api
{
    public function _initialize()
    {
        $header = $this->request->header();
        if (!assert($header["authorization"])) {
            $data = ['code' => '0', 'msg' => '无登录状态', 'data' => null];
            return json($data)->send();
        }
        $this->open_id = $header["authorization"];
    }
}
