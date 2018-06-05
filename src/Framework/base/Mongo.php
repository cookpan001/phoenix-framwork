<?php
namespace Phoenix\Framework\Base;

class Mongo
{
    private static $pool = array();
    private $name = '';
    private $client = null;
    
    private function __construct($name, $client)
    {
        $this->name = $name;
        $this->client = $client;
    }

    public static function getInstance($name)
    {
        if(isset(self::$pool[$name])){
            return self::$pool[$name];
        }
        $client = self::connect($name);
        if(empty($client)){
            return false;
        }
        self::$pool[$name] = new self($name, $client);
        return self::$pool[$name];
    }
    
    /**
     * 连接
     * @param dbname
     * @return object
     */
    public static function connect($name)
    {
        $config = Config::getMongo();
        if(!isset($config[$name])){
            return false;
        }
        $password = isset($config[$name]['password']) ? $config[$name]['password'] : 0;
        $client = new \MongoClient('mongodb://' . $config[$name]['host'] . ':' . $config[$name]['port'], [
                'username' => $config[$name]['username'],
                'password' => $config[$name]['password'],
                'database' => $config[$name]['database'],
            ]);
        if (!$client) {
            continue;
        }
        $database = $client->selectDatabase($config[$name]['database']);
        return $database;
    }


    /**
    * 查询表中所有数据
    * @param $table
    * @param array $where
    * @param array $sort
    * @param string $limit
    * @param string $skip
    * @return array|int
    */
    public function getAll($table, $where = array(), $sort = array(), $limit = '', $skip = '') 
    {
        if (!empty($where)) {
          $data = self::$pool[$name]->$table->find($where);
        } else {
          $data = self::$pool[$name]->$table->find();
        }
        if (!empty($sort)) {
          $data = $data->sort($sort);
        }
        if (!empty($limit)) {
          $data = $data->limit($limit);
        }
        if (!empty($skip)) {
          $data = $data->skip($skip);
        }
        $newData = array();
        while ($data->hasNext()) {
          $newData[] = $data->getNext();
        }
        if (count($newData) == 0) {
          return 0;
        }
        return $newData;
    }
    /**
    * 查询指定一条数据
    * @param $table
    * @param array $where
    * @return int
    */
    public function getOne($table, $where = array())
    {
        if (!empty($where)) {
          $data = self::$pool[$name]->$table->findOne($where);
        } else {
          $data = self::$pool[$name]->$table->findOne();
        }
        return $data;
    }
    /**
    * 统计个数
    * @param $table
    * @param array $where
    * @return mixed
    */
    public function getCount($table, $where = array()) 
    {
        if (!empty($where)) {
          $data = self::$pool[$name]->$table->find($where)->count();
        } else {
          $data = self::$pool[$name]->$table->find()->count();
        }
        return $data;
    }
    /**
    * 直接执行mongo命令
    * @param $sql
    * @return array
    */
    public function toExcute($sql) 
    {
        $result = self::$pool[$name]->execute($sql);
        return $result;
    }
    /**
    * 分组统计个数
    * @param $table
    * @param $where
    * @param $field
    */
    public function groupCount($table, $where, $field) 
    {
        $cond = array(
          array(
            '$match' => $where,
          ),
          array(
            '$group' => array(
              '_id' => '$' . $field,
              'count' => array('$sum' => 1),
            ),
          ),
          array(
            '$sort' => array("count" => -1),
          ),
        );
        self::$pool[$name]->$table->aggregate($cond);
    }
    /**
    * 删除数据
    * @param $table
    * @param $where
    * @return array|bool
    */
    public function toDelete($table, $where) 
    {
        $re = self::$pool[$name]->$table->remove($where);
        return $re;
    }
    /**
    * 插入数据
    * @param $table
    * @param $data
    * @return array|bool
    */
    public function toInsert($table, $data) 
    {
        $re = self::$pool[$name]->$table->insert($data);
        return $re;
    }
    /**
    * 更新数据
    * @param $table
    * @param $where
    * @param $data
    * @return bool
    */
    public function toUpdate($table, $where, $data) 
    {
        $re = self::$pool[$name]->$table->update($where, array('$set' => $data));
        return $re;
    }
    /**
    * 获取唯一数据
    * @param $table
    * @param $key
    * @return array
    */
    public function distinctData($table, $key, $query = array()) 
    {
        if (!empty($query)) {
          $where = array('distinct' => $table, 'key' => $key, 'query' => $query);
        } else {
          $where = array('distinct' => $table, 'key' => $key);
        }
        $data = self::$pool[$name]->command($where);
        return $data['values'];
    }
}

