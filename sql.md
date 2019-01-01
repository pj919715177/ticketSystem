```sql
    --票券信息表
    create table ticket_info (
      `seat_id` int(10) NOT NULL DEFAULT 0 COMMENT '座位号',
      `scene_id` int(10) NOT NULL DEFAULT 0 COMMENT '场次id,1-test',
      `bussiness_id` int(10) NOT NULL DEFAULT 0 COMMENT '业务id,订单场景下为订单id',
      `add_time` int(10) NOT NULL DEFAULT 0 COMMENT '关系新增时间',
      key `bussiness_id`(`bussiness_id`) ,
      primary key(`seat_id`,`scene_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='票券信息表';

    --订单表
    create table deal (
      `deal_id` int(10) NOT NULL DEFAULT 0 COMMENT '订单id',
      `token` varchar(128) NOT NULL DEFAULT '' COMMENT '订单的唯一token,由 座位号+场次+用户id+时间戳+随机字符串 决定，防止前端重复提交',
      `uid` int(10) NOT NULL DEFAULT 0 COMMENT '用户id',
      `price` int(10) NOT NULL DEFAULT 0 COMMENT '订单金额（分）',
      `state` int(10) NOT NULL DEFAULT 0 COMMENT '状态，0-初始化,1-已支付,2-未支付,3-已关闭,4-退款中,5-已退款',
      `deal_gen_time` int(10) NOT NULL DEFAULT 0 COMMENT '订单生成时间',
      `deal_pay_time` int(10) NOT NULL DEFAULT 0 COMMENT '订单支付时间',
      `deal_close_time` int(10) NOT NULL DEFAULT 0 COMMENT '订单关闭时间',
      `deal_refund_time` int(10) NOT NULL DEFAULT 0 COMMENT '订单退款时间',
      `last_modify_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后修改时间',
      UNIQUE key `token`(`token`),
      key uid(`uid`),
      key deal_gen_time(`deal_gen_time`),
      key deal_close_time(`deal_close_time`),
      primary key(`deal_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单表';
```
