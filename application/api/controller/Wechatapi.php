<?php

namespace app\api\controller;

use app\admin\model\Order;
use app\admin\model\WechatToken;
use app\admin\model\Weixin;
use app\common\controller\Api;
use Exception;
use think\Db;
use Yansongda\Pay\Log;

/**
 * 微信接口
 */
class Wechatapi extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 获取用户open_id
     *
     */
    public function get_openId()
    {
        if (request()->isPost()) {
            $wechat_model = new Weixin();
            $res = request()->post();
            $data = $res["data"];
            $code = $data["code"];
            $appid = config('appid');
            $secret = config('appSecret');
            //通过code换取网页授权access_token
            $weixin = file_get_contents("https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code");
            $jsondecode = json_decode($weixin); //对JSON格式的字符串进行编码
            $array = get_object_vars($jsondecode); //转换成数组
            $open_id = $array["openid"];
            while (true) {
                $invitation_code = mt_rand(10000, 99999);
                if (!$wechat_model->where("invitation_code", $invitation_code)->find()) {
                    break;
                }
            }
            $data = [
                "open_id" => $open_id,
                "invitation_code" => $invitation_code,
                "identification_code" => $invitation_code
            ];
            $userInfo = $wechat_model->where("open_id", $open_id)->find();
            if ($userInfo) {
                if ($userInfo->nickname == "") {
                    // $data["userInfo"] = false;
                } else {
                    $data["userInfo"] = ["avatarUrl" => $userInfo->image, "nickName" => $userInfo->nickname, "phone" => $userInfo->phone, "invitation_code" => $userInfo->invitation_code,"flag"=>$userInfo->flag];
                }
            } else {
                $result = $wechat_model->add_openId($data);
            }
            $this->success('请求成功', $data);
        } else {
            $this->error('请求失败');
        }
    }
    // 更新用户信息
    public function update_userinfo()
    {
        $wechat_model = new Weixin();
        $res = request()->post();
        $header = $this->request->header();
        $open_id = $header["authorization"];
        $userinfo = $res["data"]["userinfo"]; //转换成数组
        if (!is_array($userinfo)) {
            dump($res);
        }
        if (is_array($userinfo)) {
            $data = [
                "open_id" => $open_id,
                "nickname" => $userinfo["nickName"],
                "image" => $userinfo["avatarUrl"],
            ];
            $result = $wechat_model->update_userinfo($data);
            if ($result == 1) {
                $this->success('请求成功');
            } else {
                $this->error($result);
            }
        } else {
            $this->error("不是数组");
        }
    }

    // 支付回调
    public function notifyx()
    {
        $type = "wechat";
        $pay = \addons\epay\library\Service::checkNotify($type);
        if (!$pay) {
            Log::write("签名错误");
            return;
        }
        $data = $pay->verify();
        try {
            $out_trade_no = $data["out_trade_no"];
            $code = $data["result_code"];
            $order_model = new Order();
            $order = $order_model->where("out_trade_no", $out_trade_no)->find();
            $open_id = $order["open_id"];
            $wechat_model = new Weixin();
            $wechat = $wechat_model->where("open_id", $open_id)->find();
            $money = $wechat["money"];
            $wechat->money = $money + $order["paid"];
            $wechat->save();
            $order->status = $code;
            $order->flag = "+";
            $order->payment_time = time();
            $order->save();
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
        }
        echo $pay->success();
    }


    // 更新微信token
    public function update_wechatToken()
    {
        $appid = config('appid');
        $secret = config('appSecret');
        $weixin = file_get_contents("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$secret");
        $jsondecode = json_decode($weixin); //对JSON格式的字符串进行编码
        Db::name("wechat_token")->where('id', 1)->update(['token' => $jsondecode->access_token]);
        dump("更新成功");
    }

    /**
     *  array转xml
     */
    public function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }

    //使用证书，以post方式提交xml到对应的接口url
    /**
     *   作用：使用证书，以post方式提交xml到对应的接口url
     */
    function curl_post_ssl($url, $vars, $second = 30)
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //以下两种方式需选择一种
        /*         * ***** 此处必须为文件服务器根目录绝对路径 不可使用变量代替******** */
        curl_setopt($ch, CURLOPT_SSLCERT, "/usr/yun/addons/epay/certs/apiclient_cert.pem");
        curl_setopt($ch, CURLOPT_SSLKEY, "/usr/yun/addons/epay/certs/apiclient_key.pem");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);

        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            dump($data);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

    //企业向个人付款
    public function payToUser()
    {
        //微信付款到个人的接口
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $params["mch_appid"] = 'wxc937f7877bf6d2f8';   //公众账号appid
        $params["mchid"] = '1603850573';   //商户号 微信支付平台账号
        $params["nonce_str"] = 'abcdefghijklmnopqrstuvwxyz' . mt_rand(100, 999);   //随机字符串
        $params["partner_trade_no"] = mt_rand(10000000, 99999999);           //商户订单号
        $params["amount"] = 100;          //金额
        $params["desc"] = "提现";            //企业付款描述
        $params["openid"] = "oUbAt5PFXEVMXnd0BI1ky9FPDL6Q";          //用户openid
        $params["check_name"] = 'NO_CHECK';       //不检验用户姓名
        $params['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];   //获取IP
        //生成签名(签名算法后面详细介绍)
        $str = 'amount=' . $params["amount"] . '&check_name=' . $params["check_name"] . '&desc=' . $params["desc"] . '&mch_appid=' . $params["mch_appid"] . '&mchid=' . $params["mchid"] . '&nonce_str=' . $params["nonce_str"] . '&openid=' . $params["openid"] . '&partner_trade_no=' . $params["partner_trade_no"] . '&spbill_create_ip=' . $params['spbill_create_ip'] . '&key=shitoushuangshuangniubimemeda123';
        //md5加密 转换成大写
        $sign = strtoupper(md5($str));
        $params["sign"] = $sign; //签名
        $xml = $this->arrayToXml($params);
        return $this->curl_post_ssl($url, $xml);
    }

    public function push()
    {
        $wechat_token_model = new WechatToken();
        $wechat = $wechat_token_model->where("id", 1)->find();
        $access_token = $wechat["token"];
        $post_data = array(
            'touser' => "oCnfa5L4NNVtw0mMUFiskLQW8WBY",
            'template_id' => 'C1vNk1sjHu_gmKA5Kg3qc5elNc4IWaEnTt20m5xv8Dg',
            "page" => "pages/recharge/index",
            "data"=>array('amount1'=>array("value"=>0),"thing2"=>array("value"=>'余额不足'))
        );
        $jsonStr = json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=$access_token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        printf($response);
    }
}
