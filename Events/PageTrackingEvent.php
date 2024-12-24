<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Events;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\HttpFoundation\Request;

class PageTrackingEvent extends CommonEvent
{
    private array $data = [];

    public function __construct(private Request $request, private Lead $lead)
    {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function appendData(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContact(): Lead
    {
        return $this->lead;
    }
}