<?php

declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Mautic\LeadBundle\Entity\LeadEventLogRepository;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticFactorialBundle\Entity\PageActivityTrackingRepository;
use MauticPlugin\MauticFactorialBundle\Twig\DurationExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TimelineSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PageActivityTrackingRepository $trackingRepository,
        private TranslatorInterface            $translator
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    public function onTimelineGenerate(LeadTimelineEvent $event): void
    {
        $eventType     = 'factorial_page_tracking';
        $eventTypeName = $this->translator->trans('mautic.integration.sync.timeline_notices');
        $event->addEventType($eventType, $eventTypeName);

        if (!$event->isApplicable($eventType)) {
            return;
        }

        $events = $this->trackingRepository->getEvents($event->getLead(), null, $event->getQueryOptions());

        // Add to counter
        $event->addToCounter($eventType, count($events));

        if ($event->isEngagementCount()) {
            return;
        }

        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventTypeName)
            );
        }
    }

    /**
     * @param mixed[] $pageActivity
     *
     * @return mixed[]
     */
    private function getEventEntry(array $pageActivity, string $eventType, string $eventTypeName): array
    {
        $properties = json_decode($pageActivity['properties'], true);
        $t = (int) $pageActivity['dwell_time'];
        $urlParts = parse_url($pageActivity['page_url']);
        $pageUrl = (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '') .
            $urlParts['host'] .
            (isset($urlParts['port']) ? ':' . $urlParts['port'] : '') .
            $urlParts['path'];

        return [
            'event'           => $eventType,
            'eventId'         => $eventType.$pageActivity['id'],
            'eventType'       => $this->translator->trans('Page activity'),
            'eventLabel'      => sprintf(
                $this->translator->trans('mautic.factorial.timeline.event.title'),
                DurationExtension::durationFormat($t),
                (int) $properties['scrollPercentage']
            ),
            'dwellTime'       => $t,
            'timestamp'       => $pageActivity['date_added'],
            'icon'            => 'ri-refresh-line',
            'contactId'       => $pageActivity['lead_id'],
            'contentTemplate' => '@MauticFactorial/Timeline/index.html.twig',
            'extra'           => $properties,
            // url without get parameters
            'page'          => sprintf(
                '<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>',
                htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8')
            ),
        ];
    }
}
