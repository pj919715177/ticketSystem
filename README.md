# ticketSystem
### 需求点实现
##### 用户一次性可购买1-5张票
- 引入订单概念，每次购买作为一笔订单。先锁定订单，再锁定座位，最后修改订单状态为未支付。
一次购买多张票，库存不足时，订单直接创建失败，并回退库存，多人并发购买并且库存不足时，所有人都失败。具体实现在ticketController::submitOrder()。
- 在并发很高的时候，可能会严重影响用户体验（即使库存较多的时候，可能很多人生成订单失败）。这种情况下，可以考虑随机小部分用户等待重试，大部分用户直接回退库存，失败返回。优化后的锁定座位方法在ticketLogic::lockSeatV2()。
##### 系统随机为用户分配座位
- 将3维的位置转化成1维的座位号，简化分配逻辑，具体互相转化逻辑在ticketLogic::idToPos_1() 和 ticketLogic::posToId_1()。
- 大致思路是，在售票开始前，将所有库存座位打乱放到redis的list中，通过入队和出队原子性操作来分配库存和回收库存。
- 座位太多，没法放到一个数组变量进行打乱，所以实现的时候使用**蓄水池算法**，分页初始化库存，对第n个座位，随机数字0~n-1得到m，若m==n-1,则将座位n入队，否则与m位置上的座位互换。
### 规则假设
+ 假设有可能有多场（scene）票务售卖；
+ 未支付订单30分钟后自动关闭，所以生成支付信息时，支付有效期限也是30分钟；
+ 支付信息查询重试由支付系统实现；
+ 假设退款可以直接退，无需人工审核。
+ [订单状态流转图点这里>](https://github.com/pj919715177/ticketSystem/blob/master/deal_state.png)
### 运行
1. mysql和redis配置，具体配置填写在./Conf/Conf.php
2. mysql数据库建表，[建表sql点击这里>](https://github.com/pj919715177/ticketSystem/blob/master/sql.md)。
3. 功能demo运行（**cli下**）
    * 初始化座位：php index.php /ticket/initSeat
    * 提交订单：php index.php /ticket/submitOrder/user_id/18/num/3/scene_id/1/token/123 （user_id:用户id,num:购买座位数,scene_id:票务售卖场次,token:表单的唯一token，防重复提交订单）
    * 付款回调：php index.php /ticket/afterPay/deal_id/13(deal_id:已存在的订单id)
    * 申请退款：php index.php /ticket/refundDeal/deal_id/13/user_id/18
    * 退款回调：php index.php /ticket/refundDealSuccess/deal_id/13
    * 关闭订单和回收座位：php index.php /ticket/retrieveSeat
### 其他细节
- 数据表设计的时候，票券信息表将座位和场次作为联合主键，防止流程异常造成座位重复分配；
- 为了及时回收座位，座位回收脚本可能会跑的比较频繁，可重复跑，但同时两个脚本跑会出错，这里使用redis的setnx进行脚本加锁；
- 这里直接关闭超时的未支付单，实际实现里，可以在关闭之前调用支付系统封装的支付查询重试流程，防止临界情况发生时关闭了已支付订单；
- 回收座位相关流程，在服务器异常时，座位回滚失败可能会造成丢失座位的情况。因此初始化座位的脚本做成了 根据当前座位分配情况，重新生成库存list，为了减小对运行业务的影响，先将重新生成的list放在其他list中，然后在事务中删除旧的list，并重命名新的list。定时在深夜重新生成库存list，可以增加库存的准确性。如果重新生成时，有用户生成订单，会造成座位已经分配出去，但是库存list中还有，后续会有少数订单生成失败（被票券信息表的主键拒绝），这种情况影响不大。但是如果回收座位的动作同时进行，就会造成座位丢失（回收的座位跑到旧的list中），简单处理的话，可以与座位回收脚本加相同的锁，防止回收座位的流程并行，并且及时座位丢失了，也可以在下次重新生成库存时找回来。或者可以参考redis的AOF文件重写的思想，将这些并行的改动记录下来，重命名list之后，再在新的list上执行这些改动。
