<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
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
             ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->load('MauticPlugin\\MauticFactorialBundle\\Twig\\', '../Twig/*Extension.php')
        ->tag('twig.extension');

    $services->alias('mautic.factorial.repository.page_activity', MauticPlugin\MauticFactorialBundle\Entity\PageActivityTrackingRepository::class);
    $services->alias(MauticPlugin\MauticFactorialBundle\Services\DwellTimeFilterQueryBuilder::getServiceId(), MauticPlugin\MauticFactorialBundle\Services\DwellTimeFilterQueryBuilder::class);
    $services->alias(MauticPlugin\MauticFactorialBundle\Services\DwellTimeOverallFilterQueryBuilder::getServiceId(), MauticPlugin\MauticFactorialBundle\Services\DwellTimeOverallFilterQueryBuilder::class);
};

