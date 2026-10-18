<?php
declare(strict_types=1);
spl_autoload_register(static function(string $name): void {
    $prefix = 'Firebase\\JWT\\';
    if (strncmp($name, $prefix, strlen($prefix)) !== 0) return;
    $class = substr($name, strlen($prefix));
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $class)) return;
    $path = __DIR__ . '/vendor/php-jwt/src/' . $class . '.php';
    if (is_file($path)) require_once $path;
});
