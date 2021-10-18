<?php

namespace app\api\controller;

use app\admin\model\Callback;
use app\admin\model\Group;
use app\admin\model\Profit;
use app\admin\model\Robots;
use app\admin\model\Source;
use app\admin\model\WechatToken;
use app\admin\model\Weixin;
use app\admin\model\YunToken;
use app\common\controller\Home;

/**
 * 其他接口
 */
class Other extends Home
{
    // 获取手机号
    public function get_phone()
    {
        if (request()->isPost()) {
            $wechat_model = new Weixin();
            $res = request()->post();
            $appid = config('appid');
            $secret = config('appSecret');
            $encryptedData = $res['encryptedData'];
            $js_code = $res['js_code'];
            $iv = $res['iv'];
            $weixin = file_get_contents("https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$js_code&grant_type=authorization_code");
            $jsondecode = json_decode($weixin); //对JSON格式的字符串进行编码
            $array = get_object_vars($jsondecode); //转换成数
            $wx = new \addons\WXBizDataCrypt($appid, $array["session_key"]);
            $errCode = $wx->decryptData($encryptedData, $iv, $data1); //微信解密函数
            if ($errCode == 0) {
                $data1 = json_decode($data1, true);
                $phone = $data1["phoneNumber"];
                $user = $wechat_model->where("open_id", $this->open_id)->find();
                $user->phone = $phone;
                $user->save();
                $this->success('请求成功', $phone);
            } else {
                $this->error('请求失败');
            }
        } else {
            $this->error('请求失败');
        }
    }

