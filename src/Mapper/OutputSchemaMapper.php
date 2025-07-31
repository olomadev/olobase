<?php

namespace Olobase\Mapper;

use ReflectionClass;
use ReflectionNamedType;
use OpenApi\Attributes\Property as OAProperty;
use Olobase\Exception\UncodedObjectIdException;

class OutputSchemaMapper
{
    public function map(string $schemaClass, array $row): array
    {
        $reflection = new ReflectionClass($schemaClass);
        $viewData = [];
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $propName = $property->getName();

            // OA\Property attribute
            $oaPropertyAttr = $property->getAttributes(OAProperty::class)[0] ?? null;
            /** @var OAProperty|null $oaProperty */
            $oaProperty = $oaPropertyAttr ? $oaPropertyAttr->newInstance() : null;

            // DB column - property name if not from attribute (optional to convert column name with camelCase)
            $columnName = $oaProperty?->property ?? $this->camelToSnake($propName);

            // Incoming row data
            $value = $row[$columnName] ?? null;

            if ($value === null) {
                $viewData[$propName] = null;
                continue;
            }

            // Type inference (Reflection property type or OA type)
            $type = $this->detectType($property, $oaProperty);

            // Convert the value to the appropriate type
            $viewData[$propName] = $this->castValue($value, $type);
        }

        return $viewData;
    }

    protected function detectType(\ReflectionProperty $property, ?OAProperty $oaProperty): string
    {
        // First get the PHP type hint
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        // Check OAProperty type field
        if ($oaProperty?->type) {
            return match ($oaProperty->type) {
                'integer' => 'int',
                'number' => 'float',
                'boolean' => 'bool',
                'string' => 'string',
                'array' => 'array',
                default => 'string',
            };
        }

        // Default
        return 'string';
    }

    protected function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int)$value,
            'integer' => (int)$value,
            'float' => (float)$value,
            'double' => (float)$value,
            'bool', 'boolean' => (bool)$value,
            'array' => is_string($value) ? json_decode($value, true) ?? [] : (array)$value,
            default => $value,
        };
    }

    protected function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}
