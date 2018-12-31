<?php
function getConf($keyStr)
{
	$conf = array(
	    'seatListName' => 'TICKET_SEAT',            //座位库存队列前缀

	    'db' => array(
            'host' => '',
            'port' => '3306',
            'user' => '',
            'password' => '',
            'dbname' => 'xproject',
        ),
        'redis' => array(
            'host' => '',
            'port' => '6379',
            'auth' => ''
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
