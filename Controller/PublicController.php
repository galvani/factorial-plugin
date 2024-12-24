<?php

namespace MauticPlugin\MauticFactorialBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Helper\ContactRequestHelper;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\MauticFactorialBundle\Events\PageTrackingEvent;
use MauticPlugin\MauticFactorialBundle\FactorialEvents;
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
}
