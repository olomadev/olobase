<?php

declare(strict_types=1);

namespace Olobase\Filter;

use ReflectionClass;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterPluginManager;
use Olobase\Attribute\Input;
use Olobase\Attribute\ObjectInput;
use Olobase\Attribute\CollectionInput;
use Olobase\Attribute\ObjectInputFilter;
use Olobase\Attribute\CollectionInputFilter;

class AttributeInputFilterCollector
{
    public function __construct(private InputFilterPluginManager $pluginManager)
    {
    }

    public function fromObject(object $dto, array $data): InputFilter
    {
        $reflection = new ReflectionClass($dto);
        $inputFilter = new InputFilter();

        foreach ($reflection->getProperties() as $property) {
            $inputAttrs = $property->getAttributes(Input::class);
            if ($inputAttrs) {
                /** @var Input $inputAttr */
                $inputAttr = $inputAttrs[0]->newInstance();
                $inputFilter->add([
                    'name' => $inputAttr->name,
                    'required' => $inputAttr->required,
                    'filters' => $inputAttr->filters,
                    'validators' => $inputAttr->validators,
                ]);
                continue;
            }

            $objectAttrs = $property->getAttributes(ObjectInput::class);
            if ($objectAttrs) {
                /** @var ObjectInput $nestedAttr */
                $nestedAttr = $objectAttrs[0]->newInstance();
                $nested = $this->pluginManager->get(ObjectInputFilter::class);

                foreach ($nestedAttr->fields as $field) {
                    $nested->add([
                        'name' => $field['name'],
                        'required' => $field['required'] ?? true,
                        'filters' => $field['filters'] ?? [],
                        'validators' => $field['validators'] ?? [],
                    ]);
                }

                $inputFilter->add($nested, $nestedAttr->name);
                continue;
            }

            $collectionAttrs = $property->getAttributes(CollectionInput::class);
            if ($collectionAttrs) {
                /** @var CollectionInput $collectionAttr */
                $collectionAttr = $collectionAttrs[0]->newInstance();
                $collection = $this->pluginManager->get(CollectionInputFilter::class);
                $itemFilter = $this->pluginManager->get(InputFilter::class);

                foreach ($collectionAttr->fields as $field) {
                    $itemFilter->add([
                        'name' => $field['name'],
                        'required' => $field['required'] ?? true,
                        'filters' => $field['filters'] ?? [],
                        'validators' => $field['validators'] ?? [],
                    ]);
                }

                $collection->setInputFilter($itemFilter);
                $inputFilter->add($collection, $collectionAttr->name);
            }
        }

        $inputFilter->setData($data);
        return $inputFilter;
    }
}
