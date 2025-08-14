<?php

declare(strict_types=1);

namespace Olobase\Authorization;

use Laminas\Paginator\Paginator;
use Olobase\Repository\CrudRepositoryInterface;

interface RoleRepositoryInterface extends CrudRepositoryInterface
{
    /**
     * Find roles assigned to a user by their userId.
     *
     * @param string $userId User ID
     * @return array List of role keys
     */
    public function findByUserId(string $userId): array;

    /**
     * Find all available roles for selection.
     *
     * @return array List of roles
     */
    public function findAll(): ?array;

    /**
     * Find a role by its roleId.
     *
     * @param string $roleId Role ID
     * @return array Role details with permissions
     */
    public function findById(string $roleId);

    /**
     * Find all roles by pagination
     *
     * @param  array  $get query string
     * @return Laminas\Paginator\Paginator
     */
    public function findAllByPaging(array $get): Paginator;
}
