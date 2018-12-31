<?php
class dealModel extends Model
{
    protected $dbNum = 0;
    protected $table = 'deal';

    public function changeState($where, $state)
    {
        $sql = "update deal set state={$state} where {$where}";
        $ret = $this->execute($sql);
        if ($ret === false) {
            $this->errCode = 20000;
            $this->errMsg = "数据库错误";
            return false;
        }
        return true;
    }

    public function getDealInfoByDealid($deal_id, &$dealInfo)
    {
        $dealInfo = array();
        $where = "deal_id={$deal_id}";
        $ret = $this->getDataDetail('*', $where);
        if ($ret === false) {
            $this->errCode = 30000;
            $this->errMsg = '数据库错误！';
            return false;
        }
        if (!$ret) {
            $this->errCode = 20000;
            $this->errMsg = '订单不存在！';
            return false;
        }
        $dealInfo = $ret;
        return true;
    }
}