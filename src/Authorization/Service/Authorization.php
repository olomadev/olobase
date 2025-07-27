<?php

declare(strict_types=1);

namespace Olobase\Authorization\Service;

use Mezzio\Authorization\AuthorizationInterface;
use Mezzio\Authorization\Exception;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;
use Olobase\Authorization\Contract\PermissionModelInterface;

use function sprintf;
use function in_array;

/**
 * @author Oloma <support@oloma.dev>
 *
 * Authorization class
 */
class Authorization implements AuthorizationInterface
{
    private $permissions = array();

    /**
     * Constructor
     *
     * @param PermissionModelInterface $permissionModel permissions
     */
    public function __construct(PermissionModelInterface $permissionModel)
    {
        $this->permissions = $permissionModel->findPermissions();
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception\RuntimeException
     */
    public function isGranted(string $role, ServerRequestInterface $request) : bool
    {
        $routeResult = $request->getAttribute(RouteResult::class, false);
        if (false === $routeResult) {
            throw new Exception\RuntimeException(sprintf(
                'The %s attribute is missing in the request; cannot perform authorizations',
                RouteResult::class
            ));
        }
        // No matching route. Everyone can access.
        if ($routeResult->isFailure()) {
            return true;
        }
        $permissions = $this->getPermissions($role);
        if (false == $permissions) {
            return false;
        }
        // Check user has permission to the route
        //
        $routeName = $routeResult->getMatchedRouteName();
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
