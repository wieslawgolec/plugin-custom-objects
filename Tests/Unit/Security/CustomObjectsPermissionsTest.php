<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Security;

use MauticPlugin\CustomObjectsBundle\Security\CustomObjectsPermissions;
use PHPUnit\Framework\TestCase;

final class CustomObjectsPermissionsTest extends TestCase
{
    public function testDefaultsAllowAll(): void
    {
        $perms = new CustomObjectsPermissions();
        foreach (CustomObjectsPermissions::permissionList() as $p) {
            $this->assertTrue($perms->isGranted($p), "Expected $p to be granted");
        }
    }

    public function testOverrideDenies(): void
    {
        $perms = new CustomObjectsPermissions([
            CustomObjectsPermissions::DELETE => false,
        ]);
        $this->assertFalse($perms->isGranted(CustomObjectsPermissions::DELETE));
        $this->assertTrue($perms->isGranted(CustomObjectsPermissions::VIEW));
    }

    public function testUnknownPermissionDenied(): void
    {
        $perms = new CustomObjectsPermissions();
        $this->assertFalse($perms->isGranted('customobjects:unknown:foo'));
    }

    public function testPermissionConfigStructure(): void
    {
        $cfg = CustomObjectsPermissions::getPermissionConfig();
        $this->assertArrayHasKey('objects', $cfg);
        $this->assertArrayHasKey('items', $cfg);
        $this->assertArrayHasKey('import', $cfg);
        $this->assertArrayHasKey('export', $cfg);
        $this->assertSame(CustomObjectsPermissions::VIEW, $cfg['objects']['view']);
    }

    public function testAllReturnsMap(): void
    {
        $perms = new CustomObjectsPermissions();
        $this->assertCount(count(CustomObjectsPermissions::permissionList()), $perms->all());
    }
}
