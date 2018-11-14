<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Listener;


use ExtraSwoft\Jaeger\Manager\TracerManager;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Inject;
use Swoft\Bean\Annotation\Listener;
use Swoft\Core\Coroutine;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Exception\Exception;


/**
 * http request
 *
 * @Listener("Redis")
 */
class JaegerRedisListener implements EventHandlerInterface
{

    protected $profiles = [];

    /**
     * @Inject()
     * @var TracerManager
     */
    private $tracerManager;

    /**
     * @param EventInterface $event
     * @throws Exception
     */
    public function handle(EventInterface $event)
    {
        if (empty($this->tracerManager->getServerSpan()))
        {
            return;
        }

        $cid = Coroutine::tid();
        if ($event->getTarget() == 'start') {
            $method = $event->getParams()[0];
            $params = $event->getParams()[1];

            $tag = [
                'method' => $method,
                'params' => json_encode($params)
            ];


            $this->profiles[$cid]['span'] = GlobalTracer::get()->startSpan('redis',
                [
                    'child_of' => $this->tracerManager->getServerSpan(),
                    'tags' => $tag
                ]);
        } else {

            $this->profiles[$cid]['span']->finish();
        }

    }
}