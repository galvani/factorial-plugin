<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\Chart\PieChart;
use Mautic\PageBundle\Entity\HitRepository;
use MauticPlugin\MauticFactorialBundle\Entity\PageActivityTrackingRepository;

class PageActivityModel
{
    public function __construct(
        private PageActivityTrackingRepository $trackingRepository,
        private HitRepository                  $hitRepository,
    )
    {
    }

    public function getDwellTimesPieChart(\DateTime $dateFrom, \DateTime $dateTo, $filters = [], $canViewOthers = true): array
    {

        $timesOnSite = $this->hitRepository->getDwellTimeLabels();
        $chart       = new PieChart();

        foreach ($timesOnSite as $time) {
            $data = $this->trackingRepository
                ->createQueryBuilder('pat')
                ->select('COUNT(pat.sessionId) as total')
                ->where('pat.dwellTime BETWEEN :from AND :till')
                ->setParameter('from', $time['from'])
                ->setParameter('till', $time['till'])
                ->andWhere('pat.dateAdded BETWEEN :dateFrom AND :dateTo')
                ->setParameter('dateFrom', $dateFrom)
                ->setParameter('dateTo', $dateTo)
                ->getQuery()->getSingleResult();
            $chart->setDataset($time['label'], $data['total']);
        }

        return $chart->render();
    }

    public function getPopularPagesChartData(int $limit, \DateTime $dateFrom, \DateTime $dateTo, $filters = [], $canViewOthers = true): array
    {
        return $this->trackingRepository
            ->createQueryBuilder('pat')
            ->select('COUNT(pat.sessionId) as hits, pat.pageUrl')
            ->andWhere('pat.dateAdded BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->addGroupBy('pat.pageUrl')
            ->setMaxResults($limit)
            ->getQuery()->getResult();

    }
}