<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Each PHPStan config the tests run under builds and keeps a container of its own, which takes
// the suite past PHP's default limit. Raised only, so a higher limit or none is left as it is.
$memoryLimit = (string) ini_get('memory_limit');
if ('-1' !== $memoryLimit && ini_parse_quantity($memoryLimit) < ini_parse_quantity('512M')) {
    ini_set('memory_limit', '512M');
}
