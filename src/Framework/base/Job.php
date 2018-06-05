<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Job
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Job
{
    private static $storage = array();
    
    public static function register($type, callable $callback)
    {
        if(!is_callable($callback)){
            return;
        }
        self::$storage[$type] = $callback;
    }
    
    public static function fire(...$args)
    {
        Watcher::getInstance()->fire('job', $args);
    }

    public static function execute($type0, ...$args)
    {
        $action = $type0.'Action';
        if(method_exists(get_called_class(), $action)){
            call_user_func_array(static::$action, $args);
            return;
        }
        if(isset(self::$storage[$action])){
            call_user_func_array(self::$storage[$action], $args);
            return;
        }
        $type = str_replace(['-', '_', '.', '/'], ['\\', '\\', '\\', '\\'], $type0);
        $classname = 'App\Job\\'. ucwords($type, '\\').'Job';
        if(class_exists($classname)){
            if(method_exists($classname, 'run')){
                self::$storage[$action] = [$classname, 'run'];
                call_user_func_array([$classname, 'run'], $args);
                return;
            }
        }
    }
}