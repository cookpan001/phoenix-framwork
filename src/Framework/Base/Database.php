<?php
namespace Phoenix\Framework\Base;

class Database
{
    const DB_NAME = 'main';
    
    protected static $pool = array();
    protected static $data_fields = array();
    protected static $partitions = null;
    protected static $connMap = array();
    protected static $md5s = array();
    /**
     * 获取数据库连接配置
     * @param boolean $master
     * @param string $dbname
     * @return mixed
     */
    public static function getConf($master = false, $dbname = '')
    {
        $dbConfig = Config::getMysql($dbname);
        if(empty($dbConfig)){
            return false;
        }
        if(isset($dbConfig['host'])){
            return $dbConfig;
        }
        if($master && isset($dbConfig['master'])){
            $setting = $dbConfig['master'];
        }else if(!$master && isset($dbConfig['slave'])){
            $setting = $dbConfig['slave'];
        }
        if(!isset($setting)){
            return false;
        }
        $selected = array_rand($setting);
        return $setting[$selected];
    }
    
    public static function init()
    {
        if(!is_null(self::$partitions)){
            return;
        }
        self::$partitions = Config::getTables();
        foreach(self::$partitions as $t => $info){
            self::$partitions[$t]['num'] = array_sum($info['database']);
        }
    }

    public static function getConnection($master = false, $dbName = '', $driver = null)
    {
        if(empty($dbName)){
            $dbName = static::DB_NAME;
        }
        if(!isset(self::$connMap[$dbName][$master])){
            $conf = self::getConf($master, $dbName);
            self::$connMap[$dbName][$master] = $conf;
        }else{
            $conf = self::$connMap[$dbName][$master];
        }
        if(empty($conf)){
            return false;
        }
        $realDbName = Config::getNameMap('mysql', $dbName);
        if(isset(self::$pool[$conf['host']][$conf['port']])){
            $db = self::$pool[$conf['host']][$conf['port']];
            $ret = $db->changeDb($realDbName);
            if($ret){
                return $db;
            }
            $conf = self::getConf($master, $dbName);
            self::$connMap[$dbName][$master] = $conf;
        }
        $type = 'mysqli';
        if($driver){
            $type = $driver;
        }else if(defined('PHOENIX_MYSQL_DRIVER')){
            $type = PHOENIX_MYSQL_DRIVER;
        }
        if($type == 'pdo'){
            $db = new Pdo($conf['host'], $conf['port'], $conf['user'], $conf['password'], $realDbName);
        }else if ($type == 'comysql'){
            $db = new CoMysql($conf['host'], $conf['port'], $conf['user'], $conf['password'], $realDbName);
        }else{
            $db = new Mysqli($conf['host'], $conf['port'], $conf['user'], $conf['password'], $realDbName);
        }
        $db->connect();
        self::$pool[$conf['host']][$conf['port']] = $db;
        self::$connMap[$dbName][$master] = $conf;
        self::$md5s[$dbName][$master] = md5($conf['host'].':'.$conf['port'].':'.$dbName);
        return $db;
    }
    /**
     * 单表返回表名(字符串), 多表多库返回 $ret[serial] = tableName;
     * @param type $arr where条件数组 或 需要insert的数据
     * @return array
     */
    public static function getTableName($arr = array(), $table = '')
    {
        self::init();
        if(empty($table)){
            $table = static::TABLE_NAME;
        }
        //没有多表配置, 认为只有一张表
        if(!isset(self::$partitions[$table])){
            return array($table);
        }
        //有多表配置, 且只有一张表
        $conf = self::$partitions[$table];
        if(self::$partitions[$table]['num'] == 1){
            return array($table);
        }
        return self::parse($arr, $conf, $table);
    }
    
