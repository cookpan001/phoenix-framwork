<?php
namespace Phoenix\Service;

class QueueServer
{
    const SUB_NAMESPACE = 'Queue';
    
    private $script = '';
    protected $connections = array();
    protected $queues = array();
    protected $handler = array();
    protected $args = array();
    protected $keysNum = 0;
    protected $batch = 10;
    protected $timeout = 2;
    protected $sleep = 50000;

    public function init($type, $index)
    {
        foreach($this->conf['handler'] as $queue => $handler){
            if(false !== strpos($handler, '\\')){
                $classname = ROOT_NAMESPACE . '\\'.self::SUB_NAMESPACE.'\\' . ucwords(trim($handler, '\\'), '\\');
            }else{
                $classname = ROOT_NAMESPACE . '\\'.self::SUB_NAMESPACE.'\\' . ucfirst(trim($handler, '\\'));
            }
            if(class_exists($classname)){
                $this->handler[$queue] = $classname;
            }
        }
        $this->resetKeys();
    }
    
    public function resetKeys()
    {
        $this->keysNum = count($this->handler);
        $this->args = array_keys($this->handler);
        $this->args[] = $this->batch;
    }
    
    public function afterStart()
    {
        $this->script = <<<LUA
        local ret = {}
        local num = tonumber(ARGV[1])
        for i, k in pairs(KEYS) do
            local c = tonumber(redis.call('llen', k))
            if c > 0 then
                if not ret[k] then
                    ret[k] = {}
                end
                local i = 1
                while i <= num and i <= c do
                    ret[k][#ret[k] + 1] = redis.call('lpop', k)
                    i = i + 1
                end
            end
        end
        return cmsgpack.pack(ret)
LUA;
    }
    
    public function afterStop()
    {
        $this->sendbackEvent();
    }
    
    public function restart()
    {
        $this->error("restarting...");
        $this->sendbackEvent();
        global $argv;
        array_shift($argv);
        $command = 'php ' . __FILE__ .' '. implode(' ', $argv);
        exec($command);
    }

    public function reload()
    {
        
    }
    
    function sendbackEvent()
    {
        $data = $this->msg;
        $this->msg = array();
        $this->error("send back...");
        foreach((array)$data as $queue => $arr){
            $i = array_rand($this->connections);
            $conn = $this->connections[$i];
            $tmp = array();
            foreach($arr as $i => $line){
                $tmp[] = gzdeflate(json_encode(array($line)));
            }
            call_user_func(array($conn, 'lpush'), $queue, ...$tmp);
        }
    }
    
    function fetch()
    {
        $ret = array();
        foreach($this->connections as $conn){
            if(extension_loaded('redis')){
                $messages = $conn->eval($this->script, $this->args, $this->keysNum);
            }else{
                $messages = $conn->eval($this->script, $this->keysNum, ...$this->args);
            }
            //兼容不同的redis客户端(\Redis, \DF\Sys\MyRedis, \DF\Sys\RedisClient)
            foreach((array)$messages as $message){
                $arr = msgpack_unpack($message);
                if(empty($arr) || !is_array($arr)){
                    continue;
                }
                foreach($arr as $k => $info){
                    if(!is_array($info)){
                        $info = (array)$info;
                    }
                    foreach($info as $str){
                        $json = json_decode(gzinflate($str), true);
                        if(!is_array($json)){
                            continue;
                        }
                        if(!isset($ret[$k])){
                            $ret[$k] = $json;
                        }else{
                            $ret[$k] = array_merge($ret[$k], $json);
                        }
                    }
                }
            }
        }
        return $ret;
    }
    
    function getMessage()
    {
        if(empty($this->msg) && !$this->terminate){
            try{
                $ret = $this->fetch();
            } catch (\Throwable $ex) {
                $ret = array();
            }
            if(empty($ret)){
                return false;
            }
            $this->msg = $ret;
        }
        return true;
    }

    public function handle()
    {
        $ret = $this->getMessage();
        if(false === $ret){
            return false;
        }
        foreach($this->msg as $queue => $arr){
            $classname = isset($this->handler[$queue]) ? $this->handler[$queue] : null;
            if(is_null($classname)){
                unset($this->msg[$queue]);
                $i = array_rand($this->connections);
                $conn = $this->connections[$i];
                $tmp = array();
                foreach($arr as $i => $line){
                    $tmp[] = gzdeflate(json_encode(array($line)));
                }
                call_user_func(array($conn, 'lpush'), $queue, ...$tmp);
                unset($this->handler[$queue]);
                $this->resetKeys();
                $this->error("no handler for queue: $queue");
                continue;
            }
            foreach($arr as $i => $line){
                $this->curMsg = $line;
                if(is_array($line) && isset($line[0]) && 'restart' === $line[0]){
                    $this->terminate = self::SIG_RESTART;
                }else if(is_array($line) && isset($line[0]) && 'stop' === $line[0]){
                    $this->terminate = self::SIG_STOP;
                }else{
                    unset($this->msg[$queue][$i]);
                    $t1 = microtime(true);
                    $classname::process($line);
                    $t2 = microtime(true);
                    \DF\Base\Log::receive($queue, $line, ($t2 - $t1) * 1000);
                }
                //test sendback
//                $this->terminate = self::SIG_STOP;
//                break;
            }
            if(empty($this->msg[$queue])){
                unset($this->msg[$queue]);
            }
        }
    }
    
    public function process()
    {
        while(true){
            if($this->terminate == self::SIG_STOP){
                $this->error("terminate...");
                break;
            }else if($this->terminate == self::SIG_RESTART){
                $this->restart();
                break;
            }else if($this->terminate == self::SIG_RELOAD){
                $this->terminate = self::SIG_NONE;
                $this->reload();
            }
            pcntl_signal_dispatch();
            $ret = $this->handle();
            if(false === $ret){
                usleep($this->sleep);
            }else{
                usleep(50);
            }
        }
    }
}
$app = new QueueServer();
$app->run();