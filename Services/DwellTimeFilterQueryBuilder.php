<?php

namespace MauticPlugin\MauticFactorialBundle\Services;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\Query\LeadBatchLimiterTrait;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\MauticFactorialBundle\Segment\BaseSegmentFilterQueryBuilder;

class DwellTimeFilterQueryBuilder extends BaseSegmentFilterQueryBuilder
{
    use LeadBatchLimiterTrait;

    public static function getServiceId(): string
    {
        return 'factorial.lead.query.builder.page_activity.dwell_time';
    }

    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $leadsTableAlias  = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.'leads');
        $filterOperator   = $filter->getOperator();
        $batchLimiters    = $filter->getBatchLimiters();
        $filterParameters = $filter->getParameterValue();

        $foreignContactColumn = $filter->getForeignContactColumn();

        if (is_array($filterParameters)) {
            $parameters = [];
            foreach ($filterParameters as $filterParameter) {
                $parameters[] = $this->generateRandomParameterName();
            }
        } else {
            $parameters = $this->generateRandomParameterName();
        }

        $filterParametersHolder = $filter->getParameterHolder($parameters);

        if ($filter->getGlue() === 'and') {
            $tableAlias = $this->getTableAliasFromQueryBuilderWhereAndHaving($queryBuilder, $filter->getTable());
        }
        $tableAlias ??= $this->generateRandomParameterName();


        $subQueryBuilder = $queryBuilder->createQueryBuilder();

        if (!is_null($filter->getWhere())) {
            $subQueryBuilder->andWhere(str_replace(str_replace(MAUTIC_TABLE_PREFIX, '', $filter->getTable()).'.', $tableAlias.'.', $filter->getWhere()));
        }

        switch ($filterOperator) {
            case 'empty':
                $subQueryBuilder->select($tableAlias.'.'.$foreignContactColumn)->from($filter->getTable(), $tableAlias);
                $queryBuilder->addLogic($queryBuilder->expr()->notIn($leadsTableAlias.'.id', $subQueryBuilder->getSQL()), $filter->getGlue());
                break;
            case 'notEmpty':
                $subQueryBuilder->select($tableAlias.'.'.$foreignContactColumn)->from($filter->getTable(), $tableAlias);

                $this->addLeadAndMinMaxLimiters($subQueryBuilder, $batchLimiters, str_replace(MAUTIC_TABLE_PREFIX, '', $filter->getTable()), $foreignContactColumn);

                $queryBuilder->addLogic(
                    $queryBuilder->expr()->in($leadsTableAlias.'.id', $subQueryBuilder->getSQL()),
                    $filter->getGlue()
                );
                break;
            case 'notIn':
                $subQueryBuilder
                    ->select('NULL')->from($filter->getTable(), $tableAlias)
                    ->andWhere($tableAlias.'.'.$foreignContactColumn.' = '.$leadsTableAlias.'.id');

                $expression = $subQueryBuilder->expr()->in(
                    $tableAlias.'.'.$filter->getField(),
                    $filterParametersHolder
                );

                $subQueryBuilder->andWhere($expression);
                $queryBuilder->addLogic($queryBuilder->expr()->notExists($subQueryBuilder->getSQL()), $filter->getGlue());
                break;
            case 'neq':
                $subQueryBuilder
                    ->select('NULL')->from($filter->getTable(), $tableAlias)
                    ->andWhere($tableAlias.'.'.$foreignContactColumn.' = '.$leadsTableAlias.'.id');

                $expression = $subQueryBuilder->expr()->or(
                    $subQueryBuilder->expr()->eq($tableAlias.'.'.$filter->getField(), $filterParametersHolder),
                    $subQueryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField())
                );

                $subQueryBuilder->andWhere($expression);

                $queryBuilder->addLogic($queryBuilder->expr()->notExists($subQueryBuilder->getSQL()), $filter->getGlue());
                break;
            case 'notLike':
                $subQueryBuilder
                    ->select('NULL')->from($filter->getTable(), $tableAlias)
                    ->andWhere($tableAlias.'.'.$foreignContactColumn.' = '.$leadsTableAlias.'.id');

                $expression = $subQueryBuilder->expr()->or(
                    $subQueryBuilder->expr()->isNull($tableAlias.'.'.$filter->getField()),
                    $subQueryBuilder->expr()->like($tableAlias.'.'.$filter->getField(), $filterParametersHolder)
                );

                $subQueryBuilder->andWhere($expression);

                $queryBuilder->addLogic($queryBuilder->expr()->notExists($subQueryBuilder->getSQL()), $filter->getGlue());
                break;
            case 'regexp':
            case 'notRegexp':
                $subQueryBuilder->select($tableAlias.'.'.$foreignContactColumn)
                                ->from($filter->getTable(), $tableAlias);

                $this->addLeadAndMinMaxLimiters($subQueryBuilder, $batchLimiters, str_replace(MAUTIC_TABLE_PREFIX, '', $filter->getTable()), $foreignContactColumn);

                $not        = ('notRegexp' === $filterOperator) ? ' NOT' : '';
                $expression = $tableAlias.'.'.$filter->getField().$not.' REGEXP '.$filterParametersHolder;

                $subQueryBuilder->andWhere($expression);

                $queryBuilder->addLogic($queryBuilder->expr()->in($leadsTableAlias.'.id', $subQueryBuilder->getSQL()), $filter->getGlue());

                break;
            default:
                $subQueryBuilder->select($tableAlias.'.'.$foreignContactColumn)->from($filter->getTable(), $tableAlias);

                $this->addLeadAndMinMaxLimiters($subQueryBuilder, $batchLimiters, str_replace(MAUTIC_TABLE_PREFIX ?? '', '', $filter->getTable()), $foreignContactColumn);

                $expression = $subQueryBuilder->expr()->$filterOperator(
                    $tableAlias.'.'.$filter->getField(),
                    $filterParametersHolder
                );

                $existingTableExpression = $this->ejectCurrentConditionFromQueryBuilderByTableName($queryBuilder, $tableAlias);

                $subQueryBuilder = $queryBuilder->createQueryBuilder();
                $subQueryBuilder
                    ->select('lead_id')
                    ->from($filter->getTable(), $tableAlias);

                if ($existingTableExpression !== null) {
                    if (count($existingTableExpression[0] ?? [])) {
                        $condition = reset($existingTableExpression[0]); // Moves the internal pointer to the first element and returns its value
                        match (strtoupper($filter->getGlue())) {
                            CompositeExpression::TYPE_AND => $subQueryBuilder->andWhere($condition),
                            CompositeExpression::TYPE_OR => $subQueryBuilder->orWhere($condition),
                        };
                    }

                    if (count($existingTableExpression[1] ?? [])) {
                        $condition = reset($existingTableExpression[1]); // Moves the internal pointer to the first element and returns its value
                        match (strtoupper($filter->getGlue())) {
                            CompositeExpression::TYPE_AND => $subQueryBuilder->andHaving($condition),
                            CompositeExpression::TYPE_OR => $subQueryBuilder->orHaving($condition),
                        };
                    }
                }

                $subQueryBuilder->andWhere($expression);
                $queryBuilder->addLogic($queryBuilder->expr()->in($leadsTableAlias.'.id', $subQueryBuilder->getSQL()), $filter->getGlue());
        }

        $queryBuilder->setParametersPairs($parameters, $filterParameters);

        return $queryBuilder;
    }

    protected function ejectCurrentConditionFromQueryBuilderByTableName($queryBuilder, $table): ?array
    {
        $wherePart = $queryBuilder->getQueryPart('where');
        if ($wherePart === null) {
            return null;
        }

        $conditions = $this->getCompositeParts($wherePart);
        if ($conditions === null) {
            return null;
        }

        $givenTableWhere     = [];
        $givenTableHaving    = [];
        $preservedConditions = [];

        foreach ($conditions as $condition) {
            $parsedCondition = $this->parseCondition($condition);

            if ($parsedCondition === null || $table !== $parsedCondition['alias']) {
                $preservedConditions[] = $condition;
            } else {
                $givenTableWhere[$parsedCondition['alias']] = $parsedCondition['condition'];
                if ($parsedCondition['having'] !== null) {
                    $givenTableHaving[$parsedCondition['alias']] = $parsedCondition['having'];
                }
            }
        }

        $queryBuilder->resetQueryPart('where');
        foreach ($preservedConditions as $condition) {
            match ($wherePart->getType()) {
                CompositeExpression::TYPE_AND => $queryBuilder->andWhere($condition),
                CompositeExpression::TYPE_OR => $queryBuilder->orWhere($condition),
            };
        }

        return [$givenTableWhere, $givenTableHaving];
    }

    protected function parseCondition($condition): ?array
    {
        // Try to match the specific pattern first - now properly handling parentheses
        if (preg_match('/SELECT\s+(\w+)\.lead_id\s+FROM\s+(\w+)\s+(\w+)\s+WHERE\s+(.*?)(?=\)|\s+GROUP BY|\s+HAVING|$)/i', $condition, $matches)) {
            return [
                'alias'     => $matches[1],        // Extracted alias
                'table'     => $matches[2],        // Extracted table name
                'condition' => trim($matches[4]), // Extracted condition (now using group 4 since we removed WHERE capture)
                'having'    => null               // No HAVING clause in this pattern
            ];
        }

        // If specific pattern doesn't match, try the generic SQL parser
        if (preg_match('/FROM\s+(\w+)(?:\s+(\w+))?/i', $condition, $tableMatch)) {
            $tableName  = $tableMatch[1];
            $tableAlias = $tableMatch[2] ?? null;

            // Extract WHERE clause - now not capturing WHERE keyword
            $whereClause = null;
            if (preg_match('/WHERE\s+(.*?)(?=\s+GROUP BY|\s+HAVING|\)$)/is', $condition, $whereMatch)) {
                $whereClause = trim($whereMatch[1]);
            }

            // Extract HAVING clause
            $havingClause = null;
            if (preg_match('/HAVING\s+(.*?)(?=\)$)/is', $condition, $havingMatch)) {
                $havingClause = trim($havingMatch[1]);
            }

            return [
                'alias'     => $tableAlias,
                'table'     => $tableName,
                'condition' => $whereClause,
                'having'    => $havingClause
            ];
        }

        return null;
    }

    protected function getCompositeParts(CompositeExpression $compositeExpression)
    {
        // Access the internal parts using reflection
        $reflection    = new \ReflectionClass($compositeExpression);
        $partsProperty = $reflection->getProperty('parts');
        $partsProperty->setAccessible(true);

        // Get the parts (conditions) of the composite expression
        return $partsProperty->getValue($compositeExpression);
    }

    protected function getTableAliasFromQueryBuilderWhereAndHaving(QueryBuilder $queryBuilder, string $table): ?string
    {
        $wherePart  = $queryBuilder->getQueryPart('where');
        $havingPart = $queryBuilder->getQueryPart('having');
        if ($wherePart === null && $havingPart === null) {
            return null;
        }

        $conditions = $this->getCompositeParts($wherePart);
        if ($conditions === null) {
            return null;
        }

        foreach ($conditions as $condition) {
            if (preg_match('/SELECT lead_id\s+FROM\s+(\w+)\s+(\w+)\s+WHERE\s+(.+)/i', $condition, $matches)) {
                $alias      = $matches[2]; // Extracted alias
                $tableMatch = $matches[1]; // Extracted table name
                if ($tableMatch === $table) {
                    return $alias;
                }
            }
        }

        return null;
    }

}
