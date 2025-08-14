<?php

declare(strict_types=1);

namespace Olobase\Authorization;

use Mezzio\Authorization\AuthorizationInterface;
use Mezzio\Authorization\Exception;
use Mezzio\Router\RouteResult;
use Olobase\Authorization\PermissionRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function sprintf;

class Authorization implements AuthorizationInterface
{
    private $permissions = [];

    /**
     * Constructor
     *
     * @param PermissionRepositoryInterface $permissionRepository permissions
     */
    public function __construct(PermissionRepositoryInterface $permissionRepository)
    {
        $this->permissions = $permissionRepository->findPermissions();
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception\RuntimeException
     */
    public function isGranted(string $role, ServerRequestInterface $request): bool
    {
        $routeResult = $request->getAttribute(RouteResult::class, false);
        if (false === $routeResult) {
            throw new Exception\RuntimeException(sprintf(
                'The %s attribute is missing in the request; cannot perform authorizations',
                RouteResult::class
            ));
        }
        if ($routeResult->isFailure()) { // No matching route. Everyone can access.
            return true;
        }
        $permissions = $this->getPermissions($role);
        if (false == $permissions) {
            return false;
        }
        $routeName = $routeResult->getMatchedRouteName(); // Check user has permission to the route
        if (in_array($routeName, $permissions)) {
            return true;
        }
        return false;
    }

    /**
     * Return to permissions of role
     *
     * @param  string $role role name
     * @return bool|array
     */
    private function getPermissions(string $role)
    {
        if (! empty($this->permissions[$role])) {
            return $this->permissions[$role];
        }
        return false;
    }
}
