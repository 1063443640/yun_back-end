<?php

namespace app\api\controller;

use app\admin\model\Order as ModelOrder;
use app\admin\model\Profit;
use app\admin\model\ProOrder;
use app\admin\model\Weixin;
use app\common\controller\Home;

/**
 * 商品接口
 */
class Order extends Home
{
    // 提交订单
    public function index()
    {
        $order_model = new ModelOrder();
        $res = request()->post();
        $data["out_trade_no"] = rand(100000, 999999) + time();
        $data["paid"] = $res["amount"];
        $data["status"] = "NOTPAY";
        $amount = $res["amount"];
        $data["content"] = "充值 $amount 元";
        $data["open_id"] = $this->open_id;
        $data1 = $order_model->allowField(true)->save($data);
        $params = [
            'amount' => $res["amount"],
            'orderid' => $data["out_trade_no"],
            'type' => "wechat",
            'title' => "商品订单",
            'notifyurl' => "https://yun.mkstone.club/index.php/api/Wechatapi/notifyx",
            'returnurl' => "https://yun.mkstone.club/index.php/api/Wechatapi/returnx",
            'method' => "miniapp",
            'openid' => $this->open_id,
            'auth_code' => "验证码"
        ];
        $result =  \addons\epay\library\Service::submitOrder($params);

        if ($result) {
            $this->success("提交订单成功", $result);
        } else {
            $this->error("提交订单失败");
        }
    }

    public function get_order()
    {
        $open_id = $this->open_id;
        $order_model = new ModelOrder();
        $order = $order_model->where("open_id", $open_id)->where("status", "NEQ", "NOTPAY")->select();
        $this->success("查询成功", $order);
    }

    // 查询收益订单
    public function get_profit_list()
    {
        $day_startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d' . '00:00:00', time())));
        $endTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', time())));
        $month_startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-01'))); //这个月的开始日期
        $res = request()->post();
        $index = $res["index"];
        $current_page = $res["current_page"];
        $page_size = 5;
        $wechat_model = new Weixin();
        $phone = $wechat_model->where("open_id", $this->open_id)->value("phone");
        $flag = $wechat_model->where("open_id", $this->open_id)->value("flag");
        if ($flag == 0) {
            $where["positionId"] = ["=", $phone];
            $profit_model = new Profit();
        }else{
            $where["open_id"] = ["=", $this->open_id];
            $profit_model = new ProOrder();
        }
        if ($index == 0) {
            $profit = $profit_model->where($where)->order("orderTime", "desc")->page($current_page, $page_size)->select();
            $day_count = $profit_model->where("orderTime", "between time", [$day_startTime, $endTime])->where($where)->count();
            $month_count = $profit_model->where("orderTime", "between time", [$month_startTime, $endTime])->where($where)->count();
        } elseif ($index == 1) {
            $profit = $profit_model->where($where)->order("orderTime", "desc")->page($current_page, $page_size)->where("validCode", "in", ["16", "17"])->select();
            $day_count = $profit_model->where("validCode", "in", ["16", "17"])->where("orderTime", "between time", [$day_startTime, $endTime])->where($where)->count();
            $month_count = $profit_model->where("validCode", "in", ["16", "17"])->where("orderTime", "between time", [$month_startTime, $endTime])->where($where)->count();
        } else {
            $profit = $profit_model->where($where)->order("orderTime", "desc")->page($current_page, $page_size)->where("validCode", "not in", ["16", "17"])->select();
            $day_count = $profit_model->where("validCode", "not in", ["16", "17"])->where("orderTime", "between time", [$day_startTime, $endTime])->where($where)->count();
            $month_count = $profit_model->where("validCode", "not in", ["16", "17"])->where("orderTime", "between time", [$month_startTime, $endTime])->where($where)->count();
        }
        foreach ($profit as $key => $value) {
            // pro版本用
            $value["estimateFee1"] = $value["estimateFee"];
            $value["estimateFee"] = $value["estimateFee"] * 0.9 * 0.7;
            $profit[$key] = $value;
        }
        $res["profit"] = $profit;
        $res["day_count"] = $day_count;
        $res["month_count"] = $month_count;
        $this->success("查询成功", $res);
    }

