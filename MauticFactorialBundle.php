<?php

namespace MauticPlugin\MauticFactorialBundle;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;

class MauticFactorialBundle extends PluginBundleBase
{
    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedSchema = null): void
    {
        if (null === $metadata) {
            $metadata = self::getMetadata($factory->getEntityManager());
        }

        if (null !== $metadata) {
            parent::onPluginInstall($plugin, $factory, $metadata, $installedSchema);
        }
    }

    /**
     * Fix: plugin installer doesn't find metadata entities for the plugin
     * PluginBundle/Controller/PluginController:410.
     *
     * @return array|null
     */
    private static function getMetadata(EntityManager $em)
    {
        $allMetadata   = $em->getMetadataFactory()->getAllMetadata();
        $currentSchema = $em->getConnection()->createSchemaManager()->introspectSchema();

        $classes = [];

        /** @var \Doctrine\ORM\Mapping\ClassMetadata $meta */
        foreach ($allMetadata as $meta) {
            if (!str_contains($meta->namespace, 'MauticPlugin\\MauticFactorialBundle')) {
                continue;
            }

            $table = $meta->getTableName();

            if ($currentSchema->hasTable($table)) {
                continue;
            }

            $classes[] = $meta;
        }

        return $classes ?: null;
    }

    public static function onPluginUpdate(
        Plugin $plugin,
        MauticFactory $factory,
        $metadata = null,
        Schema $installedSchema = null
    ): void {
        // Not recommended although availalbe for simple schema changes - see updatePluginSchema docblock
        self::updatePluginSchema($metadata, $installedSchema, $factory);
    }
}
