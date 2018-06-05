<?php
namespace Phoenix\Service\Tcp;

/**
 * Description of TcpServer
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
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


$socket = socket_create(AF_INET, SOCK_STREAM, 0);

$bind   = socket_bind($socket, '127.0.0.1', '33307');

$listen = socket_listen($socket, 10);

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//非阻塞模式
socket_set_nonblock($socket);

$arrSocket = array($socket);

$wfds = array();

do {

    $rs = $arrSocket; 

    $ws = $wfds; 

    $es = array();

    $ret = socket_select($rs, $ws, $es, 20);

    foreach ($rs as $fd) {
        if($fd == $socket){ 
 
            $cfd = socket_accept($socket); 
 
            socket_set_nonblock($cfd); 
 
            $rfds[] = $cfd;

            $msg = socket_read($cfd, 1024);

            echo $msg;

            socket_write($cfd, "hello\n");
 
            echo "new client coming, fd=$cfd\n"; 
 
        }else{ 
 
            $msg = socket_read($fd, 1024); 
 
            if($msg <= 0){ 
 
                echo 'close';
 
            }else{ 
 
                //recv msg 
 
                echo "on message, fd=$fd data=$msg\n"; 
 
            }
        }
    }

    echo time()."\n";
} while (true);

