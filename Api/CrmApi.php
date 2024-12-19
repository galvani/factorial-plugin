<?php

namespace MauticPlugin\MauticFactorialBundle\Api;


use MauticPlugin\MauticFactorialBundle\Integration\CrmAbstractIntegration;

/**
 * @method createLead()
 */
class CrmApi
{
    public function __construct(
        protected CrmAbstractIntegration $integration
    ) {
    }
}
