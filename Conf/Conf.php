<?php
function getConf($keyStr)
{
	$conf = array();
	$keyArr = explode('.', $keyStr);
	$result = '';
	foreach ($keyArr as $key) {
		if (isset($conf[$key])) {
			$conf = $conf[$key];
		} else {
			return null;
		}
	}
	return $conf;
}