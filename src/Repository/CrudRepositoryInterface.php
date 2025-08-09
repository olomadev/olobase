<?php

declare(strict_types=1);

namespace Olobase\Repository;

interface CrudRepositoryInterface
{
    public function createEntity(object $entity): void;

    public function updateEntity(object $entity): void;

    public function deleteEntity(string $id): void;
}
