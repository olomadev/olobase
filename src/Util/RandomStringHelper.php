<?php

declare(strict_types=1);

namespace Olobase\Util;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Uid\Uuid as SymfonyUuid;
use InvalidArgumentException;

class RandomStringHelper
{
    /**
     * Check if the value is a valid UUID.
     *
     * @param  string  $value
     * @return bool
     */
    public static function isUid(string $value): bool
    {
        return SymfonyUuid::isValid($value);
    }

    /**
     * Generate a random uppercase alphanumeric string.
     *
     * @param  int $length Length of the string
     * @param  bool $lowercase Whether to include lowercase letters
     * @return string
     */
    public static function generateRandomString(int $length = 10, bool $lowercase = false): string
    {
        $chars = $lowercase ? '0123456789abcdefghijklmnopqrstuvwxyz' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return self::generateRandom($chars, $length);
    }

    /**
     * Generate a random numeric string.
     *
     * @param  int $length Length of the number
     * @return string
     */
    public static function generateRandomNumber(int $length = 10): string
    {
        return self::generateRandom('0123456789', $length);
    }

    /**
     * Generate a random alphabetic string.
     *
     * @param  int $length Length of the string
     * @return string
     */
    public static function generateRandomAlpha(int $length = 10): string
    {
        return self::generateRandom('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length);
    }

    /**
     * Generate a random alphanumeric string.
     *
     * @param  int $length Length of the string
     * @return string
     */
    public static function generateRandomAlnum(int $length = 10): string
    {
        return self::generateRandom('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length);
    }

    /**
     * Generate UUID based on the specified version using Ramsey Uuid.
     *
     * @param  int $version UUID version (1, 3, 4, 5)
     * @param  string|null $namespace Used for UUID v3 and v5 (must be a valid UUID)
     * @param  string|null $name The name for UUID v3 and v5
     * @return string
     * @throws InvalidArgumentException if an unsupported version or invalid namespace is provided
     */
    public static function generateUuid(int $version = 4, ?string $namespace = null, ?string $name = null): string
    {
        switch ($version) {
            case 1:
                return Uuid::uuid1()->toString();
            case 3:
                if (!$namespace || !$name || !Uuid::isValid($namespace)) {
                    throw new InvalidArgumentException("UUID v3 requires a valid namespace UUID and name.");
                }
                return Uuid::uuid3(Uuid::fromString($namespace), $name)->toString();
            case 4:
                return Uuid::uuid4()->toString();
            case 5:
                if (!$namespace || !$name || !Uuid::isValid($namespace)) {
                    throw new InvalidArgumentException("UUID v5 requires a valid namespace UUID and name.");
                }
                return Uuid::uuid5(Uuid::fromString($namespace), $name)->toString();
            default:
                throw new InvalidArgumentException("Unsupported UUID version: $version. Allowed versions: 1, 3, 4, 5.");
        }
    }

    /**
     * Private function to generate a random string based on given characters.
     *
     * @param  string $characters Available characters
     * @param  int $length Desired length of the string
     * @return string
     */
    private static function generateRandom(string $characters, int $length): string
    {
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
