<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Storefront;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Skyyware\Starcode\Protection\{ProofService, ProtectionPolicy, SettingsProvider};
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;

final readonly class ChallengeController
{
    public function __construct(private SettingsProvider $settings, private ProtectionPolicy $policy, private ProofService $proof)
    {
    }

    #[Route(path: '/starcode/challenge', name: 'frontend.starcode.challenge', defaults: ['_routeScope' => ['storefront'], 'XmlHttpRequest' => true], methods: ['GET'])]
    #[Route(path: '/store-api/starcode/challenge', name: 'store-api.starcode.challenge', defaults: ['_routeScope' => ['store-api']], methods: ['GET'])]
    public function challenge(Request $request, SalesChannelContext $context): JsonResponse
    {
        $path = $request->query->all()['target'] ?? null;
        $channel = $context->getSalesChannelId();
        if (!is_string($path) || !$this->policy->protects($this->settings->forChannel($channel), $path)) {
            return $this->response(['error' => 'STARCODE_SCOPE_NOT_ENABLED'], 404);
        }
        try {
            $challenge = $this->proof->issue($channel, ProtectionPolicy::normalize($path), $request->getClientIp() ?? 'unknown');
        } catch (\OverflowException) {
            $response = $this->response(['error' => 'STARCODE_RATE_LIMIT'], 429);
            $response->headers->set('Retry-After', '60');
            return $response;
        } catch (\Doctrine\DBAL\Exception) {
            return $this->response(['error' => 'STARCODE_TEMPORARILY_UNAVAILABLE'], 503);
        }
        return $this->response($challenge, 200);
    }

    private function response(array $data, int $status): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }
}
