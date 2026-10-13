<?php

$autoload = getenv('PURESMS_TEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
if (! is_file($autoload)) {
    throw new RuntimeException('Set PURESMS_TEST_AUTOLOAD to an installed Laravel application vendor/autoload.php.');
}
require_once $autoload;

spl_autoload_register(function ($class) {
    $prefix = 'Puresms\\Laravel\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
}, true, true);
