<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Tests\php;

use Skyyware\Starcode\Protection\ProofStore;

final class MemoryProofStore implements ProofStore
{
    private array $used = [];
    private array $buckets = [];

    public function consume(string $signature, int $expires): bool
    {
        if (isset($this->used[$signature])) {
            return false;
        }
        $this->used[$signature] = $expires;
        return true;
    }

    public function allow(string $key, int $limit): bool
    {
        $this->buckets[$key] = ($this->buckets[$key] ?? 0) + 1;
        return $this->buckets[$key] <= $limit;
    }
}
