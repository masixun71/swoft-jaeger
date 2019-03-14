<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Manager;

use ExtraSwoft\Jaeger\Sampler\SwooleProbabilisticSampler;
use ExtraSwoft\Jaeger\Transport\JaegerTransportLog;
use ExtraSwoft\Jaeger\Transport\JaegerTransportUdp;
use Jaeger\Config;
use const OpenTracing\Formats\TEXT_MAP;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Value;
use Swoft\Core\Coroutine;
use Swoft\Core\RequestContext;

/**
 *
 * @Bean()
 * Class TracerManager
 * @package ExtraSwoft\Jaeger\Manager
 */
class TracerManager
{

    protected $serverSpans = [];
    protected $configs;

    public function init()
    {
        $config = Config::getInstance();
        $config->setSampler(new SwooleProbabilisticSampler(env('JAEGER_RATE'), $this->getIp(), env('HTTP_PORT')));

        $mode = env('JAEGER_MODE', 1);
        if ($mode == 1) {
            $config->setTransport(new JaegerTransportUdp(env('JAEGER_SERVER_HOST'), 8000));
        } elseif ($mode == 2) {
            $config->setTransport(new JaegerTransportLog(4000));
        } else {
            throw new \Exception("jaeger's mode is not set");
        }
        $tracer = $config->initTrace(env('PNAME'));

        GlobalTracer::set($tracer); // optional
        $this->configs = $config;
    }


    public function setServerSpan($span)
    {
        $cid = Coroutine::tid();

        $this->serverSpans[$cid] = $span;
    }

    public function getServerSpan()
    {
        $cid = Coroutine::tid();

        if (!isset($this->serverSpans[$cid]))
        {
            return null;
        }

        return $this->serverSpans[$cid];
    }


    public function getHeader()
    {
        $headers = [];
        $cid = Coroutine::tid();
        GlobalTracer::get()->inject($this->serverSpans[$cid]->getContext(), TEXT_MAP,
            $headers);

        return $headers;
    }


    public function flush()
    {
        $config = $this->configs;
        $cid = Coroutine::tid();
        swoole_timer_after(1000, function () use ($config, $cid) {
            $config->flush();
            unset($this->serverSpans[$cid]);
        });
    }


    private function getIp()
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