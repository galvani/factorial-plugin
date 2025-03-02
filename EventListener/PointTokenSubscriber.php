<?php

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PointTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface    $translator,
        private EntityManagerInterface $entityManager
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_BUILD   => ['onEmailBuild', 0],
            EmailEvents::EMAIL_ON_SEND    => ['onEmailGenerate', 0],
            EmailEvents::EMAIL_ON_DISPLAY => ['onEmailDisplay', 0],
        ];
    }

    public function onEmailBuild(EmailBuilderEvent $event): void
    {
        $event->addToken('{pointsTotal}', $this->translator->trans('mautic.factorial.segment.filter.points_total'));
    }

    public function onEmailDisplay(EmailSendEvent $event): void
    {
        $this->onEmailGenerate($event);
    }

    public function onEmailGenerate(EmailSendEvent $event): void
    {
        $content = $event->getSubject();
        $content .= $event->getContent();
        $content .= $event->getPlainText();
        $content .= implode(' ', $event->getTextHeaders());

        if (!str_contains($content, '{pointsTotal}')) {
            return;
        }

        $leadArray = $event->getLead();

        $qb = $this->entityManager->getConnection()->createQueryBuilder();

        // Subquery for the aggregated points
        $subQuery = $this->entityManager->getConnection()->createQueryBuilder()
                                        ->select('p.contact_id, SUM(p.score) AS group_total')
                                        ->from('point_group_contact_score', 'p')
                                        ->where('p.contact_id=:id')
                                        ->groupBy('p.contact_id');

        $qb
            ->select('l2.id, l2.points + COALESCE(aggregated.group_total, 0) AS total')
            ->from('leads', 'l2')
            ->where('l2.id=:id')
            ->leftJoin('l2', '('.$subQuery->getSQL().')', 'aggregated', 'l2.id = aggregated.contact_id');

        $qb->setParameter('id', intval($leadArray['id']));

        $data = $qb->fetchAssociative();

        $event->addTokens(['{pointsTotal}' => $data === false ? '' : $data['total'] ?? '']);
    }
}
