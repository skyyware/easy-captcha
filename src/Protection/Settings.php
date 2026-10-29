<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Protection;

final readonly class Settings
{
    public bool $enabled;
    public string $mode;
    public array $groups;
    public array $paths;

    public function __construct(array $values)
    {
        $this->enabled = ($values['enabled'] ?? false) === true;
        $this->mode = ($values['mode'] ?? '') === 'all' ? 'all' : 'selected';
        $groups = $values['groups'] ?? ['newsletter'];
        $this->groups = is_array($groups) ? array_values(array_intersect(array_keys(ProtectionPolicy::GROUPS), $groups)) : [];
        $lines = is_string($values['paths'] ?? null) ? preg_split('/\R/', $values['paths']) : [];
        $this->paths = array_values(array_filter(array_slice(array_map('trim', $lines ?: []), 0, 40), static fn (string $path): bool =>
            strlen($path) <= 256 && preg_match('~^/(?:[a-zA-Z0-9_-]+/)+(?:[a-zA-Z0-9_-]+|\*)$~D', $path) === 1
        ));
    }
}
