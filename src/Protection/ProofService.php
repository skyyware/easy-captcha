<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Protection;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\{Altcha, CreateChallengeOptions, Payload, VerifySolutionOptions};

final readonly class ProofService
{
    public const TTL = 300;
    private Altcha $altcha;

    public function __construct(private string $secret, private ProofStore $store)
    {
        if (strlen($secret) < 16) {
            throw new \InvalidArgumentException('Starcode requires a configured application secret.');
        }
        $this->altcha = new Altcha(
            hash_hmac('sha256', 'starcode.challenge.v1', $secret),
            hash_hmac('sha256', 'starcode.key.v1', $secret),
        );
    }

    public function issue(string $channel, string $path, string $client): array
    {
        $rateKey = hash_hmac('sha256', 'starcode.rate.v1:' . $client, $this->secret);
        if (!$this->store->allow('global', 600) || !$this->store->allow($rateKey, 30)) {
            throw new \OverflowException('Challenge limit reached.');
        }
        return $this->altcha->createChallenge(new CreateChallengeOptions(
            new Pbkdf2(), 1000, counter: random_int(80, 160), expiresAt: time() + self::TTL,
            data: ['scope' => $this->scope($channel, $path)],
        ))->toArray();
    }

    public function verify(mixed $value, string $channel, string $path): bool
    {
        if (!is_string($value) || $value === '' || strlen($value) > 8192) {
            return false;
        }
        try {
            $decoded = base64_decode($value, true);
            if ($decoded === false) {
                return false;
            }
            $data = json_decode($decoded, true, 12, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !is_array($data['challenge']['parameters'] ?? null) || !is_array($data['solution'] ?? null)) {
                return false;
            }
            $parameters = $data['challenge']['parameters'];
            if (($parameters['data']['scope'] ?? null) !== $this->scope($channel, $path)
                || !is_int($parameters['expiresAt'] ?? null)
                || $parameters['expiresAt'] <= time()
                || $parameters['expiresAt'] > time() + self::TTL
                || ($parameters['algorithm'] ?? '') !== 'PBKDF2/SHA-256'
                || ($parameters['cost'] ?? null) !== 1000
                || !is_string($parameters['keySignature'] ?? null)
                || !is_string($data['challenge']['signature'] ?? null)
                || !is_string($data['solution']['derivedKey'] ?? null)
            ) {
                return false;
            }
            foreach (['nonce' => 32, 'salt' => 32, 'keyPrefix' => 32, 'keySignature' => 64] as $key => $length) {
                if (!is_string($parameters[$key] ?? null) || !preg_match('/^[a-f0-9]{' . $length . '}$/D', $parameters[$key])) {
                    return false;
                }
            }
            if (($parameters['keyLength'] ?? null) !== 32
                || !is_int($data['solution']['counter'] ?? null)
                || !is_numeric($data['solution']['time'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/D', $data['solution']['derivedKey'])
                || !preg_match('/^[a-f0-9]{64}$/D', $data['challenge']['signature'])
            ) {
                return false;
            }
            $payload = Payload::fromArray($data);
            if (!$this->altcha->verifySolution(new VerifySolutionOptions(payload: $payload, algorithm: new Pbkdf2()))->verified) {
                return false;
            }
        } catch (\InvalidArgumentException | \JsonException | \TypeError | \ValueError) {
            return false;
        }
        return $this->store->consume($payload->challenge->signature, $parameters['expiresAt']);
    }

    private function scope(string $channel, string $path): string
    {
        return hash('sha256', $channel . "\0" . $path);
    }
}
