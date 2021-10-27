<?php

namespace app\home\command;

use app\admin\model\Group;
use app\admin\model\Source;
use app\admin\model\Weixin;
use app\admin\model\YunToken;
use Exception;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use WebSocket\Client;

class Test extends Command
{
    protected function configure()
    {
        $this->setName('test')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        $group_model = new Group();
        $source_model = new Source();
        $source = "ws://159.75.84.206:8860/yuntip";
        $source_id = 1;
        $client = new Client($source, ['headers' => [
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8,vi;q=0.7',
            'Cache-Control' => 'no-cache',
            'Connection' => 'Upgrade',
            'Origin' => 'http://coolaf.com',
            'Pragma' => 'no-cache',
            'Host' => '159.75.84.206:8860',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ], 'timeout' => 9999999999999]);
        $client->send("123");

        while (true) {
            try {
                $message = $client->receive();
                $res1 = "/(https?):\/\/gchat.qpic.cn[^\s]*term=2/";
                preg_match($res1, $message, $matches);
                $str = preg_replace($res1, "", $message);
                $res2 = "/(https?):\/\/[-A-Za-z0-9+&@#\/%?=~_|!:,.;]+[-A-Za-z0-d9+&@#\/%=~_|]/";
                preg_match_all($res2, $str, $url_matches);
                foreach ($url_matches[0] as $key1 => $value1) {
                    if (strpos($value1, "u.jd") == false) {
                        $link = urlencode($value1);
                        $weixin = file_get_contents("https://cloud.kuaizhan.com/api/v1/km/genShortLink?appKey=5l2m0Ig69qul&link=$link&format=json");
                        $jsondecode = json_decode($weixin); //对JSON格式的字符串进行编码
                        $array = get_object_vars($jsondecode); //转换成数组
                        if ($array["code"] == 0) {
                            $url = $array["url"];
                            $message = str_replace($value1, $url, $message);
                        }
                    }
                }
                $yun_token_model = new YunToken();
                $wechat_model = new Weixin();
                $token = $yun_token_model->where("id", "1")->value('token');
                $group = $group_model->where("source_id", $source_id)->where("send", 1)->select();
                foreach ($group as $key => $value) {
                    $change_message = $message;
                    $open_id =  $value["open_id"];
                    $phone = $wechat_model->where("open_id", $open_id)->value("phone");
                    $wx_flag = $wechat_model->where("open_id", $open_id)->value("flag");
                    $unionId = $wechat_model->where("open_id", $open_id)->value("unionId");
                    $flag = false;
                    $url_flag = false;
                    if (count($matches) > 0) {
                        $flag = true;
                    }
                    if (count($url_matches[0]) > 0) {
                        $url_flag = true;
                        foreach ($url_matches[0] as $key1 => $value1) {
                            if (strpos($value1, "u.jd") !== false) {
                                $curl  =  curl_init();
                                if ($wx_flag == 1) {
                                    $post_data = array("appkey" => "ByEwACeiKsZTd7hDY", "material_id" => $value1, "union_id" => $unionId, "position_id" => "10086");
                                } else {
                                    $post_data = array("appkey" => "ByEwACeiKsZTd7hDY", "material_id" => $value1, "union_id" => "1001695308", "position_id" => $phone);
                                }
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
                                // dump($value["vcChatRoomSerialNo"]."++++".$url);
                                if ($url != "") {
                                    dump("原message" . $message);
                                    // dump("value1".$value1);
                                    // dump("url".$url);
                                    $change_message = str_replace($value1, $url, $change_message);
                                    dump("替换后message" . $change_message);
                                }
                            }
                        }
                    }
                    $str = preg_replace($res1, "", $change_message);

                    $data = array(
                        'nMsgNum' => time(),
                        'nMsgType' => 2001,
                        'msgContent' => $str,
                        'nVoiceTime' => 0,
                        'vcHref' => "",
                        'vcTitle' => "",
                        'vcDesc' => "",
                        'nIsHit' => 0,
                        'nAtLocation' => 0,
                        'vcAtWxSerialNos' => ""
                    );
                    $img_flag = $value["img"];
                    $vcChatRoomSerialNo = $value["vcChatRoomSerialNo"];
                    $vcRobotSerialNo = $value["vcRobotSerialNo"];

                    $post_data = array(
                        'vcMerchantNo' => "202108060036284",
                        'vcRobotSerialNo' => $vcRobotSerialNo,
                        'vcRelaSerialNo' => '',
                        'vcChatRoomSerialNo' => $vcChatRoomSerialNo,
                        'Data' => array($data)
                    );
                    $jsonStr = json_encode($post_data);
                    // dump($post_data);
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

                    // dump($matches);
                    // dump("\n");

                    // 发送图片
                    if ($flag == true && $img_flag == 1) {
                        $data = array(
                            'nMsgNum' => time(),
                            'nMsgType' => 2002,
                            'msgContent' => $matches[0],
                            'nVoiceTime' => 0,
                            'vcHref' => "",
                            'vcTitle' => "",
                            'vcDesc' => "",
                            'nIsHit' => 0,
                            'nAtLocation' => 0,
                            'vcAtWxSerialNos' => ""
                        );
                        $post_data = array(
                            'vcMerchantNo' => "202108060036284",
                            'vcRobotSerialNo' => $vcRobotSerialNo,
                            'vcRelaSerialNo' => '',
                            'vcChatRoomSerialNo' => $vcChatRoomSerialNo,
                            'Data' => array($data)
                        );
                        $jsonStr = json_encode($post_data);


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
                    }

                    // 只发作业图
                    if ($flag == true && $img_flag == 2) {
                        if ($url_flag == false) {
                            $data = array(
                                'nMsgNum' => time(),
                                'nMsgType' => 2002,
                                'msgContent' => $matches[0],
                                'nVoiceTime' => 0,
                                'vcHref' => "",
                                'vcTitle' => "",
                                'vcDesc' => "",
                                'nIsHit' => 0,
                                'nAtLocation' => 0,
                                'vcAtWxSerialNos' => ""
                            );
                            $post_data = array(
                                'vcMerchantNo' => "202108060036284",
                                'vcRobotSerialNo' => $vcRobotSerialNo,
                                'vcRelaSerialNo' => '',
                                'vcChatRoomSerialNo' => $vcChatRoomSerialNo,
                                'Data' => array($data)
                            );
                            $jsonStr = json_encode($post_data);


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
                        }
                    }
                }
            } catch (Exception $e) {
                print(time());
                print $e->getMessage();
            }
        }

        $client->close();
    }
}
