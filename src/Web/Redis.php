<?php
namespace Phoenix\Web;

use Phoenix\Framework\Route\Response;
/**
 * Description of Redis
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Redis
{
    public static function execute($name = '', $cmd = '', ...$args)
    {
        if(empty($name)){
            Response::error('no table specified', 404);
            return;
        }
        if('' === $cmd){
            Response::error('no command specified', 404);
            return;
        }
        $redis = \Phoenix\Framework\Base\Redis::getInstance($name);
        $ret = call_user_func_array([$redis, $cmd], $args);
        Response::succ($ret);
    }
}
