**如果并发太高，也可以选择使用redis存储库存**    
##### 新增场次表
```mysql
create table scene_info (
  `scene_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '场次id',
  `left_ticket_num` int(10) NOT NULL DEFAULT 0 COMMENT '剩余票数',
  `mark` varchar(128) NOT NULL DEFAULT '' COMMENT '备注',
  `add_time` int(10) NOT NULL DEFAULT 0 COMMENT '关系新增时间',
  primary key(`scene_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='场次信息表';
```

##### 每个活动开始前，初始化场次信息
```php
//初始化
function init() {
    $time = time();
    $sql = "insert into scene_info(left_ticket_num,mark,add_time) values(7800,'test',{$time})";
    $model->execute($sql);
}
```
##### 提交订单只需要锁定座位和扣减库存
```php
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
    
    //--------------锁定座位,主要改动在这里--------------------------------------------
    if ($num<=0 && $num>5) {
        $this->tip(10001, '参数错误');
    }
    $sql = "update scene_info set left_ticket_num=left_ticket_num-{$num} where scene_id={$scene_id} and left_ticket_num>={$num}";
    $affectRow = $model->execute($sql);
    if ($affectRow === false) {
        $this->tip(10002, '服务器错误');
    }
    if ($affectRow < $num) {
        //todo:库存不足,回滚
        $this->tip(10003, '库存不足');
    }
    //--------------锁定座位,主要改动结束--------------------------------------------
    //设置订单状态为未支付
    $ret = $ticketLogic->unpayDeal($deal_id);
    if (!$ret) {
        $ticketLogic->rollBackDeal($deal_id);
        $this->tip($ticketLogic->errCode, $ticketLogic->errMsg);
    }
    //创建支付信息（省略）
    $payInfo = array();
    $this->tip(0, '订单提交成功', array('payInfo' => $payInfo, 'seat_info' => $seat_info));
}
```

##### 支付完成后锁定座位并触达用户
```php
function getTicket($num,$deal_id)
{
    $time = time();
    $seat_id = rand(0,7800);
    
   //省略获取订单id的细节，假设直接拿到deal_id
   $deal_id = (int)$_GET['deal_id'];
    //bussiness_id就是订单id
    $sql = "update ticket_info set bussiness_id={$deal_id},add_time={$time} where bussiness_id=0 and scene_id=1 order by ABS(seat_id-{$seat_id}) asc limit {$num}";
    $affectRow = $model->execute($sql);
    if ($affectRow < $num) {
        //库存不足,回滚
        //只有服务器错误的情况才会出现，因为提交订单的时候有判断库存
        //可以触达相关人员人工处理
        $sql = "update ticket_info set bussiness_id=0,add_time=0 where bussiness_id={$deal_id} and scene_id=1";
        $model->execute($sql);
        return false;
    }
    //更新订单状态
    $ticketLogic = new ticketLogic();
    $ret = $ticketLogic->successDeal($deal_id);
    if (!$ret) {
        //服务器错误，告警，log
    }
    //todo:触达用户相关座位信息
    return true;
}
```
