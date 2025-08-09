<?php

declare(strict_types=1);

namespace Olobase\Validator;

use Exception;
use Laminas\Validator\AbstractValidator;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

use function in_array;
use function mb_strlen;
use function strlen;

/**
 * Validate file input string
 */
class BlobFileValidator extends AbstractValidator
{
    public const EMPTY_FILE_CONTENT             = 'emptyFileContent';
    public const EMPTY_MIME_TYPES_OPTION        = 'emptyFileMimeTypesOption';
    public const INVALID_FILE_CONTENT           = 'invalidbinaryContent';
    public const INVALID_FILE_MIME_TYPE         = 'invalidFileMimeType';
    public const MAX_ALLOWED_UPLOAD_SIZE_EXCEED = 'exceedAllowedUploadSize';

    /** @var array */
    protected $messageTemplates = [
        self::EMPTY_FILE_CONTENT             => 'Empty file content',
        self::EMPTY_MIME_TYPES_OPTION        => 'Empty file "mime_types" option',
        self::INVALID_FILE_CONTENT           => 'Invalid file content',
        self::INVALID_FILE_MIME_TYPE         => 'Invalid file MIME type',
        self::MAX_ALLOWED_UPLOAD_SIZE_EXCEED => 'Max allowed upload size exceeded',
    ];

    /** @var array */
    protected $messageVariables = [
        'max_allowed_upload' => ['options' => 'max_allowed_upload'],
        'mime_types'         => ['options' => 'mime_types'],
    ];

    protected $options = [
        'operation'          => '',
        'mime_types'         => [],
        'max_allowed_upload' => 10 * 1024 * 1024, // 10 MB varsayılan
    ];

    /**
     * Returns true if and only if $value meets the validation requirements.
     *
     * @param mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        $maxAllowedUpload     = (int) ($this->options['max_allowed_upload'] ?? 10 * 1024 * 1024);
        $allowedFileMimeTypes = (array) ($this->options['mime_types'] ?? []);
        $operation            = (string) ($this->options['operation'] ?? '');

        if (empty($allowedFileMimeTypes)) {
            $this->error(self::EMPTY_MIME_TYPES_OPTION);
            return false;
        }

        // If it is an update operation and the content is empty, it is considered valid (for deletion).
        if ($operation === 'update' && empty($value)) {
            return true;
        }

        if ($value === false || $value === "false" || strlen($value) === 0) {
            $this->error(self::INVALID_FILE_CONTENT);
            return false;
        }

        // Check for exceeding maximum file size
        if (mb_strlen($value, '8bit') > $maxAllowedUpload) {
            $this->error(self::MAX_ALLOWED_UPLOAD_SIZE_EXCEED);
            return false;
        }

        // MIME type detection and control
        try {
            $detector     = new FinfoMimeTypeDetector();
            $realMimeType = $detector->detectMimeTypeFromBuffer($value);

            if (! $realMimeType || ! in_array($realMimeType, $allowedFileMimeTypes, true)) {
                $this->error(self::INVALID_FILE_MIME_TYPE);
                return false;
            }
        } catch (Exception $e) {
            $this->error(self::INVALID_FILE_MIME_TYPE);
            return false;
        }

        return true;
    }
}
