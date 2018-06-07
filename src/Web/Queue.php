<?php
namespace Phoenix\Web;

/**
 * Description of Queue
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
class Queue
{
    public static function execute($cmd)
    {
        if(!method_exists(__CLASS__, $cmd.'Action')){
            Response::error('command not found', 404);
            return;
        }
        
    }
    
    public static function listAction()
    {
        
    }
}
