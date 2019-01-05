```php
<?php
//初始化
function init() {
    //把所有座位添加到座位表，设置订单id为0
    for($index = 1;$index <= 7800;$index++) {
        //这里需要分页批量插入
        $sql = "insert into ticket_info(seat_id,scene_id) values($index,1)";
        $model->execute($sql);
    }    
}
//锁定座位
function getTicket($num,$deal_id)
{
    $time = time();
    $seat_id = rand(0,7800);
    //bussiness_id就是订单id
    $sql = "update ticket_info set bussiness_id={$deal_id},add_time={$time} where bussiness_id=0 and scene_id=1 order by ABS(seat_id-{$seat_id}) asc limit {$num}";
    $affectRow = $model->execute($sql);
    if ($affectRow < $num) {
        //库存不足,回滚
        $sql = "update ticket_info set bussiness_id=0,add_time=0 where bussiness_id={$deal_id} and scene_id=1";
        $model->execute($sql);
    }
}
```
