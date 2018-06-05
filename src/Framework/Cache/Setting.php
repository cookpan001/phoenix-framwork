<?php
namespace Phoenix\Framework\Cache;

/**
 * Description of newPHPClass
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Setting
{
    //表名
    const NAME = 'phoenix_setting';
    //大小
    const SIZE = 4096;
    //过期时间
    const CACHE_EXPIRE = 600;
    //定义
    const TABLE = [
        'key' => [Cache::TYPE_STRING, 60],
        'intval' => Cache::TYPE_INT,
        'strval' => [Cache::TYPE_STRING, 100],
    ];

    public static function init()
    {
        Cache::addTable(self::NAME, self::TABLE, self::SIZE);
    }
    
    public static function get($key)
    {
        $instance = Cache::get(self::NAME);
        $value = $instance->get($key);
        if(!$value){
            return false;
        }
        return $value['intval'] ?? $value['strval'];
    }
    
    public static function count()
    {
        $instance = Cache::get(self::NAME);
        return $instance->count();
    }

    public static function load()
    {
        $instance = Cache::get(self::NAME);
    }
}
