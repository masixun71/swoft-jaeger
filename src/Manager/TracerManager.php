<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Manager;

use ExtraSwoft\Jaeger\Sampler\SwooleProbabilisticSampler;
use ExtraSwoft\Jaeger\Transport\JaegerTransportUdp;
use Jaeger\Config;
use const OpenTracing\Formats\TEXT_MAP;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Value;
use Swoft\Core\Coroutine;
use Swoft\Core\RequestContext;

/**
 * Class TracerManager
 */
class TracerManager
{

    protected static $serverSpans = [];
    protected static $configs = [];

    public static function init()
    {
        $cid = Coroutine::tid();
        if (isset(self::$configs[$cid]))
        {
            return;
        } else {
            $config = Config::getInstance();
            $config->setSampler(new SwooleProbabilisticSampler(env('JAEGER_RATE'), self::getIp(), env('HTTP_PORT')));
            $config->setTransport(new JaegerTransportUdp(env('JAEGER_SERVER_HOST'), 8000));
            $tracer = $config->initTrace(env('PNAME'), env('JAEGER_SERVER_HOST'));

            GlobalTracer::set($tracer); // optional
            self::$configs[$cid] = $config;
            return;
        }
    }


    public static function setServerSpan($span)
    {
        $cid = Coroutine::tid();

        self::$serverSpans[$cid] = $span;
    }

    public static function getServerSpan()
    {
        $cid = Coroutine::tid();

        if (!isset(self::$serverSpans[$cid]))
        {
            return null;
        }

        self::$serverSpans[$cid];
    }


    public static function getHeader()
    {
        $headers = [];
        $cid = Coroutine::tid();
        GlobalTracer::get()->inject(self::$serverSpans[$cid]->getContext(), TEXT_MAP,
            $headers);

        return $headers;
    }


    public static function flush()
    {
        $cid = Coroutine::tid();
        $config = self::$configs[$cid];
        swoole_timer_after(1000, function () use ($config) {
            $config->flush();
        });
    }


    private static function getIp()
    {
        $result = shell_exec("/sbin/ifconfig");
        if (preg_match_all("/inet (\d+\.\d+\.\d+\.\d+)/", $result, $match) !== 0)  // 这里根据你机器的具体情况， 可能要对“inet ”进行调整， 如“addr:”，看如下注释掉的if
        {
            foreach ($match [0] as $k => $v) {
                if ($match [1] [$k] != "127.0.0.1") {
                    return $match[1][$k];
                }
            }
        }
        return '127.0.0.1';
    }

}