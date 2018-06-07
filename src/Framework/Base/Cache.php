<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Cache
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Cache
{
    const TYPE_INT = \Swoole\Table::TYPE_INT;
    const TYPE_STRING = \Swoole\Table::TYPE_STRING;
    const TYPE_FLOAT = \Swoole\Table::TYPE_FLOAT;
    
    private static $instances = [];

    public static function init($tablename, $instance = null)
    {
        self::$instances[$tablename] = $instance;
    }
    
    public static function get($tablename)
    {
        if(isset(self::$instances[$tablename])){
            return self::$instances[$tablename];
        }
        return null;
    }
    
    public static function addTable($name, $columns = array(), $size = 65535)
    {
        $table = new \Swoole\Table($size);
        foreach($columns as $field => $define){
            $table->column($field, ...(array)$define);
        }
        $table->column('expire', self::TYPE_INT);
        $table->create();
        self::$instances[$name] = $table;
    }
}