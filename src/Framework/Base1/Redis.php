<?php
namespace Phoenix\Framework\Base;

class Redis
{
    private static $pool = array();
    private $name = '';
    private $client = null;
    
    private function __construct($name, $client)
    {
        $this->name = $name;
        $this->client = $client;
    }

    public static function getInstance($name)
    {
        if(isset(self::$pool[$name])){
            return self::$pool[$name];
        }
        $client = self::connect($name);
        if(empty($client)){
            return false;
        }
        self::$pool[$name] = new self($name, $client);
        return self::$pool[$name];
    }
    
    public static function connect($name)
    {
        $config = Config::getRedis($name);
        if(empty($config)){
            return false;
        }
        $timeout = isset($config['timeout']) ? $config['timeout'] : 0;
        $password = isset($config['password']) ? $config['password'] : 0;
        if(extension_loaded('redis')){
            $redis = new \Redis();
            $redis->pconnect($config['host'], $config['port'], $timeout, $name);
            if($password){
                $ret = $redis->auth($config['password']);
                if(!$ret){
                    return false;
                }
            }
        }else if(class_exists('\Predis\Client')){
            $options = array();
            if($password){
                $options = [
                    'parameters' => [
                        'password' => $password,
                    ],
                ];
            }
            $redis = new \Predis\Client("tcp://{$config['host']}:{$config['port']}", $options);
        }else{
            $redis = new \Phoenix\Sys\RedisClient($config['host'], $config['port'], $timeout, $password);
        }
        return $redis;
    }
    
    private function evalsha()
    {
        $args = func_get_args();
        $script = $args[0];
        $sha1 = sha1($script);
        if(empty($this->client)){
            return null;
        }
        try{
            $args[0] = $sha1;
            $ret = call_user_func_array(array($this->client, 'evalsha'), $args);
            return $ret;
        } catch (\Exception $ex) {
            if($ex->getMessage() == 'NOSCRIPT No matching script. Please use EVAL.'){
                $args[0] = $script;
                return call_user_func_array(array($this->client, 'eval'), $args);
            }
        }
        return null;
    }
    
    public function __call($name, $arguments)
    {
        $client = $this->client;
        if(empty($client)){
            return null;
        }
        try {
            if($name == 'eval'){
                $ret = $this->evalsha(...$arguments);
                return $ret;
            }
            $t1 = microtime(true);
            $ret = $client->$name(...$arguments);
            $t2 = microtime(true);
            Log::redis($this->name, $name, ($t2 - $t1) * 1000, ...$arguments );
            return $ret;
        } catch (\Exception $exc) {
            return false;
        }
    }
}

