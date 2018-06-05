<?php
namespace Phoenix\Framework\Util;

class Helper
{

    /**
     * <p>msgpack压缩数据
     * @param mixed $data
     * @return mixed
     */
    public static function encode($data, $dataType = 'msgpack')
    {
        if($dataType == 'json'){
            return json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        return msgpack_pack($data);
    }

    /**
     * <p>msgpack解压数据
     * @param mixed $str
     * @return mixed
     */
    public static function decode($str, $dataType = 'msgpack')
    {
        if(!is_string($str)){
            return $str;
        }
        if($dataType == 'json'){
            return json_decode($str, true);
        }
        return msgpack_unpack($str);
    }

    public static function deflate($data)
    {
        return gzdeflate($data, 9);
    }

    public static function inflate($data)
    {
        return gzinflate($data);
    }

    public static function array2Bytes(array $data)
    {
        array_unshift($data, 'S*');
        $str = call_user_func_array('pack', $data);
        return $str;
    }

    public static function bytes2Array($data)
    {
        $ret = unpack('S*', $data);
        return array_values($ret);
    }
    
    /**
     * 获得num个比特位全部为1的掩码
     * @param int $num
     * @return int
     */
    public static function bitmask($num)
    {
        $ret = 0;
        while($num){
            $ret |= (1 << ($num - 1));
            $num--;
        }
        return $ret;
    }
    
    /**
     * 把数组$args中每个元素从左到右，按顺序把每个数字按它所占的bit位数，编码到一个数字里
     * @return int
     */
    public static function encode2Bit($args, $bits)
    {
        $ret = 0;
        foreach($bits as $i => $num){
            if($i){
                $ret <<= $bits[$i];
            }
            $ret |= ((int)array_shift($args)) & self::bitmask($num);
        }
        return $ret;
    }
    /**
     * 把数字按配置的bit位数的要求，分解成数组
     * @param int $input
     * @return array
     */
    public static function decodeFromBit($input, $bits)
    {
        $ret = array();
        $tmp = $bits;
        while($tmp){
            $num = array_pop($tmp);
            array_unshift($ret, $input & self::bitmask($num));
            $input >>= $num;
        }
        return $ret;
    }

    /**
     * <p>数据压缩
     * @param type $data
     * @param type $level
     * @return type
     */
    public static function compress($data, $level = 9)
    {
        return gzdeflate(json_encode($data), $level);
    }

    /**
     * <p>数据解压
     */
    public static function uncompress($data)
    {
        $ret = json_decode(gzinflate($data), true);
        return $ret;
    }

    /**
     * <p>四舍五入, 两位小数
     * @param float $val
     * @return float
     */
    public static function format($val)
    {
        return round($val, 2, PHP_ROUND_HALF_UP);
    }
    /**
     * 带毫秒数的日期
     * @return string
     */
    public static function date($format = 'Y-m-d H:i:s')
    {
        list($m1, ) = self::explode(' ', microtime());
        return date($format) . substr($m1, 1, 5);
    }
    public static function var_dump(...$data)
    {
        if(PHP_SAPI == 'cli'){
            var_dump(...$data);
        }else{
            echo "<pre>";
            var_dump(...$data);
            echo "</pre>";
        }
    }

    /**
     * 按fields的数量array_combile，避免出现警告
     * @return array
     */
    public static function array_combine($fields, $values)
    {
        if(count($fields) == count($values)){
            return array_combine($fields, $values);
        }
        $ret = array();
        foreach($fields as $key){
            $ret[$key] = array_shift($values);
        }
        return $ret;
    }
    
    public static function array_merge(...$param)
    {
        if(count($param) == 1){
            return (array)$param[0];
        }
        $tmp = array();
        foreach($param as $arr){
            if(empty($arr)){
                continue;
            }
            $tmp[] = (array)$arr;
        }
        return call_user_func_array('array_merge', $tmp);
    }

    public static function ratioRadom(array $arr)
    {
        $sum = array_sum($arr);
        $rand = mt_rand(0, $sum - 1);
        $i = 0;
        foreach($arr as $k => $v){
            $i += $v;
            if($i >= $rand){
                return $k;
            }
        }
        return 0;
    }

    /**
     * <p>分割字符串
     * @param string $delimeter
     * @param string $str
     * @return array
     */
    public static function explode($delimeter, $str, $limit = null)
    {
        if(is_array($str)){
            return $str;
        }
        $str = trim($str);
        if ($str) {
            if(is_null($limit)){
                return explode($delimeter, $str);
            }
            return explode($delimeter, $str, $limit);
        }
        return array();
    }
    
    public static function rowExplode($delimeter, $str, $limit = PHP_INT_MAX)
    {
        if(is_array($str)){
            return $str;
        }
        if (!$str) {
            return array();
        }
        if(!is_array($delimeter)){
            return self::explode($delimeter, $str, $limit);
        }
        $first = array_shift($delimeter);
        $tmp = self::explode($first, $str, $limit);
        $second = array_shift($delimeter);
        if(!$second){
            return $tmp;
        }
        $ret = array();
        foreach($tmp as $line){
            $ret[] = self::explode($second, $line, $limit);
        }
        return $ret;
    }
    public static function parseConfig($str, $delimeter = ',', $delimeter2 = ':')
    {
        $ret = array();
        $tmp = self::explode($delimeter, $str);
        foreach($tmp as $line){
            list($k, $v) = self::explode($delimeter2, $line);
            $ret[(int)$k] = (int)$v;
        }
        return $ret;
    }
    /**
     * 正则分割字符串
     * @param type $delimeter
     * @param type $str
     * @return type
     */
    public static function split($delimeter, $str)
    {
        if ($str) {
            return preg_split('#'.$delimeter.'#', trim($str));
        }
        return array();
    }
    
    public static function interLeave($x, $y)
    {
        $x0 = sprintf('%09b', $x);
        $y0 = sprintf('%09b', $y);
        $ret = array();
        for($i = 0; $i < 9; ++$i){
            array_push($ret, (int)$x0[$i]);
            array_push($ret, (int)$y0[$i]);
        }
        return implode('', $ret);
    }

    public static function calTime($class, $line)
    {
        static $total = 0;
        static $time = null;
        if (is_null($time)) {
            $time = microtime(true);
            echo $class . ':' . $line . ': 0' .  "\n";
            return;
        }
        $now = microtime(true);
        $t = 1000*($now - $time);
        $time = $now;
        $total += $t;
        echo $class . ':' . $line . ': ' . self::format($t) . ' '. self::format($total). "\n";
    }
    //marchinfo.unit[0].id
    //marchinfo.unit[0].num
    public static function parseString(&$result, $param, $value)
    {
        $arr = explode('.', $param);
        $obj = &$result;
        foreach($arr as $val){
            if(false !== ($pos = strpos($val, '['))){
                $name = substr($val, 0, $pos);
                $endPos = strpos($val, ']');
                $index = substr($val, $pos + 1, $endPos - $pos - 1);
                if(!isset($obj[$name])){
                    $obj[$name] = array();
                }
                if(!isset($obj[$name][$index])){
                    $obj[$name][$index] = array();
                }
                $obj = &$obj[$name][$index];
            }else{
                if(!isset($obj[$val])){
                    $obj[$val] = '';
                }
                $obj = &$obj[$val];
            }
        }
        $obj = $value;
    }
    /**
     * 递归检查两个数组的不同之处
     * @param type $first
     * @param type $second
     * @param type $from 输出的key值前缀
     * @return type
     */
    public static function diffArray($first, $second, $from = '')
    {
        $str = '';
        if (is_null($second)) {
            $str .= "param $from is null.\n";
            return $str;
        }
        $index = empty($from) ? '' : $from.'.';
        foreach ($first as $key => $value) {
            if (!isset($second[$key])) {
                $val = var_export($value, true);
                $str .= "miss key: {$index}{$key}={$val}.\n";
                continue;
            }
            if (is_array($value)) {
                $str .= self::diffArray($value, $second[$key], $index.$key);
            } else {
                if ($value !== $second[$key]) {
                    $str .= "{$index}{$key}, {$value} !== {$second[$key]}\n";
                }
            }
        }
        return $str;
    }

}