<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Protection;

use Doctrine\DBAL\Connection;

final readonly class DatabaseProofStore implements ProofStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function consume(string $signature, int $expires): bool
    {
        return $this->connection->executeStatement(
            'INSERT IGNORE INTO skyy_starcode_proof (id, expires_at) VALUES (?, ?)',
            [hash('sha256', $signature), $expires],
        ) === 1;
    }

    public function allow(string $key, int $limit): bool
    {
        $now = time();
        $id = hash('sha256', $key . ':' . intdiv($now, 60));
        $this->deleteExpired($now);
        $this->connection->executeStatement(
            'INSERT INTO skyy_starcode_rate (id, hits, expires_at) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE hits = LEAST(hits + 1, 100000)',
            [$id, $now + 120],
        );
        return (int) $this->connection->fetchOne('SELECT hits FROM skyy_starcode_rate WHERE id = ?', [$id]) <= $limit;
    }

    public function deleteExpired(int $cutoff, int $batchSize = 100): int
    {
        if ($batchSize < 1 || $batchSize > 10000) {
            throw new \InvalidArgumentException('Cleanup batch size must be between 1 and 10000.');
        }

        return $this->connection->executeStatement('DELETE FROM skyy_starcode_proof WHERE expires_at <= ? LIMIT ' . $batchSize, [$cutoff])
            + $this->connection->executeStatement('DELETE FROM skyy_starcode_rate WHERE expires_at <= ? LIMIT ' . $batchSize, [$cutoff]);
    }

    public function countExpired(int $cutoff): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM skyy_starcode_proof WHERE expires_at <= ?', [$cutoff])
            + (int) $this->connection->fetchOne('SELECT COUNT(*) FROM skyy_starcode_rate WHERE expires_at <= ?', [$cutoff]);
    }
}
