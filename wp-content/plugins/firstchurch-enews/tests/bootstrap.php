<?php
/**
 * PHPUnit bootstrap. The render core (src/Email.php) is pure — it escapes with
 * PHP's htmlspecialchars and depends on no WordPress primitives — so the suite
 * runs standalone through Composer's autoloader.
 *
 * @package FirstChurch\ENews
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
