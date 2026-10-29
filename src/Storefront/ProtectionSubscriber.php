<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Storefront;

use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Skyyware\Starcode\Protection\{ProofService, ProtectionPolicy, SettingsProvider};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ProtectionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SettingsProvider $settings,
        private ProtectionPolicy $policy,
        private ProofService $proof,
        private TranslatorInterface $translator,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['validate', -21]];
    }

    public function validate(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST')) {
            return;
        }
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $scopes = $request->attributes->get('_routeScope', []);
        if (!$context instanceof SalesChannelContext || !is_array($scopes)
            || array_intersect(['storefront', 'store-api'], $scopes) === []
        ) {
            return;
        }
        $path = ProtectionPolicy::normalize(rawurldecode($request->getPathInfo()));
        $channel = $context->getSalesChannelId();
        if ($path === null || !$this->policy->protects($this->settings->forChannel($channel), $path)) {
            return;
        }
        try {
            $body = $request->getPayload()->all();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            $body = [];
        }
        if ($path === '/form/newsletter' && ($body['option'] ?? null) === 'unsubscribe') {
            return;
        }
        $value = $body['starcode'] ?? $request->headers->get('X-Starcode-Proof');
        $status = 422;
        $code = 'STARCODE_PROOF_REQUIRED';
        try {
            if ($this->proof->verify($value, $channel, $path)) {
                return;
            }
        } catch (\Doctrine\DBAL\Exception) {
            $status = 503;
            $code = 'STARCODE_TEMPORARILY_UNAVAILABLE';
        }
        $message = $this->translator->trans($status === 503 ? 'starcode.unavailable' : 'starcode.retry');
        $event->setController(function () use ($request, $scopes, $status, $code, $message): Response {
            if (in_array('store-api', $scopes, true) || $request->isXmlHttpRequest()) {
                return new JsonResponse(['errors' => [['code' => $code, 'status' => (string) $status, 'title' => $message]]], $status, ['Cache-Control' => 'no-store']);
            }
            return new Response($this->twig->render('@SkyyStarcode/storefront/starcode/error.html.twig', ['message' => $message]), $status, ['Cache-Control' => 'no-store']);
        });
    }
}
