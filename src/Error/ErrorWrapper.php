<?php

declare(strict_types=1);

namespace Olobase\Error;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\I18n\Translator\TranslatorInterface as Translator;

/**
 * @author Oloma <support@oloma.dev>
 *
 * Wrap error messages
 */
class ErrorWrapper implements ErrorWrapperInterface
{
    protected $translator;

    /**
     * Constructor
     * 
     * @param TranslatorInterface $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Create input filter error messages
     * 
     * @param  InputFilterInterface $inputFilter   Laminas input filter
     * @param  boolean              $multipleError show first error or all
     * @return array
     */
    public function getMessages(InputFilterInterface $inputFilter, $multipleError = true) : array
    {
        $response = array();
        foreach ($inputFilter->getInvalidInput() as $field => $input) {
            $getMessages = $input->getMessages();
            if (false == $multipleError) {
                $errors = array_values($getMessages);
                if (! empty($errors[0])) {
                    $response['data']['error'] = $errors[0];
                    break;
                }
            }
            foreach ($getMessages as $key => $message) {
                if (is_array($message)) {
                    $arrayMessages = array();
                    foreach ($message as $k => $v) {
                        if (is_string($v)) {
                            $arrayMessages[$key][] = $this->translator->translate($field, 'labels').': '.$v;
                        } else if (is_array($v)) {
                            $subValues = array_values($v);
                            foreach ($subValues as $sField => $sv) {
                                $arrayMessages[$k][] = $this->translator->translate($k, 'labels').': '.$sv;
                            }
                        }
                    }
                    $response['data']['error'][$field][] = $arrayMessages;
                } else {
                    $response['data']['error'][$field][] = $this->translator->translate($field, 'labels').': '.$message;
                }
            }
        }
        return $response;
    }

    /**
     * Handle upload error messages
     *
     * @return string
     */
    public function getUploadError(int $code) : string
    {
        $message = "";
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = $this->translator->translate("The uploaded file exceeds the upload_max_filesize directive in php.ini");
                break;

            case UPLOAD_ERR_FORM_SIZE:
                $message = $this->translator->translate("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form");
                break;

            case UPLOAD_ERR_PARTIAL:
                $message = $this->translator->translate("The uploaded file was only partially uploaded");
                break;

            case UPLOAD_ERR_NO_FILE:
                $message = $this->translator->translate("No file was uploaded");
                break;

            case UPLOAD_ERR_NO_TMP_DIR:
                $message = $this->translator->translate("Missing a temporary folder");
                break;

            case UPLOAD_ERR_CANT_WRITE:
                $message = $this->translator->translate("Failed to write file to disk");
                break;

            case UPLOAD_ERR_EXTENSION:
                $message = $this->translator->translate("File upload stopped by extension");
                break;
            default:
                $message = $this->translator->translate("Unknown upload error");
                break;
        }
        return $message;
    }


}