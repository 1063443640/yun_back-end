<?php

namespace app\admin\model;

use think\Model;


class Robots extends Model
{

    

    

    // 表名
    protected $name = 'robots';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        "vcNickName",
        "vcHeadImgUrl"
    ];
    
    public function getVcNickNameAttr($value, $data)
    {
        return  '';
    }
    
    public function getVcHeadImgUrlAttr($value, $data)
    {
        return  '';
    }






}
