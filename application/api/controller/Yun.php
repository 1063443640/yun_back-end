<?php

namespace app\api\controller;

use app\admin\model\Callback;
use app\admin\model\Group;
use app\admin\model\Order;
use app\admin\model\Robots;
use app\admin\model\WechatToken;
use app\admin\model\Weixin;
use app\common\controller\Api;
use think\Db;
use app\admin\model\YunToken;
use fast\Date;

/**
 * 云发单接口
 */
class Yun extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    // 更新云发单token
    public function update_yunToken()
    {
        $post_data = array(
            'merchant' => '202108060036284',
            'secret' => '4c39e27f4bf44ac19d98118fef68afe7'
        );
        $postdata = http_build_query($post_data);

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）

            )

        );
        $context = stream_context_create($options);
        $result = file_get_contents("https://merchant.auth.tusepaas.com:8091/api/oauth/get_token", false, $context);
        $result = json_decode($result);
        Db::name("yun_token")->where('id', 1)->update(['token' => $result->token]);
        dump("更新成功");
    }

    // 回调接口
    public function cloud_api()
    {
        $request = request();
        $data = $request->param();
        $strContext = $data["strContext"];
        $strContext = htmlspecialchars_decode($data["strContext"]);
        $jsondecode = json_decode($strContext);
        $wechat_model = new Weixin();
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        $callback_model = new Callback();
        $callback_model->save([
            'content'  =>  $strContext,
            'nType' => $jsondecode->nType,
            'vcSerialNo'  =>  $jsondecode->vcSerialNo,
        ]);
        // 退出登录
        if ($jsondecode->nType == "1005") {
            $content = $jsondecode;
            $vcRobotSerialNo = $content->vcRobotSerialNo;
            $robots_model = new Robots();
            $robots = $robots_model->where("number", $vcRobotSerialNo)->find();
            $robots->status = 0;
            $robots->save();
            // 获取好友请求接口
        } elseif ($jsondecode->nType == "1003") {
            // 重新登录
            $content = $jsondecode;
            $vcRobotSerialNo = $content->vcRobotSerialNo;
            $robots_model = new Robots();
            $robots = $robots_model->where("number", $vcRobotSerialNo)->find();
            $robots->status = 1;
            $robots->save();
        } else if ($jsondecode->nType == "3003") {
            $content = $jsondecode;
            $vcRobotSerialNo = $content->vcRobotSerialNo;
            $Data = $content->Data;
            $vcContent = $Data->vcContent;
            $wechat = $wechat_model->where("identification_code", $vcContent)->find();
            if ($wechat) {
                $vcNewFriendWxId = $Data->vcNewFriendWxId;
                $vcSerialNo = $content->vcSerialNo;
                $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $vcRobotSerialNo, "vcSerialNo" => $vcSerialNo));
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/Friend/AcceptNewFriendRequest");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'merchantNo:202108060036284',
                    "token:$token",
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($jsonStr)
                ));
                $response = curl_exec($ch);
                curl_close($ch);
                $wechat->number = $vcNewFriendWxId;
                $nickname = $wechat["nickname"];
                $invitation_code = $wechat["invitation_code"];
                $wechat->save();
                // $res = json_decode($response);
            }
        }
        // 【直接回调】 通过好友请求回调接口（兼容PC）
        // else if ($jsondecode->nType == "3011") {
        //     $content = $jsondecode;
        //     $vcRobotSerialNo = $jsondecode->vcRobotSerialNo;
        //     $Data = $content->Data;
        //     $vcFriendSerialNo = $Data->vcFriendSerialNo;
        //     $vcNewFriendWxId = $Data->vcNewFriendWxId;
        //     $wechat = $wechat_model->where("number", $vcNewFriendWxId)->find();
        //     $nickname = $wechat["nickname"];
        //     $invitation_code = $wechat["invitation_code"];
        //     // 发送消息给用户

        // } 
        // 机器人被加/主动加好友（成为好友）回调接口
        else if ($jsondecode->nType == "3005") {
            $content = $jsondecode;
            $Data = $content->Data;
            $vcRobotSerialNo = $content->vcRobotSerialNo;
            $vcFriendSerialNo = $Data[0]->vcFriendSerialNo;
            $vcNewFriendWxId = $Data[0]->vcFriendWxId;
            $wechat = $wechat_model->where("number", $vcNewFriendWxId)->find();
            $nickname = $wechat["nickname"];
            $invitation_code = $wechat["invitation_code"];
            // 发送消息给用户
            $post_data = array(
                'vcMerchantNo' => "202108060036284",
                'vcRobotSerialNo' => $vcRobotSerialNo,
                'vcRelaSerialNo' => '',
                'vcToWxSerialNo' => $vcFriendSerialNo,
                'Data' => array(array(
                    'nMsgNum' => 1,
                    'nMsgType' => 2001,
                    'msgContent' => "Hi，$nickname ，你已成功绑定，你的邀请码是$invitation_code ，请邀请我进群", //Hi，$nickname ，你已成功绑定，你的邀请码是$invitation_code ，请邀请我进群
                    'nVoiceTime' => 0,
                    'vcHref' => "",
                    'vcTitle' => "",
                    'vcDesc' => ""
                ))
            );
            $jsonStr = json_encode($post_data);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatMessages/SendPrivateChatMessages");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'merchantNo:202108060036284',
                "token:$token",
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            ));
            $response = curl_exec($ch);
            curl_close($ch);

            $data = ['content' => $response];
            Db::name('text')->insert($data);
        }
        // 用户被邀请入群
        else if ($jsondecode->nType == "4505") {
            $content = $jsondecode;
            $Data = $content->Data;
            $group_model = new Group();
            $vcRobotSerialNo = $content->vcRobotSerialNo;
            $wechatId = $Data->vcFriendWxId;
            $vcFriendSerialNo = $Data->vcFriendSerialNo;
            $vcChatRoomSerialNo = $Data->vcChatRoomSerialNo;
            $vcChatRoomName = $Data->vcChatRoomName;
            if (!$group_model->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->find()) {
                $open_id = $wechat_model->where("number", $wechatId)->value("open_id");
                $res["vcChatRoomSerialNo"] = $vcChatRoomSerialNo;
                $res["vcRobotSerialNo"] = $vcRobotSerialNo;
                $res["open_id"] = $open_id;
                $res["flag"] = "platform";
                $group_model->allowField(true)->save($res);
            }
            // usleep(3000000);

            // 发送消息给用户
            $post_data = array(
                'vcMerchantNo' => "202108060036284",
                'vcRobotSerialNo' => $vcRobotSerialNo,
                'vcRelaSerialNo' => '',
                'vcToWxSerialNo' => $vcFriendSerialNo,
                'Data' => array(array(
                    'nMsgNum' => 1,
                    'nMsgType' => 2001,
                    'msgContent' => "我已进入“$vcChatRoomName ”，请返回小程序进行下一步操作",
                    'nVoiceTime' => 0,
                    'vcHref' => "",
                    'vcTitle' => "",
                    'vcDesc' => ""
                ))
            );
            $jsonStr = json_encode($post_data);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatMessages/SendPrivateChatMessages");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'merchantNo:202108060036284',
                "token:$token",
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            ));
            $response = curl_exec($ch);
            curl_close($ch);
            $data = ['content' => $vcRobotSerialNo . "---" . $vcFriendSerialNo];
            Db::name('text')->insert($data);
        }
        // 机器人收到用户发送的入群邀请
        elseif ($jsondecode->nType == "4506") {
            $content = $jsondecode;
            $vcSerialNo = $content->vcSerialNo;
            $vcRobotSerialNo = $content->vcRobotSerialNo;
            $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $vcRobotSerialNo, "vcSerialNo" => $vcSerialNo));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatRoom/RobotPullGroupAdopt");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'merchantNo:202108060036284',
                "token:$token",
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            ));
            $response = curl_exec($ch);
            curl_close($ch);
        }
        // 机器人被踢出群聊
        elseif ($jsondecode->nType == "4507") {
            $content = $jsondecode;
            $Data = $content->Data;
            $vcChatRoomSerialNo = $Data->vcChatRoomSerialNo;
            $group_model = new Group();
            $group = $group_model->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->find();
            $group->delete();
        }
        echo "success";
    }

    // TYPE 1025 处理登录二维码接口 
    public function type_1025()
    {
        $request = request();
        $data = $request->param();
        $vcSerialNo = $data["vcSerialNo"];
        $callback_model = new Callback();
        $content = $callback_model->where("vcSerialNo", $vcSerialNo)->where('nType', 1025)->find();
        $jsondecode = json_decode($content["content"]);
        $url = $jsondecode->Data->vcCodeUrl;
        dump($url);
    }

    // 发送群消息
    public function send_group_news()
    {
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        $post_data = array(
            'vcMerchantNo' => "202108060036284",
            'vcRobotSerialNo' => 'E439B09FC7A59062DCA3DB71F27BCADC',
            'vcRelaSerialNo' => '',
            'vcChatRoomSerialNo' => '9D46F7F17184A33BA4A184DE5F8B775B',
            'Data' => array(array(
                'nMsgNum' => 1,
                'nMsgType' => 2001,
                'msgContent' => "测试测试",
                'nVoiceTime' => 0,
                'vcHref' => "",
                'vcTitle' => "",
                'vcDesc' => "",
                'nIsHit' => 0,
                'nAtLocation' => 0,
                'vcAtWxSerialNos' => ""
            ))
        );
        $jsonStr = json_encode($post_data);
        print($jsonStr);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatMessages/SendGroupChatMessages");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'merchantNo:202108060036284',
            "token:$token",
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($jsonStr)
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $jsondecode = json_decode($response);
        dump($jsondecode);
    }

    // 更新机器人状态
    public function update_robots_status()
    {
        $robots_model = new Robots();
        $robots = $robots_model->where("type", "platform")->select();
        $group_model = new Group();
        foreach ($robots as $key => $value) {
            $number = $group_model->where("vcRobotSerialNo", $value["number"])->where("send", "<>", -1)->count();
            $robot = $robots_model->where("number", $value["number"])->find();
            if ($number >= 4) {
                $robot->flag = 0;
                $robot->save();
            } else {
                $robot->flag = 1;
                $robot->save();
            }
        }
        dump("更新成功");
    }

    // 踢出欠费三天的平台群
    public function kick_out()
    {
        $group_model = new Group();
        $group = $group_model->where("flag", "platform")->where("arrears_time", "<>", 0)->select();
        foreach ($group as $key => $value) {
            $arrears_time = $value["arrears_time"];
            $time = time();
            $day = ($time - $arrears_time) / 86400;
            if ($day > 3) {
                $group_id = $value["id"];
                $group = $group_model->where("id", $group_id)->find();
                $group->delete();
                dump("踢出成功");
            }
        }
    }

    // 平台群收费
    public function charge()
    {
        $group_model = new Group();
        $time = time();
        $group = $group_model->where("flag", "platform")->where("send", "in", [0, 1])->select();
        foreach ($group as $key => $value) {
            $open_id = $value["open_id"];
            $update_time = $value["updatetime"];
            $day = ($time - $update_time) / 86400;
            $wechat_model = new Weixin();
            $wechat = $wechat_model->where("open_id", $open_id)->find();
            $group = $group_model->where("id", $value["id"])->find();
            $count = $group->where("open_id", $open_id)->where("flag", "platform")->where("send", "in", [0, 1])->count();
            if ($wechat->money < $count) {
                $group->send = 2;
                $group->arrears_time = $time;
                $group->save();
                $wechat_token_model = new WechatToken();
                $wechat = $wechat_token_model->where("id", 1)->find();
                $access_token = $wechat["token"];
                $post_data = array(
                    'touser' => $open_id,
                    'template_id' => 'C1vNk1sjHu_gmKA5Kg3qc5elNc4IWaEnTt20m5xv8Dg',
                    "page" => "pages/recharge/index",
                    "data" => array('amount1' => array("value" => $wechat->money), "thing2" => array("value" => '余额不足,请充值！'))
                );
                $jsonStr = json_encode($post_data);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=$access_token");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
            }
            if ($day > 1) {
                $group->day = $group["day"] + 1;
                $group->save();
                $wechat->money = $wechat["money"] - 1;
                $wechat->save();
                $order_model = new Order();
                $order_model->paid = 1;
                $order_model->content = "平台群扣费1元";
                $order_model->status = "USED";
                $order_model->flag = "-";
                $order_model->open_id = $open_id;
                $order_model->save();
            }
        }
        dump("扣费成功");
    }

    public function test()
    {
        // $link = urlencode("https://coupon.m.jd.com/coupons/show.action?key=7efb2d610c04446781429a976b59eeaa&roleId=58474098");
        // $weixin = file_get_contents("https://cloud.kuaizhan.com/api/v1/km/genShortLink?appKey=5l2m0Ig69qul&link=$link&format=json");
        // $jsondecode = json_decode($weixin); //对JSON格式的字符串进行编码
        // $array = get_object_vars($jsondecode); //转换成数组
        // if($array["code"]==0){
        //     $url = $array["url"];
        // }
        // dump($url);
        $curl  =  curl_init();
        $post_data = array("appkey" => "ByEwACeiKsZTd7hDY", "material_id" => "https://u.jd.com/rLiIvXv", "union_id" => "1001695308", "position_id" => "3003051141");
        $url = "http://api.mkstone.club/api/v1/open/jd/getPromotion";
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,  1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST,  1);
        // curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        curl_setopt($curl, CURLOPT_POSTFIELDS,  http_build_query($post_data));
        //执行命令
        $data  =  curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        $url = json_decode($data)->Data;
        dump($url);
    }

    public function send()
    {
        $message = "【12点】黑人星耀白美白牙膏 
        https://u.jd.com/1LpDwHy 
        限量180件9.90亓 
        吴大嫂 东北水饺 鲅鱼馅800g40只 
        https://u.jd.com/1tpYPzT 
        限量1400件23.40亓";
        $res1 = "/(https?):\/\/gchat.qpic.cn[^\s]*term=2/";
        preg_match($res1, $message, $matches);
        $str = preg_replace($res1, "", $message);
        $res2 = "/(https?):\/\/[-A-Za-z0-9+&@#\/%?=~_|!:,.;]+[-A-Za-z0-d9+&@#\/%=~_|]/";
        preg_match_all($res2, $str, $url_matches);
        // dump($matches);
        dump($url_matches);
        foreach ($url_matches[0] as $key => $value) {
            $curl  =  curl_init();
            $post_data = array("appkey" => "ByEwACeiKsZTd7hDY", "material_id" => $value, "union_id" => "1001695308", "position_id" => 1234);
            $url = "http://api.mkstone.club/api/v1/open/jd/getPromotion";
            curl_setopt($curl, CURLOPT_URL, $url);
            //设置获取的信息以文件流的形式返回，而不是直接输出。
            curl_setopt($curl, CURLOPT_RETURNTRANSFER,  1);
            //设置post方式提交
            curl_setopt($curl, CURLOPT_POST,  1);
            // curl_setopt($curl, CURLOPT_COOKIE, $cookie);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  http_build_query($post_data));
            //执行命令
            $data  =  curl_exec($curl);
            //关闭URL请求
            curl_close($curl);
            $url = json_decode($data)->Data;
            $message = str_replace($value, $url, $message);
        }
        dump($message);
    }

    public function send_wxFriend()
    {
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');


        $post_data = array(
            'vcMerchantNo' => "202108060036284",
            'vcRobotSerialNo' => '2BDA52042129B147C16119DA73C09106',
        );
        $jsonStr = json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/Friend/GetFriendList");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'merchantNo:202108060036284',
            "token:$token",
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($jsonStr)
        ));
        $response = curl_exec($ch);
        printf($response);


        // $post_data = array(
        //     'vcMerchantNo' => "202108060036284",
        //     'vcRobotSerialNo' => '2BDA52042129B147C16119DA73C09106',
        //     'vcRelaSerialNo' => '',
        //     'vcToWxSerialNo' => strtoupper(md5("wxid_vf1gt0ktsz1f22")),
        //     'Data' => array(array(
        //         'nMsgNum' => 1,
        //         'nMsgType' => 2001,
        //         'msgContent' => "1234",
        //         'nVoiceTime' => 0,
        //         'vcHref' => "",
        //         'vcTitle' => "",
        //         'vcDesc' => ""
        //     ))
        // );
        // $jsonStr = json_encode($post_data);
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_POST, 1);
        // curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatMessages/SendPrivateChatMessages");
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        //     'merchantNo:202108060036284',
        //     "token:$token",
        //     'Content-Type: application/json; charset=utf-8',
        //     'Content-Length: ' . strlen($jsonStr)
        // ));
        // $response = curl_exec($ch);
        // dump($response);
    }

    public function del()
    {
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');

        $post_data = array(
            'vcMerchantNo' => "202108060036284",
            'vcRobotSerialNo' => '2BDA52042129B147C16119DA73C09106',
            "vcContactSerialNo" => "B60E0C0B334A1FE1AF77298204232A1B"
        );
        $jsonStr = json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/Friend/DeleteContact");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'merchantNo:202108060036284',
            "token:$token",
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($jsonStr)
        ));
        $response = curl_exec($ch);
        printf($response);
    }
}