    // 查询团队收益订单
    public function get_team_profit_list()
    {
        $day_startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d' . '00:00:00', time())));
        $endTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', time())));
        $month_startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-01'))); //这个月的开始日期
        $res = request()->post();
        $index = $res["index"];
        $current_page = $res["current_page"];
        if ($index == 0) {
            $where["id"] = ["neq", ""];
        } elseif ($index == 1) {
            $where["validCode"] = ["in", ["16", "17"]];
        } else {
            $where["validCode"] = ["not in", ["16", "17"]];
        }
        $page_size = 5;
        $wechat_model = new Weixin();
        $profit_model = new Profit();
        $invitation_code = $wechat_model->where("open_id", $this->open_id)->value("invitation_code");
        $subordinate_phone = $wechat_model->where("superior_invitation_code", $invitation_code)->column("phone");
        $subordinate_code = $wechat_model->where("superior_invitation_code", $invitation_code)->column("invitation_code");
        // dump($subordinate_code);
        $list = [];
        $day_count = 0;
        $month_count = 0;
        if ($subordinate_code != []) {
            $subordinate_profit = $profit_model->order("orderTime", "desc")->where($where)->page($current_page, $page_size)->where("positionId", "in", $subordinate_phone)->select();
            $day_count += $profit_model->where("orderTime", "between time", [$day_startTime, $endTime])->where("positionId", "in", $subordinate_phone)->where($where)->count();
            $month_count += $profit_model->where("orderTime", "between time", [$month_startTime, $endTime])->where("positionId", "in", $subordinate_phone)->where($where)->count();
            foreach ($subordinate_profit as $key => $value) {
                $value["estimateFee"] = $value["estimateFee"] * 0.9 * 0.2;
                array_push($list, $value);
            }
            // 下下级收益
            $subordinate_subordinate_phone = $wechat_model->where("superior_invitation_code", "in", $subordinate_code)->column("phone");
            if ($subordinate_subordinate_phone) {
                $subordinate_subordinate_profit = $profit_model->order("orderTime", "desc")->where($where)->page($current_page, $page_size)->where("positionId", "in", $subordinate_subordinate_phone)->select();
                $day_count += $profit_model->where("orderTime", "between time", [$day_startTime, $endTime])->where("positionId", "in", $subordinate_subordinate_phone)->where($where)->count();
                $month_count += $profit_model->where("orderTime", "between time", [$month_startTime, $endTime])->where("positionId", "in", $subordinate_subordinate_phone)->where($where)->count();
                foreach ($subordinate_subordinate_profit as $key1 => $value1) {
                    $value1["estimateFee"] = $value1["estimateFee"] * 0.9 * 0.1;
                    array_push($list, $value1);
                }
            }
        }
        $res["list"] = $list;
        $res["day_count"] = $day_count;
        $res["month_count"] = $month_count;
        $this->success("查询成功", $res);
    }

