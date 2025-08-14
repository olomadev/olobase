<?php

declare(strict_types=1);

namespace Olobase\Middleware;

use Olobase\Util\RequestHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClientIpMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = RequestHelper::getRealUserIp($request->getServerParams());
        $request  = $request->withAttribute('client_ip', $clientIp);

        return $handler->handle($request);
    }
}
