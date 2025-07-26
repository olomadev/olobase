<?php

declare(strict_types=1);

namespace Olobase\Exception;

use Mezzio\Exception\MissingDependencyException;

class MissingDependencyErrorFormatter
{
    protected $moduleDependencies = array();

    public function __construct(array $moduleDependencies)
    {
        $this->moduleDependencies = $moduleDependencies;
    }

    public function format(MissingDependencyException $e): array
    {
        $message = $e->getMessage();
        $namespace = $this->extractNamespace($message);
        $moduleName = $this->findModuleByClass($namespace);

        if ($namespace !== null && $moduleName) {
            $website = "https://olobase.dev";
            $message = sprintf(
                "%s or similar module needed. Please visit %s to find or install the modules.",
                $moduleName,
                $website
            );
        }
        return [
            'title'  => get_class($e),
            'type'   => 'https://httpstatus.es/400',
            'status' => 400,
            'file'   => defined('APP_ROOT') ? str_replace(APP_ROOT, '', $e->getFile()) : $e->getFile(),
            'line'   => $e->getLine(),
            'error'  => $message,
        ];
    }

    protected function extractNamespace(string $message): ?string
    {
        if (preg_match_all('/"((?:\\\\?[\w]+)+)"/', $message, $matches)) {
            foreach ($matches[1] as $class) {
                return $class;
            }
        }
        return null;
    }

    protected function findModuleByClass(string $className): ?string
    {
        foreach ($this->moduleDependencies as $module => $dependencies) {
            if (in_array($className, $dependencies, true)) {
                return $module;
            }
        }
        return null;
    }
}