    public static function parse($arr, $conf, $table = '')
    {
        if(empty($table)){
            $table = static::TABLE_NAME;
        }
        $format = '_%03d';
        //有主键
        if(isset($conf['primary']) && isset($arr[$conf['primary']])){
            if(!is_array($arr[$conf['primary']])){
                $serial = self::getSerialNum($conf, $arr[$conf['primary']]);
                return array($serial => $table . sprintf($format, $serial));
            }
            $tmp = array();
            foreach($arr[$conf['primary']] as $val){
                $serial = self::getSerialNum($conf, $val);
                if(!isset($tmp[$serial])){
                    $tmp[$serial] = $table . sprintf($format, $serial);
                }
            }
            return $tmp;
        }
        foreach ($arr as $key => $value) {
            if(is_int($key) && is_array($value)){
                $ret = array();
                foreach ($arr as $v) {
                    $ret += self::parse($v, $conf);
                }
                return $ret;
            }else if(is_int($key) && !is_array($value)){//需要解析的where条件
                $i = 0;
                $tmp = array();
                while($i < $conf['num']){
                    $serial = self::getSerialNum($conf, $i);
                    $tmp[$serial] = $table . sprintf($format, $serial);
                    ++$i;
                }
                return $tmp;
            }
        }
        if(empty($arr)){
            $i = 0;
            $tmp = array();
            while($i < $conf['num']){
                $serial = self::getSerialNum($conf, $i);
                $tmp[$serial] = $table . sprintf($format, $serial);
                ++$i;
            }
            return $tmp;
        }
        return array(1 => $table . sprintf($format, 1));
    }
    /**
     * 获取多表时的序号
     * @param type $conf
     * @param type $val
     * @return string
     */
    public static function getSerialNum($conf, $val)
    {
        if($conf['type'] == 'mod'){
            $mod = $val % $conf['num'];
            return $mod;
        }
        if($conf['type'] == 'range'){
            $mod = intval($val / $conf['step']) + 1;
            return $mod;
        }
        return '';
    }
    /**
     * 获取数据表所有的库名, 因为有可能数据表分布在不同的库里
     * @param type $serial
     * @return string
     */
    public static function getDbName($serial = 0)
    {
        self::init();
        //没有多表配置, 认为只有一张表
        if(!isset(self::$partitions[static::TABLE_NAME])){
            return static::DB_NAME;
        }
        if(!isset(self::$partitions[static::TABLE_NAME]['database'])){
            return static::DB_NAME;
        }
        //使用连接池
        if(!empty(self::$partitions[static::TABLE_NAME]['usePool'])){
            return self::$partitions[static::TABLE_NAME]['usePool'];
        }
        $dbs = self::$partitions[static::TABLE_NAME]['database'];
        if(count($dbs) == 1){
            return key($dbs);
        }
        $index = 0;
        foreach($dbs as $dbName => $num){
            $index += $num;
            if($serial <= $index){
               return $dbName; 
            }
        }
        return '';
    }
    
    public static function checkMulti($tableSetting)
    {
        $ret = array();
        foreach($tableSetting as $serial => $_v){
            $dbName = self::getDbName($serial);
            $ret[$dbName][] = $serial;
        }
        return $ret;
    }
    
    public static function reloadFields()
    {
        self::$data_fields = array();
    }

    public static function fields()
    {
        if(isset(self::$data_fields[static::TABLE_NAME])){
            return self::$data_fields[static::TABLE_NAME];
        }
        $tableSetting= self::getTableName();
        $dbName = self::getDbName(key($tableSetting));
        $table = current($tableSetting);
        $db = self::getConnection(false, $dbName);
        $desc = $db->describe($table);
        self::$data_fields[static::TABLE_NAME] = array_column($desc, 'Field');
        return self::$data_fields[static::TABLE_NAME];
    }
    
    public static function dataType()
    {
        $tableSetting= self::getTableName();
        $dbName = self::getDbName(key($tableSetting));
        $table = current($tableSetting);
        $db = self::getConnection(false, $dbName);
        $desc = $db->describe($table);
        $arr = array_column($desc, 'Type');
        $ret = array();
        foreach($arr as $type){
            if($type != 'point' && (false !== strpos($type, 'int'))){
                $ret[] = 'int';
            }elseif($type != 'point' && (false !== strpos($type, 'float'))){
                $ret[] = 'double';
            }else{
                $ret[] = 'string';
            }
        }
        return $ret;
    }
    
