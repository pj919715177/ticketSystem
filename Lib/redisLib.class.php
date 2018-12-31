<?php
class redisLib
{
    public static $redis;

    function __construct()
    {
//        self::rdsConnect();
    }

    private static function rdsConnect()
    {
        if (!self::$redis) {
            self::$redis = new \Redis();
            $ret = self::$redis->connect(getConf('redis.host'), getConf('redis.port'), 9);
            if ($ret === false) {
                return false;
            }
            $ret = self::$redis->auth(getConf('redis.auth'));
            if ($ret === false) {
                return false;
            }
        }
        return true;
    }

    public static function delete($key)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $ret = self::$redis->del($key);
        return $ret;
    }

    //获取列表指定位置的值
    public static function rdsLget($listName, $index)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $index = (int)$index;
        $ret = self::$redis->lGet($listName, $index);
        return $ret;
    }


    //设置列表指定位置的值
    public static function rdsLset($listName, $index, $value)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $index = (int)$index;
        $ret = self::$redis->lSet($listName, $index, $value);
        return $ret;
    }


    //入队列
    public static function publish($listName, $value)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $ret = self::$redis->rPush($listName, $value);
        return $ret;
    }

    //出队列
    public static function subscripe($listName)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $ret = self::$redis->lPop($listName);
        return $ret;
    }

    //重命名
    public static function rdsRename($srcKey, $dstKey)
    {
        if(!self::rdsConnect()){
            return false;
        }

        self::$redis->multi();
        self::$redis->del($dstKey);
        self::$redis->rename($srcKey, $dstKey);
        $ret = self::$redis->exec();
        return $ret;
    }
    //设置锁
    public static function lock($key, $expire)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $key = 'LOCK_' . $key;
        $ret = self::$redis->setnx($key, 1);
        if (!$ret) {
            return false;
        }
        self::$redis->expire($key, $expire);
        return true;
    }

    //解锁
    public static function unlock($key)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $key = 'LOCK_' . $key;
        $ret = self::$redis->del($key);
        return $ret;
    }

    //变量自增
    public static function rdsInc($key)
    {
        if(!self::rdsConnect()){
            return false;
        }
        $ret = self::$redis->incr($key);
        return $ret;

    }

}