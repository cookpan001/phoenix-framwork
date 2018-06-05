<?php
namespace Phoenix\Framework\Base;

use Phoenix\Framework\Route\Request;

if(PHP_OS == 'Darwin'){
    !defined('EINVAL') && define('EINVAL', 22);/* Invalid argument */
    !defined('EPIPE') && define('EPIPE', 32);/* Broken pipe */
    !defined('EAGAIN') && define('EAGAIN', 35);/* Resource temporarily unavailable */
    !defined('EINPROGRESS') && define('EINPROGRESS', 36);/* Operation now in progress */
    !defined('EWOULDBLOCK') && define('EWOULDBLOCK', EAGAIN);/* Operation would block */
    !defined('EADDRINUSE') && define('EADDRINUSE', 48);/* Address already in use */
    !defined('ECONNRESET') && define('ECONNRESET', 54);/* Connection reset by peer */
    !defined('ETIMEDOUT') && define('ETIMEDOUT', 60);/* Connection timed out */
    !defined('ECONNREFUSED') && define('ECONNREFUSED', 61);/* Connection refused */
}else if(PHP_OS == 'Linux'){
    !defined('EINVAL') && define('EINVAL', 22);/* Invalid argument */
    !defined('EPIPE') && define('EPIPE', 32);/* Broken pipe */
    !defined('EAGAIN') && define('EAGAIN', 11);/* Resource temporarily unavailable */
    !defined('EINPROGRESS') && define('EINPROGRESS', 115);/* Operation now in progress */
    !defined('EWOULDBLOCK') && define('EWOULDBLOCK', EAGAIN);/* Operation would block */
    !defined('EADDRINUSE') && define('EADDRINUSE', 98);/* Address already in use */
    !defined('ECONNRESET') && define('ECONNRESET', 104);/* Connection reset by peer */
    !defined('ETIMEDOUT') && define('ETIMEDOUT', 110);/* Connection timed out */
    !defined('ECONNREFUSED') && define('ECONNREFUSED', 111);/* Connection refused */
}

/**
 * Description of Bootstrap
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Bootstrap
{
    /**
     * 获取配置文件和启动环境，先判断命令行参数
     * @return type
     */
    public static function startUp($documentRoot, $name = '')
    {
        define('PHOENIX_WORK_ROOT', $documentRoot . DIRECTORY_SEPARATOR);
        if(file_exists(PHOENIX_WORK_ROOT . '.envkey')){
            $env = trim(file_get_contents(PHOENIX_WORK_ROOT . '.envkey'));
        }else if ('cli' == php_sapi_name()) {
            $options = 'e:';
            $params = getopt($options);
            $env = $params['e'] ?? 'local';
        } else {
            $env = Request::server('HTTP_ENV') ?? 'local';
        }
        //环境定义
        define('PHOENIX_ENV', $env);
        define('PHOENIX_CONTROLLER_DIR', PHOENIX_WORK_ROOT  . 'app' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR);
        //配置路径
        define('PHOENIX_CONFIG_PATH', PHOENIX_WORK_ROOT . 'conf' . DIRECTORY_SEPARATOR);
        if('' == $name){
            if(file_exists(PHOENIX_CONFIG_PATH . 'application_name')){
                $name = trim(file_get_contents(PHOENIX_CONFIG_PATH . 'application_name'));
            }else{
                $name = 'app';
            }
        }
        define('PHOENIX_NAME', $name);
        $env = PHOENIX_ENV;
        //数据访问层的连接配置文件, 开发环境适用
        if (file_exists(PHOENIX_CONFIG_PATH . "connection-{$env}.json")) {
            define('PHOENIX_CONNECTION_FILE', PHOENIX_CONFIG_PATH . "connection-{$env}.json");
        } else if (file_exists(PHOENIX_CONFIG_PATH . 'connection.json')) {
            define('PHOENIX_CONNECTION_FILE', PHOENIX_CONFIG_PATH . 'connection.json');
        }
        $configFilePath = '';
        if (file_exists(PHOENIX_CONFIG_PATH . "application-{$env}.ini")) {
            $configFilePath = PHOENIX_CONFIG_PATH . "application-{$env}.ini";
        } else if (file_exists(PHOENIX_CONFIG_PATH . 'application.ini')) {
            $configFilePath = PHOENIX_CONFIG_PATH . 'application.ini';
        }
        $config = array();
        if ($configFilePath) {
            $config = parse_ini_file($configFilePath, true);
        }
        if(isset($config['all']['logLevel'])){
            $logLevelArr = explode(',', $config['all']['logLevel']);
            $logLevel = 0;
            foreach($logLevelArr as $ll){
                if(!defined('"'.strtoupper(trim($ll)).'"')){
                    continue;
                }
                $logLevel |= strtoupper(trim($ll));
            }
            define('PHOENIX_LOGLEVEL', $logLevel);
        }else{
            define('PHOENIX_LOGLEVEL', E_ALL);
        }
        //日志路径
        if (isset($config['all']['log']) && file_exists($config['all']['log']) && is_writeable($config['all']['log'])) {
            define('PHOENIX_LOG_PATH', $config['all']['log']);
        } else {
            define('PHOENIX_LOG_PATH', '/tmp/');
        }
        //数据访问层的连接配置目录, 如果存在配置目录配置目录,则不使用上面配置的配置文件
        if (isset($config['connection']['dir']) && $config['connection']['dir'] && file_exists($config['connection']['dir'])) {
            define('PHOENIX_CONNECTION_DIR', $config['connection']['dir'] . DIRECTORY_SEPARATOR);
        }
        //mysql的驱动名，默认使用mysqli
        if (isset($config['connection']['mysql']) && $config['connection']['mysql']) {
            define('PHOENIX_MYSQL_DRIVER', $config['connection']['mysql']);
        } else {
            define('PHOENIX_MYSQL_DRIVER', 'mysqli');
        }
    }
}
