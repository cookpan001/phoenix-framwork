<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Lock
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Lock extends Cache
{
    const DEFAULT_LOCK_NAME = 'phoenix_default_lock';
    
    private static $lastKey = null;
    
    public static function initLockTable()
    {
        $columns = array(
            'val' => Cache::TYPE_INT,
        );
        Cache::addTable(self::DEFAULT_LOCK_NAME, $columns, 1024);
    }

    public static function trylock($key)
    {
        $instance = Cache::get(self::DEFAULT_LOCK_NAME);
        $val = $instance->incr($key, 'val');
        if(1 == $val){
            self::$lastKey = $key;
            return true;
        }
        return false;
    }
    
    public static function unlock($key = null)
    {
        $instance = Cache::get(self::DEFAULT_LOCK_NAME);
        if(!is_null($key)){
            $instance->del($key);
            self::$lastKey = null;
            return;
        }
        if(!is_null(self::$lastKey)){
            $instance->del(self::$lastKey);
            self::$lastKey = null;
        }
    }
}