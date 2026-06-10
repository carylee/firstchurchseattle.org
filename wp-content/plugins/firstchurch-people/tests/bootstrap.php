<?php
/**
 * PHPUnit bootstrap. src/Person.php is pure PHP (no WordPress), so Composer's
 * autoloader is all we need — the same WP-free unit-test approach as events.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
