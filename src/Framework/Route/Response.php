<?php
namespace Phoenix\Framework\Route;

/**
 * Description of Response
 *
 * @author pzhu
 */
class Response
{
    private static $data = '';
    private static $debug = '';
    //错误码， 前三位为HTTP状态码， 后两位为项目错误码，如: 40101
    private static $code = 0;
    private static $errorClass = '';
    private static $html = false;
    private static $cookie = [];
    private static $contentType = 'text/html; charset=utf-8';
    
    public static function reset()
    {
        self::$data = '';
        self::$debug = '';
        self::$code = 0;
        self::$html = false;
        self::$errorClass = '';
        self::$contentType = 'text/html; charset=utf-8';
        self::$cookie = [];
    }
    
    public static function succ($data, $isHtml = false)
    {
        self::$data = $data;
        self::$code = 0;
        self::$html = $isHtml;
    }
    
    public static function error($message, $code = 500, $file = null)
    {
        self::$data = $message;
        self::$code = $code;
        self::$errorClass = $file;
    }
    
    public static function trigger(array $codeArr)
    {
        list($code, $message) = $codeArr;
        self::$code = $code;
        self::$data = $message;
    }

    public static function debug($mixed)
    {
        self::$debug .= $mixed;
    }
    
    public static function setCookie($name, $value = '', $expire = 0, $path = '/', $domain = '', $secure = fase, $httpOnly = false)
    {
        self::$cookie[] = func_get_args();
    }
    
    public static function cookie()
    {
        return self::$cookie;
    }
    /**
     * 返回HTTP状态码
     * @return type
     */
    public static function getCode()
    {
        if(self::$code > 1000){
            return intval(self::$code * 0.001);
        }
        return self::$code;
    }
    
    public static function contentType()
    {
        return self::$contentType;
    }
    
    public static function data()
    {
        return self::$data;
    }
    
    public static function raw()
    {
        return self::$debug . (is_scalar(self::$data) ? self::$data : var_export(self::$data, true));
    }
    
    private static function format($name, $data)
    {
        return '<font color=red>'.strtoupper($name) . '</font>:<br/>' . (is_scalar($data) ? $data : var_export($data, true)) . '<br/>';
    }
    
    public static function rawResult()
    {
        return array(
            'code' => self::$code,
            'data' => self::$data,
        );
    }

    /**
     * 数据返回
     * @return type
     */
    public static function result()
    {
        if(self::$html){
            return self::$debug . self::$data;
        }
        if(self::$debug || Request::inDebug()){
            return '<pre>' . self::format('debug', self::$debug) . self::format('data', self::$data) .
                    self::format('errorClass', self::$errorClass) . self::format('server', Request::server())
                    . '</pre>';
        }
        $arr = array(
            'code' => self::$code,
            'data' => self::$data,
        );
        self::$contentType = 'application/json';
        return json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_PRETTY_PRINT);
    }
    /**
     * 遇到FatalError时的返回
     * @param type $code
     * @param type $msg
     * @param type $file
     * @param type $line
     * @return type
     */
    public static function fatalError($code, $msg, $file, $line)
    {
        $arr = array(
            'code' => $code,
            'data' => array(
                'msg' => $msg,
                'file' => $file.':'.$line,
            ),
        );
        self::$contentType = 'application/json';
        return json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_PRETTY_PRINT);
    }
}
