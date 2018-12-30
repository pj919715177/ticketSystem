<?php
class ticketController extends Controller
{
    //初始化座位
    public function initSeat()
    {
        //checkAdminLogin(省略)
        //checkRight(省略)
        $scene_id = 1;          //第一场
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->getSceneConf($scene_id, $sceneConf);
        if (!$ret) {
            $this->tip(10000, '该场次未配置');
        }
        $listName = "TICKET_SEAT_{$scene_id}";
        $listNameBak = "TICKET_SEAT_{$scene_id}_BAK";
        $limit = 500;       //每次插入500个座位
        $offset = 1;
        $lenght = 0;
        $maxSeatId = $sceneConf['maxSeatId'];
        while ($offset < $maxSeatId) {
            //[$beg,$end)
            $beg = $offset;
            $end = $offset + $limit;
            $end > $maxSeatId && $end = $maxSeatId;
            $seatIdArr = $ticketLogic->getRangeSeatId($beg, $end);
            //$redis->pipeline();        //管道
            for ($index = $beg; $index < $end; $index++) {
                if (in_array($index, $seatIdArr)) {
                    continue;
                }
                //随机插入
                $position = rand(0, $lenght);
                if ($position == $lenght) {
                    $redis->rpush($listNameBak, $index);
                } else  {
                    $tempValue = $redis->lGet($listNameBak, $position);
                    $redis->lset($listNameBak, $position, $index);
                    $redis->rpush($listNameBak, $tempValue);
                }
                $lenght++;
            }
            $offset += $limit;
        }
    }

    //提交订单
    public function submitOrder()
    {
        //checkLogin(省略)
        $user_id = (int)$_GET['user_id'];
        //序列号（座位号+场次+用户id+时间戳+随机字符串），防止前端重复提交
        $token = addslashes(trim($_GET['token']));//转义防sql注入
        $num = (int)$_GET['num'];       //购买座位数
        $scene_id = (int)$_GET['scene_id'];         //活动场次，1-test
        //计算价格（省略）
        $price = 18;
        //锁定订单
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->addOriginDeal($user_id, $token, $num, $price, $deal_id);
        if (!$ret) {
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //锁定座位
        $ret = $ticketLogic->lockSeat($num, $scene_id);
        if (!$ret) {
            $ticketLogic->rollBackDeal($deal_id);
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //设置订单状态为未支付
        $ret = $ticketLogic->unpayDeal($deal_id, $deal_gen_time);
        if (!$ret) {
            $ticketLogic->rollBackDeal($deal_id);
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //创建支付信息（省略）
        $payInfo = array();
        $this->tip(0, '订单提交成功', array('payInfo' => $payInfo));
    }

    //付款回调
    public function afterPay()
    {
        //省略获取订单id的细节，假设直接拿到deal_id
        $deal_id = (int)$_GET['deal_id'];
        //更新订单状态
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->successDeal($deal_id);
        if (!$ret) {
            //errLog(省略)
        }
        //infoLog（省略）
    }

    //申请退款
    public function refundDeal()
    {
        //checkLogin(省略)
        $user_id = (int)$_GET['user_id'];
        $deal_id = (int)$_GET['deal_id'];

        //todo:checkDeal
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->refundDeal($deal_id);

        //todo:refund
        //异步退款（退款成功后修改订单状态为已退款）（省略）
        //修改订单状态
    }

    //退款成功
    public function refundDealSuccess()
    {
        //省略获取订单id的细节，假设直接拿到deal_id
        $deal_id = (int)$_GET['deal_id'];
        //更新订单状态
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->refundDealSuccess($deal_id);
        if (!$ret) {
            //errLog(省略)
        }
        //infoLog（省略）
    }


    //回收座位（建议5分钟运行一次）
    public function retrieveSeat()
    {
        $currentTime = strtotime(trim($_GET['currentTime']));       //支持脚本补跑
        !$currentTime && $currentTime = time();
        //redis加锁
        //关闭失效订单
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->closeUselessDeal();
        if (!$ret) {
            //errLog(省略)
            //redis解锁
            return false;
        }
        //分页查询失效订单关联的座位
        $lastCount = -1;
        $limit = 200;       //一次处回收200个座位
        $begTime = $currentTime - 2 * 24 * 3600;        //默认处理两天内的无效订单，指定时间时，则从指定时间至今
        while (true) {
            $ret = $ticketLogic->getUselessSeatCount($begTime, $count);
            if (!$ret) {
                //errLog(省略)
                //redis解锁
                return false;
            }
            if ($lastCount != -1 && $count <= $lastCount) {
                //流程出错或有其他进程在处理，
                //errLog(省略)
                //redis解锁
                return false;
            }
            $lastCount = $count;
            if ($count > 0) {
                $dataList = array();
                //获取失效座位
                $ret = $ticketLogic->getUselessSeat($begTime, $limit, $dataList);
                if (!$ret) {
                    //errLog(省略)
                    //redis解锁
                    return false;
                }
                //删除这部分座位
                $seatIdArr = array_column($dataList, 'seat_id');
                $ret = $ticketLogic->delSeat($seatIdArr);
                //去掉已经在队列中的座位
                $redis->multi();        //事务
                foreach ($seatIdArr as $value) {
                    $redis->rPush($lishName, $value);
                }
                $val = $redis->exec();
                if ($val === false){
                    //errlog(省略)
                    //回退，新增回数据
                    $ticketLogic->rollBackUselessSeat($dataList);
                    //redis解锁
                    return false;
                }
                //将这部分座位放回队列
            } else {
                break;
            }
        }
        //redis解锁
    }
}