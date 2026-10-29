<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Storefront;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Skyyware\Starcode\Protection\{ProtectionPolicy, SettingsProvider};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RenderSubscriber implements EventSubscriberInterface
{
    public function __construct(private SettingsProvider $settings, private ProtectionPolicy $policy, private UrlGeneratorInterface $router, private TranslatorInterface $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [StorefrontRenderEvent::class => 'render'];
    }

    public function render(StorefrontRenderEvent $event): void
    {
        $settings = $this->settings->forChannel($event->getSalesChannelContext()->getSalesChannelId());
        if (!$settings->enabled) {
            return;
        }
        $event->setParameter('starcode', [
            'challengeUrl' => $this->router->generate('frontend.starcode.challenge'),
            'basePath' => $event->getRequest()->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL, ''),
            'patterns' => $this->policy->patterns($settings),
            'language' => str_starts_with($event->getRequest()->getLocale(), 'de') ? 'de' : 'en',
            'retryMessage' => $this->translator->trans('starcode.retry'),
            'unavailableMessage' => $this->translator->trans('starcode.unavailable'),
        ]);
    }
}
