<?php
namespace Phoenix\Service\TcpServer;


class TcpServer
{
    protected $host = '127.0.0.1';
    protected $port = 33307;
    protected $fp   = null;
    
    public function __construct()
    {
        $this->fp = stream_socket_server($this->host.':'.$this->port, $errno, $errstr);

        $this->fileHandle();
    }

    /**
     * 操作
     */
    public function fileHandle()
    {
        swoole_event_add($this->fp, function($server) {

            swoole_event_defer(function(){
                echo microtime(true)."一\n";
            });

            $fs = stream_socket_accept($server, 0);

            swoole_event_add($fs, function($cl){

                swoole_event_defer(function(){
                    echo microtime(true)."二\n";
                });

                echo fread($cl, 8192);

                swoole_event_write($cl, 'dlzxlzll'."\r\n");//支持异步发送 
                //fwrite($cl, 'dlzxlzll'."\r\n");
                swoole_event_del($cl);

                fclose($cl);
                echo microtime(true)."二\n";
            });
            echo microtime(true)."一\n";
        });
    }

}

new TcpServer();