<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Protection;

final class ProtectionPolicy
{
    public const GROUPS = [
        'newsletter' => ['/form/newsletter', '/store-api/newsletter/subscribe'],
        'contact' => ['/form/contact', '/store-api/contact-form'],
        'registration' => ['/account/register', '/store-api/account/register'],
        'recovery' => ['/account/recover', '/store-api/account/recovery-password'],
        'reviews' => ['/product/*/rating', '/store-api/product/*/review'],
        'revocation' => ['/form/revocation/request', '/store-api/revocation-request-form'],
    ];

    public function patterns(Settings $settings): array
    {
        if (!$settings->enabled) {
            return [];
        }
        $patterns = $settings->paths;
        foreach (self::GROUPS as $group => $paths) {
            if ($settings->mode === 'all' || in_array($group, $settings->groups, true)) {
                array_push($patterns, ...$paths);
            }
        }
        return array_values(array_unique(array_filter($patterns, fn (string $path): bool => !$this->reserved($path))));
    }

    public function protects(Settings $settings, string $path): bool
    {
        $normalized = self::normalize($path);
        if ($normalized === null || $this->reserved($normalized)) {
            return false;
        }
        foreach ($this->patterns($settings) as $pattern) {
            if (self::matches($pattern, $normalized)) {
                return true;
            }
        }
        return false;
    }

    public static function normalize(string $path): ?string
    {
        if (strlen($path) > 512 || !preg_match('~^/(?:[a-zA-Z0-9_-]+/?)*$~D', $path) || str_contains($path, '//')) {
            return null;
        }
        return rtrim($path, '/') ?: '/';
    }

    public static function matches(string $pattern, string $path): bool
    {
        return preg_match('~^' . str_replace('\\*', '[^/]+', preg_quote($pattern, '~')) . '$~D', $path) === 1;
    }

    private function reserved(string $path): bool
    {
        foreach (self::GROUPS as $paths) {
            foreach ($paths as $pattern) {
                if (self::matches($pattern, $path)) {
                    return false;
                }
            }
        }
        return preg_match('~^/(?:api|admin|store-api|checkout|account|registration|newsletter|payment|payments|paypal|amazon|adyen|mollie|stripe|widgets|starcode|easy-captcha|hook|hooks|webhook|webhooks)(?:/|$)~i', $path) === 1;
    }
}