    public static function tables()
    {
        $tableSetting= self::getTableName();
        $dbName = self::getDbName(key($tableSetting));
        $db = self::getConnection(false, $dbName);
        return $db->tables();
    }
    
    public static function desc($table)
    {
        $tableSetting= self::getTableName();
        $dbName = self::getDbName(key($tableSetting));
        $db = self::getConnection(false, $dbName);
        return $db->describe($table);
    }

    /**
     * 同库跨表查询
     * @param type $lines
     */
    public static function multiGet($lines)
    {
        foreach($lines as $line){
            list($table, $where, $option, $fields) = $line;
            $tableSetting = self::getTableName($where);
        }
    }
    /**
     * 同一逻辑表中的查询
     * 支持多表在同一库中时批量查询
     * @param type $where
     * @param type $option
     * @param type $fields
     * @return type
     */
    public static function getData($where = array(), $option = array(), $fields = array())
    {
        $tableSetting = self::getTableName($where);
        $ret = array();
        $count = count($tableSetting);
        $multi = self::checkMulti($tableSetting);
        $option['multi'] = true;
        $id = isset($option['id']) ? $option['id'] : '';
        $overwrite = isset($option['overwrite']) ? $option['overwrite'] : null;
        $value = isset($option['value']) ? $option['value'] : null;
        //多表,无查询条件,需要限制查询数量
        if($count > 1 && empty($where) && !isset($option['limit'])){
            $option['limit'] = intval(2000 / $count);
        }
        foreach($multi as $dbName => $serials){
            $sqls = array();
            $db = self::getConnection(false, $dbName);
            foreach($serials as $serial){
                $tableName = $tableSetting[$serial];
                $sqls[] = $db->select($tableName, $where, $option, $fields);
            }
            if($sqls){
                $tmp = $db->multiQuery($sqls, $id, $overwrite, $value);
                if($count == 1){
                    return $tmp;
                }
                if(isset($tmp[0])){
                    $ret = array_merge($ret, $tmp);
                }else{
                    $ret += $tmp;
                }
            }
        }
        return $ret;
    }
    
    public static function union($whereArr = array(), $option = array(), $fields = array())
    {
        $ret = array();
        $option['multi'] = true;
        $id = isset($option['id']) ? $option['id'] : '';
        $overwrite = isset($option['overwrite']) ? $option['overwrite'] : null;
        $value = isset($option['value']) ? $option['value'] : null;
        $sqls = array();
        foreach($whereArr as $where){
            $tableSetting = self::getTableName($where);
            $multi = self::checkMulti($tableSetting);
            foreach($multi as $dbName => $serials){
                $db = self::getConnection(false, $dbName);
                foreach($serials as $serial){
                    $tableName = $tableSetting[$serial];
                    $sqls[$dbName][] = '(' . $db->select($tableName, $where, $option, $fields) . ')';
                }
            }
        }
        foreach($sqls as $dbName => $ss){
            $db = self::getConnection(false, $dbName);
            $tmp = $db->query(implode(' UNION ', $ss), $id, $overwrite, $value);
            if(isset($tmp[0])){
                $ret = array_merge($ret, $tmp);
            }else{
                $ret += $tmp;
            }
        }
        return $ret;
    }
    
    public static function getOne($where = array(), $option = array(), $fields = array())
    {
        $arRet = self::getData($where, $option, $fields);
        return !empty($arRet) ? $arRet[0] : array();
    }
    
