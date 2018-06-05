<?php
namespace Phoenix\Framework\Base;

use Phoenix\Framework\Route\Response;
use Phoenix\Framework\Route\Request;

class Log
{
    const DEBUG = 110;
    /**
     * 获取毫秒级时间
     * @return string
     */
    private static function time()
    {
        list($micro, $second) = explode(' ', microtime());
        return date('Y-m-d H:i:s', $second) . substr($micro, 1);
    }
    /**
     * 拼装一行日志
     * @param type $values
     * @return string
     */
    private static function msg($values)
    {
        if(defined('PHOENIX_ENV')){
            $str = self::time() . ' [' . PHOENIX_ENV . ']';
        }else{
            $str = self::time();
        }
        $str .= ' ['.Request::uri().']';
        foreach($values as $val){
            if(is_array($val)){
                $str .= ' ['. json_encode($val).']';
            }else{
                $str .= ' ['. $val;
            }
        }
        $str .= "\n";
        return $str;
    }
    /**
     * 保存日志
     * @param string $name
     * @param string $str
     */
    public static function save($name, $str)
    {
        static $writeable = null;
        if(is_null($writeable)){
            $writeable = is_writeable(PHOENIX_LOG_PATH);
        }
        $path = '';
        if(defined('PHOENIX_NAME')){
            $path = PHOENIX_LOG_PATH . PHOENIX_NAME . '-' . php_sapi_name() . '-' . $name.'-'.date('Ym').'.log';
        }else{
            $path = PHOENIX_LOG_PATH . $name . '-' . php_sapi_name() . '-' . date('Ym') .'.log';
        }
        if($path && $writeable){
            file_put_contents($path, $str, LOCK_EX | FILE_APPEND);
        }
    }
    /**
     * 
     * @param string $moduleName 名称，如: mysql, redis, etc...
     * @param array $arguments  要记录的数值
     */
    public static function __callStatic($moduleName, $arguments)
    {
        $str = self::msg($arguments);
        if(Request::inDebug()){
            if('cli' != php_sapi_name()){
                echo $str;
            }else{
                Response::debug($str);
            }
            return;
        }
        if('cli' == php_sapi_name()){
            Watcher::getInstance()->fire('log', [$moduleName, $str]);
        }else{
            self::save($moduleName, $str);
        }
    }
}