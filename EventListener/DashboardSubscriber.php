<?php

namespace MauticPlugin\MauticFactorialBundle\EventListener;

use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\EventListener\DashboardSubscriber as MainDashboardSubscriber;
use MauticPlugin\MauticFactorialBundle\Model\PageActivityModel;
use Symfony\Component\Routing\RouterInterface;

class DashboardSubscriber extends MainDashboardSubscriber
{
    /**
     * Define the name of the bundle/category of the widget(s).
     *
     * @var string
     */
    protected $bundle = 'page_activity';

    /**
     * Define the widget(s).
     *
     * @var string
     */
    protected $types = [
        'page_activity.dwell.times'   => [],
        'page_activity.popular.pages' => [],
    ];

    /**
     * Define permissions to see those widgets.
     *
     * @var array
     */
    protected $permissions = [
        'page:pages:viewown',
        'page:pages:viewother',
    ];

    public function __construct(
        protected PageActivityModel $activityModel,
        protected RouterInterface   $router
    )
    {
    }

    /**
     * Set a widget detail when needed.
     */
    public function onWidgetDetailGenerate(WidgetDetailEvent $event): void
    {
        $this->checkPermissions($event);
        $canViewOthers = $event->hasPermission('page:pages:viewother');


        if ('page_activity.dwell.times' == $event->getType()) {
            if (MAUTIC_ENV === 'dev' || !$event->isCached()) {
                $params = $event->getWidget()->getParams();
                $event->setTemplateData(
                    [
                        'chartType'   => 'pie',
                        'chartHeight' => $event->getWidget()->getHeight() - 80,
                        'chartData'   => $this->activityModel->getDwellTimesPieChart($params['dateFrom'], $params['dateTo'], [], $canViewOthers),
                    ]);
            }

            $event->setTemplate('@MauticCore/Helper/chart.html.twig');
            $event->stopPropagation();
        }

        if ('page_activity.popular.pages' == $event->getType()) {
            if (MAUTIC_ENV === 'dev' || !$event->isCached()) {
                $params = $event->getWidget()->getParams();

                if (empty($params['limit'])) {
                    // Count the pages limit from the widget height
                    $limit = round((($event->getWidget()->getHeight() - 80) / 35) - 1);
                } else {
                    $limit = $params['limit'];
                }

                $pages = $this->activityModel->getPopularPagesChartData($limit, $params['dateFrom'], $params['dateTo'], [], $canViewOthers);
                $items = [];

                foreach ($pages as &$page) {
                    $parsedUrl = parse_url($page['pageUrl']);

                    $cleanUrl = rtrim($parsedUrl['host'], '/');
                    if (isset($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
                        $cleanUrl .= $parsedUrl['path'];
                    }
                    $row     = [
                        [
                            'value' => $cleanUrl,
                            'type'  => 'link',
                            'link'  => $page['pageUrl'],
                        ],
                        [
                            'value' => $page['hits'],
                        ],
                    ];
                    $items[] = $row;
                }

                $event->setTemplateData(
                    [
                        'headItems' => [
                            'mautic.dashboard.label.title',
                            'mautic.dashboard.label.hits',
                        ],
                        'bodyItems' => $items,
                        'raw'       => $pages,
                    ]);
            }

            $event->setTemplate('@MauticCore/Helper/table.html.twig');
            $event->stopPropagation();
        }
    }
}
