<?php

namespace MauticPlugin\MauticFactorialBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Exception\InvalidDecodedStringException;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Helper\ContactRequestHelper;
use Mautic\LeadBundle\Helper\IdentifyCompanyHelper;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\LeadBundle\Tracker\Service\DeviceTrackingService\DeviceTrackingServiceInterface;
use Mautic\PageBundle\Event\TrackingEvent;
use Mautic\PageBundle\Helper\TrackingHelper;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\PageEvents;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticCrmBundle\Integration\HubspotIntegration;
use MauticPlugin\MauticFactorialBundle\Events\PageTrackingEvent;
use MauticPlugin\MauticFactorialBundle\FactorialEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends CommonController
{
    public function pageTrackingAction(
        Request              $request,
        ContactRequestHelper $contactRequestHelper,
        EventDispatcherInterface      $eventDispatcher,
    ): Response
    {
        $notSuccessResponse = new JsonResponse(
            [
                'success' => 0,
            ]
        );

        $contact = $this->identifyContact($contactRequestHelper, $request);

        if ($contact === null) {
            return $notSuccessResponse;
        }

        $event = new PageTrackingEvent(
            $request,
            $contact
        );

        $eventDispatcher->dispatch($event, FactorialEvents::ON_CONTACT_TRACKING);

        return new JsonResponse(
            [
                'success' => 1,
                'id'      => $contact !== null ? $contact->getId() : null,
                'event'   => $event->getData(),
            ]
        );
    }

    private function identifyContact(ContactRequestHelper $requestHelper, $request, $page = null): ?Lead
    {
        // Don't skew results with user hits
        if (!$this->security->isAnonymous()) {
            return null;
        }

        /** @var PageModel $model */
        $model   = $this->getModel('page');
        $query   = $model->getHitQuery($request, $page);
        $contact = $requestHelper->getContactFromQuery($query);

        if (!$contact || !$contact->getId()) {
            return null;
        }

        return $contact;
    }


    public function contactDataAction(Request $request, LoggerInterface $mauticLogger, IntegrationHelper $integrationHelper): Response
    {
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true); // 2nd param to get as array
        } else {
            return new Response('ERROR');
        }

        $integration = 'Hubspot';

        $integrationObject = $integrationHelper->getIntegrationObject($integration);
        \assert($integrationObject instanceof HubspotIntegration);

        foreach ($data as $info) {
            $object = explode('.', $info['subscriptionType']);
            $id     = $info['objectId'];

            try {
                switch ($object[0]) {
                    case 'contact':
                        $executed = [];
                        $integrationObject->getLeads($id, null, $executed);
                        break;
                    case 'company':
                        $integrationObject->getCompanies($id);
                        break;
                }
            } catch (\Exception $ex) {
                $mauticLogger->log('error', 'ERROR on Hubspot webhook: '.$ex->getMessage());
            }
        }

        return new Response('OK');
    }
}
