<?php

declare(strict_types=1);

namespace Olobase\Authorization\Contract;

interface PermissionModelInterface
{
    /**
     * Find permissions
     *
     * @return array
     */
    public function findPermissions(): array;
}
