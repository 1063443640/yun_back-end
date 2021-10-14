<?php

namespace app\api\controller;

use app\admin\model\Document;
use app\admin\model\Profit;
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
        $document_model= new Document();
        $document=$document_model->order("weigh","desc")->select();
        $this->success("获取成功", $document);
    }
}
