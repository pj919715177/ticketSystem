<?php
function getConf($keyStr)
{
	$conf = array(
	    'db' => array(
	        '0' => array(
                'host' => '',
                'port' => '',
                'user' => '',
                'password' => '',
	            'dbname' => '',
            ),
        ),
        'redis' => array(),
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