    // 获取授权二维码
    public function get_code()
    {
        $request = request();
        $data = $request->param();
        $vcCity = $data["vcCity"];
        $nRegionCode = $data["nRegionCode"];
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "nAuthorize" => 1, "vcRobotSerialNo" => "", "nRegionCode" => $nRegionCode, "vcCity" => $vcCity, "nLoginType" => 10));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/Robot/UserLogin");
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
        $vcSerialNo = $jsondecode->vcSerialNo;
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $wechat = $wechat_model->where("open_id", $open_id)->find();
        $wechat->vcSerialNo = $vcSerialNo;
        $wechat->save();
    }

    // 处理云发单登录
    public function yun_login_callback()
    {
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $vcSerialNo = $wechat_model->where("open_id", $open_id)->value('vcSerialNo');
        $callback_model = new Callback();
        $callback = $callback_model->where("vcSerialNo", $vcSerialNo)->order('id', 'desc')->find();
        if (!$callback) {
            $this->success('请求成功', "");
        }
        $content = $callback["content"];
        $nType = $callback["nType"];
        $res["nType"] = $nType;
        if ($nType == "1025") {
            $jsondecode = json_decode($content);
            $url = $jsondecode->Data->vcCodeUrl;
            $res["url"] = $url;
        } elseif ($nType == "1001") {
            $jsondecode = json_decode($content);
            $url = $jsondecode->Data->vcScanCodeUrl;
            $res["url"] = $url;
        } elseif ($nType == "1002") {
            $jsondecode = json_decode($content);
            $nQrcodeStatus = $jsondecode->Data->nQrcodeStatus;
            if ($nQrcodeStatus == 1) {
                $res["data"] = "待确认，请点击确认";
            } elseif ($nQrcodeStatus == 2) {
                $res["data"] = "确认成功，请等待5秒，长时间在此界面请联系客服";
                $vcRobotSerialNo = $jsondecode->vcRobotSerialNo;
                $res["vcRobotSerialNo"] = $vcRobotSerialNo;
            }
        } elseif ($nType == "1003") {
            $jsondecode = json_decode($content);
            $vcRobotSerialNo = $jsondecode->Data->vcRobotSerialNo;
            $res["vcRobotSerialNo"] = $vcRobotSerialNo;
            $robots_model = new Robots();
            if (!$robots_model->where("number", $vcRobotSerialNo)->find()) {
                $robots_model->open_id = $open_id;
                $robots_model->number = $vcRobotSerialNo;
                $robots_model->type = "user";
                $robots_model->save();
            } else {
                $robots = $robots_model->where("number", $vcRobotSerialNo)->find();
                $robots->status = 1;
                $robots->save();
            }
        }
        $callback->delete();
        $this->success('请求成功', $res);
        // $callback->delete();
    }

    // 获取用户群列表
    public function get_allGroup()
    {
        $request = request();
        $data = $request->param();
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $vcRobotSerialNo));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatRoom/GetChatRoomList");
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
        $data = $jsondecode->Data;
        $this->success('请求成功', $data);
    }

    // 用户选择群
    public function choose_group()
    {
        $request = request();
        $data = $request->param();
        $open_id = $this->open_id;
        $vcChatRoomSerialNo = $data["vcChatRoomSerialNo"];
        $group_model = new Group();
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $res["open_id"] = $open_id;
        $res["vcChatRoomSerialNo"] = $vcChatRoomSerialNo;
        $res["vcRobotSerialNo"] = $vcRobotSerialNo;
        $res["open_id"] = $open_id;
        $res["flag"] = "user";
        if (!$group_model->where("open_id", $open_id)->where("vcRobotSerialNo", $vcRobotSerialNo)->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->find()) {
            $group_model->allowField(true)->save($res);
        }
        $this->success('请求成功');
    }

    // 查看用户群
    public function get_userGroup()
    {
        $request = request();
        $data = $request->param();
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        $open_id = $this->open_id;
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $group_model = new Group();
        if ($vcRobotSerialNo == "平台号") {
            $group = $group_model->where("open_id", $open_id)->order("send", "desc")->where("flag", "platform")->select();
            if (count($group) == 0) {
                $res = [];
            }
            foreach ($group as $key => $value) {
                $send = $value["send"];
                $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $value['vcRobotSerialNo'], "vcChatRoomSerialNo" => $value["vcChatRoomSerialNo"]));
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatRoom/GetChatRoomList");
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
                $data = $jsondecode->Data;
                $data[0]->send = $send;
                $data[0]->vcRobotSerialNo = $value["vcRobotSerialNo"];
                $res[] = $data[0];
            }
        } else {
            $vcChatRoomSerialNo = $group_model->where("open_id", $open_id)->order("send", "desc")->where("vcRobotSerialNo", $vcRobotSerialNo)->select();
            if (count($vcChatRoomSerialNo) == 0) {
                $res = [];
            }
            foreach ($vcChatRoomSerialNo as $key => $value) {
                $vcChatRoomSerialNo = $value["vcChatRoomSerialNo"];
                $send = $value["send"];
                $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $vcRobotSerialNo, "vcChatRoomSerialNo" => $vcChatRoomSerialNo));
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/ChatRoom/GetChatRoomList");
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
                $data = $jsondecode->Data;
                $data[0]->send = $send;
                $data[0]->vcRobotSerialNo = $value["vcRobotSerialNo"];
                // dump($data);
                $res[] = $data[0];
            }
        }


        $this->success('请求成功', $res);
    }

    // 删除群
    public function del_group()
    {
        $request = request();
        $data = $request->param();
        $vcChatRoomSerialNo = $data["vcChatRoomSerialNo"];
        $open_id = $this->open_id;
        $group_model = new Group();
        $group = $group_model->where("open_id", $open_id)->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->find();
        $group->delete();
        $this->success('请求成功');
    }

    // 获取机器人信息
    public function get_robots_info()
    {
        $request = request();
        $data = $request->param();
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $robots_model = new Robots();
        $status = $robots_model->where("number", $vcRobotSerialNo)->value("status");
        $type = $robots_model->where("number", $vcRobotSerialNo)->value("type");
        $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $vcRobotSerialNo, "nPageIndex" => 1));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/Robot/MerchantRobotList");
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
        $data = $jsondecode->Data;
        if ($type == "platform") {
            $wx_id = $data->RobotList[0]->vcRobotWxId;
            $phone = $robots_model->where("wx_id", $wx_id)->value("phone");
            $data->RobotList[0]->phone = $phone;
        }
        $data->RobotList[0]->status = $status;
        $this->success('请求成功', $data);
    }

    // 获取用户机器人列表
    public function get_robots_list()
    {
        $open_id = $this->open_id;
        $robots_model = new Robots();
        $vcRobotSerialNo = $robots_model->where("open_id", $open_id)->column("number");
        $offLine = $robots_model->where("open_id", $open_id)->where("status", 0)->count();
        $yun_token_model = new YunToken();
        $token = $yun_token_model->where("id", "1")->value('token');
        if ($vcRobotSerialNo == "") {
            $res["number"] = 0;
            $res["data"] = [];
            $this->success('请求成功', $res);
        }
        $number = count($vcRobotSerialNo);
        $res["number"] = $number;
        foreach ($vcRobotSerialNo as $key => $value) {
            $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $value, "nPageIndex" => 1));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, "https://merchant.wapi.tusepaas.com:8091/api/Robot/MerchantRobotList");
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
            $data["label"] = "微信名称：" . ($jsondecode->Data->RobotList)[0]->vcNickName;
            $data["value"] = $value;
            $data["id"] = $value;
            $vcRobotSerialNo[$key] = $data;
        }
        $res["offLine"] = $offLine;
        $res["data"] = $vcRobotSerialNo;
        $this->success('请求成功', $res);
    }

    // 获取用户个人信息
    public function get_user_info()
    {
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $wechat = $wechat_model->where("open_id", $open_id)->find();
        $this->success('请求成功', $wechat);
    }

    // 获取上级微信名称
    public function get_superior_info()
    {
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $superior_invitation_code = $wechat_model->where("open_id", $open_id)->value("superior_invitation_code");
        if ($superior_invitation_code == "10086") {
            $superior_nickname = "最顶级";
        } else {
            $superior_nickname = $wechat_model->where("invitation_code", $superior_invitation_code)->value("nickname");
        }
        $this->success('请求成功', $superior_nickname);
    }

    // 获取小程序码
    public function get_wxcode()
    {
        $open_id = $this->open_id;
        $wechat_token_model = new WechatToken();
        $wechat = $wechat_token_model->where("id", 1)->find();
        $access_token = $wechat["token"];
        $wechat_model = new Weixin();
        $invitation_code = $wechat_model->where("open_id", $open_id)->value("invitation_code");
        $jsonStr = json_encode(array('scene' => $invitation_code, 'page' => "pages/inviting/index"));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=$access_token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($jsonStr)
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $path = ROOT_PATH . 'public/upload/' . date("Ymd/");
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $time = time();
        $filepath = $path . $time . ".png";
        $file = fopen($filepath, "w");
        fwrite($file, $response);
        fclose($file);
        $image_path = "https://yun.mkstone.club/upload/" . date("Ymd/") . $time . ".png";
        $this->success('请求成功', $image_path);
    }

    // 添加邀请码
    public function  add_code()
    {
        $request = request();
        $data = $request->param();
        $code = $data["code"];
        if (trim($code) == "") {
            $this->error('邀请码不能为空');
        }
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        if (!$wechat_model->where("invitation_code", $code)->find()) {
            $this->error('邀请码错误');
        }
        $wechat = $wechat_model->where("open_id", $open_id)->find();
        $wechat->superior_invitation_code = $code;
        $wechat->save();
        $this->success('添加成功');
    }

    // 获取空闲机器人
    public function get_free_robot()
    {
        $robots_model = new Robots();
        $robots = $robots_model->where("type", "platform")->where("flag", 1)->where("status", 1)->select();
        $robot = array_rand($robots, 1);
        $this->success('请求成功', $robots[$robot]);
    }

    // 获取信息源
    public function get_source()
    {
        $source_model = new Source();
        $source = $source_model->select();
        $this->success('请求成功', $source);
    }

    // 获取当前消息源
    public function get_ChatRoom_source()
    {
        $request = request();
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $wechat = $wechat_model->where("open_id", $open_id)->find();
        $data = $request->param();
        $vcChatRoomSerialNo = $data["vcChatRoomSerialNo"];
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $group_model = new Group();
        $group = $group_model->where("open_id", $open_id)->where("vcRobotSerialNo", $vcRobotSerialNo)->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->find();
        $source_id = $group["source_id"];
        if ($source_id == 0) {
            $this->success('请求成功', "");
        } else {
            $source_model = new Source();
            $source = $source_model->where("id", $source_id)->find();
            $source["img"] = $group["img"];
            $source["send"] = $group["send"];
            if ($wechat->flag == 1) {
                $source["subside"] = $group["subside"];
            }
            $this->success('请求成功', $source);
        }
    }

    // 选择消息源
    public function choose_source()
    {
        $request = request();
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $wechat = $wechat_model->where("open_id", $open_id)->find();
        $data = $request->param();
        $vcChatRoomSerialNo = $data["vcChatRoomSerialNo"];
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $robots_model = new Robots();
        $group_model = new Group();
        $type = $robots_model->where("number", $vcRobotSerialNo)->value("type");
        $send = $group_model->where("open_id", $open_id)->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->where("vcRobotSerialNo", $vcRobotSerialNo)->value("send");
        $group = $group_model->where("open_id", $open_id)->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->where("vcRobotSerialNo", $vcRobotSerialNo)->find();
        if ($group["flag"] == "platform") {
            if ($group["send"] == 2 or $group["send"] == -1) {
                $count = $group_model->where("open_id", $open_id)->where("flag", "platform")->where("send", "in", [0, 1])->count();
                if ($wechat->money <= $count) {
                    $this->error("余额不足，请充值！", "money");
                }
            }
        }
        if ($type == "platform" && $send == -1) {
            $number = $group_model->where("vcRobotSerialNo", $vcRobotSerialNo)->where("send", "<>", -1)->count();
            if ($number >= 4) {
                $this->error("当前平台号发送已达上限，请删除该群并添加其他平台号");
            }
        }
        $source_id = $data["source_id"];
        $img = $data["img"];
        $send = $data["send"];
        $group = $group_model->where("vcRobotSerialNo", $vcRobotSerialNo)->where("vcChatRoomSerialNo", $vcChatRoomSerialNo)->find();
        if ($group) {
            $group->source_id = $source_id;
            $group->img = $img;
            $group->send = $send;
            if ($wechat->flag == 1) {
                $group->subside = $data["subsidy_id"];
            }
            $group->save();
        } else {
            $res["source_id"] = $source_id;
            $res["img"] = $img;
            $res["send"] = $send;
            $res["vcChatRoomSerialNo"] = $vcChatRoomSerialNo;
            if ($wechat->flag == 1) {
                $res["subside"] = $data["subsidy_id"];
            }
            $res["vcRobotSerialNo"] = $vcRobotSerialNo;
            $res["open_id"] = $open_id;
            $res["flag"] = "user";
            $group_model->save($res);
        }
        $this->success('请求成功');
    }

    // 获取邀请人信息
    public function get_inviting_info()
    {
        $request = request();
        $data = $request->param();
        $invitation_code = $data["invitation_code"];
        $wechat_model = new Weixin();
        $weixin = $wechat_model->where('invitation_code', $invitation_code)->find();
        $this->success('请求成功', $weixin);
    }

    // 查询是否有邀请码
    public function query()
    {
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $weixin = $wechat_model->where('open_id', $open_id)->find();
        if ($weixin["superior_invitation_code"]) {
            $this->success('请求成功', true);
        } else {
            $this->success('请求成功', false);
        }
    }

    // 获取用户的下级和下下级个数
    public function get_user_subordinate()
    {
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $invitation_code = $wechat_model->where('open_id', $open_id)->value("invitation_code");
        // 下级
        if ($invitation_code == "") {
            $subordinate = 0;
            $subordinate_subordinate = 0;
        } else {
            $subordinate_code = $wechat_model->where('open_id', "<>", $open_id)->where("superior_invitation_code", $invitation_code)->column("invitation_code");
            $subordinate = $wechat_model->where("superior_invitation_code", $invitation_code)->count();
            // 下下级
            if ($subordinate_code == []) {
                $subordinate_subordinate = 0;
            } else {
                $subordinate_subordinate = $wechat_model->where("superior_invitation_code", "in", $subordinate_code)->count();
            }
        }
        $res["subordinate"] = $subordinate;
        $res["subordinate_subordinate"] = $subordinate_subordinate;
        $this->success('请求成功', $res);
    }

    // 获取用户的下级和下下级的详情
    public function get_user_subordinate_info()
    {
        $request = request();
        $data = $request->param();
        $index = $data["index"];
        $open_id = $this->open_id;
        $wechat_model = new Weixin();
        $invitation_code = $wechat_model->where('open_id', $open_id)->value("invitation_code");
        $subordinate_code = $wechat_model->where("superior_invitation_code", $invitation_code)->column("invitation_code");
        if ($index == 0) {
            if ($invitation_code == "") {
                $this->success('请求成功', []);
            }
            $subordinate_info = $wechat_model->where("superior_invitation_code", $invitation_code)->order("createtime desc")->select();
        } else {
            if ($subordinate_code == []) {
                $this->success('请求成功', []);
            }
            $subordinate_info = $wechat_model->where("superior_invitation_code", "in", $subordinate_code)->order("createtime desc")->select();
        }
        $res = $subordinate_info;
        $this->success('请求成功', $res);
    }

    // 删除掉线的用户号
    public function del_user_number()
    {
        $request = request();
        $data = $request->param();
        $group_model = new Group();
        $open_id = $this->open_id;
        $vcRobotSerialNo = $data["vcRobotSerialNo"];
        $robots_model = new Robots();
        $robot = $robots_model->where("open_id", $open_id)->where("number", $vcRobotSerialNo)->find();
        $robot->delete();
        $group = $group_model->where("open_id", $open_id)->where("vcRobotSerialNo", $vcRobotSerialNo)->select();
        if ($group) {
            $group->delete();
        }
        $this->success('请求成功');
    }

    // 判断用户是否可以新增用户号
    public function add_robot_flag()
    {
        $open_id = $this->open_id;
        $robots_model = new Robots();
        $robotNum = $robots_model->where("open_id", $open_id)->count();
        $wechat_model = new Weixin();
        $phone = $wechat_model->where("open_id", $this->open_id)->value("phone");
        $startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d' . '00:00:00', time() - 3600 * 24)));
        $endTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d' . '23:59:59', time() - 3600 * 24)));
        if ($robotNum == 0) {
            $this->success('请求成功');
        }
        if ($robotNum > 10) {
            $this->error('机器人上限为10个，不能新增！');
        }
        $profit_model = new Profit();
        $count = $profit_model->where("validCode", "in", ["16", "17"])->where("modifyTime", "between time", [$startTime, $endTime])->where("positionId", "=", $phone)->count();
        if ($count < 20) {
            $this->error('昨日收益订单量少于20，不能新增！');
        } else {
            $this->success('请求成功');
        }
    }
}
