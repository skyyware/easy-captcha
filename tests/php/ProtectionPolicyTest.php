<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Tests\php;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skyyware\Starcode\Protection\ProtectionPolicy;
use Skyyware\Starcode\Protection\Settings;

final class ProtectionPolicyTest extends TestCase
{
    public function testDisabledByDefault(): void
    {
        self::assertFalse((new ProtectionPolicy())->protects(new Settings([]), '/form/newsletter'));
    }

    public function testEnabledDefaultsMatchTheVisibleNewsletterSelection(): void
    {
        $policy = new ProtectionPolicy();
        $settings = new Settings(['enabled' => true]);
        self::assertTrue($policy->protects($settings, '/form/newsletter'));
        self::assertFalse($policy->protects($settings, '/form/contact'));
        self::assertFalse($policy->protects(new Settings(['enabled' => 'true']), '/form/newsletter'));
    }

    public function testGroupsCoverStorefrontAndStoreApi(): void
    {
        $policy = new ProtectionPolicy();
        $settings = new Settings(['enabled' => true, 'groups' => ['newsletter']]);
        self::assertTrue($policy->protects($settings, '/form/newsletter'));
        self::assertTrue($policy->protects($settings, '/store-api/newsletter/subscribe'));
        self::assertFalse($policy->protects($settings, '/form/contact'));
        self::assertFalse($policy->protects($settings, '/store-api/newsletter/unsubscribe'));
    }

    #[DataProvider('reservedPaths')]
    public function testCustomWildcardCannotProtectCriticalRoutes(string $path): void
    {
        $settings = new Settings(['enabled' => true, 'mode' => 'all', 'paths' => '/*']);
        self::assertFalse((new ProtectionPolicy())->protects($settings, $path));
    }

    public static function reservedPaths(): array
    {
        return array_map(static fn (string $path): array => [$path], [
            '/checkout/order', '/checkout/line-item/add', '/api/order', '/admin',
            '/payment/finalize', '/widgets/checkout/info', '/store-api/checkout/order',
            '/store-api/handle-payment', '/account/logout', '/newsletter/confirm',
            '/starcode/challenge', '/hook/payment', '/webhook/payment',
        ]);
    }

    public function testAllMeansSupportedFormsNotEveryPost(): void
    {
        $settings = new Settings(['enabled' => true, 'mode' => 'all']);
        $policy = new ProtectionPolicy();
        self::assertTrue($policy->protects($settings, '/form/contact'));
        self::assertTrue($policy->protects($settings, '/product/123/rating'));
        self::assertFalse($policy->protects($settings, '/some-extension/action'));
    }

    public function testCustomPathsAreAnchoredAndSingleSegmentWildcards(): void
    {
        $settings = new Settings(['enabled' => true, 'groups' => [], 'paths' => "/form/custom/*\n/landing/subscribe"]);
        $policy = new ProtectionPolicy();
        self::assertTrue($policy->protects($settings, '/form/custom/one'));
        self::assertFalse($policy->protects($settings, '/form/custom/one/two'));
        self::assertFalse($policy->protects($settings, '/other/form/custom/one'));
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsAmbiguousPaths(string $path): void
    {
        self::assertNull(ProtectionPolicy::normalize($path));
    }

    public static function invalidPaths(): array
    {
        return array_map(static fn (string $path): array => [$path], [
            '//external.invalid/form', 'https://external.invalid/form', '/foo/../bar',
            '/foo%2fbar', '/foo\\bar', "/foo\nbar", '/foo//bar', '/foo?redirect=elsewhere',
        ]);
    }
}
