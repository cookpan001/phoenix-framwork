<?php
namespace Phoenix\Framework\Util;

use Phoenix\Framework\Route\Request;
/**
 * Description of Sign
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Sign
{
    const SECRET_KEY = 'abcdeefg987654;-=+$%zzzz';
    
    public static function encode()
    {
        
    }
    
    public static function check($version = 1)
    {
        $arr = Request::request();
        $sig = $arr['sign'] ?? '';
        if(empty($arr) || empty($sig)){
            return false;
        }
        unset($arr['sign']);
        $tmp = [];
        ksort($arr);
        foreach($arr as $k => $v){
            $tmp[] = "$k=$v";
        }
        $tmp[] = md5(self::SECRET_KEY. ':' . $version . ':' . ($version % 10));
        $dest = strtoupper(md5(implode('', $tmp)));
        if($dest && $dest == $sig){
            return true;
        }
        return false;
    }
}
