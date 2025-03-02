<?php

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Mautic\CacheBundle\Cache\CacheProvider;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\LeadChangeCompanyEvent;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Event\LeadUtmTagsEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\PageEvents;
use Mautic\PointBundle\Entity\Point;
use Mautic\PointBundle\Entity\PointRepository;
use Mautic\PointBundle\Event\PointBuilderEvent;
use Mautic\PointBundle\Model\PointModel;
use Mautic\PointBundle\PointEvents;
use MauticPlugin\MauticFactorialBundle\Form\Type\PageUtmSourceHitType;
use MauticPlugin\MauticFactorialBundle\Form\Type\PointPropertyChangeType;
use MauticPlugin\MauticFactorialBundle\Helper\PointFieldChangeHelper;
use MauticPlugin\MauticFactorialBundle\Helper\PointUtmSourceHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PointSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LeadModel             $leadModel,
        private PointModel            $pointModel,
        private PointRepository       $pointRepository,
        private CacheProvider         $cacheProvider,
        private CompanyLeadRepository $companyLeadRepository,
        private LoggerInterface       $logger,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PointEvents::POINT_ON_BUILD     => ['onPointBuild', 0],
            LeadEvents::LEAD_POST_SAVE      => ['onLeadPostSave', 0],
            LeadEvents::COMPANY_POST_SAVE   => ['onCompanyPostSave', 0],
            LeadEvents::LEAD_COMPANY_CHANGE => ['onLeadAddToCompany', 0],
            LeadEvents::LEAD_UTMTAGS_ADD    => ['onLeadTagsAdd', 0],
            PageEvents::PAGE_ON_HIT         => ['onPageHitTracked', 0],
        ];
    }

    public function onPointBuild(PointBuilderEvent $event): void
    {
        $action = [
            'group'    => 'mautic.lead.actions',
            'label'    => 'mautic.lead.point.action.property_change',
            'callback' => [PointFieldChangeHelper::class, 'match'],
            'formType' => PointPropertyChangeType::class,
        ];

        $event->addAction('lead.property_change', $action);

        $action = [
            'group'       => 'mautic.page.point.action',
            'label'       => 'mautic.page.point.action.utm_source',
            'description' => 'mautic.page.point.action.utm_source_description',
            'callback'    => [PointUtmSourceHelper::class, 'match'],
            'formType'    => PageUtmSourceHitType::class,
        ];

        $event->addAction('page.utm.hit', $action);
    }

    public function onLeadAddToCompany(LeadChangeCompanyEvent $event): void
    {
        if (!$event->wasAdded()) {
            return;
        }

        $propertyChangePoints = $this->getPoints();
        $forcedChanges        = [];
        /** @var Point $changePoint */
        foreach ($propertyChangePoints as $changePoint) {
            $properties = $changePoint->getProperties()['lead_field'];
            if (str_starts_with($properties, 'company')) {
                $forcedChanges[] = $properties;
            }
        }
        $forcedChanges = array_unique($forcedChanges);
        $fields        = $event->getCompany()->getFields(true);

        $changeSet = [];
        foreach ($forcedChanges as $fieldName) {
            $changeSet[$fieldName][0] = $changeSet[$fieldName][1] = $fields[$fieldName]['value'];
        }

        $this->triggerCompanyAction('lead.property_change', $changeSet, $event->getCompany(), true);
    }

    public function triggerCompanyAction($type, $eventDetails = null, Company $company = null, $allowUserRequest = false): void
    {
        $leads = $this->leadModel->getEntities(['ids' => array_column($this->companyLeadRepository->getCompanyLeads($company->getId()), 'lead_id'),]);
        foreach ($leads as $lead) {
            $this->pointModel->triggerAction($type, $eventDetails, null, $lead, $allowUserRequest);
        }
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if ($event->getChanges() === []) {
            return;
        }
        $this->triggerCompanyAction('lead.property_change', $event, $event->getCompany(), true);
    }

    public function onLeadPostSave(LeadEvent $event): void
    {
        $changeSet = $event->getChanges();
        if ($changeSet === []) {
            return;
        }

        $published = $this->getPoints();
        if ($published === []) {
            return;
        }

        /** @var Point $pointEntity */
        foreach ($published as $pointEntity) {
            if (in_array($pointEntity->getProperties()['lead_field'], array_keys(($changeSet['fields'] ?? [])))) {
                $this->pointModel->triggerAction('lead.property_change', $event, null, $event->getLead(), true);
            }
        }
    }

    public function onPageHitTracked(PageHitEvent $event)
    {
        $url  = parse_url($event->getHit()->getUrl());
        $lead = $event->getLead() ?? null;
        $this->logger->info('Hit utm event', ['lead_id' => $event->getLead()->getId(), 'query' => $url['query'] ?? []]);
        if (($url['query'] ?? []) === [] || $lead === null) {
            return;
        }

        $utm = array_filter(explode('&', $url['query']), fn($parameter) => str_starts_with($parameter, 'utm_'));
        if ($utm === []) {
            return;
        }

        $this->logger->info('Hit utm event trigger', ['lead_id' => $event->getLead()->getId(), 'utm' => $utm]);
        $this->pointModel->triggerAction('page.utm.hit', ['utm' => $utm], null, $lead, true);
    }

    public function onLeadTagsAdd(LeadUtmTagsEvent $event)
    {
        $this->logger->info('UTM event', ['lead_id' => $event->getLead()->getId(), 'utm' => $event->getLead()->getUtmTags()->toArray()]);
    }

    /**
     * @return array<Point>
     * @throws \DateMalformedIntervalStringException
     */
    private function getPoints(): array
    {
        $points = $this->cacheProvider->getCacheAdapter()->get(
            'points.contact.points',
            function (CacheItem $item) {
                $item->expiresAfter(new \DateInterval('PT5M'));

                return $this->pointRepository->getPublishedByType('lead.property_change');
            });

        return $points;
    }
}
