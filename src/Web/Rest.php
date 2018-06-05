<?php
namespace Phoenix\Web;

use Phoenix\Framework\Route\Request;
use Phoenix\Framework\Route\Response;

class Rest
{
    private $isAjax = false;
    
    function __construct()
    {
        if(Request::request('dataType'))
        {
            $this->isAjax = true;
            call_user_func_array(array($this, Request::request('op').'Action'), array());
        }
    }
    
    public static function execute()
    {
        ob_start();
        $app = new self();
        if(!$app->isAjax()){
            include __DIR__ . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'rest.html';
        }
        $str = ob_get_contents();
        Response::succ($str, true);
        ob_clean();
    }
    
    public function isAjax()
    {
        return $this->isAjax;
    }
    
    function run()
    {
        if(empty(Request::request('rawOutput')))
        {
            echo '<textarea id="result">';
        }
        if (!empty(Request::request('op')))
        {
            $op = Request::request('op') . 'Action';
            if (!method_exists($this, $op))
            {
                echo "method not exists.<br/>";
                return;
            }

            $ret = call_user_func_array(array($this, $op), array());
            if (is_array($ret) || is_object($ret) || is_resource($ret))
            {
                var_export($ret);
            } else
            {
                echo $ret;
            }
        }
        if(empty(Request::request('rawOutput')))
        {
            echo '</textarea>';
        }
    }
    
    function internalAction()
    {
        $request = Request::request();
        echo json_encode($request);
    }
}