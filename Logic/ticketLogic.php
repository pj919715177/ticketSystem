<?php
class ticketLogic
{
    public $errCode;
    public $errMsg;

    public function getSceneConf($scene_id, &$result)
    {
        $result = array();
        $allSceneConf = $this->getAllSceneConf();
        if (isset($allSceneConf[$scene_id]) && $allSceneConf[$scene_id]) {
            $result = $allSceneConf[$scene_id];
            return true;
        }
        return false;
    }

    public function getAllSceneConf()
    {
        return array(
            1 => array(
                'begTime' => strtotime('2018-12-30'),
                'endTime' => strtotime('2019-2-1'),
                'maxSeatId' => 7500,
                'idToPos' => 'idToPos_1',           //座位id转换成位置的方法
                'posToId' => 'posToId_1',           //位置转换成座位id的方法
            ),
        );
    }

    //初始化订单
    public function addOriginDeal($user_id, $token, $num, $price, &$deal_id)
    {
        return true;
    }

    //锁定座位
    public function lockSeat($num, $scene_id)
    {
        return true;
    }

    //设置订单状态为未支付
    public function unpayDeal($deal_id, &$deal_gen_time)
    {
        return true;
    }

    //回滚订单
    public function rollBackDeal($deal_id)
    {

    }

    //关闭无效订单
    public function closeUselessDeal()
    {
        //数据量大的话，可以考虑分页更新
        return true;
    }

    public function getUselessSeatCount($begTime, &$count)
    {
        return true;
    }

    public function getUselessSeat($begTime, &$dataList)
    {
        return true;
    }

    public function rollBackUselessSeat($dataList)
    {

    }
}