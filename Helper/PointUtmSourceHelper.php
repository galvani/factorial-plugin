<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;

class PointUtmSourceHelper
{
    public static function match(array $eventDetails, $action): bool
    {
        $pointUtm = $action['properties']['utm_source'] ?? [];
        $eventUtm = $eventDetails['utm'];
        foreach ($eventUtm as $item){
            if (!str_starts_with($item, 'utm_source=')) {
                continue;
            }
            $sources[] = strtolower(substr($item, strlen('utm_source=')));
        }

        if (($sources ?? []) === []) {
            return false;
        }

        if (array_intersect($pointUtm, $sources)===[]) {
            return false;
        }

        return true;
    }
}
