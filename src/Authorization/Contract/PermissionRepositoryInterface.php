<?php

declare(strict_types=1);

namespace Olobase\Authorization\Contract;

interface PermissionRepositoryInterface
{
    /**
     * Find permissions
     */
    public function findPermissions(): array;
}
