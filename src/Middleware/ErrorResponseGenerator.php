<?php

declare(strict_types=1);

namespace Olobase\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_map;
use function array_merge;
use function defined;
use function getenv;
use function json_encode;
use function str_contains;
use function str_replace;

use const JSON_THROW_ON_ERROR;

class ErrorResponseGenerator
{
    protected array $config;
    protected ContainerInterface $container;

    public function __construct(array $config, ContainerInterface $container)
    {
        $this->config    = $config;
        $this->container = $container;
    }

    public function __invoke(Throwable $e, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $e->getTrace();

        $trace = array_map(
            fn ($a) => isset($a['file']) && defined('APP_ROOT')
                ? array_merge($a, ['file' => str_replace(APP_ROOT, '', $a['file'])])
                : $a,
            $data
        );

        $json = [
            'title'  => $e::class,
            'type'   => 'https://httpstatus.es/400',
            'status' => 400,
            'file'   => defined('APP_ROOT') ? str_replace(APP_ROOT, '', $e->getFile()) : $e->getFile(),
            'line'   => $e->getLine(),
            'error'  => $e->getMessage(),
        ];

        if (getenv('APP_ENV') === 'local') {
            $json['trace'] = $trace;
        }

        $response = $response
            ->withHeader('Access-Control-Expose-Headers', 'Token-Expired')
            ->withHeader('Access-Control-Max-Age', '3600')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);

        $response->getBody()->write(json_encode($json, JSON_THROW_ON_ERROR));

        return $response;
    }
}
