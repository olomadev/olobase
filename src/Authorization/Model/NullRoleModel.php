<?php

declare(strict_types=1);

namespace Olobase\Authorization\Model;

use Laminas\Paginator\Paginator;
use Laminas\Paginator\Adapter\ArrayAdapter;
use Olobase\Authorization\Contracts\RoleModelInterface;

class NullRoleModel implements RoleModelInterface
{
    /**
     * Find roles assigned to a user by their userId.
     *
     * @param string $userId User ID
     * @return array List of role keys
     */
    public function findRolesByUserId(string $userId): array
    {
        return array(
            'user',
        );
    }

    /**
     * Find all available roles for selection.
     *
     * @return array List of roles
     */
    public function findAll(): ?array
    {
        return array();
    }
    
    /**
     * Find a role by its roleId.
     *
     * @param string $roleId Role ID
     * @return array Role details with permissions
     */
    public function findOneById(string $roleId)
    {
        return false;
    }

    /**
     * Find all roles by pagination
     * 
     * @param  array  $get query string
     * @return Laminas\Paginator\Paginator
     */
    public function findAllByPaging(array $get) : Paginator
    {
        $paginatorAdapter = new ArrayAdapter([]);
        return new Paginator($paginatorAdapter);
    }

    /**
     * Create a new role and its associated permissions.
     *
     * @param array $data Role and permission data
     * @return void
     */
    public function create(array $data) : void
    {
        return;
    }

    /**
     * Update an existing role and its associated permissions.
     *
     * @param array $data Role and permission data
     * @return void
     */
    public function update(array $data) : void
    {
        return;
    }

    /**
     * Delete a role by its roleId.
     *
     * @param string $roleId Role ID
     * @return void
     */
    public function delete(string $roleId) : void
    {
        return;
    }
}
