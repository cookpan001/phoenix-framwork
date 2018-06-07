<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Concurrency
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Concurrency
{
    
    public function attampt($name, $func, $param, $expireTime = 600, $default = null)
    {
        $instance = Cache::get($name);
        if(!$instance){
            $data = $func();
            return $data;
        }
        $arr = $instance->get($param);
        if(false === $arr){
            $status = Lock::trylock($param);
            if($status){
                $data = $func();
                $arr = [
                    'seller_name' => $data,
                    'expire' => Request::server('REQUEST_TIME') + $expireTime,
                ];
                $instance->set($param, $arr);
                Lock::unlock($param);
                return $data;
            }
            $n = 5;
            while (false === ($arr = $instance->get($param)) && $n) {
                usleep(2000);
                $n--;
            }
        }
        if(false === $arr || !is_array($arr)){
            return $default;
        }
    }
}
