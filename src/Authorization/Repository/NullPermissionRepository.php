<?php

declare(strict_types=1);

namespace Olobase\Authorization\Repository;

use Olobase\Authorization\Contract\PermissionRepositoryInterface;

class NullPermissionRepository implements PermissionRepositoryInterface
{
    /**
     * Find permissions
     */
    public function findPermissions(): array
    {
        return [
            'admin' => [],
            'user'  => [],
        ];
    }
}
