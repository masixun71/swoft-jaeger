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
        $serverSpan = $this->tracerManager->getServerSpan();
        if (empty($serverSpan))
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
                    'child_of' => $serverSpan,
                    'tags' => $tag
                ]);
        } else {
            $params = $event->getParams();
            if ((bool)$params[0]) {
                $this->profiles[$cid]['span']->finish();
            } else {
                $this->profiles[$cid]['span']->setTags([
                    'error' => true,
                    'error.msg' => $params[1]
                ]);
                $this->profiles[$cid]['span']->finish();
                \Swoft::getBean(TracerManager::class)->flush();
            }
        }

    }
}