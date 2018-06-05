<?php
include 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if(!php_sapi_name() == 'cli'){
    \Phoenix\Framework\Base\Bootstrap::startUp(__DIR__ . DIRECTORY_SEPARATOR, 'PhoenixApp');
    Phoenix\Framework\Route\Dispatcher::getInstance(PHOENIX_WORK_ROOT)->run();
    $cookies = Phoenix\Framework\Route\Response::cookie();
    if($cookies){
        foreach($cookies as $v){
            setcookie(...$v);
        }
    }
    echo Phoenix\Framework\Route\Response::result();
    exit;
}
if(!extension_loaded('swoole')){
    exit("php extension: [swoole] is needed\n");
}
class Server extends Phoenix\Service\SuperServer
{
    public function __construct($services = array())
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
\Phoenix\Framework\Base\Bootstrap::startUp(__DIR__ . DIRECTORY_SEPARATOR);
$app = new Server();
$app->start();