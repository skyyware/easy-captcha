<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Tests\php;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\{Altcha, Challenge, CreateChallengeOptions, Payload, SolveChallengeOptions};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skyyware\Starcode\Protection\ProofService;

final class ProofServiceTest extends TestCase
{
    private const SECRET = 'test-only-secret-never-used-outside-this-suite';

    public function testValidProofIsBoundToChannelAndPathAndSingleUse(): void
    {
        $service = new ProofService(self::SECRET, new MemoryProofStore());
        $token = $this->solve($service->issue('channel-a', '/form/contact', 'test-ip'));
        self::assertFalse($service->verify($token, 'channel-b', '/form/contact'));
        self::assertFalse($service->verify($token, 'channel-a', '/form/newsletter'));
        self::assertTrue($service->verify($token, 'channel-a', '/form/contact'));
        self::assertFalse($service->verify($token, 'channel-a', '/form/contact'));
    }

    public function testSignatureAndSolutionTamperingRejected(): void
    {
        $service = new ProofService(self::SECRET, new MemoryProofStore());
        $data = json_decode(base64_decode($this->solve($service->issue('a', '/form/contact', 'ip'))), true);
        $data['solution']['derivedKey'] = str_repeat('00', 32);
        self::assertFalse($service->verify(base64_encode(json_encode($data)), 'a', '/form/contact'));
        $data['challenge']['signature'] = str_repeat('0', 64);
        self::assertFalse($service->verify(base64_encode(json_encode($data)), 'a', '/form/contact'));
    }

    public function testExpiredSignedProofRejected(): void
    {
        $altcha = new Altcha(
            hash_hmac('sha256', 'starcode.challenge.v1', self::SECRET),
            hash_hmac('sha256', 'starcode.key.v1', self::SECRET),
        );
        $challenge = $altcha->createChallenge(new CreateChallengeOptions(
            new Pbkdf2(), 1000, counter: 1, expiresAt: time() - 1,
            data: ['scope' => hash('sha256', 'a' . "\0" . '/form/contact')],
        ));
        $service = new ProofService(self::SECRET, new MemoryProofStore());
        self::assertFalse($service->verify($this->solve($challenge->toArray()), 'a', '/form/contact'));
    }

    #[DataProvider('malformed')]
    public function testMalformedProofIsRejectedWithoutException(mixed $token): void
    {
        self::assertFalse((new ProofService(self::SECRET, new MemoryProofStore()))->verify($token, 'a', '/form/contact'));
    }

    public static function malformed(): array
    {
        return [[null], [[]], [''], ['%%%'], [str_repeat('A', 8193)], [base64_encode('null')],
            [base64_encode('{"challenge":{},"solution":{}}')], [base64_encode('{"challenge":[],"solution":"x"}')]];
    }

    public function testChallengeRateLimitIsBounded(): void
    {
        $service = new ProofService(self::SECRET, new MemoryProofStore());
        for ($i = 0; $i < 30; ++$i) {
            $service->issue('a', '/form/contact', 'same-client');
        }
        $this->expectException(\OverflowException::class);
        $service->issue('a', '/form/contact', 'same-client');
    }

    public function testRotatedSecretInvalidatesExistingProof(): void
    {
        $service = new ProofService(self::SECRET, new MemoryProofStore());
        $token = $this->solve($service->issue('a', '/form/contact', 'ip'));
        $rotated = new ProofService(self::SECRET . '-rotated', new MemoryProofStore());
        self::assertFalse($rotated->verify($token, 'a', '/form/contact'));
    }

    public function testUntrustedParameterTypesAndWorkCostCannotTriggerExpensiveVerification(): void
    {
        $service = new ProofService(self::SECRET, new MemoryProofStore());
        $valid = json_decode(base64_decode($this->solve($service->issue('a', '/form/contact', 'ip'))), true);
        foreach (['cost' => PHP_INT_MAX, 'keySignature' => [], 'expiresAt' => 'tomorrow', 'data' => 'invalid', 'nonce' => str_repeat('a', 4096)] as $key => $value) {
            $data = $valid;
            $data['challenge']['parameters'][$key] = $value;
            self::assertFalse($service->verify(base64_encode(json_encode($data)), 'a', '/form/contact'));
        }
    }

    private function solve(array $data): string
    {
        $challenge = Challenge::fromArray($data);
        $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions($challenge, new Pbkdf2(), timeout: 5));
        self::assertNotNull($solution);
        return (new Payload($challenge, $solution))->toBase64();
    }
}
