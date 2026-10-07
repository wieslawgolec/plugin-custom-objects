<?php

declare(strict_types=1);

// Standalone test bootstrap (no full Mautic required).
// mautic/core-lib is not on public Packagist; CI removes it and installs only
// the packages needed for unit tests (doctrine/dbal, phpunit, symfony/*).

require dirname(__DIR__) . '/vendor/autoload.php';
