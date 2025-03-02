<?php

namespace MauticPlugin\MauticFactorialBundle\Services;

use Mautic\EmailBundle\Entity\Stat;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\LeadBatchLimiterTrait;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use MauticPlugin\MauticFactorialBundle\Segment\BaseSegmentFilterQueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UnopenedEmailsFilterQueryBuilder extends BaseSegmentFilterQueryBuilder
{
    use LeadBatchLimiterTrait;

    public function __construct(
        RandomParameterName      $parameterNameGenerator,
        EventDispatcherInterface $dispatcher,
    )
    {
        parent::__construct($parameterNameGenerator, $dispatcher);
    }

    public static function getServiceId(): string
    {
        return 'factorial.lead.query.builder.unopened_emails_percentage';
    }

    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $leadsTableAlias  = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads');

        $subQB = $queryBuilder->createQueryBuilder();

        $parameterHolder = $this->generateRandomParameterName();
        $subQB
            ->select('null')
            ->from(MAUTIC_TABLE_PREFIX . Stat::TABLE_NAME, 's')
            ->where('s.lead_id IS NOT NULL')
            ->groupBy('s.lead_id');

        $filterOperator = $filter->getOperator();

        $havingExpression = $subQB->expr()->$filterOperator(
            'round((sum(if(s.open_count>0, 1, 0)) / count(s.id)) * 100)',
            ':'.$parameterHolder
        );

        $subQB->andWhere($queryBuilder->expr()->eq($leadsTableAlias.'.id', 's.lead_id'));
        $subQB->having($havingExpression);

        $this->addLeadAndMinMaxLimiters($subQB, $filter->getBatchLimiters(), Stat::TABLE_NAME);

        $glue = $filter->getGlue().'Where';
        $queryBuilder->$glue($queryBuilder->expr()->exists($subQB->getSQL()));

        $queryBuilder->setParametersPairs($parameterHolder, intval($filter->getParameterValue()));

        return $queryBuilder;
    }
}
