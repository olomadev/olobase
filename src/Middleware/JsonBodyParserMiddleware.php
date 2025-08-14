<?php

declare(strict_types=1);

namespace Olobase\Middleware;

use Mezzio\Router\RouteResult;
use Olobase\Exception\BodyDecodeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $headers = $request->getHeaders();
        $routeResult = $request->getAttribute(RouteResult::class, false);
        //
        // Sets primary id key if it's exists
        // 
        $primaryKey = null;
        if ($routeResult) {
            $params = $routeResult->getMatchedParams();
            if (is_array($params) && ! empty($params)) {
                unset($params['middleware']);
                $paramArray = array_keys($params);
                $primaryKey = empty($paramArray[0]) ? null : trim((string)$paramArray[0]);
            }  
        }
        // Json Body Parse
        $contentType = $headers['content-type'][0] ?? null;
        if ($contentType && str_starts_with($contentType, 'application/json')) {
            $contentBody = $request->getBody()->getContents();
            $parsedBody = json_decode($contentBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BodyDecodeException(json_last_error_msg());
            }
        } else {
            $parsedBody = $request->getParsedBody();
        }
        // Set Json Body
        if (in_array($request->getMethod(), ['POST', 'PUT', 'OPTIONS'], true)) {
            if ($primaryKey && $primaryId = $request->getAttribute($primaryKey)) { // Primary ID settings
                $parsedBody['id'] = $primaryId;
            }
            $request = $request->withParsedBody($parsedBody);
        } else {
            $queryParams = $request->getQueryParams();
            if ($primaryKey && $primaryId = $request->getAttribute($primaryKey)) { // Primary ID settings
                $queryParams['id'] = $primaryId;
            }
            $request = $request->withQueryParams($queryParams);
        }
        return $handler->handle($request);
    }
}
