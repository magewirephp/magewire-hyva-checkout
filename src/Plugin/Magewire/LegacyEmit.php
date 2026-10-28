<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Plugin\Magewire;

use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Features\SupportEvents\Event;
use Magewirephp\MagewireHyvaCheckout\Magewire\Features\SupportHyvaCheckoutBackwardsCompatibility\BackwardsCompatibility;

use function Magewirephp\Magewire\store;

/**
 * Adapt the deprecated emit API in Checkout without changing Magewire's v3 API.
 * Magento's interceptors forward all supplied arguments, including legacy extra arguments.
 */
class LegacyEmit
{
    public function __construct(
        private readonly BackwardsCompatibility $backwardsCompatibility
    ) {
    }

    public function beforeEmit(Component $subject, string $event, mixed ...$arguments): array|null
    {
        return $this->backwardsCompatibility->isEnabled($subject) ? [$event, $this->params($arguments)] : null;
    }

    public function beforeEmitSelf(Component $subject, string $event, mixed ...$arguments): array|null
    {
        return $this->beforeEmit($subject, $event, ...$arguments);
    }

    public function beforeEmitTo(Component $subject, string $name, string $event, mixed ...$arguments): array|null
    {
        return $this->backwardsCompatibility->isEnabled($subject) ? [$name, $event, $this->params($arguments)] : null;
    }

    public function beforeEmitToRefresh(Component $subject, string $name, mixed ...$arguments): array|null
    {
        return $this->backwardsCompatibility->isEnabled($subject) ? [$name, $this->params($arguments)] : null;
    }

    public function aroundEmitUp(Component $subject, callable $proceed, string $event, mixed ...$arguments): Event
    {
        if (! $this->backwardsCompatibility->isEnabled($subject)) {
            return $proceed($event, ...$arguments);
        }

        return $this->queueAncestorEvent($subject, $event, $arguments);
    }

    public function aroundEmitToRefreshUp(Component $subject, callable $proceed, mixed ...$arguments): Event
    {
        if (! $this->backwardsCompatibility->isEnabled($subject)) {
            return $proceed(...$arguments);
        }

        return $this->queueAncestorEvent($subject, '$refresh', $arguments);
    }

    private function queueAncestorEvent(Component $component, string $name, array $arguments): Event
    {
        $event = new Event($name, $this->params($arguments));
        store($component)->push('hyvaCheckout:ancestorEvents', $event);

        return $event;
    }

    /**
     * Several arguments become separate listener arguments. A single array keeps the
     * existing v3 parameter-map behavior; an explicit null stays a single argument.
     */
    private function params(array $arguments): array
    {
        if (count($arguments) > 1) {
            return $arguments;
        }

        $params = array_key_exists(0, $arguments) ? $arguments[0] : [];

        return is_array($params) ? $params : [$params];
    }
}
