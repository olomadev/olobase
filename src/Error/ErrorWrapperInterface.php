<?php

declare(strict_types=1);

namespace Olobase\Error;

use Laminas\InputFilter\InputFilterInterface;

/**
 * @author Oloma <support@oloma.dev>
 *
 * Permission model interface
 */
interface ErrorWrapperInterface
{
    /**
     * Returns to validation errors
     * 
     * @param  InputFilterInterface $inputFilter
     * @return array
     */
    public function getMessages(InputFilterInterface $inputFilter) : array;

    /**
     * Handle upload error messages
     *
     * @return string
     */
    public function getUploadError(int $code) : string;
}