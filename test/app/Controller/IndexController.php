<?php
namespace App\Controller;

use Phoenix\Framework\Route\Response;
use Phoenix\Framework\Route\Request;
use Phoenix\Framework\Base\Config;
/**
 * Description of IndexController
 *
 * @author pzhu
 */
class IndexController
{
    const MIDDLEWARE = [
        'App\Middleware\Auth' => true,
    ];
    
    public static function run()
    {
        $data = $_SERVER;
        Response::succ($data);
    }
    
    public static function detailAction()
    {
        $data = Request::server();
        Response::succ($data);
    }
    
    public static function mysqlAction()
    {
        $data = \App\Data\TablesData::getData();
        Response::succ($data);
    }
    
    public static function tablesAction()
    {
        $data = \App\Data\TablesData::tables();
        Response::succ($data);
    }
    
    public static function userAction()
    {
        $data = \App\Data\UserData::getData();
        Response::succ($data);
    }
    
    public static function configAction()
    {
        $data = Config::getService('');
        Response::succ($data);
    }
    
    public static function redisAction()
    {
        $data = \Phoenix\Framework\Base\Redis::getInstance('main')->info();
        Response::succ($data);
    }
}
