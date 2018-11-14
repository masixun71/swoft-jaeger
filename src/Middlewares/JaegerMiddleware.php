<?php
namespace ExtraSwoft\Jaeger\Middlewares;

use ExtraSwoft\Jaeger\Manager\TracerManager;
use const OpenTracing\Formats\TEXT_MAP;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoft\App;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Inject;
use Swoft\Core\RequestContext;
use Swoft\Http\Message\Middleware\MiddlewareInterface;
use Swoft\Http\Message\Uri\Uri;
use OpenTracing\GlobalTracer;


/**
 * @Bean()
 */
class JaegerMiddleware implements MiddlewareInterface
{

    /**
     * @Inject()
     * @var TracerManager
     */
    private $tracerManager;


    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \InvalidArgumentException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $spanContext = GlobalTracer::get()->extract(
            TEXT_MAP,
            RequestContext::getRequest()->getSwooleRequest()->header
        );
        $span = GlobalTracer::get()->startSpan('server', ['child_of' => $spanContext]);
        GlobalTracer::get()->inject($span->getContext(), TEXT_MAP,
            RequestContext::getRequest()->getSwooleRequest()->header);

        $this->tracerManager->setServerSpan($span);


        $response = $handler->handle($request);

        $span->finish();
        $this->tracerManager->flush();

        return $response;
    }
}