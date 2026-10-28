<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Tests\Fixtures;

use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;

#[HandleBackwardsCompatibility(enabled: false)]
class OptedOutComponent extends EventComponent
{
}
