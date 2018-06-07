<?php
namespace Phoenix\Service;

use Phoenix\Framework\Route\Dispatcher;
use Phoenix\Framework\Route\Response;
use Phoenix\Framework\Base\Watcher;
use Phoenix\Framework\Base\Job;
use Phoenix\Framework\Base\Log;
use Phoenix\Framework\Base\Config;
use Phoenix\Framework\Route\Request;
use Phoenix\Framework\Base\Lock;

use Phoenix\Framework\Codec\Redis as RedisCodec;

use Swoole\Redis\Server;

/**
 * 集成Http协议, Redis协议, WebSocket协议三种协议的服务端
 * Description of SuperServer
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
abstract class SuperServer
{
    protected $host = '0.0.0.0';
    protected $port = 9609;
    
    protected $services = [];
    //主服务HTTP
    protected $instance = null;
    //Redis协议服务
    protected $redisService = null;
    //websocket协议服务
    protected $webSocketService = null;
    //Redis注册的方法
    protected $register = [];
    
    protected $currentFd = null;
    /**
     * @var \Phoenix\Framework\Codec\Redis
     */
    protected $redisCodec = null;
    /**
     * @var \Swoole\Table
     */
    protected $routes = [];

    public function __construct($services = [])
    {
        $this->services = $services;
    }
    
    public function __destruct()
    {
        $this->instance = null;
        $this->redisService = null;
        $this->webSocketService = null;
    }
    
    public function onStart($serv)
    {
        if('Linux' == PHP_OS){
            swoole_set_process_name(PHOENIX_NAME.'Service:Master Process');
        }
    }
    
    public function onManagerStart($serv)
    {
        if('Linux' == PHP_OS){
            swoole_set_process_name(PHOENIX_NAME.'Service:Manager Process');
        }
    }
    
    public function onWorkerStart($serv, $worker_id)
    {
        if($worker_id >= $serv->setting['worker_num']) {
            if('Linux' == PHP_OS){
                swoole_set_process_name(PHOENIX_NAME.'Service:Task Process');
            }
            define('PHOENIX_PROCESS', 'task');
        } else {
            if('Linux' == PHP_OS){
                swoole_set_process_name(PHOENIX_NAME.'Service:Worker Process');
            }
            define('PHOENIX_PROCESS', 'worker');
            if($this->routes->count()){
                Dispatcher::getInstance(PHOENIX_WORK_ROOT)->setUriObj($this->routes);
            }
        }
        if($worker_id == $serv->setting['worker_num']){
            $routes = Dispatcher::getInstance(PHOENIX_WORK_ROOT)->initUri();
            foreach ($routes as $k => $v){
                $this->routes->del($k);
            }
            foreach ($routes as $k => $v){
                $this->routes->set($k, ['detail' => json_encode($v)]);
            }
            for($i = 0; $i < $serv->setting['worker_num']; ++$i){
                $serv->sendMessage("route", $i);
            }
        }
        /**
         * 注册异步日志处理逻辑
         */
        Watcher::getInstance()->register('log', function($name, $msg) use ($serv){
            if(defined('PHOENIX_PROCESS') && PHOENIX_PROCESS == 'worker'){
                $serv->task(['log', $name, $msg]);
            }else{
                Log::save($name, $msg);
            }
        });
        Watcher::getInstance()->register('job', function(...$args) use ($serv){
            $serv->task(['job', $args]);
        });
        static::registerWatcher();
        $this->onInit();
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
    public function onFinish($serv, int $task_id, string $data){}
    
    public function onPipeMessage($serv, $src_worker_id, $data)
    {
        if('route' == $data && $this->routes->count()){//处理路由
            Dispatcher::getInstance(PHOENIX_WORK_ROOT)->setUriObj($this->routes);
        }
    }
    /**
     * 服务器统计信息
     */
    public function stats()
    {
        $data = array(
            'connections' => count($this->instance->connections),
            'lastError' => $this->instance->getLastError(),
            'server_setting' => $this->instance->setting,
            'stats' => $this->instance->stats(),
            'name' => PHOENIX_NAME,
            'env' => PHOENIX_ENV,
            'mysql_driver' => PHOENIX_MYSQL_DRIVER,
            'config_path' => PHOENIX_CONFIG_PATH,
            'connection_dir' => defined('PHOENIX_CONNECTION_DIR') ? PHOENIX_CONNECTION_DIR : '',
            'connection_file' => defined('PHOENIX_CONNECTION_FILE') ? PHOENIX_CONNECTION_FILE : '',
            'log_path' => PHOENIX_LOG_PATH,
            'memory_used' => (memory_get_usage(true) / 1024/ 1024) . 'MB',
            'memory_used_peak' => (memory_get_peak_usage(true) / 1024/ 1024) . 'MB',
            'sevices' => implode(',', $this->services),
        );
        Response::succ($data);
    }
    /**
     * 处理HTTP请求
     * @param type $request
     * @param type $response
     */
    public function onHttpRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        $this->currentFd = $response->fd;
        Request::reset();
        Response::reset();
        Request::setHttp();
        
        foreach($request->server as $k => $v){
            Request::server(strtoupper($k), $v);
        }
        foreach($request->header as $k => $v){
            Request::server(strtoupper($k), $v);
            Request::server('HTTP_'.strtoupper($k), $v);
        }
        foreach((array)$request->get as $k => $v){
            Request::get($k, $v);
        }
        foreach((array)$request->post as $k => $v){
            Request::post($k, $v);
        }
        foreach((array)$request->cookie as $k => $v){
            Request::cookie($k, $v);
        }
        try{
            $uri = trim(Request::server('REQUEST_URI'), '/');
            if('_stats' == trim($uri, '/')){
                $this->stats();
            }else{
                Dispatcher::getInstance(PHOENIX_WORK_ROOT)->proceed($uri);
            }
        } catch (\Throwable $ex) {
            Response::debug($ex->getMessage()."<br/><pre>".$ex->getTraceAsString().'</pre>');
            Lock::unlock();
        }
        $code = Response::getCode();
        if($code){
            $response->status($code);
        }
        $response->header('Content-Type', Response::contentType());
        $str = Response::result();
        if($str > 102400){
            $response->gzip();
        }
        $cookies = Response::cookie();
        if($cookies){
            foreach($cookies as $v){
                $response->rawCookie(...$v);
            }
        }
        $response->end($str);
        Response::reset();
    }
    /**
     * 处理redis协议的请求
     * @param type $fd
     * @param type $action
     * @param type $data
     * @return type
     */
    public function onRedisRequest($serv, $fd, $reactor_id, $data)
    {
        $tmp = $this->redisCodec->unserialize($data);
        Response::reset();
        Request::reset();
        Request::setRedis();
        $this->currentFd = $fd;
        try{
            $cmd = array_shift($tmp[0]);
            Dispatcher::getInstance(PHOENIX_WORK_ROOT)->proceed(trim($cmd, '/'));
        } catch (\Throwable $ex) {
            $serv->send($fd, $this->redisCodec->serialize($ex));
            return;
        }
        if(Response::getCode() > 0){
            $serv->send($fd, $this->redisCodec->serialize('-'.implode(' ', Response::rawResult())));
            return;
        }
        $serv->send($fd, $this->redisCodec->serialize(json_encode(Response::data())));
        Response::reset();
    }
    /**
     * 处理websocket消息
     * @param type $serv
     * @param type $frame
     * @return type
     */
    public function onWsRequest($serv, $frame)
    {
        Response::reset();
        Request::reset();
        Request::setWebsocket();
        $this->currentFd = $frame->fd;
        if(!$frame->finish){
            $serv->push($frame->fd, Response::result());
            return;
        }
        $data = json_decode($frame->data, true);
        if(!isset($data['action'])){
            Response::error('invalid message format', 400);
            $serv->push($frame->fd, Response::result());
            return;
        }
        try{
            Dispatcher::getInstance(PHOENIX_WORK_ROOT)->proceed(trim($data['action'], '/'));
        } catch (\Throwable $ex) {
            Response::error($ex->getMessage());
            Lock::unlock();
        }
        $serv->push($frame->fd, Response::result());
    }
    
    public function start()
    {
        $this->routes = new \Swoole\Table(2000);
        $this->routes->column('detail',\Swoole\Table::TYPE_STRING, 100);
        $this->routes->create();
        register_shutdown_function([$this, 'onShutdown']);
        Lock::initLockTable();
        $this->beforeStart();
        $config = array(
            'reactor_num' => 2,
            'worker_num' => 16,
            'task_worker_num' => 2,
            'max_request' => 5000,
            'max_conn' => 256,
            'daemonize' => true,
            'dispatch_mode' => 2,
            'open_tcp_keepalive' => 1,
            'document_root' => PHOENIX_WORK_ROOT,
            'enable_static_handler' => true,
            'request_slowlog_timeout' => 1,
            'trace_event_worker' => true,
            'request_slowlog_file' => '/tmp/slow.log',
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
        if(in_array('websocket', $this->services)){
            $this->instance = new \swoole_websocket_server($this->host, $this->port);
            $this->instance->on('message', [$this, 'onWsRequest']);
        }else{
            $this->instance = new \swoole_http_server($this->host, $this->port);
        }
        $this->instance->on('start', array($this, 'onStart'));
        $this->instance->on('manager', array($this, 'onManagerStart'));
        $this->instance->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->instance->on('request', array($this, 'onHttpRequest'));
        $this->instance->on('task', array($this, 'onTask'));
        $this->instance->on('finish', array($this, 'onFinish'));
        $this->instance->on('pipeMessage', array($this, 'onPipeMessage'));
        if(in_array('redis', $this->services)){
            $this->redisCodec = new RedisCodec();
            $redisPort = $this->instance->listen($this->host, $this->port + 1, SWOOLE_SOCK_TCP);
            $redisPort->set([
                'open_redis_protocol' => true,
                'open_http_protocol' => false,
            ]);
            $redisPort->on('receive', [$this, 'onRedisRequest']);
            $this->redisService = $redisPort;
        }
        $this->instance->set($config);
        $this->instance->start();
    }
    
    /**
     * 出现FatalError时，向客户端返回错误信息
     * @return type
     */
    public function onShutdown()
    {
        $error = error_get_last();
        if(empty($error)){
            return;
        }
        Lock::unlock();
        Log::fatal($error['message'], $error['file'].':'.$error['line']);
        if(Request::type() == Request::HTTP){
            if(Request::inDebug()){
                $str = Response::result();
            }else{
                $str = Response::fatalError(500, $error['message'], $error['file'], $error['line']);
            }
            $length = strlen($str);
            $header = "HTTP/1.1 500 Internal Server Error\r\nServer: Davdian-FatalError\r\nContent-Type: text/html\r\nContent-Length: $length\r\n\r\n$str";
            $this->instance->send($this->currentFd, $header);
        }else if(Request::type() == Request::REDIS){
            $str = "{$error['message']}. {$error['file']}:{$error['line']}";
            $this->redisService->send($this->currentFd, Server::format(Server::ERROR, $str));
        }else if(Request::type() == Request::WEBSOCKET){
            $str = "{$error['message']}. {$error['file']}:{$error['line']}";
            $this->instance->push($this->currentFd, $str);
        }
        Response::reset();
    }
    
    /**
     * 有错误时调用
     */
    public function onError($errfile, $errline, $errno, $errstr)
    {
        try {
            Lock::unlock();
            throw new \Exception;
        } catch (\Exception $exc) {
            $errcontext = $exc->getTraceAsString();
            $str = sprintf("%s:%d\nerrcode:%d\t%s\n%s\n", $errfile, $errline, $errno, $errstr, $errcontext);
            Log::error($str);
            if(Request::type() == Request::HTTP){
                if(Request::inDebug()){
                    $str = Response::raw();
                    $length = strlen($str);
                    $header = "HTTP/1.1 200 OK\r\nServer: Davdian-Error\r\nContent-Type: text/html\r\nContent-Length: $length\r\n\r\n$str";
                    $this->instance->send($this->currentFd, $header);
                }
            }else if(Request::type() == Request::REDIS){
                $this->redisService->send($this->currentFd, Server::format(Server::ERROR, $str));
            }else if(Request::type() == Request::WEBSOCKET){
                $this->instance->push($this->currentFd, $str);
            }
        }
        return true;
    }
    
    /**
     * 注册观察者事件及回调
     */
    public static function registerWatcher(){}
    /**
     * 每个子进程启动时调用
     */
    public abstract function onInit();
    /**
     * Server启动前调用
     */
    public abstract function beforeStart();
    /**
     * 每次请求完后调用
     */
    public abstract function afterRequest();
}