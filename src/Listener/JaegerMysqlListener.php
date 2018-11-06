<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Listener;


use ExtraSwoft\Jaeger\Manager\TracerManager;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Listener;
use Swoft\Core\Coroutine;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Exception\Exception;


/**
 * http request
 *
 * @Listener("Mysql")
 */
class JaegerMysqlListener implements EventHandlerInterface
{

    protected $profiles = [];


    /**
     * @param EventInterface $event
     * @throws Exception
     */
    public function handle(EventInterface $event)
    {
        if (empty(\Swoft::getBean(TracerManager::class)->getServerSpan()))
        {
            return;
        }

        $profileKey = $event->getParams()[0];
        $cid = Coroutine::tid();
        if ($event->getTarget() == 'start') {
            $sql = $event->getParams()[1];

            $tag = [
                'profileKey' => $profileKey,
                'sql' => $sql
            ];


            $this->profiles[$cid][$profileKey]['span'] = GlobalTracer::get()->startSpan('mysql',
                [
                    'child_of' => \Swoft::getBean(TracerManager::class)->getServerSpan(),
                    'tags' => $tag
                ]);
        } else {

            $this->profiles[$cid][$profileKey]['span']->finish();
        }

    }
}