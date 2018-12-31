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
                'maxSeatId' => 30,
                'idToPos' => 'idToPos_1',           //座位id转换成位置的方法
                'posToId' => 'posToId_1',           //位置转换成座位id的方法
            ),
        );
    }

    //初始化订单
    public function addOriginDeal($user_id, $token, $price, &$deal_id, &$deal_gen_time)
    {
        $ret = $this->dealIdMaker($deal_id);
        if (!$ret) {
            $this->errCode = 20000;
            $this->errMsg = "订单id生成失败";
            return false;
        }
        $deal_gen_time = time();
        $data = array(
            'deal_id' => $deal_id,
            'token' => $token,
            'uid' => $user_id,
            'price' => $price,
            'state' => 0,
            'deal_gen_time' => $deal_gen_time,
        );
        $dealModel = new dealModel();
        $ret = $dealModel->insertData($data);
        if ($ret === false) {
            $this->errCode = 30000;
            $this->errMsg = "数据库错误";
            return false;
        }
        return true;
    }

    //锁定座位
    public function lockSeat($num, $scene_id, $deal_id)
    {
        if ($num < 1 || $num > 5) {
            $this->errCode = 20000;
            $this->errMsg = "只能购买不超过5个座位";
            return false;
        }

        $listName = getConf('seatListName') . "_{$scene_id}";
        $ticketInfoModel = new ticketInfoModel();
        for ($index = 0; $index < $num;  $index++) {
            $seatId = redisLib::subscripe($listName);
            if ($seatId === false) {
                $this->errCode = 20000;
                $this->errMsg = "库存不足";
                return false;
            }
            $addData = array('seat_id' => $seatId, 'scene_id' => $scene_id, 'bussiness_id' => $deal_id, 'add_time' => time());
            $ret = $ticketInfoModel->insertData($addData);
            if ($ret === false) {
                $this->errCode = 20001;
                $this->errMsg = "服务器错误";
                return false;
            }
        }
        return true;
    }

    //设置订单状态为未支付
    public function unpayDeal($deal_id)
    {
        $where = "deal_id={$deal_id} and state=0";
        $state = 2;
        $dealModel = new dealModel();
        $ret = $dealModel->changeState($where, $state);
        if ($ret === false) {
            $this->errCode = $dealModel->errCode;
            $this->errMsg = $dealModel->errMsg;
            return false;
        }
        return true;
    }

    //回滚订单
    public function rollBackDeal($deal_id)
    {
        $ret = $this->rollBackSeat($deal_id);
        if (!$ret) {
            return false;
        }
        //删除订单信息
        $sql = "delete from deal where deal={$deal_id}";
        $dealModel = new dealModel();
        $ret = $dealModel->execute($sql);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        return true;
    }

    public function rollBackSeat($deal_id)
    {
        //查询回退订单的锁定座位
        $select = "*";
        $where = "bussiness_id={$deal_id}";
        $ticketInfoModel = new ticketInfoModel();
        $dateList= $ticketInfoModel->getDataList($select, $where);
        if ($dateList === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        //删除锁定座位
        $sql = "delete from ticket_info where bussiness_id=$deal_id";
        $ret = $ticketInfoModel->execute($sql);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        //座位放回库存
        foreach ($dateList as $key => $row) {
            $lishName = getConf('seatListName') . "_{$row['scene_id']}";
            $val = redisLib::publish($lishName, $row['seat_id']);
            if ($val === false){
                $this->errCode = 20000;
                $this->errMsg = "服务器错误";
                return false;
            }
        }
        return true;
    }

    //关闭无效订单
    public function closeUselessDeal()
    {
        $expire = time() - 30 * 60;
        $where = "deal_gen_time<{$expire} and state in(0,2)";
        $state = 3;
        $ret = $this->changeDealState($where, $state);
        if ($ret === false) {
            return false;
        }
        return true;
    }

    public function getUselessSeatCount($begTime, &$count)
    {
        $count = 0;
        $sql = "select count(*) as count from ticket_info where bussiness_id in (
            select deal_id from deal where deal_close_time>$begTime and state=3
        )";
        $Model = new Model();
        $ret = $Model->execute($sql);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        if (!$ret) {
            return true;
        }
        $row = $ret->fetch(PDO::FETCH_ASSOC);
        isset($row['count']) && $count = $row['count'];
        return true;
    }

    public function getUselessSeat($begTime, $limit, &$dataList)
    {
        $dataList = array();
        $sql = "select * from ticket_info where bussiness_id in (
            select deal_id from deal where deal_close_time>$begTime and state=3
        ) limit {$limit}";
        $Model = new Model();
        $ret = $Model->execute($sql);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        if (!$ret) {
            return true;
        }
        $dataList = $ret->fetchAll(PDO::FETCH_ASSOC);
        return true;
    }

    public function delSeat($seatIdArr)
    {
        if ($seatIdArr && is_array($seatIdArr)) {
            $sql = "delete from ticket_info where seat_id in(" . implode(',', $seatIdArr) . ")";
            $ticketInfoModel = new ticketInfoModel();
            $ret = $ticketInfoModel->execute($sql);
            if ($ret === false) {
                $this->errCode = 20000;
                $this->errMsg = "数据库错误";
                return false;
            }
        }
        return true;
    }

    public function rollBackUselessSeat($dataList)
    {
        $ticketInfoModel = new ticketInfoModel();
        $ret = $ticketInfoModel->multipleInsert($dataList);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = '数据库错误！';
            return false;
        }
        return true;
    }

    public function getRangeSeatId($beg, $end, $scene_id = 1, &$seatIdArr)
    {
        $seatIdArr = array();
        $beg = (int)$beg;
        $end = (int)$end;
        $scene_id = (int)$scene_id;
        $select = "seat_id";
        $where = "seat_id>=:beg and seat_id<:end and scene_id=:scene_id";
        $param = array(
            'beg' => $beg,
            'end' => $end,
            'scene_id' => $scene_id,
        );
        $ticketInfoModel = new ticketInfoModel();
        $ret = $ticketInfoModel->getDataList($select, $where, $param);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        $seatIdArr = array_column($ret, 'seat_id');
        return true;
    }


    public function initSeat($scene_id)
    {
        $this->getSceneConf($scene_id, $sceneConf);
        $listName = getConf('seatListName') . "_{$scene_id}";
        $listNameBak = getConf('seatListName') . "_{$scene_id}_BAK";
        redisLib::delete($listNameBak);         //清空备用列表
        $lenght = 0;        //队列长度
        $limit = 500;       //每次插入500个座位
        $offset = 1;
        $maxSeatId = $sceneConf['maxSeatId'];
        while ($offset < $maxSeatId) {
            //[$beg,$end)
            $beg = $offset;
            $end = $offset + $limit;
            $end > $maxSeatId && $end = $maxSeatId;
            //获取范围内已锁定的座位
            $this->getRangeSeatId($beg, $end, $scene_id, $seatIdArr);
            for ($index = $beg; $index <= $end; $index++) {
                if (in_array($index, $seatIdArr)) {
                    continue;
                }
                //随机插入
                $position = rand(0, $lenght);
                if ($position == $lenght) {
                    redisLib::publish($listNameBak, $index);
                } else  {
                    $tempValue = redisLib::rdsLget($listNameBak, $position);
                    redisLib::rdsLset($listNameBak, $position, $index);
                    redisLib::publish($listNameBak, $tempValue);
                }
                $lenght++;
            }
            $offset += $limit;
        }
        //使用事务 重命名
        $ret = redisLib::rdsRename($listNameBak, $listName);
        if (!$ret) {
            $this->errCode = 30000;
            $this->errMsg = '迁移列表时出错！';
            return false;
        }
        return true;
    }
    public function dealIdMaker(&$id)
    {
        $dif = rand(1, 3);
        $key = "DEAL_ID_MAKER";
        for ($index = 1; $index <= $dif; $index++) {
            $id = redisLib::rdsInc($key);
            if ($id === false) {
                return false;
            }
        }
        return true;
    }
    public function successDeal($deal_id)
    {
        $dealModel = new dealModel();
        $ret = $dealModel->getDealInfoByDealid($deal_id, $dealInfo);
        if ($ret === false) {
            $this->errCode = $dealModel->errCode;
            $this->errMsg = $dealModel->errMsg;
            return false;
        }
        if ($dealInfo['state'] != 1 && $dealInfo['state'] != 2) {
            //其他状态不能转换成已支付
            $this->errCode = 20001;
            $this->errMsg = '只有未支付单才能支付！';
            return false;
        }
        //更新订单
        $where = "deal_id={$deal_id} and state=2";
        $state = 1;
        $ret = $this->changeDealState($where, $state);
        if ($ret === false) {
            return false;
        }
        return true;
    }
    public function refundDeal($user_id, $deal_id)
    {
        //checkdeal
        $dealModel = new dealModel();
        $ret = $dealModel->getDealInfoByDealid($deal_id, $dealInfo);
        if ($ret === false) {
            $this->errCode = $dealModel->errCode;
            $this->errMsg = $dealModel->errMsg;
            return false;
        }
        if ($dealInfo['uid'] != $user_id) {
            $this->errCode = 30000;
            $this->errMsg = '订单不是该用户的';
            return false;
        }
        if ($dealInfo['state'] != 1) {
            $this->errCode = 30001;
            $this->errMsg = '订单状态错误';
            return false;
        }
        //changeState
        $where = "deal_id={$deal_id} and uid={$user_id} and state=1";
        $state = 4;
        $ret = $dealModel->changeState($where, $state);
        if ($ret === false) {
            $this->errCode = $dealModel->errCode;
            $this->errMsg = $dealModel->errMsg;
            return false;
        }
        //getSeat
        $ret = $this->rollBackSeat($deal_id);
        if (!$ret) {
            return false;
        }
        return true;
    }
    public function refundDealSuccess($deal_id)
    {
        //更新订单
        $where = "deal_id={$deal_id} and state=4";
        $state = 5;
        $ret = $this->changeDealState($where, $state);
        if ($ret === false) {
            return false;
        }
        return true;

    }

    public function changeDealState($where, $state)
    {
        $data = array('state' => $state);
        switch ($state) {
            case 1:
                $data['deal_pay_time'] = time();
                break;
            case 3:
                $data['deal_close_time'] = time();
                break;
            case 5:
                $data['deal_refund_time'] = time();
                break;
            default:
                break;
        }
        $dealModel = new dealModel();
        $ret = $dealModel->updateData($data, $where);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = '数据库错误！';
            return false;
        }
        return true;
    }

    //座位id转换成位置信息
    public function idToPos_1($seat_id, &$position)
    {
        if ($seat_id > 0 && $seat_id <= 1950) {
            $position['area'] = 'A';
        } elseif ($seat_id > 1950 && $seat_id <= 3900) {
            $position['area'] = 'B';
        } elseif ($seat_id > 3900 && $seat_id <= 5850) {
            $position['area'] = 'C';
        } elseif ($seat_id > 5850 && $seat_id <= 7800) {
            $position['area'] = 'D';
        } else {
            return false;
        }
        $areaOffset = $seat_id % 1950;      //一个区域是1950个座位
        $row = 1;
        $rowSize = 50;
        while($areaOffset > $rowSize) {
            $areaOffset -= $rowSize;
            $rowSize += 2;
            $row++;
        }
        $position['row'] = $row;
        $position['offset'] = $areaOffset;
        return true;
    }

    //座位id转换成位置信息
    public function posToId_1($position, &$seat_id)
    {
        $seat_id = 0;
        if (!isset($position['area']) || !$position['row'] || !$position['offset']) {
            return false;
        }
        switch ($position['area']) {
            case 'A':
                $addNum = 0 * 1950;
                break;
            case 'B':
                $addNum = 1 * 1950;
                break;
            case 'C':
                $addNum = 2 * 1950;
                break;
            case 'D':
                $addNum = 3 * 1950;
                break;
            default:
                return false;
        }
        $seat_id += $addNum;
        if ($position['row'] > 26 || $position['row'] < 1) {
            return false;
        }
        $maxRowSize = 50 + ($position['row'] - 1) * 2;
        if ($position['offset'] > $maxRowSize || $position['offset'] < 1) {
            return false;
        }
        $seat_id += (49 * $position['row'] + $position['row'] * $position['row'] + $position['offset']);
        return true;
    }
}