<?php

namespace app\admin\model;

use think\Model;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'flag_text',
        'payment_time_text',
        'refund_time_text'
    ];
    

    
    public function getStatusList()
    {
        return ['SUCCESS' => __('Status success'), 'REFUND' => __('Status refund'), 'NOTPAY' => __('Status notpay'), 'CLOSED' => __('Status closed'), 'REVOKED' => __('Status revoked'), 'USERPAYING' => __('Status userpaying'), 'PAYERROR' => __('Status payerror'), 'USED' => __('Status used')];
    }

    public function getFlagList()
    {
        return ['+' => __('+'), '-' => __('-')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getFlagTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['flag']) ? $data['flag'] : '');
        $list = $this->getFlagList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPaymentTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['payment_time']) ? $data['payment_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getRefundTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['refund_time']) ? $data['refund_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaymentTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setRefundTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
