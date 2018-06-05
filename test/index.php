<?php
include __DIR__ . DIRECTORY_SEPARATOR . 'base.php';

\Phoenix\Framework\Base\Bootstrap::startUp(__DIR__ . DIRECTORY_SEPARATOR, 'PhoenixTest');

if(extension_loaded('swoole') && php_sapi_name() == 'cli'){
    class HttpServer extends Phoenix\Service\SuperServer
    {
        public function __construct()
        {
            parent::__construct(['redis', 'websocket']);
        }
        
        public function onInit()
        {
            
        }

        public function beforeStart()
        {
            
        }

        public function afterRequest()
        {
            
        }

    }
    $app = new HttpServer();
    $app->start();
}else{
    Phoenix\Framework\Route\Dispatcher::getInstance(PHOENIX_WORK_ROOT)->run();
    echo Phoenix\Framework\Route\Response::result();
}