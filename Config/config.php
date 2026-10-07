<?php

declare(strict_types=1);

return [
    'name'        => 'Custom Objects Engine',
    'description' => 'Unlocks infinite One-to-Many relational database entities within Mautic 7.2+ via dynamic Doctrine schema.',
    'version'     => '1.0.0',
    'author'      => 'Wieslaw Golec',

    'routes' => [
        'main' => [
            'mautic_customobjects_schema_create' => [
                'path'       => '/customobjects/schema/create',
                'controller' => 'MauticPlugin\CustomObjectsBundle\Controller\SchemaController::createAction',
                'methods'    => ['POST'],
            ],
            'mautic_customobjects_schema_list' => [
                'path'       => '/customobjects/schema/list',
                'controller' => 'MauticPlugin\CustomObjectsBundle\Controller\SchemaController::listAction',
                'methods'    => ['GET'],
            ],
            'mautic_customobjects_schema_drop' => [
                'path'       => '/customobjects/schema/drop/{objectName}',
                'controller' => 'MauticPlugin\CustomObjectsBundle\Controller\SchemaController::dropAction',
                'methods'    => ['DELETE'],
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.customobjects.campaign.subscriber' => [
                'class'     => \MauticPlugin\CustomObjectsBundle\EventSubscriber\CampaignSubscriber::class,
                'arguments' => [
                    'database_connection',
                    'mautic.customobjects.schema_manager',
                ],
            ],
        ],
        'other' => [
            'mautic.customobjects.schema_manager' => [
                'class'     => \MauticPlugin\CustomObjectsBundle\Service\DynamicSchemaManager::class,
                'arguments' => [
                    'database_connection',
                ],
            ],
            'mautic.customobjects.schema_controller' => [
                'class'     => \MauticPlugin\CustomObjectsBundle\Controller\SchemaController::class,
                'arguments' => [
                    'mautic.customobjects.schema_manager',
                ],
            ],
        ],
    ],
];
