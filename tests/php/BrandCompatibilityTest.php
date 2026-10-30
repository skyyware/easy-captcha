<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Tests\php;

use PHPUnit\Framework\TestCase;
use Skyyware\Starcode\Storefront\ChallengeController;
use Symfony\Component\Routing\Attribute\Route;

final class BrandCompatibilityTest extends TestCase
{
    public function testPackageRenamePreservesShopwareIdentity(): void
    {
        $root = dirname(__DIR__, 2);
        $package = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('skyyware/easy-captcha', $package['name']);
        self::assertSame('MIT', $package['license']);
        self::assertSame('Skyyware\\Starcode\\SkyyStarcode', $package['extra']['shopware-plugin-class']);
        self::assertSame(['Skyyware\\Starcode\\' => 'src/'], $package['autoload']['psr-4']);
        self::assertSame(['en-GB' => 'Easy Captcha', 'de-DE' => 'Easy Captcha'], $package['extra']['label']);
        self::assertSame('0.1.*', $package['replace']['skyyware/starcode']);
        foreach (['base.html.twig', 'starcode/error.html.twig'] as $template) {
            self::assertStringContainsString('?v=' . $package['version'], file_get_contents($root . '/src/Resources/views/storefront/' . $template));
        }
    }

    public function testChallengeRoutesKeepOldNamesAndNewAliases(): void
    {
        $method = new \ReflectionMethod(ChallengeController::class, 'challenge');
        $routes = [];
        foreach ($method->getAttributes(Route::class) as $attribute) {
            $route = $attribute->getArguments();
            $routes[$route['name']] = $route['path'];
            self::assertSame(['GET'], $route['methods']);
        }
        self::assertSame('/starcode/challenge', $routes['frontend.starcode.challenge']);
        self::assertSame('/store-api/starcode/challenge', $routes['store-api.starcode.challenge']);
        self::assertSame('/easy-captcha/challenge', $routes['frontend.easy_captcha.challenge'] ?? null);
        self::assertSame('/store-api/easy-captcha/challenge', $routes['store-api.easy_captcha.challenge'] ?? null);
    }
}
