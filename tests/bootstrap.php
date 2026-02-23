<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// nextcloud/ocp package doesn't define autoload — register OCP namespace manually
spl_autoload_register(function (string $class): void {
    $prefix = 'OCP\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/../vendor/nextcloud/ocp/OCP/' . $relative . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    $prefix = 'OCA\\';
    if (str_starts_with($class, $prefix)) {
        // Already handled by composer autoload for our app
    }
});
