<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Postgres;

use A2A\Bundle\Storage\DoctrineTaskRepository;
use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Lf\A2a\V1\{SendMessageRequest, Task};
use PHPUnit\Framework\TestCase;

final class DoctrineRepositoryTest extends TestCase
{
    private Connection $connection;
    private DoctrineTaskRepository $repository;
    private string $table;

    protected function setUp(): void
    {
        $this->connection = $this->connect();
        $this->table = 'tasks_'.bin2hex(random_bytes(8));
        $this->repository = new DoctrineTaskRepository($this->connection, $this->table);
        $this->repository->initializeSchema();
        $this->repository->initializeSchema();
        $this->repository->create((new Record())->setTask((new Task())->setId('task'))->setPrincipal('alice')->setTenant('tenant'));
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    private function connect(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $this->requiredEnv('A2A_TEST_DB_HOST'),
            'port' => (int) $this->requiredEnv('A2A_TEST_DB_PORT'),
            'dbname' => $this->requiredEnv('A2A_TEST_DB_NAME'),
            'user' => $this->requiredEnv('A2A_TEST_DB_USER'),
            'password' => $this->requiredEnv('A2A_TEST_DB_PASSWORD'),
        ]);
    }

    private function requiredEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new \RuntimeException('Missing '.$name.'; run make test-postgres');
        }
        return $value;
    }

    public function testCommittedTaskSurvivesReconnect(): void
    {
        $this->connection->close();
        $record = $this->repository->get(new CallContext('alice', 'tenant'), 'task');
        self::assertSame('task', $record->getTask()->getId());
        self::assertSame('alice', $record->getPrincipal());
        self::assertCount(1, [...$this->repository->list(new CallContext('alice', 'tenant'))]);
    }

    public function testOtherPrincipalsAndTenantsCannotReadOrUpdateTask(): void
    {
        foreach ([new CallContext('bob', 'tenant'), new CallContext('alice', 'other')] as $context) {
            self::assertCount(0, [...$this->repository->list($context)]);
            foreach (['get', 'update'] as $operation) {
                try {
                    if ($operation === 'get') {
                        $this->repository->get($context, 'task');
                    } else {
                        $this->repository->update($context, 'task', static function (Record $record): void {
                            $record->setLeaseToken('unauthorized');
                        });
                    }
                    self::fail('Task was accessible outside its principal and tenant');
                } catch (ProtocolException $error) {
                    self::assertSame(ErrorCode::TaskNotFound, $error->error);
                }
            }
        }
    }

    public function testUpdateRetriesAfterAnotherConnectionWins(): void
    {
        $otherConnection = $this->connect();
        $other = new DoctrineTaskRepository($otherConnection, $this->table);
        $context = new CallContext('alice', 'tenant');
        $attempts = 0;
        try {
            $record = $this->repository->update($context, 'task', function (Record $record) use ($other, $context, &$attempts): void {
                if (++$attempts === 1) {
                    $other->update($context, 'task', static function (Record $winner): void {
                        $winner->setEventSequence(9);
                    });
                }
                $record->setLeaseToken('claimed');
            });
            self::assertSame(2, $attempts);
            self::assertSame(9, (int) $record->getEventSequence());
            self::assertSame(2, (int) $record->getRevision());
            self::assertSame('claimed', $other->get($context, 'task')->getLeaseToken());
        } finally {
            $otherConnection->close();
        }
    }

    public function testContentionLimitLeavesWinningUpdateIntact(): void
    {
        $otherConnection = $this->connect();
        $other = new DoctrineTaskRepository($otherConnection, $this->table);
        $limited = new DoctrineTaskRepository($this->connection, $this->table, maxUpdateAttempts: 1);
        $context = new CallContext('alice', 'tenant');
        try {
            try {
                $limited->update($context, 'task', static function (Record $record) use ($other, $context): void {
                    $other->update($context, 'task', static function (Record $winner): void {
                        $winner->setLeaseToken('winner');
                    });
                    $record->setLeaseToken('loser');
                });
                self::fail('Conflicting update exceeded the retry limit');
            } catch (ProtocolException $error) {
                self::assertSame(ErrorCode::ResourceExhausted, $error->error);
            }
            self::assertSame('winner', $other->get($context, 'task')->getLeaseToken());
        } finally {
            $otherConnection->close();
        }
    }

    public function testTransactionRollbackRestoresTask(): void
    {
        $context = new CallContext('alice', 'tenant');
        $this->connection->beginTransaction();
        try {
            $this->repository->update($context, 'task', static function (Record $record): void {
                $record->setLeaseToken('uncommitted');
            });
        } finally {
            $this->connection->rollBack();
        }
        self::assertSame('', $this->repository->get($context, 'task')->getLeaseToken());
        self::assertSame(0, (int) $this->repository->get($context, 'task')->getRevision());
    }

    public function testDuplicateTaskDoesNotOverwriteOriginal(): void
    {
        try {
            $this->repository->create((new Record())->setTask((new Task())->setId('task'))->setPrincipal('bob'));
            self::fail('Duplicate task ID was accepted');
        } catch (UniqueConstraintViolationException) {
            self::assertSame('alice', $this->repository->get(new CallContext('alice', 'tenant'), 'task')->getPrincipal());
        }
    }

    public function testWorkerSkipsActiveLeaseAndFindsExpiredLease(): void
    {
        $context = new CallContext('alice', 'tenant');
        $this->repository->update($context, 'task', static function (Record $record): void {
            $record->setPending(new SendMessageRequest())->setLeaseUntil(microtime(true) + 60);
        });
        self::assertCount(0, [...$this->repository->pending()]);
        $this->repository->update($context, 'task', static function (Record $record): void {
            $record->setLeaseUntil(0);
        });
        $pending = [...$this->repository->pending()];
        self::assertCount(1, $pending);
        self::assertSame('task', $pending[0]->getTask()->getId());
    }
}
