<?php
namespace Phoenix\Framework\Pool;

/**
 * Description of MysqlPool
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class MysqlPool
{
    protected $capacity = 5;
    protected $stoarge = array();
    protected $cache = array();
    protected $connections = array();
    
    public function __construct($capacity = 5)
    {
        $this->capacity = $capacity;
    }
    
    public function __destruct()
    {
        foreach($this->connections as $conn){
            if($conn && method_exists($conn, 'close')){
                $conn->close();
            }
        }
    }
    
    public function create($dbname, $master)
    {
        
    }
    
    public function get($dbname, $master = false)
    {
        
    }
}