<?php

namespace MauticPlugin\MauticFactorialBundle\Services;

use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\LeadBatchLimiterTrait;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\MauticFactorialBundle\Segment\BaseSegmentFilterQueryBuilder;

class TotalPointsFilterQueryBuilder extends BaseSegmentFilterQueryBuilder
{
    use LeadBatchLimiterTrait;

    public static function getServiceId(): string
    {
        return 'factorial.lead.query.builder.points_total';
    }

    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $parameterHolder = $this->generateRandomParameterName();

        $subQuery = $queryBuilder->createQueryBuilder()
                                  ->select('p.contact_id, SUM(p.score) AS group_total')
                                  ->from('point_group_contact_score', 'p')
                                  ->groupBy('p.contact_id');

        $subQuery2 = $queryBuilder->createQueryBuilder()
                                   ->select('l2.id')
                                   ->addSelect('l2.points')
                                   ->addSelect('l2.points + COALESCE(aggregated.group_total, 0) AS total')
                                   ->from('leads', 'l2')
                                   ->leftJoin('l2', '(' . $subQuery->getSQL() . ')', 'aggregated', 'l2.id = aggregated.contact_id')
                                   ->having('l2.points + COALESCE(total, 0) >= :' . $parameterHolder);

        $subQuery2->andWhere('l2.id=l.id');
        $queryBuilder->setParametersPairs($parameterHolder, intval($filter->getParameterValue()));

        $glue = $filter->getGlue().'Where';
        $queryBuilder->$glue($queryBuilder->expr()->exists($subQuery2->getSQL()));

        return $queryBuilder;
    }
}
