<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Config
 *
 * @author pzhu
 */
class Config
{
    const CONFIG_REDIS = 'redis';
    const CONFIG_DB = 'db';
    const CONFIG_POOL = 'pool';
    const CONFIG_TABLES = 'tables';
    const CONFIG_CONNECTION = 'connection';
    const CONFIG_MONGO = 'mongo';
    
    public static $pool = null;
    public static $tables = null;

    public static function flush()
    {
        self::$pool = array();
        self::$tables = array();
    }
    
    public static function init()
    {
        if(!is_null(self::$pool)){
            return;
        }
        if(defined('PHOENIX_CONNECTION_FILE') && file_exists(PHOENIX_CONNECTION_FILE)){
            self::$pool = json_decode(file_get_contents(PHOENIX_CONNECTION_FILE), true);
            //return;
        }
        if(!defined('PHOENIX_CONNECTION_DIR') || !file_exists(PHOENIX_CONNECTION_DIR)){
            return;
        }
        $handle = opendir(PHOENIX_CONNECTION_DIR);
        if(empty($handle)){
            return;
        }
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..' || $entry == 'log') {
                continue;
            }
            if(!is_dir(PHOENIX_CONNECTION_DIR . $entry. DIRECTORY_SEPARATOR . PHOENIX_ENV)){
                continue;
            }
            $files = scandir(PHOENIX_CONNECTION_DIR . $entry . DIRECTORY_SEPARATOR. PHOENIX_ENV);
            foreach($files as $item){
                if ($item == '.' || $item == '..') {
                    continue;
                }
                $content = parse_ini_file(PHOENIX_CONNECTION_DIR . $entry . DIRECTORY_SEPARATOR. PHOENIX_ENV . DIRECTORY_SEPARATOR . $item, true);
                if(!isset($content['role']['roles']) && !isset($content['role']['srv'])){
                    continue;
                }
                $roleStr = $content['role']['roles'] ?? $content['role']['srv'];
                $roles = array_map('trim', explode(',', $roleStr));
                $service = str_replace('.ini', '', $item);
                foreach($roles as $role){
                    if(isset($content['role']['srv'])){
                        self::$pool[$entry][$service][$role] = $content[$role];
                        continue;
                    }
                    $roleArr = explode(',', $content['role'][$role]);
                    foreach($roleArr as $roleName){
                        if(!isset($content[$roleName])){
                            continue;
                        }
                        self::$pool[$entry][$service][$role][] = $content[$roleName];
                    }
                }
            }
        }
        closedir($handle);
    }
    
    public static function getMysql($name = '')
    {
        self::init();
        if($name){
            if(isset(self::$pool['mysql'][$name])){
                return self::$pool['mysql'][$name];
            }
            if(isset(self::$pool['mysql']['*'])){
                return self::$pool['mysql']['*'];
            }
            return array();
        }
        return self::$pool['mysql'];
    }
    /**
     * 获取Redis配置
     * @return type
     */
    public static function getRedis($name = '')
    {
        self::init();
        if($name){
            $name = self::getNameMap('redis', $name);
            if(isset(self::$pool['redis'][$name])){
                return self::$pool['redis'][$name];
            }
            if(isset(self::$pool['redis']['*'])){
                return self::$pool['redis']['*'];
            }
            return array();
        }
        return self::$pool['redis'];
    }

    /**
     * 获取Mongo配置
     * @return type
     */
    public static function getMongo()
    {
        $type = self::CONFIG_MONGO;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $dbConfig = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        foreach($dbConfig as $k => $v){
            if(is_string($v) && isset($connectionConfig[$type][$v])){
                $dbConfig[$k] = $connectionConfig[$type][$v];
            }
        }
        self::$storage[$type] = $dbConfig;
        return self::$storage[$type];
    }

    /**
     * 获取数据库中各表的分表配置情况
     * @return type
     */
    public static function getTables()
    {
        if(!is_null(self::$tables)){
            return self::$tables;
        }
        if(!defined('PHOENIX_CONFIG_PATH') || !file_exists(PHOENIX_CONFIG_PATH . 'tables.json')){
            self::$tables = array();
            return self::$tables;
        }
        $detail = json_decode(trim(file_get_contents(PHOENIX_CONFIG_PATH . 'tables.json')), true);
        self::$tables = $detail;
        return self::$tables;
    }
    
    public static function getService($name0)
    {
        self::init();
        $name = strtolower($name0);
        if(isset(self::$pool['swoole'][$name]) && count(self::$pool['swoole'][$name]) == 1){
            return current(self::$pool['swoole'][$name]);
        }
        return array();
    }
    /**
     * 获取代码中的库名=>实际库名的映射
     * @param string $resourceType mysql, redis, etc
     * @param string $name
     * @return mixed
     */
    public static function getNameMap($resourceType, $name)
    {
        if(class_exists('\App\Config\Common') && defined('\App\Config\Common::MAP_'. ucwords($resourceType))){
            $map = constant('\App\Config\Common::MAP_'. ucwords($resourceType));
            if(isset($map[$name])){
                return $map[$name];
            }
            return $name;
        }
        return $name;
    }

    public static function getAll()
    {
        self::init();
        return self::$pool;
    }

    /**
     * 获取Socket配置
     * @return type
     */
    public static function getSocket()
    {
        $type = self::CONFIG_MONGO;
        if(isset(self::$storage[$type])){
            return self::$storage[$type];
        }
        $dbConfig = self::getConfig($type);
        $connectionConfig = self::getCommon(self::CONFIG_CONNECTION);
        foreach($dbConfig as $k => $v){
            if(is_string($v) && isset($connectionConfig[$type][$v])){
                $dbConfig[$k] = $connectionConfig[$type][$v];
            }
        }
        self::$storage[$type] = $dbConfig;
        return self::$storage[$type];
    }
}