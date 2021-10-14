<?php

namespace app\admin\controller;

use app\admin\model\YunToken;
use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Robots extends Backend
{

    /**
     * Robots模型对象
     * @var \app\admin\model\Robots
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Robots;
    }

    public function import()
    {
        parent::import();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model

                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                $row->visible(['id', 'open_id', 'number', 'type', 'status', 'flag', 'wx_id', 'phone', 'remark']);
            }
            $items = $list->items();
            $yun_token_model = new YunToken();
            $token = $yun_token_model->where("id", "1")->value('token');
            foreach ($items as $key => &$value) {
                $jsonStr = json_encode(array('vcMerchantNo' => "202108060036284", "vcRobotSerialNo" => $value["number"], "nPageIndex" => 1));
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
                $items[$key]["vcHeadImgUrl"]=$data->RobotList[0]->vcHeadImgUrl;
                $items[$key]["vcNickName"]=$data->RobotList[0]->vcNickName;
            }
            
            $result = array("total" => $list->total(), "rows" => $items);
            return json($result);
        }
        return $this->view->fetch();
    }
}
