<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Tests\php;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skyyware\Starcode\Command\CleanupCommand;
use Skyyware\Starcode\Protection\DatabaseProofStore;
use Symfony\Component\Console\Tester\CommandTester;

final class ProofCleanupTest extends TestCase
{
    public static function invalidBatchSizes(): array
    {
        return [[0], [-1], [10001]];
    }

    #[DataProvider('invalidBatchSizes')]
    public function testInvalidBatchSizeDoesNotReachTheDatabase(int $batchSize): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(\InvalidArgumentException::class);

        (new DatabaseProofStore($connection))->deleteExpired(1234, $batchSize);
    }

    public function testCleanupUsesOneCutoffAndBoundsEachTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $queries = [];
        $connection->expects(self::exactly(2))->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters) use (&$queries): int {
                $queries[] = $sql;
                self::assertSame([1234], $parameters);
                self::assertStringContainsString('WHERE expires_at <= ? LIMIT 100', $sql);
                return 3;
            });

        self::assertSame(6, (new DatabaseProofStore($connection))->deleteExpired(1234));
        self::assertStringContainsString('skyy_starcode_proof', $queries[0]);
        self::assertStringContainsString('skyy_starcode_rate', $queries[1]);
    }

    public function testDryRunDoesNotDelete(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls(2, 3);
        $connection->expects(self::never())->method('executeStatement');
        $command = new CommandTester(new CleanupCommand(new DatabaseProofStore($connection)));

        self::assertSame(0, $command->execute(['--dry-run' => true]));
        self::assertStringContainsString('5 expired rows; no changes made', $command->getDisplay());
    }

    public function testCommandRemovesAllBatchesAtTheOriginalCutoff(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturn(0);
        $cutoff = null;
        $results = [1000, 1000, 1, 2, 0, 0];
        $connection->expects(self::exactly(6))->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters) use (&$cutoff, &$results): int {
                $cutoff ??= $parameters;
                self::assertSame($cutoff, $parameters);
                self::assertStringContainsString('LIMIT 1000', $sql);
                return array_shift($results);
            });
        $command = new CommandTester(new CleanupCommand(new DatabaseProofStore($connection)));

        self::assertSame(0, $command->execute([]));
        self::assertStringContainsString('2003 expired rows removed; 0 remaining', $command->getDisplay());
    }

    public function testCommandDoesNotClaimCompletionWhenItsBatchBudgetIsExhausted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(200))->method('executeStatement')->willReturn(1000);
        $connection->expects(self::exactly(2))->method('fetchOne')->willReturn(1);
        $command = new CommandTester(new CleanupCommand(new DatabaseProofStore($connection)));

        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('2 remaining', $command->getDisplay());
    }
}
