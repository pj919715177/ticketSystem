<?php
spl_autoload_register(function ($class) {
    $dirArr = array('Controller','Logic','Model');
    foreach ($dirArr as $dir) {
        if (strpos($class, $dir) !== false) {
            include "./{$dir}/{$class}.class.php";
            return;
        }
    }
    echo "对象{$class}不存在";
});

include "./Conf/Conf.php";
$controller = '';
$function = '';
if (preg_match("/cli/i", php_sapi_name())) {
    //cli模式下
    //controller/function/key1/value1/key2/value2/
    if (isset($argv[1]) && $argv[1]) {
        $info = explode('/', trim($argv[1],'/'));
        if ($info && isset($info[0])) {
            $controller = $info[0];
        }
        if ($info && isset($info[1])) {
            $controller = $info[1];
        }
        //设置参数
        for ($index = 2;$index < count($info); $index+=2) {
            if (isset($info[$index]) && isset($info[$index + 1])) {
                $_GET[$info[$index]] = $_GET[$info[$index + 1]];
            } else {
                break;
            }
        }
    }
} else {
    $controller = $_GET['controller'];
    $function = $_GET['function'];
}
!$controller && $controller = 'index';
!$function && $function = 'index';
$controller = strtolower($controller);
$class = $controller . 'Controller';
$obj = new $class();
if (method_exists($class, $function)) {
    $obj->$function();
} else {
    echo '地址不存在';
}

