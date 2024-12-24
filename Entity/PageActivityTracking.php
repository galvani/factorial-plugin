<?php

namespace MauticPlugin\MauticFactorialBundle\Entity;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

class PageActivityTracking implements \Stringable
{
    private $id;
    private $pageUrl;
    private $sessionId;
    private $lead;
    private $properties;
    private DateTime $dateAdded;
    private DateTime $dateModified;
    private $dwellTime;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('page_activity_tracking')
                ->setCustomRepositoryClass(PageActivityTrackingRepository::class)
        ;

        $builder->addId();

        $builder->addNamedField('pageUrl', Types::TEXT, 'page_url');
        $builder->addNamedField('sessionId', Types::STRING, 'session_id');
        $builder->addNamedField('dateAdded', Types::DATETIME_MUTABLE, 'date_added');
        $builder->addNamedField('dateModified', Types::DATETIME_MUTABLE, 'date_modified');
        $builder->addNamedField('dwellTime', Types::INTEGER, 'dwell_time');

        $builder->addNullableField('properties', Types::JSON);
        $builder->addLead();
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPageUrl()
    {
        return $this->pageUrl;
    }

    public function setPageUrl($pageUrl): self
    {
        $this->pageUrl = $pageUrl;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function __toString()
    {
        return json_encode($this->toArray());
    }

    public function getLead()
    {
        return $this->lead;
    }

    public function setLead($lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperties($properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    public function getDateAdded()
    {
        return $this->dateAdded;
    }

    public function setDateAdded($dateAdded): self
    {
        $this->dateAdded = $dateAdded;
        return $this;
    }

    public function getDateModified(): \DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified($dateModified): self
    {
        $this->dateModified = $dateModified;
        return $this;
    }

    public function getDwellTime()
    {
        return $this->dwellTime;
    }

    public function setDwellTime($dwellTime): self
    {
        $this->dwellTime = $dwellTime;
        return $this;
    }
}
