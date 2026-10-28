<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Tests\Fixtures;

use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;

#[HandleBackwardsCompatibility]
class OptedInComponent extends EventComponent
{
}
