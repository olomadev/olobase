<?php

declare(strict_types=1);

namespace Olobase\Entity;

use Laminas\Hydrator\ReflectionHydrator;
use Olobase\Util\RandomStringHelper;
use ReflectionClass;

use function array_key_exists;

abstract class AbstractEntity
{
    private static ?ReflectionHydrator $hydrator = null;

    /** @var string|int|null */
    protected $id;

    public function __construct(string|int|null $id = null)
    {
        $this->id = $id;

        if ($id === null) {
            $this->id = RandomStringHelper::generateUuid();
        }

        if (self::$hydrator === null) {
            self::$hydrator = new ReflectionHydrator();
        }
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function toArray(array $exclude = []): array
    {
        $data = self::$hydrator->extract($this);

        foreach ($exclude as $key) {
            unset($data[$key]);
        }

        if (array_key_exists('id', $data) && empty($data['id'])) {
            unset($data['id']);
        }

        return $data;
    }

    public static function hydrate(array $data, string $entityClass): object
    {
        $reflection = new ReflectionClass($entityClass);
        $params     = [];

        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $name     = $param->getName();
            $params[] = $data[$name] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
        }

        return $reflection->newInstanceArgs($params);
    }
}
