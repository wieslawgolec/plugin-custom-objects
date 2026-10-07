<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Security;

/**
 * Permission constants and helper for Custom Objects.
 */
class CustomObjectsPermissions
{
    public const BUNDLE = 'customobjects';

    public const VIEW   = 'customobjects:objects:view';
    public const CREATE = 'customobjects:objects:create';
    public const EDIT   = 'customobjects:objects:edit';
    public const DELETE = 'customobjects:objects:delete';

    public const ITEMS_VIEW   = 'customobjects:items:view';
    public const ITEMS_CREATE = 'customobjects:items:create';
    public const ITEMS_EDIT   = 'customobjects:items:edit';
    public const ITEMS_DELETE = 'customobjects:items:delete';

    public const IMPORT = 'customobjects:import:full';
    public const EXPORT = 'customobjects:export:full';

    /** @var array<string, bool> */
    private array $grants;

    /**
     * @param array<string, bool> $grants
     */
    public function __construct(array $grants = [])
    {
        $defaults = [
            self::VIEW         => true,
            self::CREATE       => true,
            self::EDIT         => true,
            self::DELETE       => true,
            self::ITEMS_VIEW   => true,
            self::ITEMS_CREATE => true,
            self::ITEMS_EDIT   => true,
            self::ITEMS_DELETE => true,
            self::IMPORT       => true,
            self::EXPORT       => true,
        ];
        $this->grants = array_merge($defaults, $grants);
    }

    public function isGranted(string $permission): bool
    {
        return $this->grants[$permission] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return $this->grants;
    }

    /**
     * @return list<string>
     */
    public static function permissionList(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::ITEMS_VIEW,
            self::ITEMS_CREATE,
            self::ITEMS_EDIT,
            self::ITEMS_DELETE,
            self::IMPORT,
            self::EXPORT,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function getPermissionConfig(): array
    {
        return [
            'objects' => [
                'view'   => self::VIEW,
                'create' => self::CREATE,
                'edit'   => self::EDIT,
                'delete' => self::DELETE,
            ],
            'items' => [
                'view'   => self::ITEMS_VIEW,
                'create' => self::ITEMS_CREATE,
                'edit'   => self::ITEMS_EDIT,
                'delete' => self::ITEMS_DELETE,
            ],
            'import' => [
                'full' => self::IMPORT,
            ],
            'export' => [
                'full' => self::EXPORT,
            ],
        ];
    }
}
