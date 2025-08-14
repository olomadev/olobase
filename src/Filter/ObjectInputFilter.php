<?php

declare(strict_types=1);

namespace Olobase\Filter;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputInterface;

use function array_merge;
use function is_array;

class ObjectInputFilter extends InputFilter
{
    protected array $objectMessages = [];

    public function add($input, $name = null)
    {
        // Eğer array olarak geldiyse ve required belirtilmemişse false yap
        if (is_array($input)) {
            if (! isset($input['required'])) {
                $input['required'] = false;
            }
        }

        // Eğer InputInterface ise ve required hiç set edilmemişse false yap
        if ($input instanceof InputInterface) {
            // OptionalInputFilter burada required=false yapıyordu, biz engelliyoruz
            // Sadece explicitly set edilmemişse false yap
            if ($input->isRequired() === null) {
                $input->setRequired(false);
            }
        }

        return parent::add($input, $name);
    }

    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->getInvalidInput() as $name => $input) {
            $messages[$name] = $input->getMessages();
        }
        return ! empty($this->objectMessages)
            ? array_merge($messages, $this->objectMessages)
            : $messages;
    }
}
