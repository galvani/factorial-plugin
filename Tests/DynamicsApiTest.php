<?php

namespace MauticPlugin\MauticFactorialBundle\Tests;

use Mautic\PluginBundle\Tests\Integration\AbstractIntegrationTestCase;
use MauticPlugin\MauticFactorialBundle\Api\DynamicsApi;
use MauticPlugin\MauticFactorialBundle\Integration\DynamicsIntegration;

class DynamicsApiTest extends AbstractIntegrationTestCase
{
    private DynamicsApi $api;

    private DynamicsIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integration = new DynamicsIntegration(
            $this->dispatcher,
            $this->cache,
            $this->em,
            $this->session,
            $this->request,
            $this->router,
            $this->translator,
            $this->logger,
            $this->encryptionHelper,
            $this->leadModel,
            $this->companyModel,
            $this->pathsHelper,
            $this->notificationModel,
            $this->fieldModel,
            $this->integrationEntityModel,
            $this->doNotContact
        );

        $this->api         = new DynamicsApi($this->integration);
    }

    public function testIntegration(): void
    {
        $this->assertSame('Dynamics', $this->integration->getName());
    }
}
