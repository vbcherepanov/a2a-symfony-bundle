<?php

declare(strict_types=1);

namespace A2A\Bundle\Storage;

use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;
use A2A\Storage\TaskRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;

final readonly class DoctrineTaskRepository implements TaskRepository
{
    public function __construct(private Connection $connection, private string $table = 'a2a_tasks', private int $maxUpdateAttempts = 5)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table) || $maxUpdateAttempts < 1) {
            throw new \InvalidArgumentException('Invalid task storage configuration');
        }
    }
    public function initializeSchema(): void
    {
        $manager = $this->connection->createSchemaManager();
        if ($manager->tablesExist([$this->table])) {
            return;
        }
        $table = new Table($this->table);
        $table->addColumn('id', 'string', ['length' => 128]);
        $table->addColumn('principal', 'string', ['length' => 512]);
        $table->addColumn('tenant', 'string', ['length' => 512]);
        $table->addColumn('payload', 'text');
        $table->addColumn('revision', 'bigint');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['principal', 'tenant']);
        $manager->createTable($table);
    }
    public function create(Record $record): void
    {
        $id = $record->getTask()?->getId() ?? '';
        if ($id === '') {
            throw new \InvalidArgumentException('Task ID is required');
        }
        $this->connection->insert($this->table, ['id' => $id, 'principal' => $record->getPrincipal(), 'tenant' => $record->getTenant(), 'payload' => base64_encode($record->serializeToString()), 'revision' => $record->getRevision()]);
    }
    public function get(CallContext $context, string $id): Record
    {
        $query = $this->connection->createQueryBuilder()->select('payload')->from($this->table)->where('id = :id')->andWhere('principal = :principal')->andWhere('tenant = :tenant')->setParameters(['id' => $id, 'principal' => $context->principal, 'tenant' => $context->tenant]);
        $payload = $query->executeQuery()->fetchOne();
        if (!is_string($payload)) {
            throw new ProtocolException(ErrorCode::TaskNotFound, 'Task not found');
        }
        return $this->decode($payload);
    }
    public function update(CallContext $context, string $id, \Closure $change): Record
    {
        for ($attempt = 0; $attempt < $this->maxUpdateAttempts; ++$attempt) {
            $record = $this->get($context, $id);
            $revision = (int) $record->getRevision();
            $change($record);
            if ($record->getTask()?->getId() !== $id || $record->getPrincipal() !== $context->principal || $record->getTenant() !== $context->tenant) {
                throw new \LogicException('Task identity cannot change');
            }
            $record->setRevision($revision + 1);
            $updated = $this->connection->update($this->table, ['revision' => $revision + 1, 'payload' => base64_encode($record->serializeToString())], ['id' => $id, 'principal' => $context->principal, 'tenant' => $context->tenant, 'revision' => $revision]);
            if ($updated === 1) {
                return $record;
            }
        }
        throw new ProtocolException(ErrorCode::ResourceExhausted, 'Task update contention; retry request');
    }
    public function list(CallContext $context): iterable
    {
        $query = $this->connection->createQueryBuilder()->select('payload')->from($this->table)->where('principal = :principal')->andWhere('tenant = :tenant')->setParameters(['principal' => $context->principal, 'tenant' => $context->tenant]);
        foreach ($query->executeQuery()->iterateColumn() as $payload) {
            if (!is_string($payload)) {
                throw new \RuntimeException('Invalid task payload');
            }
            yield $this->decode($payload);
        }
    }
    public function pending(): iterable
    {
        foreach ($this->all() as $record) {
            if ($record->hasPending() && $record->getLeaseUntil() < microtime(true)) {
                yield $record;
            }
        }
    }
    public function deliveries(): iterable
    {
        foreach ($this->all() as $record) {
            if (count($record->getOutbox()) > 0) {
                yield $record;
            }
        }
    }
    /** @return iterable<Record> */
    private function all(): iterable
    {
        foreach ($this->connection->createQueryBuilder()->select('payload')->from($this->table)->executeQuery()->iterateColumn() as $payload) {
            if (!is_string($payload)) {
                throw new \RuntimeException('Invalid task payload');
            }
            yield $this->decode($payload);
        }
    }
    private function decode(string $payload): Record
    {
        $bytes = base64_decode($payload, true);
        if ($bytes === false) {
            throw new \RuntimeException('Corrupt task payload');
        }
        $record = new Record();
        $record->mergeFromString($bytes);
        return $record;
    }
}
