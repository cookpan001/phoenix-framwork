<?php
namespace Phoenix\Framework\Codec;

use Phoenix\Framework\Base\Codec;

class Redis implements Codec
{
    const END = "\r\n";
    
    public function encode(...$data)
    {
        return $this->serialize($data);
    }
    
    public function serialize($data)
    {
        if(is_string($data) && $data[0] == '-'){//Error
            return $data.self::END;
        }
        if($data instanceof \Throwable){//Error
            return '-'.$data->getCode() . ' ' . $data->getMessage().self::END;
        }
        if($data instanceof TimeoutException){
            return '*-1'.self::END;
        }
        if(is_int($data)){
            return ':'.$data.self::END;
        }
        if(is_string($data) && $data == 'OK'){
            return '+'.$data.self::END;
        }
        if(is_string($data)){
            return '$'.strlen($data).self::END.$data.self::END;
        }
        if(is_null($data)){
            return '$-1'.self::END;
        }
        $count = count($data);
        $str = '*'.$count.self::END;
        foreach($data as $line){
            if(is_null($line)){
                $str .= '$-1'.self::END;
            }else if(is_int($line)){
                $str .= ':'.$line.self::END;
            }else if(is_array($line)){
                $str .= $this->serialize($line);
            }else{
                $str .= '$'.strlen($line).self::END.$line.self::END;
            }
        }
        return $str;
    }

    public function parse(&$response, &$cur = 0)
    {
        $pos = strpos($response, self::END, $cur);
        if(false === $pos){
            $ret = preg_split('#\s+#', $response);
            $cur = strlen($response);
            return $ret;
        }
        $ret = null;
        switch ($response[$cur]) {
            case '-' : // Error message
                $ret = substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                break;
            case '+' : // Single line response
                $ret = substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                break;
            case ':' : //Integer number
                $ret = (int)substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                break;
            case '$' : //bulk string or null
                $len = (int)substr($response, $cur + 1, $pos - $cur - 1);
                if($len == -1){
                    $ret = null;
                    $cur = $pos + 2;
                }else{
                    $ret = substr($response, $pos + 2, $len);
                    $cur = $pos + 2 + $len + 2;
                }
                break;
            case '*' : //Bulk data response
                $length = (int)substr($response, $cur + 1, $pos - $cur - 1);
                $cur = $pos + 2;
                if($length == -1){
                    $ret = array();//empty array
                    break;
                }
                if($length == 0){
                    $ret = array();//empty array
                    break;
                }
                for ($c = 0; $c < $length; $c ++) {
                    //$cur += 1;
                    //echo substr($response, $cur);
                    $ret[] = $this->parse($response, $cur);
                }
                break;
            default :
                $ret = substr($response, $cur, $pos - $cur);
                $cur = $pos + 2;
                break;
        }
        return $ret;
    }
    
    public function unserialize($str)
    {
        $ret = array();
        if("\r\n" == $str || "\r" == $str || "\n" == $str || '' == $str){
            return $ret;
        }
        $cur = 0;
        while($cur < strlen($str)){
            $ret[] = $this->parse($str, $cur);
        }
        return $ret;
    }
}