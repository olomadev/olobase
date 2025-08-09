<?php

declare(strict_types=1);

namespace Olobase\Filter;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterPluginManager;
use Olobase\Attribute\CollectionInput;
use Olobase\Attribute\CollectionInputFilter;
use Olobase\Attribute\Input;
use Olobase\Attribute\ObjectInput;
use Olobase\Attribute\ObjectInputFilter;
use ReflectionClass;

use function file_exists;
use function file_put_contents;
use function filemtime;
use function is_array;
use function opcache_get_status;
use function var_export;

class AttributeInputFilterCollector
{
    private string $cacheFile;

    /** @var array|null */
    private static ?array $opcacheFilters = null;

    public function __construct(
        private InputFilterPluginManager $pluginManager,
        string $cacheDir = APP_ROOT . '/data/cache'
    ) {
        $this->cacheFile = $cacheDir . '/input_filters.cache.php';
    }

    public function fromObject(object $dto, array $data): InputFilter
    {
        $dtoFile      = (new ReflectionClass($dto))->getFileName();
        $lastModified = filemtime($dtoFile);

        // 1. Use OPcache if it exists and is in memory
        if ($this->canUseOpcache()) {
            if (
                isset(self::$opcacheFilters[$dto::class]) &&
                self::$opcacheFilters[$dto::class]['_lastModified'] === $lastModified
            ) {
                return $this->createFromArray(self::$opcacheFilters[$dto::class]['data'], $data);
            }

            if ($this->isCacheValid($dto::class, $lastModified)) {
                $filters                           = include $this->cacheFile;
                self::$opcacheFilters[$dto::class] = $filters[$dto::class];
                return $this->createFromArray($filters[$dto::class]['data'], $data);
            }
        }

        // 2. If there is no OPcache, check the file cache
        if (! $this->canUseOpcache() && $this->isCacheValid($dto::class, $lastModified)) {
            $filters = include $this->cacheFile;
            return $this->createFromArray($filters[$dto::class]['data'], $data);
        }

        // 3.Cache invalid → Regenerate with Reflection
        $filterConfig = $this->generateFilterConfig($dto);
        $this->writeCache($dto::class, $filterConfig, $lastModified);

        // If there is OPcache, store it in memory
        if ($this->canUseOpcache()) {
            self::$opcacheFilters[$dto::class] = [
                '_lastModified' => $lastModified,
                'data'          => $filterConfig,
            ];
        }

        return $this->createFromArray($filterConfig, $data);
    }

    private function generateFilterConfig(object $dto): array
    {
        $reflection = new ReflectionClass($dto);
        $config     = [];

        foreach ($reflection->getProperties() as $property) {
            $inputAttrs = $property->getAttributes(Input::class);
            if ($inputAttrs) {
                /** @var Input $inputAttr */
                $inputAttr = $inputAttrs[0]->newInstance();
                $config[]  = [
                    'type'       => 'input',
                    'name'       => $inputAttr->name,
                    'required'   => $inputAttr->required,
                    'filters'    => $inputAttr->filters,
                    'validators' => $inputAttr->validators,
                ];
                continue;
            }

            $objectAttrs = $property->getAttributes(ObjectInput::class);
            if ($objectAttrs) {
                /** @var ObjectInput $nestedAttr */
                $nestedAttr = $objectAttrs[0]->newInstance();
                $config[]   = [
                    'type'   => 'object',
                    'name'   => $nestedAttr->name,
                    'fields' => $nestedAttr->fields,
                ];
                continue;
            }

            $collectionAttrs = $property->getAttributes(CollectionInput::class);
            if ($collectionAttrs) {
                /** @var CollectionInput $collectionAttr */
                $collectionAttr = $collectionAttrs[0]->newInstance();
                $config[]       = [
                    'type'   => 'collection',
                    'name'   => $collectionAttr->name,
                    'fields' => $collectionAttr->fields,
                ];
            }
        }

        return $config;
    }

    private function createFromArray(array $config, array $data): InputFilter
    {
        $inputFilter = new InputFilter();

        foreach ($config as $item) {
            if ($item['type'] === 'input') {
                $inputFilter->add([
                    'name'       => $item['name'],
                    'required'   => $item['required'],
                    'filters'    => $item['filters'],
                    'validators' => $item['validators'],
                ]);
            } elseif ($item['type'] === 'object') {
                $nested = $this->pluginManager->get(ObjectInputFilter::class);
                foreach ($item['fields'] as $field) {
                    $nested->add([
                        'name'       => $field['name'],
                        'required'   => $field['required'] ?? true,
                        'filters'    => $field['filters'] ?? [],
                        'validators' => $field['validators'] ?? [],
                    ]);
                }
                $inputFilter->add($nested, $item['name']);
            } elseif ($item['type'] === 'collection') {
                $collection = $this->pluginManager->get(CollectionInputFilter::class);
                $itemFilter = $this->pluginManager->get(InputFilter::class);
                foreach ($item['fields'] as $field) {
                    $itemFilter->add([
                        'name'       => $field['name'],
                        'required'   => $field['required'] ?? true,
                        'filters'    => $field['filters'] ?? [],
                        'validators' => $field['validators'] ?? [],
                    ]);
                }
                $collection->setInputFilter($itemFilter);
                $inputFilter->add($collection, $item['name']);
            }
        }

        $inputFilter->setData($data);
        return $inputFilter;
    }

    private function isCacheValid(string $className, int $lastModified): bool
    {
        if (! file_exists($this->cacheFile)) {
            return false;
        }
        $data = include $this->cacheFile;
        return isset($data[$className]['_lastModified']) &&
               $data[$className]['_lastModified'] === $lastModified;
    }

    private function writeCache(string $className, array $filterConfig, int $lastModified): void
    {
        $data             = file_exists($this->cacheFile) ? include $this->cacheFile : [];
        $data[$className] = [
            '_lastModified' => $lastModified,
            'data'          => $filterConfig,
        ];

        file_put_contents(
            $this->cacheFile,
            "<?php\n\nreturn " . var_export($data, true) . ";\n"
        );
    }

    private function canUseOpcache(): bool
    {
        $status = opcache_get_status(false);
        return is_array($status) && ! empty($status['opcache_enabled']);
    }
}
