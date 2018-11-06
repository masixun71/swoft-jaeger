<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Manager;

use Jaeger\Config;
use Jaeger\Sampler\ProbabilisticSampler;
use const OpenTracing\Formats\TEXT_MAP;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Value;
use Swoft\Core\Coroutine;

/**
 * @Bean()
 * Class TracerManager
 */
class TracerManager
{

    protected $serverSpans = [];

    /** @var Config $config */
    protected $config;

    public function init()
    {
        $config = Config::getInstance();
        $config->setSampler(new ProbabilisticSampler(env('JAEGER_RATE')));
        $tracer = $config->initTrace(env('PNAME'), env('JAEGER_SERVER_HOST'));

        GlobalTracer::set($tracer); // optional
        $this->config = $config;
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
        $config = $this->config;
        swoole_timer_after(1000, function () use ($config) {
            $config->flush();
        });
    }


}