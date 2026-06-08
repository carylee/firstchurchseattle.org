<?php
/**
 * PHPUnit bootstrap. The src/ core is pure PHP (DateTime + rlanvin/php-rrule).
 * rlanvin is vendored under lib/ (runtime dep, not Composer); require it the
 * same way production does. Composer's autoloader provides our own classes +
 * PHPUnit.
 */

declare(strict_types=1);

date_default_timezone_set('UTC'); // deterministic weekday math outside WordPress

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../lib/rrule/RRuleInterface.php';
require_once __DIR__ . '/../lib/rrule/RRuleTrait.php';
require_once __DIR__ . '/../lib/rrule/RfcParser.php';
require_once __DIR__ . '/../lib/rrule/RRule.php';
require_once __DIR__ . '/../lib/rrule/RSet.php';
