<?php

declare(strict_types=1);

namespace Olobase\Authorization\Repository;

use Laminas\Paginator\Adapter\ArrayAdapter;
use Laminas\Paginator\Paginator;
use Olobase\Authorization\Contract\RoleRepositoryInterface;

class NullRoleRepository implements RoleRepositoryInterface
{
    /**
     * Find roles assigned to a user by their userId.
     *
     * @param string $userId User ID
     * @return array List of role keys
     */
    public function findRolesByUserId(string $userId): array
    {
        return [
            'user',
        ];
    }

    /**
     * Find all available roles for selection.
     *
     * @return array List of roles
     */
    public function findAll(): ?array
    {
        return [];
    }

    /**
     * Find a role by its roleId.
     *
     * @param string $roleId Role ID
     * @return array Role details with permissions
     */
    public function findById(string $roleId)
    {
        return false;
    }

    /**
     * Find all roles by pagination
     *
     * @param  array  $get query string
     * @return Laminas\Paginator\Paginator
     */
    public function findAllByPaging(array $get): Paginator
    {
        $paginatorAdapter = new ArrayAdapter([]);
        return new Paginator($paginatorAdapter);
    }

    /**
     * Create entity
     *
     * @param  object $entity entity class
     * @return mixed
     */
    public function createEntity(object $entity)
    {
        return null;
    }

    /**
     * Update entity
     *
     * @param  object $entity entity class
     * @return mixed
     */
    public function updateEntity(object $entity)
    {
        return null;
    }

    /**
     * Delete entity
     *
     * @param  mixed $id id
     * @return mixed
     */
    public function deleteEntity(int|string $id)
    {
        return null;
    }
}
