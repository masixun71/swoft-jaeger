<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Listener;

use OpenTracing\Ext\Tags;
use ExtraSwoft\Jaeger\Manager\TracerManager;
use OpenTracing\GlobalTracer;
use Swoft\Bean\Annotation\Listener;
use Swoft\Core\Coroutine;
use Swoft\Event\EventHandlerInterface;
use Swoft\Event\EventInterface;
use Swoft\Exception\Exception;
use Psr\Http\Message;
use Psr\Http\Message\ResponseInterface;


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
        if (empty(TracerManager::getServerSpan()))
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
                Tags\HTTP_METHOD => $request->getMethod(),
                Tags\HTTP_URL => $uri->getHost(),
                'http.port' => $uri->getPort(),
                'http.path' => $uri->getPath(),
                'http.query' => $uri->getQuery(),
                'http.headers' => !empty($request->getHeaders()) ? json_encode($request->getHeaders()) : ''
            ];

            if ($request->getMethod() != 'GET')
            {
                $tags['http.body'] = $options['body'];
            }

            $this->profiles[$cid]['span'] = GlobalTracer::get()->startSpan('httpRequest',
                [
                    'child_of' => TracerManager::getServerSpan(),
                    'tags' => $tags
                ]);
        } else {
            /** @var ResponseInterface $response */
            $response = $event->getParams()[0];

            $this->profiles[$cid]['span']->log([
                Tags\HTTP_STATUS_CODE => $response->getStatusCode()
            ]);
            $this->profiles[$cid]['span']->finish();
        }

    }
}