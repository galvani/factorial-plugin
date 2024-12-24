<?php

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\BuildJsEvent;
use MauticPlugin\MauticFactorialBundle\Entity\PageActivityTracking;
use MauticPlugin\MauticFactorialBundle\Entity\PageActivityTrackingRepository;
use MauticPlugin\MauticFactorialBundle\Events\PageTrackingEvent;
use MauticPlugin\MauticFactorialBundle\FactorialEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class TrackingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface                $router,
        private PageActivityTrackingRepository $pageActivityTrackingRepository

    )
    {

    }

    public static function getSubscribedEvents()
    {
        return [
            CoreEvents::BUILD_MAUTIC_JS          => ['onBuildJs', 0],
            FactorialEvents::ON_CONTACT_TRACKING => ['onContactTracked', 0],
        ];
    }

    public function onBuildJs(BuildJsEvent $event)
    {
        $request = Request::createFromGlobals();
        if (basename($request->getRequestUri()) !== 'mtc.js') {
            return;
        }

        $content       = file_get_contents(__DIR__.'/../Resources/js/tracking.js');
        $mauticBaseUrl = $this->router->generate('mautic_base_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $event->appendJs(str_replace('{$mauticBaseUrl}', $mauticBaseUrl, $content));
    }

    public function onContactTracked(PageTrackingEvent $event)
    {
        try {
            $contact    = $event->getContact();
            $pageUrl    = $event->getRequest()->get('pageUrl');
            $session    = $event->getRequest()->get('session');
            $properties = $event->getRequest()->request->all();
            $updated    = new \DateTime();

            /** @var PageActivityTracking $currentActivity */
            $currentActivity = $this->pageActivityTrackingRepository->findOneBy(['sessionId' => $event->getRequest()->get('session')]);

            if ($currentActivity === null) {
                $currentActivity = (new PageActivityTracking())
                    ->setLead($contact)
                    ->setPageUrl($this->stripGetArguments($pageUrl))
                    ->setSessionId($session)
                    ->setProperties($properties)
                    ->setDateAdded($updated);
            } else {
                $currentProperties                     = $currentActivity->getProperties();
                $currentProperties['end']              = $properties['end'];
                $currentProperties['spent']            = max($properties['spent'], $currentActivity->getDwellTime() * 1000);
                $currentProperties['scrollPercentage'] = max($properties['scrollPercentage'], $currentProperties['scrollPercentage']);
                $currentActivity->setProperties($currentProperties);
            }

            $currentActivity
                ->setDateModified($updated)
                ->setDwellTime($properties['spent'] / 1000);

            try {
                $this->pageActivityTrackingRepository->saveEntity($currentActivity);
            } catch (\Exception $e) {
                $event->setData(['error' => $e->getMessage(),]);
                return;
            }

            if ($contact->getEmail())
                $event->setData(
                    [
                        'email'      => $contact->getEmail(),
                        'uri'        => $pageUrl,
                        'session'    => $event->getRequest()->get('session'),
                        'properties' => $properties
                    ]
                );
        } catch (\Exception $e) {
            $event->setData(
                [
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    private function stripGetArguments($url): string
    {
        $parsedUrl = parse_url($url);
        $scheme    = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'].'://' : '';
        $host      = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port      = isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '';
        $path      = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

        return $scheme.$host.$port.$path;
    }
}