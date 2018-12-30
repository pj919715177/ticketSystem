<?php
class ticketController extends Controller
{
    //提交订单
    public function submitOrder()
    {
        //checkLogin(省略)
        $user_id = (int)$_GET['user_id'];
        //序列号（座位号+场次+用户id+时间戳+随机字符串），防止前端重复提交
        $token = addslashes(trim($_GET['token']));//转义防sql注入
        $num = (int)$_GET['num'];       //购买座位数
        //计算价格（省略）
        $price = 18;
        //初始化订单
        //锁定座位
        //修改订单状态
    }

    //付款回调
    public function afterPay()
    {
        //省略获取订单id的细节，假设直接拿到deal_id
        $deal_id = (int)$_GET['deal_id'];
        //更新订单状态
    }

    //退款
    public function refundDeal()
    {
        //checkLogin(省略)
        $user_id = (int)$_GET['user_id'];
        $deal_id = (int)$_GET['deal_id'];

        //todo:checkDeal
        //todo:refund
    }

    //回收座位（建议5分钟运行一次）
    public function retrieveSeat()
    {
        //调用支付查询接口，将已支付但未调用支付回调的订单修改状态（省略）
        //redis加锁
        //关闭失效订单
        //分页查询失效订单关联的座位
        //try....catch
        //删除这部分座位
        //将这部分座位放回队列
        //redis解锁
    }
}