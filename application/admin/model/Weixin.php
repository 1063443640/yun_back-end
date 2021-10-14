<?php

namespace app\admin\model;

use think\Model;


class Weixin extends Model
{





    // 表名
    protected $name = 'weixin';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'flag_text'
    ];



    public function getFlagList()
    {
        return ['1' => __('Flag 1'), '0' => __('Flag 0')];
    }


    public function getFlagTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['flag']) ? $data['flag'] : '');
        $list = $this->getFlagList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function add_openId($data)
    {
        $info = $this->allowField(true)->save($data);
        if ($info) {
            return 1;
        } else {
            return "添加失败";
        }
    }

    public function update_userinfo($data)
    {
        $info = $this->where('open_id', $data["open_id"])->find();
        // while (true) {
        //     $invitation_code = mt_rand(10000, 99999);
        //     if (!$this->where("invitation_code", $invitation_code)->find()) {
        //         break;
        //     }
        // }
        // $info->invitation_code = $invitation_code;
        // $info->identification_code = $invitation_code;
        $info->nickname = $data["nickname"];
        $info->image = $data["image"];
        $flag = $info->save();
        if ($flag) {
            return 1;
        } else {
            return "更新失败";
        }
    }
}
