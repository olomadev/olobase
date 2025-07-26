<?php

declare(strict_types=1);

namespace Olobase\Validation;

use Laminas\InputFilter\InputFilterInterface;

class ValidationErrorFormatter implements ValidationErrorFormatterInterface
{
    private string $responseKey;
    private bool $multipleError;

    public function __construct(array $config = [])
    {
        $this->responseKey = $config['response_key'];
        $this->multipleError = $config['multiple_error'];
    }

    public function format(InputFilterInterface $inputFilter): array
    {
        return $this->getMessages($inputFilter);
    }

    protected function getMessages(InputFilterInterface $inputFilter): array
    {
        $response = [];
        foreach ($inputFilter->getInvalidInput() as $field => $input) {
            $getMessages = $input->getMessages();

            if (!$this->multipleError) {
                $errors = array_values($getMessages);
                if (!empty($errors[0])) {
                    $response[$this->responseKey]['error'] = $errors[0];
                    break;
                }
            }
            
            foreach ($getMessages as $key => $message) {
                if (is_array($message)) {
                    $arrayMessages = [];
                    foreach ($message as $k => $v) {
                        if (is_string($v)) {
                            $arrayMessages[$key][] = "$field: $v";
                        } elseif (is_array($v)) {
                            foreach (array_values($v) as $sv) {
                                $arrayMessages[$k][] = "$k: $sv";
                            }
                        }
                    }
                    $response[$this->responseKey]['error'][$field][] = $arrayMessages;
                } else {
                    $response[$this->responseKey]['error'][$field][] = $message;
                }
            }
        }

        return $response;
    }
}