    public function get_my_stimate()
    {
        $startTime = date("Y-m-d");
        $endTime = date("Y-m-d", strtotime("+1 day"));
        $m = date('Y-m-d', strtotime(date('Y-m-01'))); //上个月的开始日期
        $time = date('Y-m-d', strtotime('+1 month', strtotime($m)));
        $wechat_model = new Weixin();
        $phone = $wechat_model->where("open_id", $this->open_id)->value("phone");
        $month_my_profit = $wechat_model->where("open_id", $this->open_id)->value("month_my_profit");
        $month_team_profit = $wechat_model->where("open_id", $this->open_id)->value("month_team_profit");
        $all_profit = $wechat_model->where("open_id", $this->open_id)->value("all_profit");
        $withdrawal_amount = $wechat_model->where("open_id", $this->open_id)->value("withdrawal_amount");
        $month_profit = $wechat_model->where("open_id", $this->open_id)->value("month_profit");
        // $where["positionId"] = ['=', $phone];
        $where["validCode"] = ["in", ["16", "17"]];
        $profit_model = new Profit();
        $day_effective_order = $profit_model->where("positionId", '=', $phone)->where($where)->where("orderTime", "between time", [$startTime, $endTime])->sum("estimateFee");
        $month_effective_order = $profit_model->where("positionId", '=', $phone)->where($where)->where("orderTime", "between time", [$m, $time])->sum("estimateFee");
        $day = $day_effective_order * 0.9 * 0.7;
        $month = $month_effective_order * 0.9 * 0.7;
        $res["day"] = $day;
        $res["month"] = $month;
        $invitation_code = $wechat_model->where("open_id", $this->open_id)->value("invitation_code");
        $subordinate_code = $wechat_model->where("superior_invitation_code", $invitation_code)->column("invitation_code");
        $subordinate_phone = $wechat_model->where("superior_invitation_code", $invitation_code)->column("phone");
        $team_day = 0;
        $team_month = 0;
        if ($subordinate_code != []) {
            $day_team_effective_order = $profit_model->where("positionId", "in", $subordinate_phone)->where($where)->where("orderTime", "between time", [$startTime, $endTime])->sum("estimateFee");

            $month_team_effective_order = $profit_model->where("positionId", "in", $subordinate_phone)->where($where)->where("orderTime", "between time", [$m, $time])->sum("estimateFee");
            // dump($subordinate_profit);
            $team_day += $day_team_effective_order * 0.9 * 0.2;
            $team_month += $month_team_effective_order * 0.9 * 0.2;
            // 下下级收益
            $subordinate_subordinate_phone = $wechat_model->where("superior_invitation_code", "in", $subordinate_code)->column("phone");
            if ($subordinate_subordinate_phone) {
                $day_team_effective_order = $profit_model->where($where)->where("positionId", "in", $subordinate_subordinate_phone)->where("orderTime", "between time", [$startTime, $endTime])->sum("estimateFee");

                $month_team_effective_order = $profit_model->where($where)->where("positionId", "in", $subordinate_subordinate_phone)->where("orderTime", "between time", [$m, $time])->sum("estimateFee");
                $team_day += $day_team_effective_order * 0.9 * 0.1;
                $team_month += $month_team_effective_order * 0.9 * 0.1;
            }
        }
        $res["team_day"] = $team_day;
        $res["team_month"] = $team_month;
        $res["month_my_profit"] = $month_my_profit;
        $res["month_team_profit"] = $month_team_profit;
        $res["month_profit"] = $month_profit;
        $res["all_profit"] = $all_profit;
        $res["withdrawal_amount"] = $withdrawal_amount;
        $this->success("查询成功", $res);
    }

    public function withdrawal()
    {
        $res = request()->post();
        $amount = $res["amount"];
        $wechat_model = new Weixin();
        $all_profit = $wechat_model->where("open_id", $this->open_id)->value("all_profit");
        $withdrawal_amount = $wechat_model->where("open_id", $this->open_id)->value("withdrawal_amount");
        $money = (float)$all_profit - (float)$withdrawal_amount;
        if ($amount > $money) {
            $this->error("提现金额大于可提现金额");
        } else {
            //微信付款到个人的接口
            $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
            $params["mch_appid"] = 'wxc937f7877bf6d2f8';   //公众账号appid
            $params["mchid"] = '1603850573';   //商户号 微信支付平台账号
            $params["nonce_str"] = 'abcdefghijklmnopqrstuvwxyz' . mt_rand(100, 999);   //随机字符串
            $params["partner_trade_no"] = mt_rand(10000000, 99999999);           //商户订单号
            $params["amount"] = $amount * 100;          //金额
            $params["desc"] = "提现 $amount 元";            //企业付款描述
            $params["openid"] = $this->open_id;          //用户openid
            $params["check_name"] = 'NO_CHECK';       //不检验用户姓名
            $params['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];   //获取IP
            //生成签名(签名算法后面详细介绍)
            $str = 'amount=' . $params["amount"] . '&check_name=' . $params["check_name"] . '&desc=' . $params["desc"] . '&mch_appid=' . $params["mch_appid"] . '&mchid=' . $params["mchid"] . '&nonce_str=' . $params["nonce_str"] . '&openid=' . $params["openid"] . '&partner_trade_no=' . $params["partner_trade_no"] . '&spbill_create_ip=' . $params['spbill_create_ip'] . '&key=shitoushuangshuangniubimemeda123';
            //md5加密 转换成大写
            $sign = strtoupper(md5($str));
            $params["sign"] = $sign; //签名
            $xml = $this->arrayToXml($params);
            $flag = $this->curl_post_ssl($url, $xml);
            if ($flag) {
                $weixin = $wechat_model->where("open_id", $this->open_id)->find();
                $weixin->withdrawal_amount = $weixin["withdrawal_amount"] += $amount;
                $weixin->save();
                $this->success("提现成功");
            }
        }
    }



    // 提现流程
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
            return true;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return false;
        }
    }
}
