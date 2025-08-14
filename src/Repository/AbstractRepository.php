<?php

declare(strict_types=1);

namespace Olobase\Repository;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\EventManager\EventManagerInterface;
use Throwable;

abstract class AbstractRepository implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    protected $conn;
    protected bool $eventsEnabled = false;

    public function __construct(
        TableGatewayInterface $table,
        StorageInterface $cache,
        ?EventManagerInterface $eventManager = null
    ) {
        $this->conn = $table->getAdapter()->getDriver()->getConnection();

        if ($eventManager) {
            $this->setEventManager($eventManager);
            $this->eventsEnabled = true;
        }
    }

    protected function transactional(callable $callback): mixed
    {
        $this->conn->beginTransaction();
        try {
            $result = $callback();
            $this->deleteCache();
            $this->conn->commit();
            return $result;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function createEntity(object $entity)
    {
        return $this->transactional(function () use ($entity) {
            $this->beforeCreate($entity);
            $id = $this->doCreate($entity);
            $this->afterCreate($entity);
            return $id;
        });
    }

    public function updateEntity(object $entity)
    {
        return $this->transactional(function () use ($entity) {
            $this->beforeUpdate($entity);
            $affectedRows = $this->doUpdate($entity);
            $this->afterUpdate($entity);
            return $affectedRows;
        });
    }

    public function deleteEntity(string|int $id)
    {
        return $this->transactional(function () use ($id) {
            $this->beforeDelete($id);
            $affectedRows = $this->doDelete($id);
            $this->afterDelete($id);
            return $affectedRows;
        });
    }

    abstract protected function doCreate(object $entity);

    abstract protected function doUpdate(object $entity);

    abstract protected function doDelete(int|string $id);

    protected function beforeCreate(object $entity): void
    {
        if ($this->eventsEnabled) {
            $this->getEventManager()->trigger(__FUNCTION__, $this, ['entity' => $entity]);
        }
    }

    protected function afterCreate(object $entity): void
    {
        if ($this->eventsEnabled) {
            $this->getEventManager()->trigger(__FUNCTION__, $this, ['entity' => $entity]);
        }
    }

    protected function beforeUpdate(object $entity): void
    {
        if ($this->eventsEnabled) {
            $this->getEventManager()->trigger(__FUNCTION__, $this, ['entity' => $entity]);
        }
    }

    protected function afterUpdate(object $entity): void
    {
        if ($this->eventsEnabled) {
            $this->getEventManager()->trigger(__FUNCTION__, $this, ['entity' => $entity]);
        }
    }

    protected function beforeDelete(string $id): void
    {
        if ($this->eventsEnabled) {
            $this->getEventManager()->trigger(__FUNCTION__, $this, ['id' => $id]);
        }
    }

    protected function afterDelete(string $id): void
    {
        if ($this->eventsEnabled) {
            $this->getEventManager()->trigger(__FUNCTION__, $this, ['id' => $id]);
        }
    }

    protected function deleteCache(): void
    {
    }
}
