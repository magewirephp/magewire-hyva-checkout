<?php

/**
 * Copyright © Willem Poortman 2021-present. All rights reserved.
 *
 * Please read the README and LICENSE files for more
 * details on copyrights and license information.
 */

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Magewire\Features\SupportHyvaCheckoutBackwardsCompatibility;

use Hyva\Checkout\Magewire\Checkout\AddressView\AbstractMagewireAddressForm;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\ComponentHook;
use Magewirephp\Magewire\Features\SupportEvents\Event;
use Magewirephp\Magewire\Features\SupportEvents\SupportEvents;
use Magewirephp\Magewire\Mechanisms\HandleComponents\ComponentContext;
use Psr\Log\LoggerInterface;
use ReflectionException;

use function Magewirephp\Magewire\after;
use function Magewirephp\Magewire\before;
use function Magewirephp\Magewire\store;

/**
 * Bridges Magewire v1 (Livewire v2) component behavior for Hyvä Checkout.
 *
 * Hyvä Checkout was built on Magewire v1, which used Livewire v2 conventions. Magewire v3
 * (Livewire v3) introduced breaking changes to wire directives and entangle behavior:
 *
 *   - wire:model (instant sync)  → wire:model.live
 *   - wire:model.defer           → wire:model (now the default)
 *   - wire:model.lazy            → wire:model.blur
 *   - $wire.entangle() (live)    → $wire.entangle() (deferred by default)
 *
 * This feature flags components that need backwards compatibility by pushing a`bc.enabled`
 * memo flag into the snapshot. The flag is resolved through three sources (in priority order):
 *
 *   1. The #[HandleBackwardsCompatibility] attribute on the component class
 *   2. A previously hydrated value from the component's data store
 *   3. For newly mounted components: an opt-in set by the component resolver, or else whether
 *      the component lives inside the 'hyva-checkout-main' layout container
 *
 * The frontend JS (in magewire-attributes.phtml and magewire-components.phtml) reads this
 * flag to automatically migrate wire:model directives and make entangle default to live,
 * so existing Hyvä Checkout components work without code changes.
 */
class SupportHyvaCheckoutBackwardsCompatibility extends ComponentHook
{
    public function __construct(
        private readonly BackwardsCompatibility $backwardsCompatibility,
        private readonly LoggerInterface $logger,
        private readonly SupportEvents $supportEvents
    ) {
    }

    public function provide()
    {
        // Component boot()/hydrate() hooks may emit before this feature's hydrate() runs.
        before('hydrate', function (Component $component, array $memo): void {
            $this->backwardsCompatibility->hydrate($component, $memo);
        });

        after('dehydrate', function (Component $component, ComponentContext $context) {
            if ($context->isMounting() || ! $component instanceof AbstractMagewireAddressForm) {
                return;
            }

            /*
             * Temporary guard against duplicate event dispatches.
             * This is a minimal implementation, logic should be revisited
             * to ensure correctness across all edge cases.
             */
            $effects = $context->getEffects();
            $currentDispatches = $effects->getData('dispatches') ?? [];
            $newDispatches = $this->supportEvents->getServerDispatchedEvents($component);

            if ($currentDispatches === []) {
                $currentDispatches = $newDispatches;
            } else {
                foreach ($currentDispatches as $current) {
                    foreach ($newDispatches as $new) {
                        if ($current['name'] === $new['name']) {
                            continue;
                        }

                        $currentDispatches[] = $new;
                    }
                }
            }

            $context->addEffect('dispatches', $currentDispatches);
        });
    }

    public function hydrate($memo): void
    {
        $this->backwardsCompatibility->hydrate($this->component(), $memo);
    }

    public function dehydrate(ComponentContext $context): void
    {
        // For backwards compatibility, if an evaluation effect is present, push it into the 'serverMemo'
        // under the 'bc' key so older clients that rely on this memo structure continue to work correctly.
        $bcEffect = $context->getEffects()->getData('bc') ?? [];
        $evaluationEffect = $context->getEffects()->getData('evaluation');

        if (is_array($evaluationEffect)) {
            $bcEffect['serverMemo']['evaluation'] = $evaluationEffect;
            $context->pushEffect('bc', $bcEffect['serverMemo'], 'serverMemo');
        }

        try {
            $component = $this->component();
            store($component)->set('magewire:bc', $this->backwardsCompatibility->isEnabled($component));
        } catch (ReflectionException $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }

        $backwardsCompatibilityActive = $this->component() ? store($this->component())->get('magewire:bc') : false;

        $context->pushMemo('bc', $backwardsCompatibilityActive ?? false, 'enabled');

        $ancestorEvents = store($this->component())->get('hyvaCheckout:ancestorEvents', []);

        if ($ancestorEvents !== []) {
            $context->addEffect('hyvaCheckoutAncestorEvents', array_map(static fn (Event $event) => $event->serialize(), $ancestorEvents));
        }
    }
}
