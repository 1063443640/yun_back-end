<?php

namespace app\api\controller;

use app\admin\model\Document;
use app\admin\model\Profit;
use app\admin\model\ProOrder;
use app\admin\model\Weixin;
use app\common\controller\Api;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功');
    }

    // 更新月收益
    public function update_profit()
    {
        $request = request();
        $data = $request->param();
        $sign = $data["sign"];
        $timeStamp = $data["time"];
        if (md5("gjrmkstone" . $timeStamp) == $sign) {
            $wechat_model = new Weixin();
            $weixin = $wechat_model->where("phone", "neq", "")->select();
            $time = date('Ym', strtotime(date('Y-m-20'))); //上个月的开始日期
            dump($time);
            // $time = date('Ym', strtotime('-1 month', strtotime($time)));
            $where["validCode"] = ["in", ["16", "17"]];
            foreach ($weixin as $key => $value) {
                // 个人收益
                $phone = $value["phone"];
                $profit_model = new Profit();
                $my_profit = $profit_model->where("positionId", $phone)->where($where)->where("payMonth", 'like', $time . "%")->sum("actualFee");
                $month_my_profit = $my_profit * 0.9 * 0.7;
                // dump($my_profit);
                // 下级收益
                $month_team_profit = 0;
                $invitation_code = $value["invitation_code"];
                $subordinate_phone = $wechat_model->where("superior_invitation_code", $invitation_code)->column("phone");
                // 下级邀请码
                $subordinate_code = $wechat_model->where("superior_invitation_code", $invitation_code)->column("invitation_code");
                if ($subordinate_code) {
                    $subordinate_profit = $profit_model->where("positionId", "in", $subordinate_phone)->where($where)->where("payMonth", 'like', $time . "%")->sum("actualFee");
                    $month_team_profit += $subordinate_profit * 0.9 * 0.2;
                    // 下下级收益
                    $subordinate_subordinate_phone = $wechat_model->where("superior_invitation_code", "in", $subordinate_code)->column("phone");
                    if ($subordinate_subordinate_phone) {
                        $subordinate_subordinate_profit = $profit_model->where("positionId", "in", $subordinate_subordinate_phone)->where($where)->where("payMonth", 'like', $time . "%")->sum("actualFee");
                        $month_team_profit += $subordinate_subordinate_profit * 0.9 * 0.1;
                    }
                }

                $user = $wechat_model->where("id", $value["id"])->find();
                $user->month_my_profit = $month_my_profit;
                $user->month_team_profit = $month_team_profit;
                $user->month_profit = $month_my_profit + $month_team_profit;
                $user->all_profit = $user["all_profit"] + $month_my_profit + $month_team_profit;
                $user->save();
            }
            dump("更新成功");
        } else {
            dump("失败");
        }
    }
    //获取教程列表
    public function getVideoList()
    {
        $document_model = new Document();
        $document = $document_model->order("weigh", "desc")->select();
        $this->success("获取成功", $document);
    }

    public function get_ProOrder()
    {
        $order_model = new ProOrder();
        $curl = curl_init();    //初始化一个cURL会话。
        curl_setopt($curl, CURLOPT_TIMEOUT, 100);  //设置cURL允许执行的最长秒数
        $endTime = date("Y-m-d H:i:s");
        // $endTime = "2021-08-25 21:20:34";
        $startTime = date("Y-m-d H:i:s", strtotime("$endTime-15 Minute"));
        $wechat_model = new Weixin();
        $pro = $wechat_model->where("flag", 1)->select();
        foreach ($pro as $key1 => $value1) {
            $data = ["key" => $value1["key"], "appkey" => "ByEwACeiKsZTd7hDY", "startTime" => $startTime, "endTime" => $endTime, "pageIndex" => 1, "pageSize" => 100];
            curl_setopt($curl, CURLOPT_URL, "http://api.mkstone.club/api/v1/open/jd/getOrder?" . http_build_query($data));  //URL地址
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);  //禁用后cURL将终止从服务端进行验证
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);  //不验证证书是否存在
            curl_setopt($curl, CURLOPT_HEADER, FALSE);    //禁止后使用CURL_TIMECOND_IFUNMODSINCE，默认值为CURL_TIMECOND_IFUNMODSINCE
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);  //将curl_exec()获取的信息以文件流的形式返回，而不是直接输出
            $res = curl_exec($curl);  //执行一个cURL会话
            curl_close($curl);  //关闭一个cURL会话
            $res = json_decode($res, true);
            dump($res);
            if ($res["Code"] == 1) {
                // dump("成功");
                $result = $res["Data"]["result"]["data"];
                if ($result == null) {
                    $res = ["code" => 1, "msg" => "暂无数据发生变化"];
                    return json($res);
                }
                foreach ($result as $key => $value) {
                    $result[$key]["commission"] = (int)$result[$key]["actualCosPrice"] * ($result[$key]["goodsInfo"]["owner"] == 'g' ? 1 : 0.9) * (int)$result[$key]["commissionRate"] * 0.01;
                    $result[$key]["imageUrl"] = $result[$key]["goodsInfo"]["imageUrl"];
                    $result[$key]["shopName"] = $result[$key]["goodsInfo"]["shopName"];
                    if (((int)$result[$key]["actualCosPrice"] > 9.9) && (strstr($result[$key]["goodsInfo"]["shopName"], "拼购") == false)) {
                        $result[$key]["subsidy"] = 1;
                    } else {
                        $result[$key]["subsidy"] = 0;
                    }
                    $result[$key]["owner"] = $result[$key]["goodsInfo"]["owner"];
                    $result[$key]["mainId"] = $result[$key]["id"];
                    $result[$key]["open_id"] = $value1["open_id"];
                    unset($result[$key]["id"]);
                    $info = $order_model->where("mainId", $value["id"])->find();
                    if ($info) {
                        $result[$key]["id"] = $info["id"];
                        $info->allowField(true)->data($result[$key], true)->save();
                    } else {
                        $order_model->allowField(true)->isUpdate(false)->data($result[$key], true)->save();
                    }
                }
                $data = $order_model->allowField(true)->saveAll($result);
                if ($data) {
                    $res = ["code" => 1, "msg" => "更新成功"];
                } else {
                    $res = ["code" => 0,  "msg" => "更新失败"];
                }
                return json($res);
            }
        }
    }
}
