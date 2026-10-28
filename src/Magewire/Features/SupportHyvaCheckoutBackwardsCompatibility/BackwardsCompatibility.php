<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Magewire\Features\SupportHyvaCheckoutBackwardsCompatibility;

use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;
use Magewirephp\Magewire\Mechanisms\ResolveComponents\Management\LayoutLifecycleManager;
use ReflectionClass;

use function Magewirephp\Magewire\store;

class BackwardsCompatibility
{
    public function __construct(
        private readonly LayoutLifecycleManager $renderLifecycleManager
    ) {
    }

    public function hydrate(Component $component, array $memo): void
    {
        store($component)->set('magewire:bc', $memo['bc']['enabled'] ?? false);
        store($component)->set('hyvaCheckout:bc:hydrated', true);
    }

    public function isEnabled(Component $component): bool
    {
        // Include parent classes: Magento's generated interceptors do not carry PHP attributes.
        for ($class = new ReflectionClass($component); $class !== false; $class = $class->getParentClass()) {
            $attributes = $class->getAttributes(HandleBackwardsCompatibility::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance()->isBackwardsCompatible();
            }
        }

        $enabled = store($component)->get('magewire:bc', false) === true;

        if (store($component)->get('hyvaCheckout:bc:hydrated', false)) {
            return $enabled;
        }

        // Resolve this before mount() can emit, rather than waiting for dehydrate().
        return $enabled || $this->renderLifecycleManager->forMagewire()->within('hyva-checkout-main');
    }
}
