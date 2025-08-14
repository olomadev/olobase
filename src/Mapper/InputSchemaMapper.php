<?php

declare(strict_types=1);

namespace Olobase\Mapper;

use Laminas\InputFilter\InputFilterInterface;
use Olobase\Attribute\ObjectInput;
use Olobase\Entity\AbstractEntity;
use ReflectionClass;
use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_string;
use function is_subclass_of;

class InputSchemaMapper
{
    public function mapToEntity(InputFilterInterface $inputFilter, object|string $dtoOrEntity, ?string $entityClass = null): object
    {
        if (is_string($dtoOrEntity)) {
            $entityClass = $dtoOrEntity;
            $dto         = null;
        } else {
            $dto = $dtoOrEntity;
        }

        if (! $entityClass || ! is_subclass_of($entityClass, AbstractEntity::class)) {
            throw new RuntimeException("Entity class {$entityClass} must extend AbstractEntity");
        }

        $data = $inputFilter->getValues();

        //  ObjectInput "id" support begin ...
        if ($dto) {
            $dtoReflection = new ReflectionClass($dto);
            foreach ($dtoReflection->getProperties() as $prop) {
                foreach ($prop->getAttributes(ObjectInput::class) as $attr) {
                    $propName = $prop->getName();
                    if (isset($data[$propName]) && is_array($data[$propName]) && array_key_exists('id', $data[$propName])) {
                        $data[$propName] = $data[$propName]['id'];
                    }
                }
            }
        }
        //  ObjectInput "id" support end ...
        $entityReflection = new ReflectionClass($entityClass);
        $params           = [];

        foreach ($entityReflection->getConstructor()->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $data)) {
                $params[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                $params[] = null;
            }
        }

        return $entityReflection->newInstanceArgs($params);
    }
}
