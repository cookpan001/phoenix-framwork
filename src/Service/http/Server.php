<?php
namespace Phoenix\Service\Http;

use Phoenix\Framework\Route\Dispatcher;
use Phoenix\Framework\Route\Response;
use Phoenix\Framework\Base\Watcher;
use Phoenix\Framework\Base\Job;
use Phoenix\Framework\Base\Log;
use Phoenix\Framework\Base\Config;
use Phoenix\Framework\Route\Request;
use Phoenix\Framework\Base\Lock;
/**
 * Description of Server
 *
 * @author pzhu
 */
abstract class Server
{
    protected $host = '0.0.0.0';
    protected $port = 9501;
    protected $currentFd = null;
    protected $shutdown = null;
    protected $logLevel = null;
    /**
     * @var \swoole_http_server
     */
    protected $instance = null;

    public function __construct()
    {
        
    }
    
    public function __destruct()
    {
        $this->instance = null;
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
        Log::fatal($error['message'], $error['file'].':'.$error['line']);
        if(Request::inDebug()){
            $str = Response::result();
        }else{
            $str = Response::fatalError(500, $error['message'], $error['file'], $error['line']);
        }
        $length = strlen($str);
        $header = "HTTP/1.1 500 Internal Server Error\r\nServer: Davdian-FatalError\r\nContent-Type: text/html\r\nContent-Length: $length\r\n\r\n$str";
        $this->instance->send($this->currentFd, $header);
        Response::reset();
        Lock::unlock();
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
            if(Request::inDebug()){
                $str = Response::raw();
                $length = strlen($str);
                $header = "HTTP/1.1 200 OK\r\nServer: Davdian-Error\r\nContent-Type: text/html\r\nContent-Length: $length\r\n\r\n$str";
                $this->instance->send($this->currentFd, $header);
            }
        }
        return true;
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
        );
        Response::succ($data);
    }
    /**
     * 处理HTTP请求
     * @param type $request
     * @param type $response
     */
    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        $this->currentFd = $response->fd;
        Request::reset();
        if(is_null($this->shutdown)){
            register_shutdown_function([$this, 'onShutdown']);
            set_error_handler([$this, 'onError']);
            $this->shutdown = true;
        }
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
                Dispatcher::getInstance(PHOENIX_WORK_ROOT)->run($uri);
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

    public function onFinish($serv, int $task_id, string $data)
    {
        
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

    public function start()
    {
        $this->instance = new \swoole_http_server($this->host, $this->port);
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
            'http_parse_post' => true,
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
        $this->instance->set($config);
        $this->instance->on('start', array($this, 'onStart'));
        $this->instance->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->instance->on('manager', array($this, 'onManagerStart'));
        $this->instance->on('request', array($this, 'onRequest'));
        $this->instance->on('task', array($this, 'onTask'));
        $this->instance->on('finish', array($this, 'onFinish'));
        Lock::initLockTable();
        $this->beforeStart();
        $this->instance->start();
    }
}
