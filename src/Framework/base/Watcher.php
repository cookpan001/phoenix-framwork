<?php
namespace Phoenix\Framework\Base;

/**
 * 观察者模式
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Watcher
{
    private $events = array();
    
    private static $instance = null;
    
    private function __construct()
    {
        
    }
    /**
     * 
     * @return \self
     */
    public static function getInstance()
    {
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register($event, callable $callback, string $caller = null, $priority = 5)
    {
        if(is_null($callback)){
            return;
        }
        $this->events[$event][$priority][$caller] = $callback;
        krsort($this->events[$event], SORT_NUMERIC);
    }
    
    public function fire($event, $args = array())
    {
        if(empty($this->events[$event])){
            return;
        }
        foreach($this->events[$event] as $arr){
            foreach($arr as $c => $func){
                call_user_func_array($func, $args);
            }
        }
    }
}
