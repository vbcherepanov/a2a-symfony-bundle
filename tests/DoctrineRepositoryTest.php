<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests;

use A2A\Bundle\Storage\DoctrineTaskRepository;
use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Security\CallContext;
use A2A\Storage\Proto\Record;
use Doctrine\DBAL\DriverManager;
use Lf\A2a\V1\Task;
use PHPUnit\Framework\TestCase;

final class DoctrineRepositoryTest extends TestCase
{
    private function repository(): DoctrineTaskRepository
    {
        $repository = new DoctrineTaskRepository(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $repository->initializeSchema();
        $repository->initializeSchema();
        $repository->create((new Record())->setTask((new Task())->setId('task'))->setPrincipal('alice')->setTenant('tenant'));
        return $repository;
    }
    public function testTenantAndPrincipalIsolation(): void
    {
        $repository = $this->repository();
        self::assertCount(1, [...$repository->list(new CallContext('alice', 'tenant'))]);
        foreach ([new CallContext('bob', 'tenant'), new CallContext('alice', 'other')] as $context) {
            self::assertCount(0, [...$repository->list($context)]);
            try {
                $repository->get($context, 'task');
                self::fail('Unauthorized record visible');
            } catch (ProtocolException $error) {
                self::assertSame(ErrorCode::TaskNotFound, $error->error);
            }
        }
    }
    public function testOptimisticRetryPreservesConcurrentUpdate(): void
    {
        $repository = $this->repository();
        $context = new CallContext('alice', 'tenant');
        $attempts = 0;
        $result = $repository->update($context, 'task', function (Record $record) use ($repository, $context, &$attempts): void {
            if (++$attempts === 1) {
                $repository->update($context, 'task', static function (Record $other): void {
                    $other->setEventSequence(9);
                });
            }
            $record->setLeaseToken('owned');
        });
        self::assertSame(2, $attempts);
        self::assertSame(9, (int) $result->getEventSequence());
        self::assertSame(2, (int) $result->getRevision());
        self::assertSame('owned', $result->getLeaseToken());
    }
    public function testIdentityCannotBeMutated(): void
    {
        $repository = $this->repository();
        $this->expectException(\LogicException::class);
        $repository->update(new CallContext('alice', 'tenant'), 'task', static function (Record $record): void {
            $record->setPrincipal('bob');
        });
    }
}
