<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Protection;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final readonly class SettingsProvider
{
    public function __construct(private SystemConfigService $config)
    {
    }

    public function forChannel(string $channel): Settings
    {
        $values = [];
        foreach (['enabled', 'mode', 'groups', 'paths'] as $key) {
            $value = $this->config->get('SkyyStarcode.config.' . $key, $channel);
            if ($value !== null) {
                $values[$key] = $value;
            }
        }
        return new Settings($values);
    }
}
