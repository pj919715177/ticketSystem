<?php
class ticketController extends Controller
{
    //初始化座位
    public function initSeat()
    {
        //checkAdminLogin(省略)
        //checkRight(省略)
        //redis加锁(防止初始化座位的时候回收座位)
        $scene_id = 1;          //第一场
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->initSeat($scene_id);
        if (!$ret) {
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        $this->tip(0, '初始化座位成功');
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
        $ret = $ticketLogic->addOriginDeal($user_id, $token, $price, $deal_id, $deal_gen_time);
        if (!$ret) {
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //锁定座位
        $ret = $ticketLogic->lockSeat($num, $scene_id, $deal_id);
        if (!$ret) {
            $ticketLogic->rollBackDeal($deal_id);
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //设置订单状态为未支付
        $ret = $ticketLogic->unpayDeal($deal_id);
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
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //infoLog（省略）
        $this->tip(0, '付款成功');
    }

    //申请退款
    public function refundDeal()
    {
        //checkLogin(省略)
        $user_id = (int)$_GET['user_id'];
        $deal_id = (int)$_GET['deal_id'];

        //todo:checkDeal
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->refundDeal($user_id, $deal_id);
        if (!$ret) {
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }

        //异步退款（退款成功后修改订单状态为已退款）（省略）
        $this->tip(0, '申请退款成功');
    }

    //退款回调
    public function refundDealSuccess()
    {
        //省略获取订单id的细节，假设直接拿到deal_id
        $deal_id = (int)$_GET['deal_id'];
        //更新订单状态
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->refundDealSuccess($deal_id);
        if (!$ret) {
            $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
        }
        //infoLog（省略）
        $this->tip(0, '退款成功');

    }


    //回收座位（建议5分钟运行一次）
    public function retrieveSeat()
    {
        $currentTime = null;
        if (isset($_GET['currentTime'])) {
            $currentTime = strtotime(trim($_GET['currentTime']));       //支持脚本补跑
        }
        !$currentTime && $currentTime = time();
        $lockKey = "SEAT_CHANGE";
        //redis加锁
        $expire = 10*60;        //锁的有效时间10分钟
        if (!redisLib::lock($lockKey, $expire)) {
            $this->tip(10000, '上一个流程还在执行');
            return false;
        }
        //关闭失效订单
        $ticketLogic = new ticketLogic();
        $ret = $ticketLogic->closeUselessDeal();
        if (!$ret) {
            //errLog(省略)
            //redis解锁
            redisLib::unlock($lockKey);
            $this->tip(10000, '关闭订单出错');
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
                redisLib::unlock($lockKey);
                $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
            }
            if ($lastCount != -1 && $count >= $lastCount) {
                //流程出错或有其他进程在处理，
                //errLog(省略)
                //redis解锁
                redisLib::unlock($lockKey);
                $this->tip(10001, '流程错误！');
            }
            $lastCount = $count;
            if ($count > 0) {
                $dataList = array();
                //获取失效座位
                $ret = $ticketLogic->getUselessSeat($begTime, $limit, $dataList);
                if (!$ret) {
                    //errLog(省略)
                    //redis解锁
                    redisLib::unlock($lockKey);
                    $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
                }
                //删除这部分座位
                $seatIdArr = array_column($dataList, 'seat_id');
                $ret = $ticketLogic->delSeat($seatIdArr);
                if (!$ret) {
                    //errLog(省略)
                    //redis解锁
                    redisLib::unlock($lockKey);
                    $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
                }
                //把回收的座位放回到队列
                foreach ($dataList as $key => $row) {
                    $lishName = getConf('seatListName') . "_{$row['scene_id']}";
                    $val = redisLib::publish($lishName, $row['seat_id']);
                    if ($val === false){
                        //errlog(省略)
                        //回退，新增回数据
                        $ticketLogic->rollBackUselessSeat($dataList);
                        //redis解锁
                        redisLib::unlock($lockKey);
                    }
                    unset($dataList[$key]);
                }
            } else {
                break;
            }
        }
        //redis解锁
        redisLib::unlock($lockKey);
        $this->tip(0, '处理完成');
    }
}