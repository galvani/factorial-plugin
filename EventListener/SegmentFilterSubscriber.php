<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\Event\SegmentDictionaryGenerationEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use MauticPlugin\MauticFactorialBundle\Services\DwellTimeFilterQueryBuilder;
use MauticPlugin\MauticFactorialBundle\Services\DwellTimeOverallFilterQueryBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface           $translator,
        private TypeOperatorProviderInterface $typeOperatorProvider,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => [['onGenerateSegmentFilters', -10]],
            LeadEvents::SEGMENT_DICTIONARY_ON_GENERATE   => [['onSegmentDictionaryGenerate', 0]],
        ];
    }

    public function onGenerateSegmentFilters(LeadListFiltersChoicesEvent $event): void
    {
        if (!$event->isForSegmentation()) {
            return;
        }

        $event->addChoice(
            'page_activity',
            'page_activity_dwell_time',
            [
                'label'      => $this->translator->trans('mautic.factorial.segment.filter.dwell_time'),
                'properties' => ['type' => 'number'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('default'),
                'object'     => 'page_activity_tracking',
            ]);

        $event->addChoice(
            'page_activity',
            'page_activity_dwell_time_url',
            [
                'label'      => $this->translator->trans('mautic.factorial.segment.filter.dwell_time_page'),
                'properties' => ['type' => 'string'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('string'),
                'object'     => 'page_activity_tracking',
            ]);

        $event->addChoice(
            'page_activity',
            'page_activity_dwell_time_overall',
            [
                'label'      => $this->translator->trans('mautic.factorial.segment.filter.dwell_time_overall'),
                'properties' => ['type' => 'number'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('number'),
                'object'     => 'view_page_activity',
            ]);
    }

    public function onSegmentDictionaryGenerate(SegmentDictionaryGenerationEvent $event): void
    {
        $event->addTranslation('page_activity_dwell_time', [
            'type'                => DwellTimeFilterQueryBuilder::getServiceId(),
            'foreign_table'       => 'page_activity_tracking',
            'foreign_table_field' => 'lead_id',
            'table'               => 'leads',
            'table_field'         => 'id',
            'field'               => 'dwell_time',
            'where'               => null,
            'null_value'          => 0,
        ]);

        $event->addTranslation('page_activity_dwell_time_url', [
            'type'                => DwellTimeFilterQueryBuilder::getServiceId(),
            'foreign_table'       => 'page_activity_tracking',
            'foreign_table_field' => 'lead_id',
            'table'               => 'leads',
            'table_field'         => 'id',
            'field'               => 'page_url',
            'where'               => null,
            'null_value'          => 0,
        ]);

        $event->addTranslation('page_activity_dwell_time_overall', [
            'type'                => DwellTimeOverallFilterQueryBuilder::getServiceId(),
            'foreign_table'       => 'page_activity_tracking',
            'foreign_table_field' => 'lead_id',
            'table'               => 'leads',
            'table_field'         => 'id',
            'field'               => 'dwell_time',
            'where'               => null,
            'null_value'          => 0,
        ]);

    }

}