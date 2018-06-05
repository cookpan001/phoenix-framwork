<?php
$fp = stream_socket_client("tcp://127.0.0.1:33307", $errno, $errstr, 30);
fwrite($fp,"xll\r\ndlzxlzll\r\n\r\n");

swoole_event_add($fp, function($fp) {
    swoole_event_defer(function(){
        echo microtime(true)."一\n";
    });
    $resp = fread($fp, 8192);

    echo "$resp";
    //socket处理完成后，从epoll事件中移除socket
    //fwrite($fp,"GET / HTTP/1.1\r\nHost: www.qq.com\r\n\r\n");
    swoole_event_del($fp);
    fclose($fp);
    echo microtime(true)."一\n";
});
echo "Finish\n";  //swoole_event_add不会阻塞进程，这行代码会顺序执行