<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Segment;

use Mautic\LeadBundle\Segment\Query\Filter\BaseFilterQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use PDO;

abstract class BaseSegmentFilterQueryBuilder extends BaseFilterQueryBuilder
{
    protected function getPopulatedSQL(QueryBuilder $qb, ?array $params = null): string
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
            $sql = preg_replace('/'.preg_quote($placeholder, '/').'/', (string) $escapedValue, $sql, 1);
        }

        return $sql;
    }
}