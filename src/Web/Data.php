<?php
namespace Phoenix\Web;

use Phoenix\Framework\Route\Response;
/**
 * Description of Data
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Data
{
    private static $cache = [];
    
    public static function execute($database, $table = '', $field = '', $id = '')
    {
        if(empty($table)){
            Response::error('no table specified', 404);
            return;
        }
        if('' === $field){
            Response::error('no primary key specified', 404);
            return;
        }
        if('' === $id){
            Response::error('no key value specified', 404);
            return;
        }
        $fieldArr = explode(',', $field);
        $idArr = explode(',', $id);
        if(count($idArr) % count($fieldArr) != 0){
            Response::error('fields and id number did not match', 404);
            return;
        }
        if(!isset(self::$cache[$database][$table])){
            $classname = null;
            eval("\$classname = new class extends \Phoenix\Framework\Base\Database {
                const DB_NAME = '$database';
                const TABLE_NAME = '$table';
            };");
            self::$cache[$database][$table] = $classname;
        }else{
            $classname = self::$cache[$database][$table];
        }
        $where = [];
        $batch = count($idArr) / count($fieldArr);
        for($i = 0; $i < $batch; ++$i){
            $tmp = [];
            foreach($fieldArr as $f){
                $tmp[$f] = array_shift($idArr);
            }
            $where[] = $tmp;
        }
        $ret = $classname::union($where);
        Response::succ($ret);
    }
}