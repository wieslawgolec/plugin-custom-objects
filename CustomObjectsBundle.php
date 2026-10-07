<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Custom Objects Engine for Mautic 7.2+.
 *
 * Dynamically creates database tables (custom objects) linked to Contacts
 * via Doctrine SchemaManager, and injects Campaign Decision nodes.
 */
class CustomObjectsBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
