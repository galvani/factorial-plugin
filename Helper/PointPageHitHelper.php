<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\LeadEvent;

class PointPageHitHelper
{
    public static function match(CompanyEvent|LeadEvent|array $eventDetails, $action, MauticFactory $factory): bool
    {
        $fieldName = $action['properties']['lead_field'];
        $fields = is_array($eventDetails) ? $eventDetails : $eventDetails->getChanges()['fields'] ?? [];

        if ($fieldName === null || !in_array($fieldName, array_keys($fields))) {
            //  field no longer exists or belongs to another object type e.g. company
            return false;
        }

        $changeValue = $fields[$fieldName][1] ?? null;
        if ($changeValue === null) {
            return false;
        }

        $expectedValue1 = $action['properties']['value1'];
        $expectedValue2 = $action['properties']['value2'] ?? null;

        try {
            switch ($action['properties']['operator']) {
                case 'in':
                    $expectedValues = explode(';', strtolower($expectedValue1));
                    if (!in_array(strtolower($changeValue), $expectedValues)) {
                        return false;
                    }

                    return true;
                case 'not_in':
                    $expectedValues = explode(';', strtolower($expectedValue1));
                    if (in_array(strtolower($changeValue), $expectedValues)) {
                        return false;
                    }

                    return true;
                case 'gte':
                    return intval($changeValue) >= intval($expectedValue1);
                case 'lte':
                    return $changeValue <= intval($expectedValue1);
                case 'between':
                    return $changeValue >= intval($expectedValue1) && $changeValue <= intval($expectedValue2);
            }

        } catch (\Exception $e) {
            $factory->getLogger()->error(
                sprintf(
                    'Failed to evaluate point action for lead #%d: %s',
                    $eventDetails->getLead()->getId(),
                    $e->getMessage()
                ), $action);
        }

        return false;
    }
}
