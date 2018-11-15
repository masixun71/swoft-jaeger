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
 * @Listener("Mysql")
 */
class JaegerMysqlListener implements EventHandlerInterface
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
        $profileKey = $event->getParams()[0];
        $cid = Coroutine::tid();
        if ($event->getTarget() == 'start') {
            $sql = $event->getParams()[1];

            $tag = [
                'sql' => $sql
            ];


            $this->profiles[$cid][$profileKey]['span'] = GlobalTracer::get()->startSpan('mysql',
                [
                    'child_of' => $serverSpan,
                    'tags' => $tag
                ]);
            $this->profiles[$cid][$profileKey]['span']->log([
                'profileKey' => $profileKey,
            ]);
        } else {
            $params = $event->getParams();
            if ((bool)$params[1]) {
                $this->profiles[$cid][$profileKey]['span']->finish();
            } else {
                foreach ($this->profiles[$cid] as $v) {
                    $v['span']->setTags([
                        'error' => true,
                        'error.msg' => $params[2]
                    ]);
                    $v['span']->finish();
                }
                \Swoft::getBean(TracerManager::class)->flush();
            }
        }

    }
}