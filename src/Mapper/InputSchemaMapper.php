<?php

declare(strict_types=1);

namespace Olobase\Mapper;

use Laminas\InputFilter\InputFilterInterface;
use Olobase\Util\StringHelper;
use OpenApi\Attributes\Property as OAProperty;
use ReflectionClass;

use function array_key_exists;
use function explode;

class InputSchemaMapper
{
    public function map(InputFilterInterface $inputFilter, object $dto, ?string $tablename = null): array
    {
        $data       = $inputFilter->getData();
        $reflection = new ReflectionClass($dto);
        $properties = $reflection->getProperties();

        $namespace    = $reflection->getNamespaceName();
        $parts        = explode('\\', $namespace);
        $firstSegment = $parts[0];

        $table      = $tablename ?? StringHelper::toSnakeCase($firstSegment);
        $schemaData = [];

        foreach ($properties as $prop) {
            $propertyName = $prop->getName();

            // OA\Property(property="is_active") → 'is_active'
            $oaAttrs    = $prop->getAttributes(OAProperty::class);
            $columnName = $propertyName;

            if (! empty($oaAttrs)) {
                $column = $oaAttrs[0]->newInstance();
                if (! empty($column->property)) {
                    $columnName = $column->property;
                }
            }

            if (! array_key_exists($columnName, $data)) {
                continue;
            }

            $value                             = $inputFilter->getValue($columnName);
            $schemaData[$table][$propertyName] = $value;
        }

        if ($inputFilter->has('id')) {
            $schemaData['id'] = $inputFilter->getValue('id');
        }

        return $schemaData;
    }
}
