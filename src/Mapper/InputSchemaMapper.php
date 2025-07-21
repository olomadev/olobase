<?php

namespace Olobase\Mapper;

use ReflectionClass;
use Laminas\InputFilter\InputFilterInterface;
use OpenApi\Attributes\Property as OAProperty;

class InputSchemaMapper
{
    public function map(InputFilterInterface $inputFilter, string $schema, ?string $tablename = null): array
    {
        $data = $inputFilter->getData();
        $reflection = new ReflectionClass($schema);
        $properties = $reflection->getProperties();

        $table = $tablename ?? strtolower(rtrim($reflection->getShortName(), 'save'));
        $schemaData = [];

        foreach ($properties as $prop) {
            $propertyName = $prop->getName();

            // OA\Property(property="is_active") → 'is_active'
            $oaAttrs = $prop->getAttributes(OAProperty::class);
            $columnName = $propertyName;
            if (isset($oaAttrs[0])) {
                $column = $oaAttrs[0]->newInstance();
                $columnName = $column->property ?? $propertyName;
            }

            if (!array_key_exists($columnName, $data)) {
                continue;
            }

            $value = $inputFilter->getValue($columnName);
            $schemaData[$table][$propertyName] = $value;
        }

        if ($inputFilter->has('id')) {
            $schemaData['id'] = $inputFilter->getValue('id');
        }

        return $schemaData;
    }
}
