<?php
namespace Phoenix\Web;

use Phoenix\Framework\Route\Dispatcher;
use Phoenix\Framework\Route\Response;
/**
 * Description of Route
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Route
{
    public static function execute()
    {
        Response::succ(Dispatcher::getInstance()->routes());
    }
}
