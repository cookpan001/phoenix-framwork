<?php
namespace App\Web;

use Phoenix\Framework\Base\Cache;
/**
 * Description of Table
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Table
{
    public static function execute($name, $cmd, $key = '', ...$args)
    {
        $instance = Cache::get($name);
        if ($cmd == 'all'){
            $ret = [];
            foreach($instance as $row){
                $ret[] = $row;
                break;
            }
        }else if('' == $key){
            $ret = $instance->$cmd();
        }else if(empty($args)){
            $ret = $instance->$cmd($key);
        }else if('set' == $cmd){
            $tmp = array();
            while($args){
                $k = array_shift($args);
                $v = array_shift($args);
                $tmp[$k] = $v;
            }
            $ret = $instance->$cmd($key, $tmp);
        }else{
            $instance->$cmd($key, ...$args);
        }
        \Phoenix\Framework\Route\Response::succ($ret);
    }
}
