<?php

namespace MauticPlugin\MauticFactorialBundle\Services;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class
DwellTimeOverallFilterQueryBuilder extends DwellTimeFilterQueryBuilder
{
    public function __construct(
        RandomParameterName      $parameterNameGenerator,
        EventDispatcherInterface $dispatcher,
        private LoggerInterface  $logger
    )
    {
        parent::__construct($parameterNameGenerator, $dispatcher);
    }

    public static function getServiceId(): string
    {
        return 'factorial.lead.query.builder.page_activity.dwell_time_overall';
    }

    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        if ( $filter->getGlue() === 'and') {
            $alias = $this->getTableAliasFromQueryBuilderWhereAndHaving($queryBuilder, $filter->getTable());
        }
        $alias ??= $this->generateRandomParameterName();

        $existingTableExpression = $this->ejectCurrentConditionFromQueryBuilderByTableName($queryBuilder, $filter->getTable());

        $subQueryBuilder         = $queryBuilder->createQueryBuilder();
        $subQueryBuilder
            ->select('lead_id')
            ->from($filter->getTable(), $alias)
            ->groupBy('lead_id');

        if (count($existingTableExpression[0] ?? [])) {
            $condition = reset($existingTableExpression[0]); // Moves the internal pointer to the first element and returns its value
            $alias     = key($existingTableExpression[0]);
            match (strtoupper($filter->getGlue())) {
                CompositeExpression::TYPE_AND => $subQueryBuilder->andWhere($condition),
                CompositeExpression::TYPE_OR => $subQueryBuilder->orWhere($condition),
            };
        }

        if (count($existingTableExpression[1] ?? [])) {
            $condition = reset($existingTableExpression[1]); // Moves the internal pointer to the first element and returns its value
            $alias     = key($existingTableExpression[1]);
            match (strtoupper($filter->getGlue())) {
                CompositeExpression::TYPE_AND => $subQueryBuilder->andHaving($condition),
                CompositeExpression::TYPE_OR => $subQueryBuilder->orHaving($condition),
            };
        }



        $parameterHolder = $this->generateRandomParameterName();
        $subQueryBuilder->andHaving(
            $this->getHavingCondition(
                $subQueryBuilder,
                'sum('.$alias.'.dwell_time)',
                $filter->getOperator(),
                ":$parameterHolder"
            )
        );

        $queryBuilder->setParameter($parameterHolder, $filter->getParameterValue());

        $this->logger->debug($this->getPopulatedSQL($subQueryBuilder, $queryBuilder->getParameters()));

        $queryBuilder->addLogic(
            $queryBuilder->expr()->in('l.id', $subQueryBuilder->getSQL()),
            $filter->getGlue()
        );

        return $queryBuilder;
    }

    private function getPopulatedSQL(QueryBuilder $qb, ?array $params): string
    {
        $sql    = $qb->getSQL(); // Get the SQL string
        $params ??= $qb->getParameters(); // Get the bound parameters
        $types  = $qb->getParameterTypes(); // Get parameter types

        foreach ($params as $key => $value) {
            $placeholder = is_numeric($key) ? '?' : ':'.$key;

            // Determine how to escape the value based on its type
            $type         = $types[$key] ?? false;
            $escapedValue = match ($type) {
                PDO::PARAM_INT => (int)$value,
                PDO::PARAM_BOOL => $value ? 'TRUE' : 'FALSE',
                PDO::PARAM_NULL => 'NULL',
                default => is_string($value) ? "'".addslashes($value)."'" : $value,
            };

            // Replace the placeholder with the escaped value
            $sql = preg_replace('/'.preg_quote($placeholder, '/').'/', $escapedValue, $sql, 1);
        }

        return $sql;
    }

    private function getHavingCondition(QueryBuilder $queryBuilder, string $column, ?string $operator, mixed $parameterHolder)
    {
        $having          = $queryBuilder->expr();
        $havingCondition = $having->eq($column, $parameterHolder);

        switch ($operator) {
            case 'gt':
                $havingCondition = $having->gt($column, $parameterHolder);
                break;
            case 'lt':
                $havingCondition = $having->lt($column, $parameterHolder);
                break;
            case 'gte':
                $havingCondition = $having->gte($column, $parameterHolder);
                break;
            case 'lte':
                $havingCondition = $having->lte($column, $parameterHolder);
                break;
            case 'neq':
                $havingCondition = $having->neq($column, $parameterHolder);
                break;
        }

        return $havingCondition;
    }


}
