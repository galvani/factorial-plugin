<?php

namespace MauticPlugin\MauticFactorialBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\TimelineTrait;
use Mautic\PageBundle\Entity\Page;

/** @extends CommonRepository<PageActivityTracking> */
class PageActivityTrackingRepository extends CommonRepository
{
    use TimelineTrait;

    public function getEntities(array $args = [])
    {
        $alias = $this->getTableAlias();

        $q = $this->_em
            ->createQueryBuilder()
            ->select($alias)
            ->from(PageActivityTracking::class, $alias, $alias.'.id');

        if (empty($args['iterator_mode']) && empty($args['iterable_mode'])) {
            $q->leftJoin($alias.'.category', 'c');
        }

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    public function getEvents(Lead $contact = null, string|Page|null $page = null, array $options = []): array
    {
        $alias = $this->getTableAlias();
        $qb    = $this->getEntityManager()->getConnection()->createQueryBuilder()
                      ->select('*')
                      ->from(MAUTIC_TABLE_PREFIX.'page_activity_tracking', $alias);

        if ($contact) {
            $qb->andWhere($alias.'.lead_id = :lead')
               ->setParameter('lead', $contact->getId());
        }

        if ($page !== null) {
            $qb->andWhere($alias.'.pageUrl like \'%:page\'')
               ->setParameter('page', $page instanceof $page ? $page->getUrl() : $page);
        }

        return $this->getTimelineResults($qb, $options, $alias.'.page_url', $alias.'.date_added', [], ['date_added'], null, $alias.'.id');
    }

    public function getSearchCommands(): array
    {
        return $this->getStandardSearchCommands();
    }

    public function getTableAlias(): string
    {
        return 'pt';
    }

    /**
     * @return array
     */
    public function getVisitsByContactId($contactId)
    {
        $q = $this->createQueryBuilder($this->getTableAlias());
        $q->select('COUNT(pt.id) as visits')
            ->where($this->getTableAlias().'.lead = :contactId')
            ->setParameter('contactId', $contactId);

        return $q->getQuery()->getResult();
    }
}
