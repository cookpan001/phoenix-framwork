<?php
namespace Phoenix\Service\Tcp;

use \Swoole\Redis\Server;
use Phoenix\Framework\Route\Response;
use Phoenix\Framework\Base\Log;
use Phoenix\Framework\Base\Config;
/**
 * Description of RedisServer
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class RedisServer
{
    protected $host = '0.0.0.0';
    protected $port = 9610;
    /**
     * @var \Swoole\Redis\Server
     */
    protected $instance = null;
    protected $register = array();
    
    protected $currentFd = null;

    public function __construct()
    {
        $this->init();
    }
    /**
     * 解析Controller目录下的
     * @return type
     */
    private function init()
    {
        $handle = opendir(PHOENIX_CONTROLLER_DIR);
        if(!$handle){
            return;
        }
        $prefix = 'App\Controller\\';
        $tmp = array();
        while(false !== ($entry = readdir($handle))){
            if('.' == $entry || '..' == $entry){
                continue;
            }
            if(is_file(PHOENIX_CONTROLLER_DIR. $entry)){
                $uri = strtolower(str_replace(['Controller', '.php'], ['', ''], $entry));
                $classname = $prefix . substr($entry, 0, -4);
                $tmp[$uri] = $classname;
                continue;
            }
            $files = scandir(PHOENIX_CONTROLLER_DIR. $entry);
            foreach($files as $file){
                if('.' == $file || '..' == $file){
                    continue;
                }
                $uri = strtolower($entry . '/' . str_replace(['Controller', '.php'], ['', ''], $file));
                $classname = $prefix . $entry . '\\' . substr($file, 0, -4);
                $tmp[$uri] = $classname;
            }
        }
        closedir($handle);
        foreach($tmp as $uri => $classname){
            if(!class_exists($classname)){
                continue;
            }
            $reflection = new \ReflectionClass($classname);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                if($method->name == 'run'){
                    $this->register[$uri] = [$classname, 'run'];
                    continue;
                }
                $methodName = str_replace('Action', '', $method->name);
                if($methodName == $method->name){
                    continue;
                }
                $this->register[$uri . '/' . strtolower($methodName)] = [$classname, $method->name];
            }
        }
        
    }
    
    public function setUpHandler($fd, $action, $data)
    {
        Response::reset();
        $this->currentFd = $fd;
        try{
            call_user_func_array($action, $data);
        } catch (\Throwable $ex) {
            $this->instance->send($fd, Server::format(Server::ERROR, $ex->getMessage()));
            return;
        }
        $ret = Response::data();
        if(is_null($ret)){
            $this->instance->send($fd, Server::format(Server::NIL));
        }
        if(is_int($ret) || is_bool($ret)){
            $this->instance->send($fd, Server::format(Server::INT, $ret));
        }
        if(is_string($ret)){
            $this->instance->send($fd, Server::format(Server::STRING, $ret));
        }
        if(!is_array($ret)){
            $this->instance->send($fd, Server::format(Server::ERROR, 'illegal response: ' . json_encode($ret)));
            return;
        }
        if(isset($ret[0])){
            $this->instance->send($fd, Server::format(Server::SET, $ret));
        }else{
            $this->instance->send($fd, Server::format(Server::MAP, $ret));
        }
    }
    /**
     * 处理日志，及其他Job
     * @param type $serv
     * @param int $task_id
     * @param int $src_worker_id
     * @param type $data
     */
    public function onTask($serv, int $task_id, int $src_worker_id, $data)
    {
        $type = $data[0];
        if('log' == $type){
            list(, $name, $str) = $data;
            Log::save($name, $str);
            return;
        }else if('job' == $type){
            $args = $data[1];
            Job::execute(...$args);
        }
    }
    
    public function onFinish($serv, int $task_id, string $data)
    {
        
    }
    /**
     * 服务器统计信息
     */
    
    public function start()
    {
        $config = array(
            'reactor_num' => 2,
            'worker_num' => 16,
            'task_worker_num' => 2,
            'max_request' => 5000,
            'max_conn' => 256,
            'dispatch_mode' => 2,
            'open_tcp_keepalive' => 1,
            'document_root' => PHOENIX_WORK_ROOT,
            'enable_static_handler' => true,
        );
        $service = Config::getService(PHOENIX_NAME);
        if($service){
            foreach($service as $k => $v){
                if(is_array($v)){
                    continue;
                }
                $config[$k] = $v;
            }
        }
        if(isset($config['host']) && $config['host']){
            $this->host = $config['host'];
        }
        if(isset($config['port']) && $config['port']){
            $this->port = $config['port'];
        }
        $this->instance = new Server($this->host, $this->port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->instance->set($config);
        foreach($this->register as $cmd => $action){
            $this->instance->setHandler($cmd, function($fd, $data) use ($action) {
                $this->setUpHandler($fd, $action, $data);
            });
        }
        register_shutdown_function(function(){
            $error = error_get_last();
            if(empty($error)){
                return;
            }
            if($this->currentFd){
                Log::fatal($error['message'], $error['file'].':'.$error['line']);
                $str = "{$error['message']}. {$error['file']}:{$error['line']}";
                $this->instance->send($this->currentFd, Server::format(Server::ERROR, $str));
            }
            Response::reset();
        });
        $this->instance->on('task', array($this, 'onTask'));
        $this->instance->on('finish', array($this, 'onFinish'));
        $this->instance->start();
    }
}