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
use Psr\Http\Message;


/**
 * http request
 *
 * @Listener("HttpClient")
 */
class JaegerHttpClientListener implements EventHandlerInterface
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


        $cid = Coroutine::tid();
        if ($event->getTarget() == 'start') {
            /** @var Message\RequestInterface $request */
            $request = $event->getParams()[0];
            $options = $event->getParams()[1];
            $uri = $request->getUri();


            $tags = [
                'method' => $request->getMethod(),
                'host' => $uri->getHost(),
                'port' => $uri->getPort(),
                'path' => $uri->getPath(),
                'query' => $uri->getQuery(),
                'headers' => !empty($request->getHeaders()) ? json_encode($request->getHeaders()) : ''
            ];

            if ($request->getMethod() != 'GET')
            {
                $tags['body'] = $options['body'];
            }


            $this->profiles[$cid]['span'] = GlobalTracer::get()->startSpan('httpRequest',
                [
                    'child_of' => \Swoft::getBean(TracerManager::class)->getServerSpan(),
                    'tags' => $tags
                ]);
        } else {
            $this->profiles[$cid]['span']->finish();
        }

    }
}