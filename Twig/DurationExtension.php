<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Twig;

use Twig\TwigFilter;

class DurationExtension extends \Twig\Extension\AbstractExtension
{
    public function getFilters()
    {
        return array(
            new TwigFilter('duration', array($this, 'formatDuration')),
        );
    }

    public function formatDuration($duration)
    {
        $duration = (int)$duration;
        $hours   = floor($duration / 3600);
        $minutes = floor(($duration / 60) % 60);
        $seconds = $duration % 60;

        $time = '';
        if ($hours > 0) {
            $time .= $hours.':';
        }

        $time .= str_pad((string)$minutes, 2, '0', STR_PAD_LEFT).':';
        $time .= str_pad((string)$seconds, 2, '0', STR_PAD_LEFT);

        return $time;
    }

    public static function durationFormat($duration): string {
        return (new self())->formatDuration($duration);
    }
}