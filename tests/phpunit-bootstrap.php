<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv('QUIQQER_OTHER_AUTOLOADERS=KEEP');

require_once __DIR__ . '/../../../../bootstrap.php';

$packageRoot = dirname(__DIR__);

spl_autoload_register(
    static function (string $class) use ($packageRoot): void {
        $prefix = 'QUI\\REST\\';

        if (
            !str_starts_with($class, $prefix)
            || str_starts_with($class, 'QUI\\REST\\Tests\\')
        ) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $packageRoot
            . '/src/QUI/REST/'
            . str_replace('\\', '/', $relativeClass)
            . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    },
    true,
    true
);

require_once __DIR__ . '/Fixtures/DocumentationTestProvider.php';
require_once __DIR__ . '/Fixtures/RoutingTestProvider.php';
require_once __DIR__ . '/Fixtures/RoutingTestServer.php';
