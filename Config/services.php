<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\MauticFactorialBundle\Entity\PageActivityTrackingRepository;
use MauticPlugin\MauticFactorialBundle\Services\DwellTimeFilterQueryBuilder;
use MauticPlugin\MauticFactorialBundle\Services\DwellTimeOverallFilterQueryBuilder;
use MauticPlugin\MauticFactorialBundle\Services\TotalPointsFilterQueryBuilder;
use MauticPlugin\MauticFactorialBundle\Services\UnopenedEmailsFilterQueryBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
                             ->defaults()
                             ->autowire()
                             ->autoconfigure()
                             ->public();

    $excludes = ['Api', 'Events'];

    $services->load('MauticPlugin\\MauticFactorialBundle\\', '../')
             ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->load('MauticPlugin\\MauticFactorialBundle\\Entity\\', '../Entity/*Repository.php')
             ->tag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->load('MauticPlugin\\MauticFactorialBundle\\Twig\\', '../Twig/*Extension.php')
        ->tag('twig.extension');

    $services->alias('mautic.factorial.repository.page_activity', PageActivityTrackingRepository::class);
    $services->alias(DwellTimeFilterQueryBuilder::getServiceId(), DwellTimeFilterQueryBuilder::class);
    $services->alias(DwellTimeOverallFilterQueryBuilder::getServiceId(), DwellTimeOverallFilterQueryBuilder::class);
    $services->alias(UnopenedEmailsFilterQueryBuilder::getServiceId(), UnopenedEmailsFilterQueryBuilder::class);
    $services->alias(TotalPointsFilterQueryBuilder::getServiceId(), TotalPointsFilterQueryBuilder::class);
};

