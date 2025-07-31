<?php

declare(strict_types=1);

namespace Olobase\Router;

use RuntimeException;
use Mezzio\Application;
use Psr\Container\ContainerInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use RegexIterator;
use RecursiveRegexIterator;
use Olobase\Attribute\Route;

class AttributeRouteCollector implements AttributeRouteProviderInterface
{
    public function __construct(
        private Application $app,
        private ContainerInterface $container,
        private string $modulesBasePath = APP_ROOT . '/src/',
        private string $namespacePrefix = 'Modules\\',
    ) {
    }

    public function registerRoutes(string $dir): void
    {
        $moduleName = basename($dir);
        $handlerPath = $this->modulesBasePath . $moduleName . "/src/Handler/";
        if (!is_dir($handlerPath)) {
            throw new RuntimeException(
                "Handler folder not found at: $moduleName/src/"
            );
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($handlerPath)
        );
        $foundFiles = new RegexIterator($iterator, '/^.+Handler\.php$/i', RecursiveRegexIterator::GET_MATCH);

        $this->setAllRouteAttributes($foundFiles);
    }

    private function setAllRouteAttributes($foundFiles)
    {
        foreach ($foundFiles as $file) {

            $class = $this->resolveNamespace(current($file));

            if (!class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);
            $attributes = $ref->getAttributes(Route::class);

            if (count($attributes) === 0) {
                continue;
            }

            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                // Get the module name from the Namespace (e.g. 'Modules' section)
                $namespaceParts = explode('\\', $ref->getNamespaceName());
                $module = $namespaceParts[0] ?? 'UnknownModule';

                $pipeline = [...$route->middlewares, $class];

                // Check if meta exists here, otherwise create it
                $meta = $route->meta ?? [];
                if (!isset($meta['module'])) {
                    $meta['module'] = $module;
                }
                // Add meta to RouteOptions
                $routeOptions = ['meta' => $meta];

                $this->app->route($route->path, $pipeline, $route->methods) // register route
                    ->setOptions($routeOptions);
            }

        }
    }

    private function resolveNamespace(string $filePath): ?string
    {
        // Convert to namespace compatible with PSR-4
        $basePath = APP_ROOT.'/src/';
        $relative = str_replace([$basePath, '/', '.php'], ['', '\\', ''], $filePath);

        // Get the class name
        $className = $relative; // e.g.: SomeModule\src\Handler\FindAllByPagingHandler

        // If the `src` directory is not included in the namespace, clear it too
        $className = str_replace('\\src\\', '\\', $className);
        return $className;
    }
}
