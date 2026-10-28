<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Tests\Fixtures;

use Magewirephp\Magewire\Component;

class EventComponent extends Component
{
    public function onPair(string $left, string $right): array
    {
        return [$left, $right];
    }
}
