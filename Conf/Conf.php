<?php
function getConf($keyStr)
{
	$conf = array(
	    'seatListName' => 'TICKET_SEAT',            //座位库存队列前缀

	    'db' => array(
            'host' => 'rm-wz9k531w73us21n79.mysql.rds.aliyuncs.com',
            'port' => '3306',
            'user' => 'xuser',
            'password' => 'Xpass611',
            'dbname' => 'xproject',
        ),
        'redis' => array(
            'host' => 'r-wz95b7e5292b9694.redis.rds.aliyuncs.com',
            'port' => '6379',
            'auth' => 'Xwed1108dev'
        ),
    );
	$keyArr = explode('.', $keyStr);
	foreach ($keyArr as $key) {
		if (is_array($conf) && isset($conf[$key])) {
			$conf = $conf[$key];
		} else {
			return null;
		}
	}
	return $conf;
}