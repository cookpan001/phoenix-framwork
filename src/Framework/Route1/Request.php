<?php
namespace Phoenix\Framework\Route;

/**
 * Description of Request
 *
 * @author pzhu
 */
class Request
{
    const DEBUG = 110;
    
    const HTTP = 1;
    const REDIS = 2;
    const WEBSOCKET = 3;
    
    private static $server = [];
    private static $get = [];
    private static $post = [];
    private static $cookie = [];
    private static $type = self::HTTP;

    public static function reset()
    {
        self::$server = [];
        self::$get = [];
        self::$post = [];
        self::$cookie = [];
        self::$type = self::HTTP;
    }
    
    public static function inDebug()
    {
        if(self::get('__DAVDIAN_DEBUG__') == self::DEBUG){
            return true;
        }
        return false;
    }

    public static function setHttp()
    {
        self::$type = self::HTTP;
    }

    public static function setRedis()
    {
        self::$type = self::REDIS;
    }

    public static function setWebsocket()
    {
        self::$type = self::WEBSOCKET;
    }
    
    public static function type()
    {
        return self::$type;
    }
    
    public static function requestTime()
    {
        if(!isset(self::$server['REQUEST_TIME'])){
            self::$server['REQUEST_TIME'] = time();
        }
        return self::$server['REQUEST_TIME'];
    }

    public static function server($k = null, $v = null)
    {
        if(is_null($v)){
            if(empty(self::$server) && 'cli' != php_sapi_name()){
                self::$server = $_SERVER;
            }
            if(is_null($k)){
                return self::$server;
            }
            if($k == 'REQUEST_TIME'){
                return self::requestTime();
            }
            return self::$server[$k] ?? null;
        }
        self::$server[$k] = $v;
    }
    
    public static function get($k = null, $v = null)
    {
        if(is_null($v)){
            if(empty(self::$get) && 'cli' != php_sapi_name()){
                self::$get = $_GET;
            }
            if(is_null($k)){
                return self::$get;
            }
            return self::$get[$k] ?? null;
        }
        self::$get[$k] = $v;
    }
    
    public static function post($k = null, $v = null)
    {
        if(is_null($v)){
            if(empty(self::$post) && 'cli' != php_sapi_name()){
                self::$post = $_POST;
            }
            if(is_null($k)){
                return self::$post;
            }
            return self::$post[$k] ?? null;
        }
        self::$post[$k] = $v;
    }
    
    public static function cookie($k = null, $v = null)
    {
        if(is_null($v)){
            if(empty(self::$cookie) && 'cli' != php_sapi_name()){
                self::$cookie = $_COOKIE;
            }
            if(is_null($k)){
                return self::$cookie;
            }
            return self::$cookie[$k] ?? null;
        }
        self::$cookie[$k] = $v;
    }
    
    public static function uri()
    {
        return self::server('REQUEST_URI') ?? '';
    }

    public static function request($k = null)
    {
        if(is_null($k)){
            return self::get() + self::post();
        }
        return self::get($k) ?? self::post($k);
    }
}