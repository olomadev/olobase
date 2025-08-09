<?php

declare(strict_types=1);

namespace Modules\Repository;

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
        protected TableGatewayInterface $table,
        protected StorageInterface $cache,
        ?EventManagerInterface $eventManager = null
    ) {
        $this->conn = $table->getAdapter()->getDriver()->getConnection();

        if ($eventManager) {
            $this->setEventManager($eventManager);
            $this->eventsEnabled = true;
        }
    }

    protected function transactional(callable $callback): void
    {
        $this->conn->beginTransaction();
        try {
            $callback();
            $this->deleteCache();
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function createEntity(object $entity): void
    {
        $this->transactional(function () use ($entity) {
            $this->beforeCreate($entity);
            $this->doCreate($entity);
            $this->afterCreate($entity);
        });
    }

    public function updateEntity(object $entity): void
    {
        $this->transactional(function () use ($entity) {
            $this->beforeUpdate($entity);
            $this->doUpdate($entity);
            $this->afterUpdate($entity);
        });
    }

    public function deleteEntity(string $id): void
    {
        $this->transactional(function () use ($id) {
            $this->beforeDelete($id);
            $this->doDelete($id);
            $this->afterDelete($id);
        });
    }

    abstract protected function doCreate(object $entity): void;

    abstract protected function doUpdate(object $entity): void;

    abstract protected function doDelete(string $id): void;

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
