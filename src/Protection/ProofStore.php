<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Protection;

interface ProofStore
{
    public function consume(string $signature, int $expires): bool;

    public function allow(string $key, int $limit): bool;
}
