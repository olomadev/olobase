<?php

declare(strict_types=1);

namespace Olobase\Authorization\Model;

use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Olobase\Authorization\Contract\PermissionModelInterface;

class NullPermissionModel implements PermissionModelInterface
{
    /**
     * Find permissions
     * 
     * @return array
     */
    public function findPermissions() : array
    {

		return [
		    'admin' => [],
		    'user' => [],
		];
    }
}