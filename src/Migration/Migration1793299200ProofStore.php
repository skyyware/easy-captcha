<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1793299200ProofStore extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793299200;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS skyy_starcode_proof (
            id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            expires_at BIGINT NOT NULL,
            INDEX idx_starcode_proof_expiry (expires_at)
        ) ENGINE=InnoDB');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS skyy_starcode_rate (
            id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            hits INT UNSIGNED NOT NULL,
            expires_at BIGINT NOT NULL,
            INDEX idx_starcode_rate_expiry (expires_at)
        ) ENGINE=InnoDB');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