    public static function pluck($where, $key)
    {
        $arRet = self::getOne($where, array(), array($key));
        return isset($arRet[$key]) ? $arRet[$key] : null;
    }
    /**
     * 根据条件返回Key-Value数组, key值相同会被覆盖
     * @param array $where
     * @param mixed $k
     * @param mixed $v
     * @param array $ops
     * @return array
     */
    public static function getKv($where, $k, $v, $ops = array())
    {
        $fields = array_merge((array)$k, (array)$v);
        $option = array('id'=>$k, 'value'=>$v, 'overwrite'=>true);
        foreach($ops as $op => $ov){
            $option[$op] = $ov;
        }
        $arRet = self::getData($where, $option, array_unique($fields));
        return !empty($arRet) ? $arRet : array();
    }
    /**
     * 根据条件返回Key-Value数组, 不会覆盖key值相同的值, Value是数组
     * @param array $where
     * @param mixed $k
     * @param mixed $v
     * @param array $ops
     * @return array
     */
    public static function getMap($where, $k, $v, $ops = array())
    {
        $fields = array_merge((array)$k, (array)$v);
        $option = array('id'=>$k, 'value'=>$v);
        foreach($ops as $op => $ov){
            $option[$op] = $ov;
        }
        $arRet = self::getData($where, $option, array_unique($fields));
        return !empty($arRet) ? $arRet : array();
    }
    
    public static function updateData($vals, $where, $rawKey = array())
    {
        if(empty($where)){
            return true;
        }
        $tableSetting = self::getTableName($where);
        $ret = 0;
        foreach($tableSetting as $serial => $tableName){
            $dbName = self::getDbName($serial);
            $db = self::getConnection(true, $dbName);
            $db->update($tableName, $vals, $where, $rawKey);
            $ret += $db->affectedRow();
        }
        return $ret;
    }
    
    public static function addUploadData($arr, $fields, $rawkey = array())
    {
        $db = self::getConnection(true);
        $db->replace(static::TABLE_NAME, $arr, $rawkey, $fields);
        return $db->affectedRow();
    }
    /**
     * 设置下一个自增ID
     * @param type $id
     */
    public static function setNextId($id)
    {
        $db = self::getConnection(true);
        $db->setNextId(static::TABLE_NAME, $id);
    }
    /**
     * 多表时按各表拆分需要插入的数据
     * @param type $arr
     * @return type
     */
    private static function splitInsertData($arr)
    {
        if(!isset($arr[0])){
            return array(0 => $arr);
        }
        self::init();
        //没有多表配置, 认为只有一张表
        if(!isset(self::$partitions[static::TABLE_NAME])){
            return array(0 => $arr);
        }
        if(!isset(self::$partitions[static::TABLE_NAME]['num'])){
            return array(0 => $arr);
        }
        $conf = self::$partitions[static::TABLE_NAME];
        
        $ret = array();
        foreach($arr as $line){
            $serial = self::getSerialNum($conf, $line[$conf['primary']]);
            $ret[$serial][] = $line;
        }
        return $ret;
    }

    public static function addData($arr, $returnId = true)
    {
        $tableSetting = self::getTableName($arr);
        $data = self::splitInsertData($arr);
        $ret = array();
        $count = count($tableSetting);
        foreach($tableSetting as $serial => $tableName){
            $dbName = self::getDbName($serial);
            $db = self::getConnection(true, $dbName);
            $db->insert($tableName, $data[$serial]);
            if($returnId){
                $ret[] = $db->insertId();
                continue;
            }
            $ret[] = $db->affectedRow();
        }
        if($count == 1){
            return array_pop($ret);
        }
        return $ret;
    }
    
    public static function addDuplicateData($arr, $update, $rawkey = array(), $returnId = true)
    {
        $tableSetting = self::getTableName($arr);
        $data = self::splitInsertData($arr);
        $ret = array();
        $count = count($tableSetting);
        foreach($tableSetting as $serial => $tableName){
            $dbName = self::getDbName($serial);
            $db = self::getConnection(true, $dbName);
            $db->insert($tableName, $data[$serial], $update, $rawkey);
            if($returnId){
                $ret[] = $db->insertId();
                continue;
            }
            $ret[] = $db->affectedRow();
        }
        if($count == 1){
            return array_pop($ret);
        }
        return $ret;
    }
    
    public static function deleteData($where)
    {
        if(empty($where)){
            return true;
        }
        $tableSetting = self::getTableName($where);
        $ret = 0;
        foreach($tableSetting as $serial => $tableName){
            $dbName = self::getDbName($serial);
            $db = self::getConnection(true, $dbName);
            $db->delete($tableName, $where);
            $ret += $db->affectedRow();
        }
        return $ret;
    }
}