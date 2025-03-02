<?php

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Model\NotificationModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticFactorialBundle\Integration\FactorialhubspotIntegration;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PointsTotalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private FactorialhubspotIntegration $factorialhubspotIntegration,
        private IntegrationHelper           $integrationHelper,
        private NotificationModel           $notificationModel,
        private LoggerInterface             $logger,
        private LeadRepository              $leadRepository
    )
    {

    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LEAD_POST_SAVE => ['onLeadPostSave', -10],
        ];
    }

    public function onLeadPostSave(LeadEvent $event): void
    {
        /** @var Lead $lead */
        $lead = $event->getLead();

        if (!$pointsColumn = $lead->getField('total_points')) {
            return;
        }

        $totalPrevious = $lead->getFieldValue('total_points');

        $connection = $this->entityManager->getConnection();
        $qb         = $connection->createQueryBuilder();
        $qb->select('SUM(p.score)')
           ->from('point_group_contact_score', 'p')
           ->where('p.contact_id = :contact')
           ->setParameter('contact', $lead->getId());
        $total = (int)$qb->fetchOne() + $lead->getPoints();

        $qb->update('leads')
           ->set($pointsColumn['alias'], ':total')
           ->where('id = :id')
           ->setParameter('id', $lead->getId())
           ->setParameter('total', $total)
           ->executeQuery();

        if ($totalPrevious == $total) {
            return;
        }

        // Limit to contacts that came from Hubspot
        if (!$lead->getEmail() || !$lead->getFieldValue('hs_object_id')) {
            return;
        }

        $integration = $this->integrationHelper->getIntegrationObject('Factorialhubspot');
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return;
        }

        $settings               = $integration->getIntegrationSettings()->getFeatureSettings();
        $leadFields             = array_intersect_key($settings['leadFields'], ['email' => 'email', 'lead_score' => 'lead_score']);
        $settings['leadFields'] = $leadFields;
        $lead                   = $this->leadRepository->getEntity($lead->getId());

        try {
            $this->factorialhubspotIntegration->pushLead($lead, $settings);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->notificationModel->addNotification(
                sprintf('Hubspot error: %s', $e->getMessage()),
                'Hubspot',
                false,
                'Hubspot error'
            );
        }
    }

}
