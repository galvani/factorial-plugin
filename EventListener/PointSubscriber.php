<?php

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Mautic\CacheBundle\Cache\CacheProvider;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\LeadChangeCompanyEvent;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PointBundle\Entity\Point;
use Mautic\PointBundle\Entity\PointRepository;
use Mautic\PointBundle\Event\PointBuilderEvent;
use Mautic\PointBundle\Model\PointModel;
use Mautic\PointBundle\PointEvents;
use MauticPlugin\MauticFactorialBundle\Form\Type\PointPropertyChangeType;
use MauticPlugin\MauticFactorialBundle\Helper\PointFieldChangeHelper;
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
