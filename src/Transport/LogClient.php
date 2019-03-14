<?php

namespace ExtraSwoft\Jaeger\Transport;

use Jaeger\Thrift\AgentClient;

/**
 * 把数据写入到日志
 * @package Jaeger
 */

class LogClient{

    private $logFile = '';

    public function __construct($logFile){

        $this->logFile = $logFile;
    }


    /**
     * @return bool
     */
    public function isOpen(){
        return true;
    }


    /**
     * 发送数据
     * @param $batch
     * @return bool
     */
    public function emitBatch($batch){
        $buildThrift = (new AgentClient())->buildThrift($batch);
        if(isset($buildThrift['len']) && $buildThrift['len'] && $this->isOpen()) {
            $len = $buildThrift['len'];
            $enitThrift = $buildThrift['thriftStr'];
            $str = urlencode($enitThrift);
            $this->aysncWrite($str);
            return true;
        }else{
            return false;
        }
    }

    /**
     * 异步写文件
     *
     * @param string $logFile     日志路径
     * @param string $messageText 文本信息
     */
    private function aysncWrite(string $messageText)
    {
        while (true) {
            $result = \Swoole\Async::writeFile($this->logFile, $messageText);
            if ($result == true) {
                break;
            }
        }
    }


    public function close(){
        return true;
    }
}