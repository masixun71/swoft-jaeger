<?php
declare(strict_types=1);

namespace ExtraSwoft\Jaeger\Adapter;

use ExtraSwoft\Jaeger\Manager\TracerManager;
use Psr\Http\Message\RequestInterface;
use Swoft\Core\RequestContext;
use Swoft\HttpClient\Adapter\CoroutineAdapter;
use Swoft\HttpClient\HttpResultInterface;

class AddJaegerAdapter extends CoroutineAdapter
{
    public function request(RequestInterface $request, array $options = []): HttpResultInterface
    {
        $options['_headers'] = array_merge($options['_headers'], \Swoft::getBean(TracerManager::class)->getHeader());

        return parent::request($request, $options);
    }

}

