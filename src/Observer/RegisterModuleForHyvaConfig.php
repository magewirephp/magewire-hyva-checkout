<?php

declare(strict_types=1);

namespace Magewirephp\MagewireHyvaCheckout\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterModuleForHyvaConfig implements ObserverInterface
{
    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar
    ) {
    }

    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');
        $extensions = $config->getData('extensions') ?? [];
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Magewirephp_MagewireHyvaCheckout');
        $extension = ['src' => substr($path, strlen(BP) + 1)];

        if (! in_array($extension, $extensions, true)) {
            $extensions[] = $extension;
        }

        $config->setData('extensions', $extensions);
    }
}
