<?php

declare(strict_types=1);

require getenv('MAGEWIRE_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Magewirephp\\MagewireHyvaCheckout\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = str_starts_with($relative, 'Tests\\')
        ? __DIR__ . '/' . substr($relative, strlen('Tests\\'))
        : dirname(__DIR__) . '/src/' . $relative;
    $file = str_replace('\\', '/', $path) . '.php';

    if (is_file($file)) {
        require $file;
    }
}, true, true);
