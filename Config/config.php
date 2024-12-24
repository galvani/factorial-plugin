<?php

return [
    'name'        => 'Factorial',
    'description' => 'Enables custom Factorial functionalities',
    'version'     => '1.0',
    'author'      => 'Factorial',
    'routes'      => [
        'public' => [
            'factorial_page_tracking_cors' => [
                'path'       => '/mtc/page',
                'controller' => 'MauticPlugin\MauticFactorialBundle\Controller\PublicController::pageTrackingAction',
            ],
        ],
    ],
    'services'    => [
        'integrations' => [
            'mautic.integration.factorialhubspot' => [
                'class'     => MauticPlugin\MauticFactorialBundle\Integration\FactorialhubspotIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                    'mautic.helper.user',
                    'mautic.point.model.point',
                ],
            ],
            'mautic.integration.factorial' => [
                'class'     => MauticPlugin\MauticFactorialBundle\Integration\FactorialIntegration::class,
                'arguments' => [],
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
        ],
    ],
    'other'       => [],
];
